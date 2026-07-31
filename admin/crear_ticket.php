<?php
require '../includes/auth.php';
require '../config/database.php';
restrict_access(['admin']);

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST['dni']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $office_id = !empty($_POST['office_id']) ? $_POST['office_id'] : null;
    $category = $_POST['category'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : null;

    if (empty($dni) || empty($office_id) || empty($category) || empty($title)) {
        $error = "Por favor, completa los campos obligatorios (DNI, Oficina, Categoría y Título).";
    } else {
        if (empty($first_name)) $first_name = 'Usuario';
        if (empty($last_name)) $last_name = 'No Registrado';
        if (empty($description)) $description = 'Sin descripción detallada.';
        try {
            $conn->beginTransaction();

            if (!$user_id) {
                // Check once more in case JS missed it
                $stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE dni = :dni");
                $stmtCheck->execute(['dni' => $dni]);
                $existing = $stmtCheck->fetchColumn();

                if ($existing) {
                    $user_id = $existing;
                } else {
                    $stmtUser = $conn->prepare("
                        INSERT INTO usuarios (dni, first_name, last_name, phone, office_id, password, is_registered) 
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                    ");
                    $stmtUser->execute([$dni, $first_name, $last_name, $phone, $office_id, $dni]);
                    $user_id = $conn->lastInsertId();
                }
            } else {
                 // Optionally update phone or office
                 $stmtUpdate = $conn->prepare("UPDATE usuarios SET phone = ?, office_id = ? WHERE id = ?");
                 $stmtUpdate->execute([$phone, $office_id, $user_id]);
            }

            // Guardar ticket
            $stmt = $conn->prepare("
                INSERT INTO tickets (user_id, category, title, description, office_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $category, $title, $description, $office_id]);

            // El trigger genera el ID; lo recuperamos con la misma logica: MAX(id) del mes actual
            $prefix = (int) date('Ym');
            $new_ticket_id = $conn->query("SELECT MAX(id) FROM tickets WHERE id DIV 1000 = $prefix")->fetchColumn();

            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, comment, changed_by) VALUES (?, 'Pendiente', 'Ticket creado (Admin)', ?)");
            $stmtHist->execute([$new_ticket_id, $_SESSION['user']['id']]);

            // Guardar archivos adjuntos si los hay
            if (isset($_FILES['archivos']['name']) && is_array($_FILES['archivos']['name'])) {
                $month_folder  = date('Y-m') . '/';
                $physical_dir  = __DIR__ . '/../ticket/uploads/' . $month_folder;
                $db_dir        = 'uploads/' . $month_folder;

                if (!is_dir(__DIR__ . '/../ticket/uploads/')) mkdir(__DIR__ . '/../ticket/uploads/', 0777, true);
                if (!is_dir($physical_dir)) mkdir($physical_dir, 0777, true);

                $total = count($_FILES['archivos']['name']);
                for ($i = 0; $i < $total; $i++) {
                    $tmp = $_FILES['archivos']['tmp_name'][$i];
                    if ($tmp != '') {
                        $name_orig = $_FILES['archivos']['name'][$i];
                        
                        // Validar extensión
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif'];
                        $file_ext = strtolower(pathinfo($name_orig, PATHINFO_EXTENSION));
                        if (!in_array($file_ext, $allowed_exts)) {
                            continue;
                        }
                        
                        $safe     = preg_replace('/[^a-zA-Z0-9.]+/', '', basename($name_orig));
                        $filename = 'ticket_' . $new_ticket_id . '_' . time() . '_' . $safe;
                        $target_file  = $physical_dir . $filename;
                        $db_file_path = $db_dir . $filename;

                        if (move_uploaded_file($tmp, $target_file)) {
                            $stmtFile = $conn->prepare('INSERT INTO ticket_files (ticket_id, file_path) VALUES (?, ?)');
                            $stmtFile->execute([$new_ticket_id, $db_file_path]);
                        }
                    }
                }
            }

            $conn->commit();
            header("Location: ../ticket/ticket_detalle.php?id=" . $new_ticket_id);
            exit();

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Hubo un error al crear el ticket: " . $e->getMessage();
        }
    }
}

// Obtener oficinas para Select
$stmtOffices = $conn->query("SELECT id, name FROM oficina WHERE is_active = TRUE ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

require 'includes/admin_header.php';
?>

<div class="card p-3 mt-4 mb-4 flex-row justify-content-between align-items-center"><div>
        <h2 class="fw-bold mb-0">Crear Nuevo Ticket</h2>
        <p class="text-muted mb-0">Formulario administrativo para levantar tickets a nombre de usuarios.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data" id="adminTicketForm">
            <input type="hidden" name="user_id" id="user_id" value="">
            
            <h5 class="fw-bold text-primary mb-3">Información del Usuario</h5>
            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-medium w-100">DNI <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="dni" id="dniSearch" class="form-control" required placeholder="Buscar DNI...">
                        <button type="button" class="btn btn-primary" id="btnSearchUser"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="userStatusText" class="form-text mt-2 text-muted">Ingrese DNI y presione buscar.</div>
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <label class="form-label fw-medium">Nombres</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" placeholder="(Opcional)">
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <label class="form-label fw-medium">Apellidos</label>
                    <input type="text" name="last_name" id="last_name" class="form-control" placeholder="(Opcional)">
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <label class="form-label fw-medium">Teléfono</label>
                    <input type="text" name="phone" id="phone" class="form-control" pattern="[0-9]{9}" maxlength="9" title="Debe contener exactamente 9 dígitos numéricos">
                </div>
            </div>

            <hr class="text-muted opacity-25 my-4">

            <h5 class="fw-bold text-primary mb-3">Detalle del Ticket</h5>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Oficina <span class="text-danger">*</span></label>
                    <select class="form-select searchable-select" name="office_id" id="office_id" required>
                        <option value="" disabled selected>Seleccione...</option>
                        <?php foreach($offices as $of): ?>
                            <option value="<?php echo $of['id']; ?>"><?php echo htmlspecialchars($of['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mt-3 mt-md-0">
                    <label class="form-label fw-medium">Categoría <span class="text-danger">*</span></label>
                    <select class="form-select" name="category" required>
                        <option value="" disabled selected>Seleccione...</option>
                        <option value="Software">Software</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Internet">Internet</option>
                        <option value="Instalacion">Instalación</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium">Título <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required placeholder="Problema principal">
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium">Descripción</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Detalle completo (Opcional)"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium mb-1">Evidencias Adjuntas <span class="text-muted fw-normal">(Máx 5 archivos)</span></label>
                <div class="card bg-light border-0 mb-2" id="adminDropZone" style="border: 1.5px dashed #c1c9d0 !important; transition: all 0.2s ease; cursor: pointer;" onclick="if(event.target.tagName !== 'BUTTON' && !event.target.closest('button')) document.getElementById('adminFileInput').click();">
                    <div class="card-body p-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-cloud-arrow-up-fill fs-4 text-primary opacity-75"></i>
                            <div>
                                <span class="fw-semibold small d-block mb-0 text-dark">Añade o arrastra fotos / documentos</span>
                                <span class="small text-muted" style="font-size: 0.75rem;">Arrastra aquí o usa los botones (Máx 5)</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2 ms-auto dropzone-buttons">
                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 btn-upload-action" onclick="event.stopPropagation(); openFotoAdmin()">
                                <i class="bi bi-camera-fill me-1"></i> Foto
                            </button>
                            <button type="button" class="btn btn-sm btn-primary py-1 px-2 btn-upload-action" onclick="event.stopPropagation(); document.getElementById('adminFileInput').click()">
                                <i class="bi bi-folder-plus me-1"></i> Explorar
                            </button>
                        </div>
                        <input type="file" id="adminFotoInput" accept="image/*" class="d-none">
                        <input type="file" id="adminFileInput" accept="image/*" class="d-none" multiple>
                        <input type="file" name="archivos[]" id="adminRealInput" accept="image/*" class="d-none" multiple>
                    </div>
                </div>
                <ul class="list-group list-group-flush border rounded-3 overflow-hidden" id="adminFilePreviewList" style="display:none;"></ul>
            </div>

            <div class="d-flex justify-content-end">
                <a href="tickets.php" class="btn btn-light me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-2"></i> Crear y Guardar Ticket</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('btnSearchUser').addEventListener('click', function() {
    searchUser();
});

document.getElementById('dniSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchUser();
    }
});

function searchUser() {
    const dni = document.getElementById('dniSearch').value.trim();
    if (dni.length < 5) return;
    
    document.getElementById('userStatusText').innerHTML = '<span class="text-info"><i class="spinner-border spinner-border-sm"></i> Buscando...</span>';
    
    fetch('ajax_search_user.php?dni=' + dni)
        .then(response => response.json())
        .then(data => {
            const statusArea = document.getElementById('userStatusText');
            if (data.success) {
                document.getElementById('first_name').value = data.data.first_name;
                document.getElementById('last_name').value = data.data.last_name;
                if(data.data.phone) document.getElementById('phone').value = data.data.phone;
                if(data.data.office_id) {
                    let officeEl = document.getElementById('office_id');
                    if(officeEl.tomselect) {
                        officeEl.tomselect.setValue(data.data.office_id);
                    } else {
                        officeEl.value = data.data.office_id;
                    }
                }
                document.getElementById('user_id').value = data.data.id;
                
                statusArea.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle"></i> Usuario encontrado y autollenado.</span>';
            } else {
                document.getElementById('first_name').value = '';
                document.getElementById('last_name').value = '';
                document.getElementById('phone').value = '';
                document.getElementById('user_id').value = '';
                statusArea.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-info-circle"></i> Usuario no encontrado. Por favor, llenar manualmente.</span>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('userStatusText').innerHTML = '<span class="text-danger">Error de conexión al buscar.</span>';
        });
}
</script>

<script>
// Manejo de archivos adjuntos en el formulario del admin
const adminFotoInput   = document.getElementById('adminFotoInput');
const adminFileInput    = document.getElementById('adminFileInput');
const adminRealInput    = document.getElementById('adminRealInput');
const adminPreviewList  = document.getElementById('adminFilePreviewList');
const adminDropZone     = document.getElementById('adminDropZone');
let adminFiles = [];
const ADMIN_MAX = 5;

function isMobileAdmin() {
    return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
}
function openFotoAdmin() {
    if (isMobileAdmin()) {
        adminFotoInput.setAttribute('capture', 'environment');
    } else {
        adminFotoInput.removeAttribute('capture');
    }
    adminFotoInput.click();
}

function adminHandleFiles(files) {
    for (let i = 0; i < files.length; i++) {
        if (adminFiles.length >= ADMIN_MAX) {
            alert('⚠️ Límite: máximo ' + ADMIN_MAX + ' archivos.');
            break;
        }
        if (!adminFiles.some(f => f.name === files[i].name && f.size === files[i].size)) {
            adminFiles.push(files[i]);
        }
    }
    adminUpdateUI();
}

if (adminFotoInput) adminFotoInput.addEventListener('change', e => { adminHandleFiles(e.target.files); e.target.value=''; });
if (adminFileInput) adminFileInput.addEventListener('change', e => { adminHandleFiles(e.target.files); e.target.value=''; });

if (adminDropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
        adminDropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }, false);
    });
    ['dragenter', 'dragover'].forEach(evt => {
        adminDropZone.addEventListener(evt, () => {
            adminDropZone.style.background = '#eef5ff';
            adminDropZone.style.borderColor = '#0d6efd';
        }, false);
    });
    ['dragleave', 'drop'].forEach(evt => {
        adminDropZone.addEventListener(evt, () => {
            adminDropZone.style.background = '';
            adminDropZone.style.borderColor = '#c1c9d0';
        }, false);
    });
    adminDropZone.addEventListener('drop', e => {
        if (e.dataTransfer && e.dataTransfer.files) {
            adminHandleFiles(e.dataTransfer.files);
        }
    }, false);
}

function adminUpdateUI() {
    adminPreviewList.innerHTML = '';
    const dt = new DataTransfer();
    adminFiles.forEach((file, idx) => {
        dt.items.add(file);
        let icon = 'bi-file-earmark';
        if (file.type.startsWith('image/')) icon = 'bi-image text-primary';
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center bg-white';
        li.innerHTML = `
            <div class="d-flex align-items-center text-truncate pe-3">
                <i class="bi ${icon} fs-5 me-3 opacity-75"></i>
                <div class="text-truncate">
                    <span class="d-block fw-medium text-dark text-truncate" style="font-size:.95rem">${file.name}</span>
                    <small class="text-muted">${(file.size/1024/1024).toFixed(2)} MB</small>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle"
                style="width:32px;height:32px;padding:0" onclick="adminRemoveFile(${idx})">
                <i class="bi bi-x-lg"></i>
            </button>`;
        adminPreviewList.appendChild(li);
    });
    adminRealInput.files = dt.files;
    adminPreviewList.style.display = adminFiles.length > 0 ? 'block' : 'none';
}

function adminRemoveFile(idx) {
    adminFiles.splice(idx, 1);
    adminUpdateUI();
}

document.getElementById('adminTicketForm').addEventListener('submit', function(e) {
    if (adminFiles.length > ADMIN_MAX) {
        e.preventDefault();
        alert('Por favor remueve archivos para cumplir el límite de ' + ADMIN_MAX + '.');
    }
});
</script>

<?php require 'includes/admin_footer.php'; ?>
