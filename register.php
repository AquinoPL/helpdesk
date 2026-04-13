<?php
session_start();
require 'config/database.php';

if (isset($_SESSION["user"])) {
    header("Location: index.php");
    exit();
}

$error = "";
$success = "";

// Obtener oficinas para el formulario
$stmtOffices = $conn->query("SELECT id, name FROM oficina ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $office_id = $_POST["office_id"];
    $password = $_POST["password"];
    $password_confirm = $_POST["password_confirm"];

    if (empty($dni) || empty($first_name) || empty($last_name) || empty($password)) {
        $error = "Por favor, completa los campos obligatorios.";
    } elseif ($password !== $password_confirm) {
        $error = "Las contraseñas no coinciden.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO usuarios (dni, first_name, last_name, email, phone, office_id, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $dni,
                $first_name,
                $last_name,
                $email ?: null,
                $phone ?: null,
                $office_id ?: null,
                $password
            ]);
            $success = "Usuario registrado correctamente. Ahora puedes iniciar sesión.";
        } catch(PDOException $e) {
            if ($e->getCode() == 23505) { // Unique violation
                $error = "El DNI o el correo electrónico ya se encuentran registrados.";
            } else {
                $error = "Hubo un error al registrar el usuario: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Soporte Alianza</title>
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
            padding: 2rem 0;
        }
        .register-container { max-width: 600px; width: 100%; margin: auto; }
    </style>
</head>
<body>

<div class="register-container fade-in px-3">
    <div class="card glass-card border-0 p-4 p-md-5">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark">Registro de Usuario</h3>
            <p class="text-muted">Crea tu cuenta en Soporte Alianza</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                <div class="mt-2 text-center">
                    <a href="login.php" class="btn btn-sm btn-success fw-bold px-3">Ir al inicio de sesión</a>
                </div>
            </div>
        <?php else: ?>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6 mb-2">
                    <label class="form-label fw-medium text-dark">DNI <span class="text-danger">*</span></label>
                    <input type="text" name="dni" class="form-control" placeholder="Ej: 70000000" required autofocus>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label fw-medium text-dark">Teléfono</label>
                    <input type="text" name="phone" class="form-control" placeholder="Opcional">
                </div>

                <div class="col-md-6 mb-2">
                    <label class="form-label fw-medium text-dark">Nombres <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label fw-medium text-dark">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>

                <div class="col-12 mb-2">
                    <label class="form-label fw-medium text-dark">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" placeholder="correo@empresa.com">
                </div>

                <div class="col-12 mb-2">
                    <label class="form-label fw-medium text-dark">Oficina</label>
                    <select name="office_id" class="form-select">
                        <option value="">Seleccione una oficina...</option>
                        <?php foreach($offices as $of): ?>
                            <option value="<?php echo $of['id']; ?>"><?php echo htmlspecialchars($of['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-dark">Contraseña <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-medium text-dark">Confirmar Contraseña <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fs-5 text-white fw-bold">
                Registrar Cuenta <i class="bi bi-person-plus-fill ms-1"></i>
            </button>
            <div class="mt-3 text-center">
                <span class="text-muted">¿Ya tienes una cuenta?</span> <a href="login.php" class="text-primary text-decoration-none fw-medium">Ingresa aquí</a>
            </div>
        </form>
        
        <?php endif; ?>
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
