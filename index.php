<?php
require 'includes/auth.php';
require 'config/database.php';

$user = $_SESSION["user"];

if ($user["role"] == "admin") {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}

// Paginación compartida
$limit = 10;
$page_active = isset($_GET['pa']) ? max(1, (int)$_GET['pa']) : 1;
$page_finished = isset($_GET['pf']) ? max(1, (int)$_GET['pf']) : 1;
$offset_ac = ($page_active - 1) * $limit;
$offset_fi = ($page_finished - 1) * $limit;

require 'includes/header.php';

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

<div class="row mb-4 align-items-center">
    <div class="col">
        <h2 class="fw-bold mb-1">Hola, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
        <p class="text-muted mb-0">Bienvenido al sistema de soporte.</p>
    </div>
    <?php if ($user["role"] == "usuario"): ?>
    <div class="col-auto">
        <a href="ticket.php" class="btn btn-primary d-flex align-items-center shadow-sm">
            <i class="bi bi-plus-lg me-2"></i> Crear Ticket
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($user["role"] == "usuario"): ?>
    <?php
    // TCKTS ACTIVOS
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
    $stmtC->execute(['id' => $user['id']]);
    $total_ac = $stmtC->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status FROM tickets t WHERE t.user_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TCKTS FINALIZADOS
    $stmtF = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND status IN ('Atendido', 'Rechazado')");
    $stmtF->execute(['id' => $user['id']]);
    $total_fi = $stmtF->fetchColumn();
    $pages_fi = ceil($total_fi / $limit);

    $stmt = $conn->prepare("SELECT t.*, t.status as current_status FROM tickets t WHERE t.user_id = :id AND status IN ('Atendido', 'Rechazado') ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_fi");
    $stmt->execute(['id' => $user['id']]);
    $tickets_fi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- TABLA ACTIVOS: USUARIO -->
    <div class="card glass-card border-0 mb-4">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-ticket-detailed text-primary me-2"></i> Mis Tickets Activos</h5>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
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
                        <?php if (count($tickets_ac) > 0): ?>
                            <?php foreach ($tickets_ac as $t): 
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']); ?>
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['category']); ?></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 text-end"><a href="ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Detalles</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No tienes tickets activos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', $page_finished); ?>
        </div>
    </div>

    <!-- TABLA FINALIZADOS: USUARIO -->
    <div class="card glass-card border-0 mb-4 opacity-75">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0 text-muted"><i class="bi bi-clock-history me-2"></i> Historial de Tickets Finalizados</h5>
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
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']); ?>
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['category']); ?></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 text-end"><a href="ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Revisar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No hay tickets finalizados aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_finished, $pages_fi, 'pf', 'pa', $page_active); ?>
        </div>
    </div>

<?php elseif ($user["role"] == "tecnico"): ?>
    <?php
    // TCKTS ACTIVOS TECNICO
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
    $stmtC->execute(['id' => $user['id']]);
    $total_ac = $stmtC->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status, u.first_name, u.last_name FROM tickets t JOIN usuarios u ON t.user_id = u.id WHERE t.technician_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TCKTS FINALIZADOS TECNICO
    $stmtF = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND status IN ('Atendido', 'Rechazado')");
    $stmtF->execute(['id' => $user['id']]);
    $total_fi = $stmtF->fetchColumn();
    $pages_fi = ceil($total_fi / $limit);

    $stmt = $conn->prepare("SELECT t.*, t.status as current_status, u.first_name, u.last_name FROM tickets t JOIN usuarios u ON t.user_id = u.id WHERE t.technician_id = :id AND status IN ('Atendido', 'Rechazado') ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_fi");
    $stmt->execute(['id' => $user['id']]);
    $tickets_fi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- TABLA ACTIVOS: TECNICO -->
    <div class="card glass-card border-0 mb-4">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
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
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']); ?>
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['category']); ?></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="pe-4 text-end"><a href="ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Gestionar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No tienes tickets activos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', $page_finished); ?>
        </div>
    </div>

    <!-- TABLA FINALIZADOS: TECNICO -->
    <div class="card glass-card border-0 mb-4 opacity-75">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
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
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']); ?>
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
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
            <?php echo renderPagination($page_finished, $pages_fi, 'pf', 'pa', $page_active); ?>
        </div>
    </div>

<?php endif; ?>

<?php require 'includes/footer.php'; ?>