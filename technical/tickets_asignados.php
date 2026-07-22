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

// Paginación
$limit = 10;
$page_active = isset($_GET['pa']) ? (int)$_GET['pa'] : 1;
if ($page_active < 1) $page_active = 1;
$offset_ac = ($page_active - 1) * $limit;

require '../includes/header.php';

// Helper para paginación visual
function renderPagination($current, $total, $paramName, $otherParamName, $otherValue) {
    if ($total <= 1) return "";
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
    for ($i = 1; $i <= $total; $i++) {
        $active = ($i == $current) ? 'active' : '';
        $html .= '<li class="page-item '.$active.'"><a class="page-link" href="?'.$paramName.'='.$i.'&'.$otherParamName.'='.$otherValue.'">'.$i.'</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

?>

<div class="py-4">

    <div class="card p-3 mt-4 mb-4 flex-row justify-content-between align-items-center"><div>
            <h2 class="fw-bold mb-1">Tickets Asignados</h2>
            <p class="text-muted mb-0">Tickets que actualmente estás gestionando.</p>
        </div>
    </div>

    <?php
    // TCKTS ACTIVOS TECNICO
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
    $stmtC->execute(['id' => $user['id']]);
    $total_ac = $stmtC->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status, u.first_name, u.last_name, o.name as office_name FROM tickets t JOIN usuarios u ON t.user_id = u.id LEFT JOIN oficina o ON t.office_id = o.id WHERE t.technician_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- TABLA ACTIVOS: TECNICO -->
    <div class="card card-plain border-0 mb-4" id="tickets-asignados">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-list-task text-primary me-2"></i> Mis Tickets Asignados Activos</h5>
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
                                    <a href="../ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0; line-height: 30px;" title="Gestionar">
                                        <i class="bi bi-gear"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No tienes tickets activos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', 1); ?>
        </div>
    </div>

</div>

<?php require '../includes/footer.php'; ?>
