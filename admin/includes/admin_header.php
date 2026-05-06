<?php
// Evitar ejecución directa y asegurar inicio de sesión
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS Base -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        body { background-color: #f8f9fa; display: flex; min-height: 100vh; overflow-x: hidden; margin: 0; }
        
        /* Sidebar Styles */
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2b3035 0%, #1e2225 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
            position: fixed;
            height: 100vh;
            z-index: 1050;
        }
        .admin-main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 2rem;
            transition: all 0.3s;
        }

        .sidebar-brand { font-size: 1.5rem; font-weight: 700; padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar-menu { flex-grow: 1; padding: 1rem 0; overflow-y: auto; }
        .nav-item-sidebar { margin: 0.25rem 1rem; }
        
        .nav-link-sidebar {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.85) !important;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .nav-link-sidebar i { margin-right: 0.75rem; font-size: 1.2rem; }
        .nav-link-sidebar:hover { color: #fff !important; background: rgba(255,255,255,0.1); }
        .nav-link-sidebar.active { color: #fff !important; background: var(--bs-primary); box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3); }

        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.05); }
        
        /* Mobile handling */
        @media (max-width: 991.98px) {
            .admin-sidebar { margin-left: -280px; }
            .admin-sidebar.show { margin-left: 0; }
            .admin-main-content { margin-left: 0; width: 100%; padding: 1rem; }
            .admin-main-content.pushed { margin-left: 280px; width: auto; }
        }
        .mobile-toggle { display: none; }
        @media (max-width: 991.98px) { .mobile-toggle { display: block; } }
    </style>
    <!-- Theme Script -->
    <script>
        const storedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', storedTheme);
    </script>
</head>
<body>

<!-- Sidebar -->
<aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-brand text-center d-flex align-items-center justify-content-center">
        <i class="bi bi-shield-check text-primary me-2"></i>
        <span>Admin Panel</span>
    </div>
    
    <!-- Perfil del Usuario Actual -->
    <div class="px-4 py-3 text-center border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-primary rounded-circle mb-2" style="width: 50px; height: 50px; font-size: 1.5rem; border: 2px solid var(--bs-primary);">
            <?php 
                $initials = strtoupper(substr($user_session['first_name'] ?? 'A', 0, 1) . substr($user_session['last_name'] ?? 'U', 0, 1));
                echo htmlspecialchars($initials); 
            ?>
        </div>
        <div class="fw-bold text-white lh-sm"><?php echo htmlspecialchars($user_session['first_name'] . ' ' . $user_session['last_name']); ?></div>
        <div class="small text-light opacity-75 mt-1" style="font-size: 0.8rem;"><i class="bi bi-person-vcard me-1"></i> <?php echo isset($user_session['dni']) ? htmlspecialchars($user_session['dni']) : 'ID: ' . htmlspecialchars($user_session['id']); ?></div>
        <div class="badge bg-primary bg-opacity-25 text-white border border-primary mt-2 fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
            <i class="bi bi-shield-lock-fill me-1"></i> <?php echo htmlspecialchars($user_session['role']); ?>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <div class="px-4 py-2 text-uppercase small fw-bold text-light opacity-75" style="letter-spacing: 0.5px; font-size: 0.7rem;">Gestión Principal</div>
        
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="nav-link-sidebar <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
        </div>
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/admin/tickets.php" class="nav-link-sidebar <?php echo $currentPage == 'tickets.php' ? 'active' : ''; ?>">
                <i class="bi bi-ticket-detailed"></i> Control de Tickets
            </a>
        </div>
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/admin/crear_ticket.php" class="nav-link-sidebar <?php echo $currentPage == 'crear_ticket.php' ? 'active' : ''; ?>">
                <i class="bi bi-plus-circle"></i> Crear Ticket
            </a>
        </div>
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/admin/vista_tecnico.php" class="nav-link-sidebar <?php echo $currentPage == 'vista_tecnico.php' ? 'active' : ''; ?>">
                <i class="bi bi-eye"></i> Ver como Técnico
            </a>
        </div>

        <div class="px-4 py-2 mt-2 text-uppercase small fw-bold text-light opacity-75" style="letter-spacing: 0.5px; font-size: 0.7rem;">Directorio</div>
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/admin/usuarios.php" class="nav-link-sidebar <?php echo $currentPage == 'usuarios.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Usuarios
            </a>
        </div>
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/admin/trabajadores.php" class="nav-link-sidebar <?php echo $currentPage == 'trabajadores.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-lines-fill"></i> Técnicos / Admins
            </a>
        </div>
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/admin/oficinas.php" class="nav-link-sidebar <?php echo $currentPage == 'oficinas.php' ? 'active' : ''; ?>">
                <i class="bi bi-building"></i> Oficinas
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <!-- Usuario Perfil Shortcut -->
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/admin/configuracion.php" class="nav-link-sidebar <?php echo $currentPage == 'configuracion.php' ? 'active' : 'bg-dark border border-secondary border-opacity-25 text-white'; ?> mb-2">
                <i class="bi bi-gear-fill"></i> Configuración
            </a>
        </div>
        <div class="nav-item-sidebar">
            <a href="<?php echo BASE_URL; ?>/logout.php" class="nav-link-sidebar bg-danger bg-opacity-25 text-danger border-0">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </div>
    </div>
</aside>

<!-- Main Wrapper -->
<main class="admin-main-content fade-in" id="main-content">
    
    <!-- Mobile Toggle Navbar -->
    <div class="d-flex align-items-center mb-4 mobile-toggle">
        <button class="btn btn-primary shadow-sm" id="btnToggleSidebar">
            <i class="bi bi-list"></i> Menú
        </button>
        <span class="ms-3 fw-bold text-dark fs-5">Admin Panel</span>
    </div>
