<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require '../config/database.php';

if (!defined('BASE_URL')) {
    if (strpos($_SERVER['SCRIPT_NAME'], '/Soporte-Alianza') !== false) {
        define('BASE_URL', '/Soporte-Alianza');
    } else {
        define('BASE_URL', '');
    }
}

$is_logged_in = isset($_SESSION["user"]);
$user = $is_logged_in ? $_SESSION["user"] : null;

if (!$is_logged_in || $user['role'] != 'tecnico') {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Cargar datos frescos desde la base de datos (ya que el SP de login podría no traer email/phone)
try {
    $stmtInfo = $conn->prepare("SELECT first_name, last_name, email, phone, dni FROM trabajadores WHERE id = ?");
    $stmtInfo->execute([$user['id']]);
    $db_info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    if ($db_info) {
        $user['first_name'] = $db_info['first_name'];
        $user['last_name']  = $db_info['last_name'];
        $user['email']      = $db_info['email'];
        $user['phone']      = $db_info['phone'];
        $user['dni']        = $db_info['dni'];
    }
} catch (PDOException $e) {
    // Ignorar si hay error temporal
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        $error_msg = 'Todos los campos son obligatorios.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE trabajadores SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $phone, $user['id']]);
            
            // Actualizar la sesión
            $_SESSION['user']['first_name'] = $first_name;
            $_SESSION['user']['last_name'] = $last_name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            
            // Refrescar la variable local $user
            $user = $_SESSION['user'];
            
            $success_msg = 'Perfil actualizado exitosamente.';
        } catch (PDOException $e) {
            $error_msg = 'Error al actualizar el perfil. Es posible que el correo ya esté en uso.';
        }
    }
}

require '../includes/header.php';
?>

<div class="py-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card p-3 mt-4 mb-4 flex-row align-items-center w-100">
                <h2 class="fw-bold mb-0">Mi Perfil</h2>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card card-plain border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="perfil.php" method="POST">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold small text-uppercase">DNI/CE (Identidad)</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['dni']); ?>" readonly disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold small text-uppercase">Nombres</label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold small text-uppercase">Apellidos</label>
                                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label text-muted fw-bold small text-uppercase">Correo Electrónico</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label text-muted fw-bold small text-uppercase">Teléfono</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn text-white py-2 px-4" style="background:var(--accent)">
                                <i class="bi bi-pencil-square me-2"></i> Modificar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
