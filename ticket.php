<?php
require 'includes/auth.php';
require 'config/database.php';

restrict_access(['usuario']);

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $user_id = $_SESSION['user']['id'];
    // Si el usuario no cambió la oficina en el form, usar la de su perfil en sesión
    $office_id = !empty($_POST['office_id'])
        ? intval($_POST['office_id'])
        : (isset($_SESSION['user']['office_id']) ? intval($_SESSION['user']['office_id']) : null);

    if (empty($title) || empty($category)) {
        $error = "Todos los campos obligatorios deben ser completados.";
    } else {
        try {
            // CALL al procedure create_ticket (crea el ticket y registra el historial inicial)
            $conn->exec("CALL create_ticket($user_id, '$category', " . $conn->quote($title) . ", " . $conn->quote($description ?? '') . ", " . ($office_id ?? 'NULL') . ", @ticket_id)");
            $row = $conn->query("SELECT @ticket_id AS ticket_id")->fetch(PDO::FETCH_ASSOC);
            $new_ticket_id = $row['ticket_id'];


            // Guardar archivos si los hay
            if (isset($_FILES['archivos']['name']) && is_array($_FILES['archivos']['name'])) {
                // Carpeta organizada por mes: uploads/YYYY-MM/
                $month_folder = date('Y-m') . '/';
                $upload_dir   = 'uploads/' . $month_folder;
                if (!is_dir('uploads/')) mkdir('uploads/', 0777, true);
                if (!is_dir($upload_dir))  mkdir($upload_dir,  0777, true);

                $total = count($_FILES['archivos']['name']);
                for ($i = 0; $i < $total; $i++) {
                    $tmp_name = $_FILES['archivos']['tmp_name'][$i];
                    if ($tmp_name != "") {
                        $name = $_FILES['archivos']['name'][$i];
                        $safe_name = preg_replace("/[^a-zA-Z0-9.]+/", "", basename($name));
                        $file_path = $upload_dir . 'ticket_' . $new_ticket_id . '_' . time() . '_' . $safe_name;

                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $stmtFile = $conn->prepare("INSERT INTO ticket_files (ticket_id, file_path) VALUES (?, ?)");
                            $stmtFile->execute([$new_ticket_id, $file_path]);
                        }
                    }
                }
            }

            $_SESSION['success_msg'] = "Ticket #$new_ticket_id creado exitosamente.";
            header("Location: index.php");
            exit();

        } catch(PDOException $e) {
            $error = "Hubo un error al crear el ticket: " . $e->getMessage();
        }
    }
}

// Fetch active offices for the dropdown
$stmtOffices = $conn->query("SELECT id, name FROM oficina WHERE is_active = TRUE ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<div class="hero-public mb-5">
    <h2 class="fw-bold mb-2">Crear Nuevo Ticket</h2>
    <p class="mb-0">Completa la información para recibir asistencia de nuestro equipo.</p>
</div>

<div class="container" style="margin-top:-4rem; padding-bottom:3rem;">
    <div class="card card-plain p-4 p-md-5 mx-auto" style="max-width:780px;">
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Ubicación / Oficina del Problema <span class="text-danger">*</span></label>
                    <?php 
                    $userOffice = $_SESSION['user']['office_id'] ?? '';
                    $userOfficeName = '';
                    foreach($offices as $of) {
                        if ($of['id'] == $userOffice) {
                            $userOfficeName = $of['name'];
                            break;
                        }
                    }
                    ?>
                    <div class="input-group">
                        <input type="hidden" name="office_id" id="ticket_office_id" value="<?php echo htmlspecialchars($userOffice); ?>" required>
                        <input type="text" class="form-control bg-white" id="ticket_office_display" value="<?php echo htmlspecialchars($userOfficeName); ?>" placeholder="Haz clic para buscar..." readonly onclick="openOfficeSearch('ticket_office_id', 'ticket_office_display')" style="cursor: pointer;">
                        <button type="button" class="btn btn-outline-secondary" onclick="openOfficeSearch('ticket_office_id', 'ticket_office_display')">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Categoría <span class="text-danger">*</span></label>
                    <select class="form-select" name="category" required>
                        <option value="" selected disabled>Selecciona una categoría...</option>
                        <option value="Software">Software</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Internet">Internet</option>
                        <option value="Instalacion">Instalación</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-medium">Asunto <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Ej: Mi computadora no enciende" required>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-medium">Descripción <span class="text-muted fw-normal">(Opcional)</span></label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Detalla lo más posible el problema..."></textarea>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-medium mb-2">Evidencias Adjuntas (Máx 5)</label>
                <div class="card bg-light border-0 mb-3" style="border: 2px dashed var(--line) !important;">
                    <div class="card-body text-center p-3">
                        <i class="bi bi-cloud-arrow-up-fill fs-2" style="color:var(--accent)"></i>
                        <h6 class="fw-bold mt-2">Añade fotos o documentos</h6>
                        
                        <div class="d-flex justify-content-center gap-2 flex-wrap mt-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('cameraInput').click()">
                                <i class="bi bi-camera me-1"></i> Foto
                            </button>
                            <button type="button" class="btn btn-sm text-white" style="background:var(--accent)" onclick="document.getElementById('fileInput').click()">
                                <i class="bi bi-folder me-1"></i> Explorar
                            </button>
                        </div>

                        <input type="file" id="cameraInput" accept="image/*" capture="environment" class="d-none" multiple>
                        <input type="file" id="fileInput" class="d-none" multiple>
                        <input type="file" name="archivos[]" id="realInput" class="d-none" multiple>
                    </div>
                </div>
                
                <ul class="list-group list-group-flush border rounded-3 overflow-hidden" id="filePreviewList" style="display: none;"></ul>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="index.php" class="btn btn-outline-secondary w-100 w-md-auto">Cancelar</a>
                <button type="submit" class="btn text-white w-100 w-md-auto" style="background:var(--deep)">Enviar reporte</button>
            </div>
        </form>
        
        <script>
            const cameraInput = document.getElementById('cameraInput');
            const fileInput = document.getElementById('fileInput');
            const realInput = document.getElementById('realInput');
            const filePreviewList = document.getElementById('filePreviewList');
            
            let selectedFiles = [];
            const MAX_FILES = 5;

            function handleFiles(files) {
                for (let i = 0; i < files.length; i++) {
                    if (selectedFiles.length >= MAX_FILES) {
                        alert('⚠️ Límite alcanzado: Solo puedes adjuntar un máximo de ' + MAX_FILES + ' evidencias.');
                        break;
                    }
                    if(!selectedFiles.some(f => f.name === files[i].name && f.size === files[i].size)) {
                        selectedFiles.push(files[i]);
                    }
                }
                updateUI();
            }

            cameraInput.addEventListener('change', (e) => { handleFiles(e.target.files); e.target.value = ''; });
            fileInput.addEventListener('change', (e) => { handleFiles(e.target.files); e.target.value = ''; });

            function updateUI() {
                filePreviewList.innerHTML = '';
                const dt = new DataTransfer();

                selectedFiles.forEach((file, index) => {
                    dt.items.add(file);
                    
                    let icon = 'bi-file-earmark';
                    if (file.type.startsWith('image/')) icon = 'bi-image text-primary';
                    else if (file.type === 'application/pdf') icon = 'bi-file-earmark-pdf text-danger';

                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center bg-white py-2';
                    li.innerHTML = `
                        <div class="d-flex align-items-center text-truncate pe-3">
                            <i class="bi ${icon} me-3 opacity-75"></i>
                            <div class="text-truncate">
                                <span class="d-block small fw-medium text-dark text-truncate">${file.name}</span>
                                <small class="text-muted" style="font-size:.7rem">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removeFile(${index})" title="Quitar archivo">
                            <i class="bi bi-x"></i>
                        </button>
                    `;
                    filePreviewList.appendChild(li);
                });

                realInput.files = dt.files;
                filePreviewList.style.display = selectedFiles.length > 0 ? 'block' : 'none';
            }

            function removeFile(index) {
                selectedFiles.splice(index, 1);
                updateUI();
            }

            document.querySelector('form').addEventListener('submit', function(e) {
                if(selectedFiles.length > MAX_FILES) {
                    e.preventDefault();
                    alert('Por favor remueve archivos para cumplir el límite de ' + MAX_FILES + '.');
                }
            });
        </script>
    </div>
</div>

<!-- Office Search Modal -->
<div class="modal fade" id="officeSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-building me-2"></i> Buscar Oficina</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="officeSearchInput" class="form-control form-control-lg" placeholder="Escriba el nombre de la oficina..." autocomplete="off">
                </div>
                <div id="officeSearchResults" class="list-group overflow-auto" style="max-height: 250px;">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let targetOfficeIdInput = '';
let targetOfficeDisplayInput = '';

function openOfficeSearch(idField, displayField) {
    targetOfficeIdInput = idField;
    targetOfficeDisplayInput = displayField;
    const modal = new bootstrap.Modal(document.getElementById('officeSearchModal'));
    document.getElementById('officeSearchInput').value = '';
    document.getElementById('officeSearchResults').innerHTML = '';
    modal.show();
    setTimeout(() => document.getElementById('officeSearchInput').focus(), 500);
}

document.getElementById('officeSearchInput').addEventListener('input', function(e) {
    const query = e.target.value.trim();
    if (query.length < 2) {
        document.getElementById('officeSearchResults').innerHTML = '';
        return;
    }
    
    fetch('ajax_search_office.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            const results = document.getElementById('officeSearchResults');
            results.innerHTML = '';
            if (data.length === 0) {
                results.innerHTML = '<div class="list-group-item text-muted text-center py-3">No se encontraron oficinas</div>';
            } else {
                data.forEach(of => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-3';
                    btn.innerHTML = `<div class="d-flex w-100 justify-content-between"><h6 class="mb-1 fw-bold text-dark">${of.name}</h6></div>`;
                    if (of.location) btn.innerHTML += `<small class="text-muted"><i class="bi bi-geo-alt me-1"></i> ${of.location}</small>`;
                    
                    btn.onclick = () => {
                        document.getElementById(targetOfficeIdInput).value = of.id;
                        document.getElementById(targetOfficeDisplayInput).value = of.name;
                        bootstrap.Modal.getInstance(document.getElementById('officeSearchModal')).hide();
                    };
                    results.appendChild(btn);
                });
            }
        })
        .catch(error => console.error('Error:', error));
});
</script>

<?php require 'includes/footer.php'; ?>
