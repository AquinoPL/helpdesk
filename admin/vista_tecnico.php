<?php
require '../includes/auth.php';
require '../config/database.php';
restrict_access(['admin']);
require 'includes/admin_header.php';

// Limite de paginacion (simulando lo de index.php)
$limit = 10;
$page_active = isset($_GET['pa']) ? max(1, (int) $_GET['pa']) : 1;
$page_finished = isset($_GET['pf']) ? max(1, (int) $_GET['pf']) : 1;
$offset_ac = ($page_active - 1) * $limit;
$offset_fi = ($page_finished - 1) * $limit;

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

// Obtener lista de técnicos
$stmtTechs = $conn->query("SELECT id, first_name, last_name, dni FROM trabajadores WHERE role = 'tecnico' AND is_active = TRUE ORDER BY first_name ASC");
$technicians = $stmtTechs->fetchAll(PDO::FETCH_ASSOC);

$tech_id = isset($_GET['tech_id']) ? (int) $_GET['tech_id'] : null;

$tickets_ac = [];
$tickets_fi = [];
$pages_ac = 0;
$pages_fi = 0;
$selected_tech_name = "";

if ($tech_id) {
    // Buscar nombre del tecnico seleccionado
    foreach ($technicians as $tech) {
        if ($tech['id'] == $tech_id) {
            $selected_tech_name = $tech['first_name'] . ' ' . $tech['last_name'];
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
        <p class="text-muted mb-0">Visualiza y gestiona el panel desde la perspectiva de un técnico.</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4 bg-light rounded">
        <form method="GET" class="d-flex align-items-end gap-3">
            <div class="flex-grow-1" style="max-width: 400px;">
                <label class="form-label fw-medium">Seleccionar Técnico</label>
                <select name="tech_id" class="form-select form-select-lg shadow-sm" required
                    onchange="this.form.submit()">
                    <option value="" disabled <?php echo !$tech_id ? 'selected' : ''; ?>>Elige un técnico de la lista...
                    </option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>" <?php echo ($tech_id == $tech['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name'] . ' - DNI: ' . $tech['dni']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($tech_id): ?>
                <div class="mb-2 ms-3">
                    <span class="badge bg-primary fs-6"><i class="bi bi-person-check-fill me-1"></i> Visualizando a:
                        <?php echo htmlspecialchars($selected_tech_name); ?></span>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($tech_id): ?>

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
                                $rowClass = '';
                                if ($t['current_status'] == 'Pendiente')
                                    $rowClass = 'table-warning';
                                elseif ($t['current_status'] == 'En camino')
                                    $rowClass = 'table-primary';
                                elseif ($t['current_status'] == 'En proceso')
                                    $rowClass = 'table-info';
                                ?>
                                <tr class="ticket-row <?php echo $rowClass; ?>"
                                    onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                    <td class="ps-4"><span
                                            class="text-muted fw-bold">#<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><?php echo htmlspecialchars($t['category']); ?></td>
                                    <td><span
                                            class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span>
                                    </td>
                                    <td class="pe-4 text-end"><a href="../ticket_detalle.php?id=<?php echo $t['id']; ?>"
                                            class="btn btn-sm btn-primary rounded-pill px-3">Gestionar</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">El técnico no tiene tickets activos.</td>
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
                                $rowClass = '';
                                if ($t['current_status'] == 'Atendido')
                                    $rowClass = 'table-success';
                                elseif ($t['current_status'] == 'Rechazado')
                                    $rowClass = 'table-danger';
                                ?>
                                <tr class="ticket-row <?php echo $rowClass; ?>"
                                    onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                    <td class="ps-4"><span
                                            class="text-muted fw-bold">#<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><?php echo htmlspecialchars($t['category']); ?></td>
                                    <td><span
                                            class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span>
                                    </td>
                                    <td class="pe-4 text-end"><a href="../ticket_detalle.php?id=<?php echo $t['id']; ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-pill px-3">Revisar</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">El técnico no tiene tickets finalizados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_finished, $pages_fi, 'pf', 'pa', $page_active, $tech_id); ?>
        </div>
    </div>

<?php else: ?>
    <div class="text-center py-5">
        <i class="bi bi-person-bounding-box text-muted" style="font-size: 4rem;"></i>
        <h4 class="mt-3 text-muted">Por favor seleccione un técnico para visualizar su panel.</h4>
    </div>
<?php endif; ?>

<?php require 'includes/admin_footer.php'; ?>