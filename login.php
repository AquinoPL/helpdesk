<?php
session_start();
require 'config/database.php';

// Redirigir si ya está logueado
if (isset($_SESSION["user"])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);
    $password = $_POST["password"];

    try {
        $stmt = $conn->prepare("SELECT * FROM login_user(:dni, :password)");
        $stmt->bindParam(':dni', $dni);
        $stmt->bindParam(':password', $password);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && isset($user['id']) && !empty($user['id'])) {
            $user['dni'] = $dni; // Store DNI explicitly
            $_SESSION["user"] = $user;

            if ($user["role"] == "admin") {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "Credenciales incorrectas o usuario no encontrado.";
        }
    } catch(PDOException $e) {
        $error = "Error al intentar iniciar sesión.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Soporte Alianza</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); 
            min-height: 100vh; 
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container { max-width: 420px; width: 100%; margin: auto; }
    </style>
</head>
<body>

<div class="login-container fade-in px-3">
    <div class="card glass-card border-0 p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px;">
                <i class="bi bi-headset fs-1"></i>
            </div>
            <h3 class="fw-bold text-dark">Soporte Alianza</h3>
            <p class="text-muted">Inicia sesión para continuar</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-medium text-dark">DNI</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                    <input type="text" name="dni" class="form-control" placeholder="Ingresa tu DNI" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium text-dark">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Tu contraseña" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fs-5 text-white">
                Ingresar <i class="bi bi-box-arrow-in-right ms-1"></i>
            </button>
            <div class="mt-3 text-center">
                <span class="text-muted">¿No tienes cuenta?</span> <a href="register.php" class="text-primary text-decoration-none fw-medium">Regístrate aquí</a>
            </div>
        </form>
    </div>
    
    <div class="text-center mt-4">
        <p class="text-muted small">&copy; <?php echo date('Y'); ?> Soporte Alianza. Todos los derechos reservados.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
</body>
</html>