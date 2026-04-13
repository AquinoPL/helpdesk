<?php
require 'includes/auth.php';
require 'config/database.php';

restrict_access(['usuario']);

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $user_id = $_SESSION['user']['id'];

    if (empty($title) || empty($category) || empty($description)) {
        $error = "Todos los campos obligatorios deben ser completados.";
    } else {
        try {
            // Insertar directamente con cast explícito para el ENUM
            $stmt = $conn->prepare("
                INSERT INTO tickets (user_id, category, title, description) 
                VALUES (?, ?::ticket_category, ?, ?) 
                RETURNING id
            ");
            $stmt->execute([$user_id, $category, $title, $description]);
            $new_ticket_id = $stmt->fetchColumn();

            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, comment) VALUES (?, 'Pendiente', 'Ticket creado')");
            $stmtHist->execute([$new_ticket_id]);

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
                
                <div class="mb-4">
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

                <div class="mb-4">
                    <label class="form-label fw-medium text-dark">Título <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control form-control-lg fs-6" placeholder="Ej: Mi computadora no enciende" required>
                    <div class="form-text">Resume tu problema en una frase corta.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium text-dark">Descripción <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Detalla lo más posible el problema que presentas..." required></textarea>
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

<?php require 'includes/footer.php'; ?>
