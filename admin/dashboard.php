<?php
require '../includes/auth.php';
require '../config/database.php';

restrict_access(['admin']);

$user = $_SESSION["user"];

// Pagination and filter
$limit = 10;
$filter = isset($_GET['f']) ? $_GET['f'] : '';
$page_active = isset($_GET['pa']) ? max(1, (int)$_GET['pa']) : 1;
$page_finished = isset($_GET['pf']) ? max(1, (int)$_GET['pf']) : 1;
$offset_ac = ($page_active - 1) * $limit;
$offset_fi = ($page_finished - 1) * $limit;

if ($filter === 'Total') $filter = '';

require 'includes/admin_header.php';

// Helper for pagination
function renderPagination($current, $total, $paramName, $otherParamName, $otherValue, $filterStr) {
    if ($total <= 1) return "";
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
    $fParam = $filterStr ? "&f=".urlencode($filterStr) : "";
    for ($i = 1; $i <= $total; $i++) {
        $active = ($i == $current) ? 'active' : '';
        $html .= '<li class="page-item '.$active.'"><a class="page-link" href="?'.$paramName.'='.$i.'&'.$otherParamName.'='.$otherValue.$fParam.'">'.$i.'</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// Obtener métricas
$stmtCount = $conn->query("SELECT count(*) as total FROM tickets");
$totalTickets = $stmtCount->fetchColumn();

$stmtStatus = $conn->query("SELECT COALESCE(status, 'Pendiente') as current_status, COUNT(*) as count FROM tickets GROUP BY current_status");
$stats = [];
while ($row = $stmtStatus->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['current_status']] = $row['count'];
}

$pendientes = $stats['Pendiente'] ?? 0;
$enCamino   = $stats['En camino'] ?? 0;
$enProceso  = $stats['En proceso'] ?? 0;
$atendidos  = $stats['Atendido'] ?? 0;
// Note: rechazado not shown in cards
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Visión General</h4>
        <div class="text-muted small">Métricas y tickets recientes del sistema.</div>
    </div>
    <?php if ($filter): ?>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Quitar Filtro</a>
    <?php endif; ?>
</div>

<style>
.stat-card-clickable { text-decoration: none; display: block; color: inherit; }
.stat-card-clickable .kpi-card { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
.stat-card-clickable:hover .kpi-card { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
</style>

<div class="row g-3 mb-4">
    <div class="col-md col-sm-6">
        <a href="?f=Total" class="stat-card-clickable">
            <div class="card kpi-card p-3">
                <div class="text-muted small fw-medium mb-1">Total</div>
                <div class="d-flex align-items-end justify-content-between">
                    <h3 class="mb-0 fw-bold" style="color:var(--deep)"><?php echo $totalTickets; ?></h3>
                    <i class="bi bi-ticket-detailed fs-4 text-muted opacity-50"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md col-sm-6">
        <a href="?f=Pendiente" class="stat-card-clickable">
            <div class="card kpi-card p-3">
                <div class="text-muted small fw-medium mb-1">Pendientes</div>
                <div class="d-flex align-items-end justify-content-between">
                    <h3 class="mb-0 fw-bold text-warning"><?php echo $pendientes; ?></h3>
                    <i class="bi bi-clock-history fs-4 text-warning opacity-50"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md col-sm-6">
        <a href="?f=En camino" class="stat-card-clickable">
            <div class="card kpi-card p-3">
                <div class="text-muted small fw-medium mb-1">En Camino</div>
                <div class="d-flex align-items-end justify-content-between">
                    <h3 class="mb-0 fw-bold text-purple" style="color:#6f42c1"><?php echo $enCamino; ?></h3>
                    <i class="bi bi-person-lines-fill fs-4 opacity-50" style="color:#6f42c1"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md col-sm-6">
        <a href="?f=En proceso" class="stat-card-clickable">
            <div class="card kpi-card p-3">
                <div class="text-muted small fw-medium mb-1">En Proceso</div>
                <div class="d-flex align-items-end justify-content-between">
                    <h3 class="mb-0 fw-bold text-info"><?php echo $enProceso; ?></h3>
                    <i class="bi bi-arrow-repeat fs-4 text-info opacity-50"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md col-sm-6">
        <a href="?f=Atendido" class="stat-card-clickable">
            <div class="card kpi-card p-3">
                <div class="text-muted small fw-medium mb-1">Atendidos</div>
                <div class="d-flex align-items-end justify-content-between">
                    <h3 class="mb-0 fw-bold text-success"><?php echo $atendidos; ?></h3>
                    <i class="bi bi-check2-circle fs-4 text-success opacity-50"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<?php if ($filter): ?>
    <?php
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE COALESCE(status, 'Pendiente') = :st");
    $stmtC->execute(['st' => $filter]);
    $total_fl = $stmtC->fetchColumn();
    $pages_fl = ceil($total_fl / $limit);
    
    $stmt = $conn->prepare("SELECT t.*, u.first_name, u.last_name, COALESCE(t.status, 'Pendiente') as current_status FROM tickets t JOIN usuarios u ON t.user_id = u.id WHERE COALESCE(t.status, 'Pendiente') = :st ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['st' => $filter]);
    $tickets_fl = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="card card-plain mb-4">
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">Tickets Filtrados: <?php echo htmlspecialchars($filter); ?></h6>
        </div>
        <div class="card-body px-4 pt-3 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: .85rem;">
                    <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                        <tr>
                            <th>Folio</th>
                            <th>Solicitante</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Creación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_fl) > 0): ?>
                            <?php foreach ($tickets_fl as $t): ?>
                            <tr class="ticket-row" onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="fw-bold text-dark">#<?php echo htmlspecialchars($t['id']); ?></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                <td><span class="badge badge-status badge-<?php echo str_replace(' ', '-', $t['current_status']); ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No hay tickets encontrados para este estado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_fl, 'pa', 'pf', 1, $filter); ?>
        </div>
    </div>
<?php else: ?>
    <?php
    $stmtAcCount = $conn->query("SELECT COUNT(*) FROM tickets WHERE COALESCE(status, 'Pendiente') NOT IN ('Atendido', 'Rechazado')");
    $total_ac = $stmtAcCount->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmtAc = $conn->query("
        SELECT t.*, u.first_name, u.last_name, COALESCE(t.status, 'Pendiente') as current_status
        FROM tickets t JOIN usuarios u ON t.user_id = u.id
        WHERE COALESCE(t.status, 'Pendiente') NOT IN ('Atendido', 'Rechazado')
        ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac
    ");
    $tickets_ac = $stmtAc->fetchAll(PDO::FETCH_ASSOC);

    $stmtFiCount = $conn->query("SELECT COUNT(*) FROM tickets WHERE status IN ('Atendido', 'Rechazado')");
    $total_fi = $stmtFiCount->fetchColumn();
    $pages_fi = ceil($total_fi / $limit);

    $stmtFi = $conn->query("
        SELECT t.*, u.first_name, u.last_name, status as current_status
        FROM tickets t JOIN usuarios u ON t.user_id = u.id
        WHERE status IN ('Atendido', 'Rechazado')
        ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_fi
    ");
    $tickets_fi = $stmtFi->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- Tickets Activos -->
    <div class="card card-plain mb-4">
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
            <h6 class="fw-bold mb-0 text-warning"><i class="bi bi-exclamation-circle me-1"></i> Tickets Activos</h6>
        </div>
        <div class="card-body px-4 pt-3 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: .85rem;">
                    <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                        <tr>
                            <th>Folio</th>
                            <th>Solicitante</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Creación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_ac) > 0): ?>
                            <?php foreach ($tickets_ac as $t): ?>
                            <tr class="ticket-row" onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="fw-bold text-dark">#<?php echo htmlspecialchars($t['id']); ?></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                <td><span class="badge badge-status badge-<?php echo str_replace(' ', '-', $t['current_status']); ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No hay tickets activos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', $page_finished, ''); ?>
        </div>
    </div>

    <!-- Tickets Finalizados -->
    <div class="card card-plain opacity-75 mb-4">
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
            <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-journal-check me-1"></i> Historial Finalizados</h6>
        </div>
        <div class="card-body px-4 pt-3 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: .85rem;">
                    <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                        <tr>
                            <th>Folio</th>
                            <th>Solicitante</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Creación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_fi) > 0): ?>
                            <?php foreach ($tickets_fi as $t): ?>
                            <tr class="ticket-row" onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="fw-bold text-dark">#<?php echo htmlspecialchars($t['id']); ?></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                <td><span class="badge badge-status badge-<?php echo str_replace(' ', '-', $t['current_status']); ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No hay tickets finalizados aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_finished, $pages_fi, 'pf', 'pa', $page_active, ''); ?>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/admin_footer.php'; ?>
