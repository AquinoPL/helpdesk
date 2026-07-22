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

<div class="card p-3 mt-4 mb-4 flex-row justify-content-between align-items-center"><div>
        <h4 class="fw-bold mb-0">Vista de Técnico</h4>
        <div class="text-muted small">Supervisa las bandejas o asume el rol interactivo de tus técnicos.</div>
    </div>
    <?php if ($tech_id): ?>
    <div>
        <a href="vista_tecnico.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-grid me-1"></i> Directorio</a>
    </div>
    <?php endif; ?>
</div>

<?php if (!$tech_id): ?>
    <!-- DIRECTORIO DE TÉCNICOS (GRID UI) -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-5">
        <?php foreach ($technicians as $tech): ?>
        <div class="col">
            <a href="?tech_id=<?php echo $tech['id']; ?>" class="card kpi-card h-100 text-decoration-none" style="transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                <div class="card-body p-4 text-center">
                    <div class="avatar-circle mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; background: var(--deep-2);">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?></h6>
                    <p class="text-muted small mb-3" style="font-size: .75rem;">DNI: <?php echo htmlspecialchars($tech['dni']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2">
                        <div class="badge bg-light text-primary border px-2 py-1">
                            <?php echo $tech['active_count']; ?> Activos
                        </div>
                        <div class="badge bg-light text-secondary border px-2 py-1">
                            <?php echo $tech['finished_count']; ?> Finalizados
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- VISTA DEL TÉCNICO SELECCIONADO -->
    <div class="card card-plain mb-4" style="border-left: 4px solid var(--accent);">
        <div class="card-body p-3 d-flex align-items-center">
            <div class="avatar-circle me-3" style="background: var(--accent);">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0">Bandeja de: <?php echo htmlspecialchars($selected_tech_name); ?></h6>
                <div class="text-muted small" style="font-size:.75rem;"><i class="bi bi-shield-lock me-1"></i> Tienes permiso para operar en su nombre.</div>
            </div>
        </div>
    </div>

    <!-- TABLA ACTIVOS: TECNICO -->
    <div class="card card-plain mb-4">
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
            <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-list-task me-1"></i> Tickets Asignados Activos</h6>
        </div>
        <div class="card-body px-4 pt-3 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: .85rem;">
                    <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                        <tr>
                            <th>Folio</th>
                            <th>Usuario</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_ac) > 0): ?>
                            <?php foreach ($tickets_ac as $t): ?>
                                <tr class="ticket-row" onclick="window.location='../ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>&impersonate_tech=<?php echo $tech_id; ?>'">
                                    <td class="fw-bold text-dark">#<?php echo htmlspecialchars($t['id']); ?></td>
                                    <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                    <td><span class="badge badge-status badge-<?php echo str_replace(' ', '-', $t['current_status']); ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                    <td class="text-end"><a href="../ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>&impersonate_tech=<?php echo $tech_id; ?>" class="btn btn-sm btn-outline-primary">Impersonar</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">El técnico no tiene tickets activos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', $page_finished, $tech_id); ?>
        </div>
    </div>

    <!-- TABLA FINALIZADOS: TECNICO -->
    <div class="card card-plain mb-4 opacity-75">
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
            <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-clock-history me-1"></i> Historial Atendidos</h6>
        </div>
        <div class="card-body px-4 pt-3 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: .85rem;">
                    <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                        <tr>
                            <th>Folio</th>
                            <th>Usuario</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_fi) > 0): ?>
                            <?php foreach ($tickets_fi as $t): ?>
                                <tr class="ticket-row" onclick="window.location='../ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>&impersonate_tech=<?php echo $tech_id; ?>'">
                                    <td class="fw-bold text-dark">#<?php echo htmlspecialchars($t['id']); ?></td>
                                    <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                    <td><span class="badge badge-status badge-<?php echo str_replace(' ', '-', $t['current_status']); ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                    <td class="text-end"><a href="../ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>&impersonate_tech=<?php echo $tech_id; ?>" class="btn btn-sm btn-outline-secondary">Revisar</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">El técnico no tiene tickets finalizados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_finished, $pages_fi, 'pf', 'pa', $page_active, $tech_id); ?>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/admin_footer.php'; ?>
