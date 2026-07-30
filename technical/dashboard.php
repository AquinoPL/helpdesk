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

    <?php
    // Paginación y consulta de Tickets Asignados Activos
    $limit = 10;
    $page_active = isset($_GET['pa']) ? (int)$_GET['pa'] : 1;
    if ($page_active < 1) $page_active = 1;
    $offset_ac = ($page_active - 1) * $limit;

    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
    $stmtC->execute(['id' => $user['id']]);
    $total_ac = $stmtC->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status, u.first_name, u.last_name, o.name as office_name FROM tickets t JOIN usuarios u ON t.user_id = u.id LEFT JOIN oficina o ON t.office_id = o.id WHERE t.technician_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!function_exists('renderTechPagination')) {
        function renderTechPagination($current, $total, $paramName) {
            if ($total <= 1) return "";
            $html = '<nav><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
            for ($i = 1; $i <= $total; $i++) {
                $active = ($i == $current) ? 'active' : '';
                $html .= '<li class="page-item '.$active.'"><a class="page-link" href="?'.$paramName.'='.$i.'">'.$i.'</a></li>';
            }
            $html .= '</ul></nav>';
            return $html;
        }
    }
    ?>

    <!-- TABLA TICKETS ASIGNADOS (TÉCNICO) -->
    <div class="card card-plain border-0 mb-4" id="tickets-asignados">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-list-task text-primary me-2"></i> Mis Tickets Asignados</h5>
            <a href="tickets_asignados.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Ver todos</a>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                        <tr>
                            <th class="ps-4">Ticket</th>
                            <th>Usuario / Remitente</th>
                            <th>Oficina</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th class="text-end pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_ac) > 0): ?>
                            <?php foreach ($tickets_ac as $t): 
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                            ?>
                            <tr class="ticket-row" onclick="window.location='../ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['office_name'] ?? 'Sin oficina'); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 text-end">
                                    <a href="../ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0; line-height: 30px;" title="Gestionar" onclick="event.stopPropagation();">
                                        <i class="bi bi-gear"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No tienes tickets asignados activos en este momento.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderTechPagination($page_active, $pages_ac, 'pa'); ?>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
