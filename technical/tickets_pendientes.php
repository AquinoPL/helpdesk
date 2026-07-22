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

// Lógica para que el técnico se auto-asigne un ticket pendiente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_ticket_id'])) {
    $take_id = (int)$_POST['take_ticket_id'];
    try {
        $conn->beginTransaction();
        $stmtTake = $conn->prepare("UPDATE tickets SET technician_id = ?, status = 'En camino' WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
        $stmtTake->execute([$user['id'], $take_id]);
        if ($stmtTake->rowCount() > 0) {
            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, changed_by, status, comment) VALUES (?, ?, 'En camino', 'Ticket auto-asignado por el técnico')");
            $stmtHist->execute([$take_id, $user['id']]);
            $_SESSION['success_msg'] = "Te has auto-asignado el ticket #$take_id exitosamente.";
        }
        $conn->commit();
        header("Location: tickets_pendientes.php?success=taken");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Error al auto-asignar el ticket: " . $e->getMessage();
    }
}

require '../includes/header.php';

?>

<div class="py-4">

    <div class="card p-3 mt-4 mb-4 flex-row justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-1">Tickets Pendientes</h2>
            <p class="text-muted mb-0">Tickets nuevos que requieren ser asignados.</p>
        </div>
    </div>

    <?php
    if (!empty($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif;

    // TICKETS PENDIENTES SIN ASIGNAR
    $stmtPend = $conn->query("
        SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status,
               u.first_name, u.last_name, o.name as office_name
        FROM tickets t
        JOIN usuarios u ON t.user_id = u.id
        LEFT JOIN oficina o ON t.office_id = o.id
        WHERE (t.technician_id IS NULL OR t.technician_id = 0)
          AND (t.status = 'Pendiente' OR t.status IS NULL)
        ORDER BY t.created_at ASC
    ");
    $tickets_pend = $stmtPend->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- BANDEJA SIN ASIGNAR: TECNICO -->
    <div class="card card-plain border-0 mb-4 shadow-sm" id="tickets-pendientes">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-inbox-fill text-warning me-2"></i> Tickets Pendientes Sin Asignar
            </h5>
            <span class="badge rounded-pill bg-warning text-dark px-3 py-2 fs-6">
                <?php echo count($tickets_pend); ?> disponible(s)
            </span>
        </div>
        <div class="card-body p-0 pb-2">
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
                        <?php if (count($tickets_pend) > 0): ?>
                            <?php foreach ($tickets_pend as $tp):
                                $tpBadge = 'badge-' . str_replace(' ', '-', $tp['current_status']);
                            ?>
                            <tr class="ticket-row" onclick="window.location='../ticket/ticket_detalle.php?id=<?php echo $tp['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo htmlspecialchars($tp['id']); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($tp['first_name'] . ' ' . $tp['last_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($tp['office_name'] ?? 'Sin oficina'); ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($tp['title']); ?></td>
                                <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($tp['category']); ?></span></td>
                                <td><span class="badge status-badge <?php echo $tpBadge; ?>"><?php echo htmlspecialchars($tp['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($tp['created_at'])); ?></td>
                                <td class="pe-4 text-end" onclick="event.stopPropagation();">
                                    <form method="POST" class="d-inline" action="tickets_pendientes.php">
                                        <input type="hidden" name="take_ticket_id" value="<?php echo $tp['id']; ?>">
                                        <button type="submit" class="btn btn-sm text-white rounded-circle shadow-sm" style="background:var(--accent); width: 32px; height: 32px; padding: 0;" title="Tomar Ticket" onclick="return confirm('¿Deseas auto-asignarte y comenzar la atención del ticket #<?php echo $tp['id']; ?>?')">
                                            <i class="bi bi-hand-index-thumb"></i>
                                        </button>
                                    </form>
                                    <a href="../ticket/ticket_detalle.php?id=<?php echo $tp['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-circle ms-1 shadow-sm" style="width: 32px; height: 32px; padding: 0; line-height: 30px;" title="Ver Detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-check-all me-1 text-success fs-5"></i> ¡Excelente! No hay tickets pendientes por asignar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require '../includes/footer.php'; ?>
