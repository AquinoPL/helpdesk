<?php
// Evitar ejecución directa y asegurar inicio de sesión
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
$user_session = $user_session ?? $_SESSION['user'];

// Identificar pagina activa para sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Soporte</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS Base -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- TomSelect CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Forzar TomSelect a abrirse siempre hacia arriba */
        .ts-wrapper.searchable-select .ts-dropdown {
            top: auto !important;
            bottom: 100% !important;
            margin-bottom: 2px !important;
            border-radius: 0.375rem 0.375rem 0 0 !important;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1) !important;
            border-bottom: none !important;
            border-top: 1px solid #ced4da !important;
        }
        /* Limitar a ~7 filas (aprox 260px) y hacer scrollable */
        .ts-wrapper.searchable-select .ts-dropdown .ts-dropdown-content {
            max-height: 260px !important;
            overflow-y: auto !important;
        }
    </style>
</head>
<body class="new-ui">

    <nav class="sidebar" id="sidebar">
        <a href="<?php echo BASE_URL; ?>/index.php" class="text-decoration-none">
            <div class="brand"><i class="bi bi-headset"></i> HelpDesk<span style="color:var(--accent)">.</span></div>
        </a>
        <div class="nav flex-column mt-2">
            <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?> d-flex align-items-center" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                <i class="bi bi-grid-1x2 me-2"></i>Dashboard
            </a>
            <a class="nav-link <?php echo $currentPage == 'tickets.php' ? 'active' : ''; ?> d-flex align-items-center" href="<?php echo BASE_URL; ?>/admin/tickets.php">
                <i class="bi bi-ticket-detailed me-2"></i>Tickets
            </a>
            <a class="nav-link <?php echo $currentPage == 'crear_ticket.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/crear_ticket.php">
                <i class="bi bi-plus-circle me-2"></i>Crear Ticket
            </a>
            <a class="nav-link <?php echo $currentPage == 'vista_tecnico.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/vista_tecnico.php">
                <i class="bi bi-eye me-2"></i>Ver como Técnico
            </a>
            
            <hr class="mx-3" style="border-color:rgba(255,255,255,.1)">
            
            <a class="nav-link <?php echo $currentPage == 'usuarios.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/usuarios.php">
                <i class="bi bi-people me-2"></i>Usuarios
            </a>
            <a class="nav-link <?php echo $currentPage == 'trabajadores.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/trabajadores.php">
                <i class="bi bi-person-lines-fill me-2"></i>Técnicos/Admins
            </a>
            <a class="nav-link <?php echo $currentPage == 'oficinas.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/oficinas.php">
                <i class="bi bi-building me-2"></i>Oficinas
            </a>
            
            <hr class="mx-3" style="border-color:rgba(255,255,255,.1)">
            <a class="nav-link <?php echo $currentPage == 'reportes.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/reportes.php">
                <i class="bi bi-graph-up me-2"></i>Reportes y SLA
            </a>
            <a class="nav-link <?php echo $currentPage == 'configuracion.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/configuracion.php">
                <i class="bi bi-gear me-2"></i>Configuración
            </a>
            <a class="nav-link text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
                <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
            </a>
        </div>
        
        <div class="user-box">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar-circle sm">
                    <?php 
                        $initials = strtoupper(substr($user_session['first_name'] ?? 'A', 0, 1) . substr($user_session['last_name'] ?? 'U', 0, 1));
                        echo htmlspecialchars($initials); 
                    ?>
                </span>
                <div>
                    <div class="text-white small fw-bold lh-1"><?php echo htmlspecialchars($user_session['first_name'] . ' ' . $user_session['last_name']); ?></div>
                    <div style="font-size:.7rem; margin-top:2px;" class="text-uppercase"><?php echo htmlspecialchars($user_session['role']); ?></div>
                </div>
            </div>
        </div>
    </nav>

    <div class="main" id="main-content">
        <!-- Main Content Wrapper -->
        <div class="main-content flex-grow-1 p-4">

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-3">
                    <i class="bi bi-box-arrow-right" style="font-size: 3rem;"></i>
                </div>
                <h5 class="fw-bold mb-3">¿Cerrar sesión?</h5>
                <p class="text-muted mb-4">¿Estás seguro de que deseas salir del sistema?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light w-50 fw-medium" data-bs-dismiss="modal">Cancelar</button>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-danger w-50 fw-medium">Salir</a>
                </div>
            </div>
        </div>
    </div>
</div>
        <!-- Botón de menú solo para móviles -->
        <div class="d-lg-none p-3 pb-0">
            <button class="btn btn-sm btn-outline-secondary" id="btnToggleSidebar">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <div class="p-4 fade-in">
