<nav class="navbar-public d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?php echo BASE_URL; ?>/index.php" class="text-decoration-none">
        <div class="brand"><i class="bi bi-headset me-2"></i>HelpDesk<span style="color:var(--accent)">.</span></div>
    </a>
    <div class="d-flex gap-2">
        <?php if(isset($_SESSION["user"])): ?>
            <?php if($_SESSION["user"]["role"] == 'admin' || $_SESSION["user"]["role"] == 'tecnico'): ?>
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="btn btn-sm btn-outline-light">Panel de Control</a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>/historial.php" class="btn btn-sm btn-outline-light">Mi Historial</a>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-sm text-white" style="background:var(--accent)">Cerrar sesión</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-sm btn-outline-light">Iniciar sesión</a>
            <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-sm text-white" style="background:var(--accent)">Crear cuenta</a>
        <?php endif; ?>
    </div>
</nav>
