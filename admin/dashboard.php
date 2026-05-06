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

<div class="row mb-4 align-items-center">
    <div class="col">
        <h2 class="fw-bold mb-1">Panel de Administración</h2>
        <p class="text-muted mb-0">Vista general de todos los tickets del sistema.</p>
    </div>
    <?php if ($filter): ?>
    <div class="col-auto">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm shadow-sm"><i class="bi bi-x-circle me-1"></i> Quitar Filtro</a>
    </div>
    <?php endif; ?>
</div>

<!-- Tarjetas de Estadísticas -->
<style>
.stat-card-clickable { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; text-decoration: none; display: block; }
.stat-card-clickable:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
</style>
<div class="row g-4 mb-5">
    <div class="col-md col-sm-6">
        <a href="?f=Total" class="card glass-card border-0 bg-primary text-white h-100 stat-card-clickable">
            <div class="card-body p-3 p-xl-4 text-center">
                <i class="bi bi-ticket-detailed fs-1 mb-2 opacity-75"></i>
                <h2 class="fw-bold mb-0 stat-card-value"><?php echo $totalTickets; ?></h2>
                <p class="mb-0 fw-medium">Total</p>
            </div>
        </a>
    </div>
    <div class="col-md col-sm-6">
        <a href="?f=Pendiente" class="card glass-card border-0 bg-warning text-dark h-100 stat-card-clickable">
            <div class="card-body p-3 p-xl-4 text-center">
                <i class="bi bi-clock-history fs-1 mb-2 opacity-75"></i>
                <h2 class="fw-bold mb-0 stat-card-value"><?php echo $pendientes; ?></h2>
                <p class="mb-0 fw-medium">Pendientes</p>
            </div>
        </a>
    </div>
    <div class="col-md col-sm-6">
        <a href="?f=En camino" class="card glass-card border-0 text-white h-100 stat-card-clickable" style="background-color: #6f42c1;">
            <div class="card-body p-3 p-xl-4 text-center">
                <i class="bi bi-person-lines-fill fs-1 mb-2 opacity-75"></i>
                <h2 class="fw-bold mb-0 stat-card-value"><?php echo $enCamino; ?></h2>
                <p class="mb-0 fw-medium">En Camino</p>
            </div>
        </a>
    </div>
    <div class="col-md col-sm-6">
        <a href="?f=En proceso" class="card glass-card border-0 bg-info text-dark h-100 stat-card-clickable">
            <div class="card-body p-3 p-xl-4 text-center">
                <i class="bi bi-arrow-repeat fs-1 mb-2 opacity-75"></i>
                <h2 class="fw-bold mb-0 stat-card-value"><?php echo $enProceso; ?></h2>
                <p class="mb-0 fw-medium">En Proceso</p>
            </div>
        </a>
    </div>
    <div class="col-md col-sm-6">
        <a href="?f=Atendido" class="card glass-card border-0 bg-success text-white h-100 stat-card-clickable">
            <div class="card-body p-3 p-xl-4 text-center">
                <i class="bi bi-check2-circle fs-1 mb-2 opacity-75"></i>
                <h2 class="fw-bold mb-0 stat-card-value"><?php echo $atendidos; ?></h2>
                <p class="mb-0 fw-medium">Atendidos</p>
            </div>
        </a>
    </div>
</div>

<?php if ($filter): ?>
    <?php
    // VISTA FILTRADA (SOLO UNA TABLA)
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE COALESCE(status, 'Pendiente') = :st");
    $stmtC->execute(['st' => $filter]);
    $total_fl = $stmtC->fetchColumn();
    $pages_fl = ceil($total_fl / $limit);
    
    $stmt = $conn->prepare("SELECT t.*, u.first_name, u.last_name, COALESCE(t.status, 'Pendiente') as current_status FROM tickets t JOIN usuarios u ON t.user_id = u.id WHERE COALESCE(t.status, 'Pendiente') = :st ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['st' => $filter]);
    $tickets_fl = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="card glass-card border-0 border-top border-4 border-primary">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-funnel text-primary me-2"></i> Tickets Filtrados: <?php echo htmlspecialchars($filter); ?></h5>
        </div>
        <div class="card-body p-2 pb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-borderless" style="border-spacing: 0 8px; border-collapse: separate;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Ticket</th>
                            <th>Creado por</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Creación</th>
                            <th class="pe-4 text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_fl) > 0): ?>
                            <?php foreach ($tickets_fl as $t): 
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                            ?>
                            <tr class="ticket-row shadow-sm bg-white rounded" style="cursor: pointer; margin-bottom: 10px;" onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4 py-3"><span class="text-muted fw-bold"><?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['category']); ?></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 py-3 text-end"><a href="../ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Revisar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No hay tickets encontrados para este estado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_fl, 'pa', 'pf', 1, $filter); ?>
        </div>
    </div>

<?php else: ?>
    <?php
    // VISTA NORMAL (DOS TABLAS, ACTIVOS Y FINALIZADOS)

    // ACTIVOS
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

    // FINALIZADOS
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

    <!-- Tickets Activos (Admin) -->
    <div class="card glass-card border-0 mb-4">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-circle text-warning me-2"></i> Tickets Activos (En Progreso)</h5>
        </div>
        <div class="card-body p-2 pb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-borderless" style="border-spacing: 0 8px; border-collapse: separate;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Ticket</th>
                            <th>Creado por</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Estado Actual</th>
                            <th>Creación</th>
                            <th class="pe-4 text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_ac) > 0): ?>
                            <?php foreach ($tickets_ac as $t): 
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                            ?>
                            <tr class="ticket-row shadow-sm bg-white rounded" style="cursor: pointer; margin-bottom: 10px;" onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4 py-3"><span class="text-muted fw-bold"><?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['category']); ?></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 py-3 text-end"><a href="../ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Gestionar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No hay tickets activos en este momento.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', $page_finished, ''); ?>
        </div>
    </div>

    <!-- Tickets Finalizados (Admin) -->
    <div class="card glass-card border-0 opacity-75">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0 text-muted"><i class="bi bi-journal-check me-2"></i> Historial de Tickets Finalizados</h5>
        </div>
        <div class="card-body p-2 pb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-borderless" style="border-spacing: 0 8px; border-collapse: separate;">
                    <thead class="table-light text-muted">
                        <tr>
                            <th class="ps-4">Ticket</th>
                            <th>Creado por</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Estado Actual</th>
                            <th>Creación</th>
                            <th class="pe-4 text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_fi) > 0): ?>
                            <?php foreach ($tickets_fi as $t): 
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                            ?>
                            <tr class="ticket-row shadow-sm bg-white rounded" style="cursor: pointer; margin-bottom: 10px;" onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4 py-3"><span class="text-muted fw-bold"><?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['category']); ?></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 py-3 text-end"><a href="../ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Revisar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No hay tickets finalizados aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_finished, $pages_fi, 'pf', 'pa', $page_active, ''); ?>
        </div>
    </div>

<?php endif; ?>

<?php require 'includes/admin_footer.php'; ?>