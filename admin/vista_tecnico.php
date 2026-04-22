<?php
require '../includes/auth.php';
require '../config/database.php';
restrict_access(['admin']);
require 'includes/admin_header.php';

$limit = 10;
$page_active = isset($_GET['pa']) ? max(1, (int) $_GET['pa']) : 1;
$page_finished = isset($_GET['pf']) ? max(1, (int) $_GET['pf']) : 1;
$offset_ac = ($page_active - 1) * $limit;
$offset_fi = ($page_finished - 1) * $limit;

// Obtener la lista de técnicos y sus métricas superficiales si las queremos (ticket count)
$stmtT = $conn->query("
    SELECT tr.*, 
           (SELECT COUNT(*) FROM tickets t WHERE t.technician_id = tr.id AND t.status NOT IN ('Atendido', 'Rechazado')) as active_count,
           (SELECT COUNT(*) FROM tickets t WHERE t.technician_id = tr.id AND t.status IN ('Atendido', 'Rechazado')) as finished_count
    FROM trabajadores tr WHERE role = 'tecnico' ORDER BY first_name
");
$technicians = $stmtT->fetchAll(PDO::FETCH_ASSOC);

function renderPagination($current, $total, $paramName, $otherParamName, $otherValue, $techId)
{
    if ($total <= 1)
        return "";
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
    for ($i = 1; $i <= $total; $i++) {
        $active = ($i == $current) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="?tech_id=' . $techId . '&' . $paramName . '=' . $i . '&' . $otherParamName . '=' . $otherValue . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

$tech_id = isset($_GET['tech_id']) ? (int) $_GET['tech_id'] : null;

$tickets_ac = [];
$tickets_fi = [];
$pages_ac = 0;
$pages_fi = 0;
$selected_tech_name = "";
$selected_tech_dni = "";

if ($tech_id) {
    foreach ($technicians as $tech) {
        if ($tech['id'] == $tech_id) {
            $selected_tech_name = $tech['first_name'] . ' ' . $tech['last_name'];
            $selected_tech_dni = $tech['dni'];
            break;
        }
    }

    // TCKTS ACTIVOS TECNICO
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
    $stmtC->execute(['id' => $tech_id]);
    $total_ac = $stmtC->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status, u.first_name, u.last_name FROM tickets t JOIN usuarios u ON t.user_id = u.id WHERE t.technician_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $tech_id]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TCKTS FINALIZADOS TECNICO
    $stmtF = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND status IN ('Atendido', 'Rechazado')");
    $stmtF->execute(['id' => $tech_id]);
    $total_fi = $stmtF->fetchColumn();
    $pages_fi = ceil($total_fi / $limit);

    $stmt = $conn->prepare("SELECT t.*, t.status as current_status, u.first_name, u.last_name FROM tickets t JOIN usuarios u ON t.user_id = u.id WHERE t.technician_id = :id AND status IN ('Atendido', 'Rechazado') ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_fi");
    $stmt->execute(['id' => $tech_id]);
    $tickets_fi = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Vista de Técnico</h2>
        <p class="text-muted mb-0">Supervisa las bandejas o asume el rol interactivo de tus técnicos.</p>
    </div>
    <?php if ($tech_id): ?>
    <div>
        <a href="vista_tecnico.php" class="btn btn-outline-secondary"><i class="bi bi-grid me-2"></i>Volver al Directorio</a>
    </div>
    <?php endif; ?>
</div>

<?php if (!$tech_id): ?>
    <!-- DIRECTORIO DE TÉCNICOS (GRID UI) -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
        <?php foreach ($technicians as $tech): ?>
        <div class="col">
            <a href="?tech_id=<?php echo $tech['id']; ?>" class="card h-100 border-0 shadow-sm text-decoration-none glass-card stat-card-clickable" style="transition: transform 0.2s, box-shadow 0.2s;">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                        <i class="bi bi-person-workspace fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?></h5>
                    <p class="text-muted small mb-3">DNI: <?php echo htmlspecialchars($tech['dni']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <div class="badge bg-light text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill">
                            <i class="bi bi-activity me-1"></i> <?php echo $tech['active_count']; ?> Activos
                        </div>
                        <div class="badge bg-light text-secondary border border-secondary border-opacity-25 px-3 py-2 rounded-pill">
                            <i class="bi bi-check2-all me-1"></i> <?php echo $tech['finished_count']; ?> Finalizados
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- VISTA DEL TÉCNICO SELECCIONADO -->
    <div class="card border-0 shadow-sm mb-4 bg-primary text-white bg-opacity-75" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
        <div class="card-body p-4 d-flex align-items-center">
            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center me-4 shadow-sm" style="width: 60px; height: 60px;">
                <i class="bi bi-person-fill fs-2"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1">Bandeja de: <?php echo htmlspecialchars($selected_tech_name); ?></h4>
                <p class="mb-0 text-white-50"><i class="bi bi-shield-lock me-1"></i> Tienes permiso para operar en su nombre.</p>
            </div>
        </div>
    </div>

    <!-- TABLA ACTIVOS: TECNICO -->
    <div class="card glass-card border-0 mb-4 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-list-task text-primary me-2"></i> Tickets Asignados Activos</h5>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Ticket</th>
                            <th>Usuario</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_ac) > 0): ?>
                            <?php foreach ($tickets_ac as $t): 
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                            ?>
                                <tr class="ticket-row"
                                    onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>&impersonate_tech=<?php echo $tech_id; ?>'">
                                    <td class="ps-4"><span class="text-muted fw-bold"><?php echo date('Y', strtotime($t['created_at'])) . str_pad($t['id'], 3, '0', STR_PAD_LEFT); ?></span></td>
                                    <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><?php echo htmlspecialchars($t['category']); ?></td>
                                    <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                    <td class="pe-4 text-end"><a href="../ticket_detalle.php?id=<?php echo $t['id']; ?>&impersonate_tech=<?php echo $tech_id; ?>" class="btn btn-sm btn-primary rounded-pill px-3">Impersonar</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">El técnico no tiene tickets activos en esta bandeja.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', $page_finished, $tech_id); ?>
        </div>
    </div>

    <!-- TABLA FINALIZADOS: TECNICO -->
    <div class="card glass-card border-0 mb-4 shadow-sm opacity-75">
        <div class="card-header bg-white border-bottom pt-4 pb-3">
            <h5 class="fw-bold mb-0 text-muted"><i class="bi bi-clock-history me-2"></i> Historial de Tickets Atendidos</h5>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted">
                        <tr>
                            <th class="ps-4">Ticket</th>
                            <th>Usuario</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_fi) > 0): ?>
                            <?php foreach ($tickets_fi as $t):
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                                ?>
                                <tr class="ticket-row"
                                    onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>&impersonate_tech=<?php echo $tech_id; ?>'">
                                    <td class="ps-4"><span class="text-muted fw-bold"><?php echo date('Y', strtotime($t['created_at'])) . str_pad($t['id'], 3, '0', STR_PAD_LEFT); ?></span></td>
                                    <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><?php echo htmlspecialchars($t['category']); ?></td>
                                    <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                    <td class="pe-4 text-end"><a href="../ticket_detalle.php?id=<?php echo $t['id']; ?>&impersonate_tech=<?php echo $tech_id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Revisar</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">El técnico no tiene tickets finalizados en el historial.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_finished, $pages_fi, 'pf', 'pa', $page_active, $tech_id); ?>
        </div>
    </div>
<?php endif; ?>

<style>
.stat-card-clickable:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
</style>

<?php require 'includes/admin_footer.php'; ?>