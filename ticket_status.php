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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';

if (!$id || !$dni) {
    header("Location: index.php");
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT t.*, u.dni, u.first_name, u.last_name, o.name as office_name 
        FROM tickets t
        JOIN usuarios u ON t.user_id = u.id
        LEFT JOIN oficina o ON t.office_id = o.id
        WHERE t.id = ? AND u.dni = ?
    ");
    $stmt->execute([$id, $dni]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $error = "No pudimos encontrar un ticket válido con esos datos.";
    } else {
        $stmtHistory = $conn->prepare("
            SELECT th.*, tr.first_name, tr.last_name 
            FROM ticket_history th
            LEFT JOIN trabajadores tr ON th.changed_by = tr.id
            WHERE th.ticket_id = ?
            ORDER BY th.created_at DESC
        ");
        $stmtHistory->execute([$id]);
        $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error al consultar los datos del ticket.";
}

require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger fw-bold text-center p-4">
                <i class="bi bi-x-circle fs-3 d-block mb-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <br>
                <a href="index.php" class="btn btn-outline-danger mt-3">Volver al inicio</a>
            </div>
        <?php else: ?>
        
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-outline-secondary rounded-circle me-3" style="width: 40px; height: 40px; padding: 0; line-height:38px; text-align:center;">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h2 class="fw-bold mb-0">Seguimiento de Ticket <span class="text-primary">#<?php echo str_pad($ticket['id'], 4, '0', STR_PAD_LEFT); ?></span></h2>
                <p class="text-muted mb-0">Actualizado en tiempo real</p>
            </div>
            
            <div class="ms-auto text-end">
                <?php 
                $badgeClass = 'badge-' . str_replace(' ', '-', $ticket['status']);
                if ($ticket['status'] == 'Pendiente') $badgeClass = 'bg-warning text-dark';
                elseif ($ticket['status'] == 'En camino') $badgeClass = 'bg-primary';
                elseif ($ticket['status'] == 'En proceso') $badgeClass = 'bg-info text-dark';
                elseif ($ticket['status'] == 'Atendido') $badgeClass = 'bg-success';
                elseif ($ticket['status'] == 'Rechazado') $badgeClass = 'bg-danger';
                ?>
                <span class="badge <?php echo $badgeClass; ?> fs-5 py-2 px-3 shadow-sm"><?php echo htmlspecialchars($ticket['status']); ?></span>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 bg-light">
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.70rem;">Información del Creador</small>
                        <div class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                        <div class="small text-muted"><i class="bi bi-person-vcard me-1"></i> DNI: <?php echo htmlspecialchars($ticket['dni']); ?></div>
                        <?php if($ticket['office_name']): ?>
                            <div class="small text-muted"><i class="bi bi-building me-1"></i> Oficina: <?php echo htmlspecialchars($ticket['office_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.70rem;">Fecha de Creación</small>
                        <div class="fw-medium text-dark"><i class="bi bi-calendar-check me-1"></i> <?php echo date('d M Y, h:i A', strtotime($ticket['created_at'])); ?></div>
                        <div class="mt-2 text-dark"><span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['category']); ?></span></div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><?php echo htmlspecialchars($ticket['title']); ?></h5>
                <p class="text-dark" style="white-space: pre-wrap; font-size: 1.05rem;"><?php echo htmlspecialchars($ticket['description']); ?></p>
            </div>
        </div>

        <h4 class="fw-bold mb-4 mt-5"><i class="bi bi-hourglass-split text-primary me-2"></i> Línea de Tiempo</h4>
        
        <div class="position-relative ps-4 ms-2 mb-5 border-start border-2 border-primary border-opacity-25">
            <?php foreach ($history as $idx => $h): ?>
                <div class="mb-4 position-relative">
                    <div class="position-absolute bg-white rounded-circle border border-2 border-primary d-flex align-items-center justify-content-center shadow-sm" style="width: 16px; height: 16px; left: -25px; top: 4px;">
                        <div class="bg-primary rounded-circle" style="width: 6px; height: 6px;"></div>
                    </div>
                    
                    <div class="card border-0 shadow-sm <?php echo $idx === 0 ? 'bg-primary bg-opacity-10 border-start border-4 border-primary' : 'bg-white'; ?>">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($h['status']); ?></span>
                                <small class="text-muted fw-medium"><i class="bi bi-clock"></i> <?php echo date('d M Y, h:i A', strtotime($h['created_at'])); ?></small>
                            </div>
                            <p class="mb-0 text-dark opacity-75"><?php echo nl2br(htmlspecialchars($h['comment'])); ?></p>
                            <?php if ($h['first_name']): ?>
                                <div class="mt-2 text-muted small"><i class="bi bi-person-badge"></i> Por: <?php echo htmlspecialchars($h['first_name'] . ' ' . $h['last_name']); ?></div>
                            <?php else: ?>
                                <div class="mt-2 text-muted small"><i class="bi bi-robot"></i> Por: Sistema</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <button class="btn btn-outline-primary shadow-sm" onclick="location.reload();">
                <i class="bi bi-arrow-clockwise me-1"></i> Actualizar Estado
            </button>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
