<?php
require 'includes/auth.php';
require 'config/database.php';

$user_session = $_SESSION["user"];
$role = $user_session['role'];
$table = ($role == 'admin' || $role == 'tecnico') ? 'trabajadores' : 'usuarios';

// Cargar todos los datos del usuario de forma completa
$stmtFullData = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$stmtFullData->execute([$user_session['id']]);
$fullData = $stmtFullData->fetch(PDO::FETCH_ASSOC);
$user_session = array_merge($fullData, ['role' => $role]);

$error = '';
$success = '';

// Obtener oficinas
$stmtOffices = $conn->query("SELECT id, name FROM oficina ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $office_id = !empty($_POST['office_id']) ? $_POST['office_id'] : null;
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($phone) || empty($office_id)) {
        $error = "Teléfono y Oficina son obligatorios.";
    } elseif (!preg_match('/^[0-9]{9}$/', $phone)) {
        $error = "El teléfono debe contener exactamente 9 dígitos numéricos.";
    } elseif (!empty($password) && $password !== $password_confirm) {
        $error = "Las contraseñas no coinciden.";
    } else {
        try {
            if (!empty($password)) {
                $stmt = $conn->prepare("UPDATE $table SET phone = ?, email = ?, office_id = ?, password = ? WHERE id = ?");
                $stmt->execute([$phone, $email ?: null, $office_id, $password, $user_session['id']]);
            } else {
                $stmt = $conn->prepare("UPDATE $table SET phone = ?, email = ?, office_id = ? WHERE id = ?");
                $stmt->execute([$phone, $email ?: null, $office_id, $user_session['id']]);
            }

            // Refrescar sesión
            $stmtUser = $conn->prepare("SELECT * FROM $table WHERE id = ?");
            $stmtUser->execute([$user_session['id']]);
            $updated_user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            // Mantener el rol
            if ($role == 'usuario') {
                $updated_user['role'] = 'usuario';
            }
            $_SESSION["user"] = $updated_user;

            $success = "Perfil actualizado correctamente.";
            $user_session = $_SESSION["user"]; // Actualizar variable local para la vista
        } catch(PDOException $e) {
            if ($e->getCode() == 23505) {
                $error = "El correo electrónico ya está en uso por otra cuenta.";
            } else {
                $error = "Error al actualizar perfil: " . $e->getMessage();
            }
        }
    }
}

if ($role == 'admin') {
    require 'admin/includes/admin_header.php';
} else {
    require 'includes/header.php';
}

?>

<div class="row justify-content-center pt-3">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-person-gear"></i> Mi Perfil</h2>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-auto-dismiss alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card glass-card border-0 p-4 p-md-5 mb-5 fade-in">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-medium text-muted">DNI</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user_session['dni']); ?>" readonly>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-medium text-muted">Rol</label>
                        <input type="text" class="form-control bg-light text-capitalize" value="<?php echo htmlspecialchars($user_session['role']); ?>" readonly>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-medium text-muted">Nombres</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user_session['first_name']); ?>" readonly>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-medium text-muted">Apellidos</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user_session['last_name']); ?>" readonly>
                    </div>

                    <hr class="text-muted opacity-25 my-4">

                    <div class="col-12 mb-2">
                        <label class="form-label fw-medium text-dark">Correo Electrónico</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_session['email'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-medium text-dark">Teléfono <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" pattern="[0-9]{9}" maxlength="9" title="Debe contener exactamente 9 dígitos" required value="<?php echo htmlspecialchars($user_session['phone'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-medium text-dark">Oficina <span class="text-danger">*</span></label>
                        <select name="office_id" class="form-select" required>
                            <option value="">Seleccione una oficina...</option>
                            <?php foreach($offices as $of): ?>
                                <option value="<?php echo $of['id']; ?>" <?php echo ($user_session['office_id'] == $of['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($of['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-medium text-dark">Nueva Contraseña <span class="text-muted fw-normal small">(Opcional)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Solo si desea cambiarla">
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-medium text-dark">Confirmar Contraseña</label>
                        <input type="password" name="password_confirm" class="form-control" placeholder="Repita la contraseña">
                    </div>
                </div>

                <div class="text-end mt-2 d-flex flex-column flex-sm-row justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary px-4 py-2 fw-bold w-100 w-sm-auto">
                        <i class="bi bi-x-circle me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold w-100 w-sm-auto">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-auto-dismiss');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });
</script>

<?php
if ($role == 'admin') {
    require 'admin/includes/admin_footer.php';
} else {
    require 'includes/footer.php';
}
?>
