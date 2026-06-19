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
    $office_id = !empty($_POST['office_id']) ? $_POST['office_id'] : null;

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
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

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

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-outline-secondary rounded-circle me-3" style="width: 40px; height: 40px; padding: 0; line-height:38px; text-align:center;">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h2 class="fw-bold mb-0">Crear Nuevo Ticket</h2>
                <p class="text-muted mb-0">Completa la información para recibir asistencia.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card glass-card border-0 p-4">
            <form method="POST" enctype="multipart/form-data">
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-medium text-dark">Ubicación / Oficina del Problema <span class="text-danger">*</span></label>
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
                            <input type="text" class="form-control form-control-lg bg-white fs-6" id="ticket_office_display" value="<?php echo htmlspecialchars($userOfficeName); ?>" placeholder="Haz clic para buscar oficina..." readonly onclick="openOfficeSearch('ticket_office_id', 'ticket_office_display')" style="cursor: pointer;">
                            <button type="button" class="btn btn-outline-primary" onclick="openOfficeSearch('ticket_office_id', 'ticket_office_display')">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                        <div class="form-text">Si este equipo es de otra sede, cámbialo aquí.</div>
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                        <label class="form-label fw-medium text-dark">Categoría del problema <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg fs-6" name="category" required>
                            <option value="" selected disabled>Selecciona una categoría...</option>
                            <option value="Software">Software (Aplicativos, ERP, Office, etc.)</option>
                            <option value="Hardware">Hardware (Computadoras, periféricos, etc.)</option>
                            <option value="Internet">Internet (Conectividad, VPN, Wifi)</option>
                            <option value="Instalacion">Instalación (Nuevos equipos, programas)</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium text-dark">Título <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control form-control-lg fs-6" placeholder="Ej: Mi computadora no enciende" required>
                    <div class="form-text">Resume tu problema en una frase corta.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium text-dark">Descripción <span class="text-muted fw-normal">(Opcional)</span></label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Detalla lo más posible el problema que presentas..."></textarea>
                </div>

                <div class="mb-5">
                    <label class="form-label fw-medium text-dark mb-3">Evidencias Adjuntas (Máximo 5 archivos)</label>
                    <div class="card bg-light border-0 mb-3" style="border: 2px dashed #c1c9d0 !important;">
                        <div class="card-body text-center p-4">
                            <i class="bi bi-cloud-arrow-up-fill fs-1 text-primary mb-2 d-block opacity-75"></i>
                            <h6 class="fw-bold text-dark">Añade fotos o documentos</h6>
                            <p class="small text-muted mb-3">Sube imágenes, reportes o captura en vivo el problema (Máx 5).</p>
                            
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('cameraInput').click()">
                                    <i class="bi bi-camera-fill me-1"></i> Tomar foto
                                </button>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                                    <i class="bi bi-folder-plus me-1"></i> Explorar equipo
                                </button>
                            </div>

                            <input type="file" id="cameraInput" accept="image/*" capture="environment" class="d-none" multiple>
                            <input type="file" id="fileInput" class="d-none" multiple>
                            <!-- Input real que se envía al servidor con las selecciones combinadas -->
                            <input type="file" name="archivos[]" id="realInput" class="d-none" multiple>
                        </div>
                    </div>
                    
                    <!-- Lista visual de archivos -->
                    <ul class="list-group list-group-flush border rounded-3 overflow-hidden" id="filePreviewList" style="display: none;">
                        <!-- JS inyecta los items aquí -->
                    </ul>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="index.php" class="btn btn-light px-4 py-2 text-dark">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-5 py-2 fw-bold">
                        <i class="bi bi-send-fill me-2"></i> Enviar Ticket
                    </button>
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
                        
                        // Infer icon from file type
                        let icon = 'bi-file-earmark';
                        if (file.type.startsWith('image/')) icon = 'bi-image text-primary';
                        else if (file.type === 'application/pdf') icon = 'bi-file-earmark-pdf text-danger';

                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center bg-white';
                        li.innerHTML = `
                            <div class="d-flex align-items-center text-truncate pe-3">
                                <i class="bi ${icon} fs-5 me-3 opacity-75"></i>
                                <div class="text-truncate">
                                    <span class="d-block fw-medium text-dark text-truncate" style="font-size: 0.95rem;">${file.name}</span>
                                    <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle" style="width:32px; height:32px; padding:0;" onclick="removeFile(${index})" title="Quitar archivo">
                                <i class="bi bi-x-lg"></i>
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
