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

require '../includes/header.php';

// Obtener métricas rápidas para el dashboard
$stmtPend = $conn->query("
    SELECT COUNT(*) 
    FROM tickets 
    WHERE (technician_id IS NULL OR technician_id = 0)
      AND (status = 'Pendiente' OR status IS NULL)
");
$pendientes = $stmtPend->fetchColumn();

$stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
$stmtC->execute(['id' => $user['id']]);
$activos = $stmtC->fetchColumn();

// Obtener tickets en historial
$stmtH = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE (user_id = :user_id OR technician_id = :tech_id) AND status IN ('Atendido', 'Rechazado')");
$stmtH->execute(['user_id' => $user['id'], 'tech_id' => $user['id']]);
$historial_count = $stmtH->fetchColumn();
?>

<style>
.stat-card-clickable { text-decoration: none; display: block; color: inherit; }
.stat-card-clickable .kpi-card { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; border-radius: 12px; }
.stat-card-clickable:hover .kpi-card { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
</style>

<div class="py-4">
    <div class="card p-3 mt-4 mb-4 flex-row justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-1">Hola, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
            <p class="text-muted mb-0">Panel de Control de Técnico.</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <a href="tickets_pendientes.php" class="stat-card-clickable">
                <div class="card kpi-card p-4 border-0 shadow-sm h-100 glass-card">
                    <div class="text-muted fw-bold mb-2 text-uppercase" style="font-size: 0.85rem;">Tickets Pendientes</div>
                    <div class="d-flex align-items-end justify-content-between">
                        <h2 class="mb-0 fw-bolder text-warning"><?php echo $pendientes; ?></h2>
                        <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-inbox-fill fs-4 text-warning"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <a href="tickets_asignados.php" class="stat-card-clickable">
                <div class="card kpi-card p-4 border-0 shadow-sm h-100 glass-card">
                    <div class="text-muted fw-bold mb-2 text-uppercase" style="font-size: 0.85rem;">Tus Asignados</div>
                    <div class="d-flex align-items-end justify-content-between">
                        <h2 class="mb-0 fw-bolder text-primary"><?php echo $activos; ?></h2>
                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-list-task fs-4 text-primary"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="../ticket/historial.php" class="stat-card-clickable">
                <div class="card kpi-card p-4 border-0 shadow-sm h-100 glass-card">
                    <div class="text-muted fw-bold mb-2 text-uppercase" style="font-size: 0.85rem;">Mi Historial</div>
                    <div class="d-flex align-items-end justify-content-between">
                        <h2 class="mb-0 fw-bolder text-info"><?php echo $historial_count; ?></h2>
                        <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-clock-history fs-4 text-info"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="perfil.php" class="stat-card-clickable">
                <div class="card kpi-card p-4 border-0 shadow-sm h-100 glass-card">
                    <div class="text-muted fw-bold mb-2 text-uppercase" style="font-size: 0.85rem;">Editar Perfil</div>
                    <div class="d-flex align-items-end justify-content-between">
                        <h2 class="mb-0 fw-bolder text-secondary"><i class="bi bi-gear"></i></h2>
                        <div class="bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-person-circle fs-4 text-secondary"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
