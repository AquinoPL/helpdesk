<?php
require '../includes/auth.php';
require '../config/database.php';
restrict_access(['admin']);
require 'includes/admin_header.php';

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

    if (empty($dni) || empty($first_name) || empty($last_name) || empty($category) || empty($title) || empty($description)) {
        $error = "Por favor, completa todos los campos obligatorios.";
    } else {
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
                        INSERT INTO usuarios (dni, first_name, last_name, phone, office_id, password) 
                        VALUES (?, ?, ?, ?, ?, ?) RETURNING id
                    ");
                    $stmtUser->execute([$dni, $first_name, $last_name, $phone, $office_id, $dni]);
                    $user_id = $stmtUser->fetchColumn();
                }
            } else {
                 // Optionally update phone or office
                 $stmtUpdate = $conn->prepare("UPDATE usuarios SET phone = ?, office_id = ? WHERE id = ?");
                 $stmtUpdate->execute([$phone, $office_id, $user_id]);
            }

            // Guardar ticket
            $stmt = $conn->prepare("
                INSERT INTO tickets (user_id, category, title, description, office_id) 
                VALUES (?, ?::ticket_category, ?, ?, ?) RETURNING id
            ");
            $stmt->execute([$user_id, $category, $title, $description, $office_id]);
            $new_ticket_id = $stmt->fetchColumn();

            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, comment, changed_by) VALUES (?, 'Pendiente', 'Ticket creado (Admin)', ?)");
            $stmtHist->execute([$new_ticket_id, $_SESSION['user']['id']]);

            $conn->commit();
            $success = "Ticket #$new_ticket_id creado exitosamente.";

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Hubo un error al crear el ticket: " . $e->getMessage();
        }
    }
}

// Obtener oficinas para Select
$stmtOffices = $conn->query("SELECT id, name FROM oficina WHERE is_active = TRUE ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
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
        <form method="POST">
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
                    <label class="form-label fw-medium">Nombres <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" id="first_name" class="form-control" required>
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <label class="form-label fw-medium">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" id="last_name" class="form-control" required>
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <label class="form-label fw-medium">Teléfono</label>
                    <input type="text" name="phone" id="phone" class="form-control">
                </div>
            </div>

            <hr class="text-muted opacity-25 my-4">

            <h5 class="fw-bold text-primary mb-3">Detalle del Ticket</h5>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Oficina <span class="text-danger">*</span></label>
                    <select class="form-select" name="office_id" id="office_id" required>
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
                <label class="form-label fw-medium">Descripción <span class="text-danger">*</span></label>
                <textarea name="description" class="form-control" rows="4" required placeholder="Detalle completo"></textarea>
            </div>

            <div class="d-flex justify-content-end">
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
                if(data.data.office_id) document.getElementById('office_id').value = data.data.office_id;
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

<?php require 'includes/admin_footer.php'; ?>
