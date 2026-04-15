<style>
/* Solo aplica los recortes visuales al menú lateral cuando estamos en móviles (pantallas pequeñas) */
@media (max-width: 991.98px) {
    #offcanvasNavbar {
        max-width: 260px !important;
        height: fit-content !important;
        bottom: auto !important;
        border-bottom-left-radius: 15px !important;
    }
}
</style>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm custom-navbar mb-4 sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo BASE_URL; ?>/index.php">
            <i class="bi bi-headset fs-4 me-2"></i> Soporte Alianza
        </a>
        
        <!-- Toggle button for offcanvas -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Offcanvas Mobile Menu -->
        <div class="offcanvas offcanvas-end text-bg-primary" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
            <div class="offcanvas-header border-bottom border-light border-opacity-10">
                <h5 class="offcanvas-title text-white fw-bold d-flex align-items-center" id="offcanvasNavbarLabel">
                    <i class="bi bi-headset me-2"></i> Menú
                </h5>
                <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav me-auto">
                    <?php if(isset($_SESSION["user"])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
                        </li>
                        <?php if($_SESSION["user"]["role"] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard Admin</a>
                            </li>
                        <?php endif; ?>
                        <?php if($_SESSION["user"]["role"] == 'usuario'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/ticket.php"><i class="bi bi-plus-circle"></i> Nuevo Ticket</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION["user"])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-5 me-2"></i> 
                                <?php echo htmlspecialchars($_SESSION["user"]["first_name"] . ' ' . $_SESSION["user"]["last_name"]); ?> 
                                <span class="badge bg-light text-primary ms-2 opacity-75"><?php echo ucfirst(htmlspecialchars($_SESSION["user"]["role"])); ?></span>
                            </a>
                            <!-- Dropdown Menu -->
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                                <li>
                                    <a class="dropdown-item py-2 fw-medium" href="<?php echo BASE_URL; ?>/perfil.php">
                                        <i class="bi bi-person-gear me-2"></i> Mi Perfil
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger py-2 fw-medium" href="<?php echo BASE_URL; ?>/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-light text-primary fw-bold px-3 py-2 ms-lg-2" href="<?php echo BASE_URL; ?>/login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</nav>
