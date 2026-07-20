<nav class="navbar-public d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?php echo BASE_URL; ?>/index.php" class="text-decoration-none">
        <div class="brand"><i class="bi bi-headset me-2"></i>HelpDesk<span style="color:var(--accent)">.</span></div>
    </a>
    
    <!-- Enlaces Escritorio -->
    <div class="d-none d-lg-flex gap-2 align-items-center">
        <?php if(isset($_SESSION["user"])): ?>
            <?php if($_SESSION["user"]["role"] == 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="btn btn-sm btn-outline-light">Panel de Control</a>
            <?php elseif($_SESSION["user"]["role"] == 'tecnico'): ?>
                <a href="<?php echo BASE_URL; ?>/index.php#dashboard" class="btn btn-sm btn-outline-light">Panel de Control</a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>/historial.php" class="btn btn-sm btn-outline-light">Mi Historial</a>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-sm text-white" style="background:var(--accent)" onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?');">Cerrar sesión</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-sm btn-outline-light">Iniciar sesión</a>
            <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-sm text-white" style="background:var(--accent)">Crear cuenta</a>
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
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileNavbar" style="width: 300px;">
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
                <a href="<?php echo BASE_URL; ?>/index.php#dashboard" class="list-group-item list-group-item-action border-0 py-3 fw-medium" data-bs-dismiss="offcanvas">
                    <i class="bi bi-grid-1x2 me-3 fs-5 text-primary"></i> Panel de Control
                </a>
            <?php endif; ?>
            
            <?php if($_SESSION["user"]["role"] == 'tecnico'): ?>
                <a href="<?php echo BASE_URL; ?>/index.php#tickets-pendientes" class="list-group-item list-group-item-action border-0 py-3 fw-medium" data-bs-dismiss="offcanvas">
                    <i class="bi bi-inbox me-3 fs-5 text-warning"></i> Tickets Pendientes
                </a>
                <a href="<?php echo BASE_URL; ?>/index.php#tickets-asignados" class="list-group-item list-group-item-action border-0 py-3 fw-medium" data-bs-dismiss="offcanvas">
                    <i class="bi bi-list-task me-3 fs-5 text-primary"></i> Tickets Asignados
                </a>
            <?php endif; ?>

            <a href="<?php echo BASE_URL; ?>/historial.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                <i class="bi bi-clock-history me-3 fs-5 text-info"></i> Mi Historial
            </a>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium text-danger mt-auto" onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?');">
                <i class="bi bi-box-arrow-right me-3 fs-5"></i> Cerrar sesión
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/login.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                <i class="bi bi-box-arrow-in-right me-3 fs-5 text-primary"></i> Iniciar sesión
            </a>
            <a href="<?php echo BASE_URL; ?>/register.php" class="list-group-item list-group-item-action border-0 py-3 fw-medium">
                <i class="bi bi-person-plus me-3 fs-5 text-success"></i> Crear cuenta
            </a>
        <?php endif; ?>
    </div>
  </div>
</div>
