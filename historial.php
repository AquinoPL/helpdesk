<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config/database.php';

if (!defined('BASE_URL')) {
    if (strpos($_SERVER['SCRIPT_NAME'], '/Soporte-Alianza') !== false) {
        define('BASE_URL', '/Soporte-Alianza');
    } else {
        define('BASE_URL', '');
    }
}

$is_logged_in = isset($_SESSION["user"]);
$user = $is_logged_in ? $_SESSION["user"] : null;

if (!$is_logged_in) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

if ($user["role"] == "admin") {
    header("Location: " . BASE_URL . "/admin/tickets.php");
    exit();
}

$limit = 10;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

require 'includes/header.php';

function renderPagination($current, $total, $paramName) {
    if ($total <= 1) return "";
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
    for ($i = 1; $i <= $total; $i++) {
        $active = ($i == $current) ? 'active' : '';
        $html .= '<li class="page-item '.$active.'"><a class="page-link" href="?'.$paramName.'='.$i.'">'.$i.'</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h2 class="fw-bold mb-1">Historial de Tickets</h2>
        <p class="text-muted mb-0">Revisa los tickets que ya han sido procesados y finalizados.</p>
    </div>
</div>

<?php if ($user["role"] == "usuario"): ?>
    <?php
    $stmtF = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND status IN ('Atendido', 'Rechazado')");
    $stmtF->execute(['id' => $user['id']]);
    $total = $stmtF->fetchColumn();
    $pages = ceil($total / $limit);

    $stmt = $conn->prepare("SELECT t.*, t.status as current_status FROM tickets t WHERE t.user_id = :id AND status IN ('Atendido', 'Rechazado') ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute(['id' => $user['id']]);
    $tickets_fi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="card glass-card border-0 mb-4 opacity-75">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0 text-muted"><i class="bi bi-clock-history me-2"></i> Mis Tickets Finalizados</h5>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted">
                        <tr>
                            <th class="ps-4">Ticket</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th class="text-end pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets_fi) > 0): ?>
                            <?php foreach ($tickets_fi as $t): 
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                            ?>
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold"><?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['category']); ?></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 text-end"><a href="ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Revisar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No tienes tickets finalizados aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page, $pages, 'p'); ?>
        </div>
    </div>

<?php elseif ($user["role"] == "tecnico"): ?>
    <?php
    $stmtF = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND status IN ('Atendido', 'Rechazado')");
    $stmtF->execute(['id' => $user['id']]);
    $total = $stmtF->fetchColumn();
    $pages = ceil($total / $limit);

    $stmt = $conn->prepare("SELECT t.*, t.status as current_status, u.first_name, u.last_name FROM tickets t JOIN usuarios u ON t.user_id = u.id WHERE t.technician_id = :id AND status IN ('Atendido', 'Rechazado') ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute(['id' => $user['id']]);
    $tickets_fi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="card glass-card border-0 mb-4 opacity-75">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0 text-muted"><i class="bi bi-clock-history me-2"></i> Tickets Atendidos por Mí</h5>
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
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold"><?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['category']); ?></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="pe-4 text-end"><a href="ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Revisar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No tienes tickets finalizados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page, $pages, 'p'); ?>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
