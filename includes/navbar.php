<nav class="navbar-public sticky-top d-flex justify-content-between align-items-center flex-wrap gap-2" style="z-index: 1030;">
    <a href="<?php echo BASE_URL; ?>/index.php" class="text-decoration-none">
        <div class="brand"><i class="bi bi-headset me-2"></i>HelpDesk<span style="color:var(--accent)">.</span></div>
    </a>
    
    <!-- Enlaces Escritorio -->
    <div class="d-none d-lg-flex gap-2 align-items-center">
        <?php if(isset($_SESSION["user"])): ?>
            <?php if($_SESSION["user"]["role"] == 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="btn btn-sm btn-outline-light">Panel de Control</a>
            <?php elseif($_SESSION["user"]["role"] == 'tecnico'): ?>
                <a href="<?php echo BASE_URL; ?>/technical/dashboard.php" class="btn btn-sm btn-outline-light">Panel de Control</a>
                <a href="<?php echo BASE_URL; ?>/technical/tickets_pendientes.php" class="btn btn-sm btn-outline-light">Tickets Pendientes</a>
                <a href="<?php echo BASE_URL; ?>/technical/tickets_asignados.php" class="btn btn-sm btn-outline-light">Tickets Asignados</a>
                <a href="<?php echo BASE_URL; ?>/technical/perfil.php" class="btn btn-sm btn-outline-light"><i class="bi bi-person-circle"></i> Mi Perfil</a>
            <?php elseif($_SESSION["user"]["role"] == 'usuario'): ?>
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-sm btn-outline-light">Principal</a>
                <a href="<?php echo BASE_URL; ?>/ticket/historial.php" class="btn btn-sm btn-outline-light">Mi Historial</a>
                <a href="<?php echo BASE_URL; ?>/perfil.php" class="btn btn-sm btn-outline-light"><i class="bi bi-person-circle"></i> Mi Perfil</a>
            <?php endif; ?>
            
            <?php if($_SESSION["user"]["role"] != 'usuario'): ?>
                <a href="<?php echo BASE_URL; ?>/ticket/historial.php" class="btn btn-sm btn-outline-light">Mi Historial</a>
            <?php endif; ?>
            
            <a href="#" class="btn btn-sm text-white" style="background:var(--accent)" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">Cerrar sesión</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-sm btn-outline-light">Principal</a>
            <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#loginModal">Iniciar sesión</button>
            <button type="button" class="btn btn-sm text-white" style="background:var(--accent)" data-bs-toggle="modal" data-bs-target="#registerModal">Crear cuenta</button>
        <?php endif; ?>
    </div>

    <!-- Toggler Móvil -->
    <div class="d-lg-none">
        <button class="btn btn-outline-light btn-sm px-3 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNavbar">
            <i class="bi bi-list fs-4"></i>
        </button>
    </div>
</nav>

<!-- Offcanvas Móvil (Derecha) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileNavbar" style="width: 240px;">
  <div class="offcanvas-header border-bottom bg-light">
    <?php if(isset($_SESSION["user"])): ?>
        <div class="d-flex align-items-center gap-3">
            <span class="avatar-circle" style="background:var(--accent); width: 45px; height: 45px; font-size: 1.2rem;">
                <?php echo strtoupper(substr($_SESSION['user']['first_name'] ?? 'U', 0, 1) . substr($_SESSION['user']['last_name'] ?? 'S', 0, 1)); ?>
            </span>
            <div>
                <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars(($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? '')); ?></h6>
                <div class="small text-muted text-uppercase fw-medium" style="font-size: 0.75rem;"><?php echo htmlspecialchars($_SESSION['user']['role'] ?? ''); ?></div>
            </div>
        </div>
    <?php else: ?>
        <h5 class="offcanvas-title fw-bold text-dark"><i class="bi bi-headset me-2 text-primary"></i>HelpDesk</h5>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column">
    <div class="list-group list-group-flush">
        <?php if(isset($_SESSION["user"])): ?>
            <?php if($_SESSION["user"]["role"] == 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-grid-1x2 me-3 fs-5 text-primary"></i> Panel de Control
                </a>
            <?php elseif($_SESSION["user"]["role"] == 'tecnico'): ?>
                <a href="<?php echo BASE_URL; ?>/technical/dashboard.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-grid-1x2 me-3 fs-5 text-primary"></i> Panel de Control
                </a>
            <?php endif; ?>
            
            <?php if($_SESSION["user"]["role"] == 'tecnico'): ?>
                <a href="<?php echo BASE_URL; ?>/technical/tickets_pendientes.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-inbox me-3 fs-5 text-warning"></i> Tickets Pendientes
                </a>
                <a href="<?php echo BASE_URL; ?>/technical/tickets_asignados.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-list-task me-3 fs-5 text-primary"></i> Tickets Asignados
                </a>
                <a href="<?php echo BASE_URL; ?>/technical/perfil.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-person-circle me-3 fs-5 text-info"></i> Mi Perfil
                </a>
            <?php endif; ?>

            <?php if($_SESSION["user"]["role"] == 'usuario'): ?>
                <a href="<?php echo BASE_URL; ?>/index.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-house-door me-3 fs-5 text-secondary"></i> Principal
                </a>
                <a href="<?php echo BASE_URL; ?>/ticket/historial.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-clock-history me-3 fs-5 text-info"></i> Mi Historial
                </a>
                <a href="<?php echo BASE_URL; ?>/perfil.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-person-circle me-3 fs-5 text-secondary"></i> Mi Perfil
                </a>
            <?php endif; ?>

            <?php if($_SESSION["user"]["role"] != 'usuario'): ?>
                <a href="<?php echo BASE_URL; ?>/ticket/historial.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                    <i class="bi bi-clock-history me-3 fs-5 text-info"></i> Mi Historial
                </a>
            <?php endif; ?>
            <a href="#" class="list-group-item list-group-item-action border-0 py-3 fw-medium text-danger mt-auto" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal" data-bs-dismiss="offcanvas">
                <i class="bi bi-box-arrow-right me-3 fs-5"></i> Cerrar sesión
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/index.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                <i class="bi bi-house-door me-3 fs-5 text-secondary"></i> Principal
            </a>
            <a href="#" class="list-group-item list-group-item-action border-0 py-3 fw-medium" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="offcanvas">
                <i class="bi bi-box-arrow-in-right me-3 fs-5 text-primary"></i> Iniciar sesión
            </a>
            <a href="#" class="list-group-item list-group-item-action border-0 py-3 fw-medium" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="offcanvas">
                <i class="bi bi-person-plus me-3 fs-5 text-success"></i> Crear cuenta
            </a>
        <?php endif; ?>
    </div>
  </div>
</div>

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
