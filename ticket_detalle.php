<?php
require 'includes/auth.php';
require 'config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$ticket_id = (int)$_GET['id'];
$user = $_SESSION["user"];
$error = '';
$success = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'assigned') $success = "Técnico asignado correctamente.";
    if ($_GET['success'] == 'updated') $success = "Estado del ticket actualizado.";

}

// Lógica de acciones por POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'asignar' && $user['role'] == 'admin') {
            $tech_id = $_POST['technician_id'];
            $stmt = $conn->prepare("UPDATE tickets SET technician_id = ?, status = 'En camino' WHERE id = ?");
            $stmt->execute([$tech_id, $ticket_id]);
            
            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, changed_by, comment) VALUES (?, 'En camino', ?, 'Técnico asignado')");
            $stmtHist->execute([$ticket_id, $user['id']]);
            header("Location: ticket_detalle.php?id=$ticket_id&success=assigned");
            exit();
        }

        if ($_POST['action'] == 'reasignar' && $user['role'] == 'admin') {
            $tech_id = $_POST['technician_id'];
            // Borramos el historial del técnico anterior (todo salvo el estado inicial 'Pendiente')
            $stmtDel = $conn->prepare("DELETE FROM ticket_history WHERE ticket_id = ? AND status != 'Pendiente'");
            $stmtDel->execute([$ticket_id]);

            // Asignamos el nuevo técnico
            $stmt = $conn->prepare("UPDATE tickets SET technician_id = ?, status = 'En camino' WHERE id = ?");
            $stmt->execute([$tech_id, $ticket_id]);
            
            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, changed_by, comment) VALUES (?, 'En camino', ?, 'Ticket reasignado a nuevo técnico')");
            $stmtHist->execute([$ticket_id, $user['id']]);
            header("Location: ticket_detalle.php?id=$ticket_id&success=assigned");
            exit();
        }

        if ($_POST['action'] == 'rechazar_admin' && $user['role'] == 'admin') {
            $comment = trim($_POST['comment']);
            if(empty($comment)) {
                $error = "Debe proporcionar un motivo para rechazar el ticket.";
            } else {
                $stmt = $conn->prepare("UPDATE tickets SET status = 'Rechazado' WHERE id = ?");
                $stmt->execute([$ticket_id]);
                
                $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, changed_by, comment) VALUES (?, 'Rechazado', ?, ?)");
                $stmtHist->execute([$ticket_id, $user['id'], $comment]);
                header("Location: ticket_detalle.php?id=$ticket_id&success=updated");
                exit();
            }
        }

        if ($_POST['action'] == 'reaccionar' && $user['role'] == 'tecnico') {
            $status = $_POST['status'];
            $comment = trim($_POST['comment']);

            if ($status == 'Rechazado' && empty($comment)) {
                $error = "Debe indicar el motivo por el cual se rechaza el ticket.";
            } else {
                if (empty($comment)) {
                    $comment = "Estado actualizado"; // Default comment  
                }

                $stmt = $conn->prepare("UPDATE tickets SET status = ?::ticket_status, attended_at = CASE WHEN ? = 'Atendido' THEN NOW() ELSE attended_at END WHERE id = ? AND technician_id = ?");
                $stmt->execute([$status, $status, $ticket_id, $user['id']]);

                $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, changed_by, comment) VALUES (?, ?::ticket_status, ?, ?)");
                $stmtHist->execute([$ticket_id, $status, $user['id'], $comment]);

                header("Location: ticket_detalle.php?id=$ticket_id&success=updated");
                exit();
            }
        }
    } catch (PDOException $e) {
        $error = "Error al procesar la solicitud: " . $e->getMessage();
    }
}

// Obtener datos principales del ticket
$stmt = $conn->prepare("
    SELECT t.*, u.first_name, u.last_name, u.email, u.phone, o.name as office_name
    FROM tickets t
    JOIN usuarios u ON t.user_id = u.id
    LEFT JOIN oficina o ON u.office_id = o.id
    WHERE t.id = ?
");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Ticket no encontrado");
}

// Seguridad: un usuario normal solo debe ver SUS propios tickets
if ($user['role'] == 'usuario' && $ticket['user_id'] != $user['id']) {
    die("Acceso denegado. Este ticket no te pertenece.");
}

// Obtener estado actual
$current_status = $ticket['status'] ?: 'Pendiente';
$badgeClass = 'badge-' . str_replace(' ', '-', $current_status);

// Historial del ticket
$stmtHist = $conn->prepare("
    SELECT th.*, w.first_name, w.last_name, w.role
    FROM ticket_history th
    LEFT JOIN trabajadores w ON th.changed_by = w.id
    WHERE th.ticket_id = ?
    ORDER BY th.created_at ASC
");
$stmtHist->execute([$ticket_id]);
$history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

// Archivos adjuntos
$stmtFiles = $conn->prepare("SELECT * FROM ticket_files WHERE ticket_id = ?");
$stmtFiles->execute([$ticket_id]);
$files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

// Si es admin, traer lista de técnicos para asignar
$tecnicos = [];
if ($user['role'] == 'admin') {
    $stmtT = $conn->query("SELECT id, first_name, last_name FROM trabajadores WHERE role = 'tecnico'");
    $tecnicos = $stmtT->fetchAll(PDO::FETCH_ASSOC);
}

// Si es técnico, verificar si está asignado a este ticket para permitirle editar
$is_assigned = false;
$tech_status = '';
$tech_comment = '';
if ($user['role'] == 'tecnico') {
    $stmtCh = $conn->prepare("SELECT status, '' as comment FROM tickets WHERE id = ? AND technician_id = ?");
    $stmtCh->execute([$ticket_id, $user['id']]);
    $det = $stmtCh->fetch(PDO::FETCH_ASSOC);
    if ($det) {
        $is_assigned = true;
        $tech_status = $det['status'];
        $tech_comment = $det['comment'];
    }
}

// Obtener asignación actual global
$stmtAsign = $conn->prepare("
    SELECT t.status, '' as comment, u.first_name, u.last_name 
    FROM tickets t 
    JOIN trabajadores u ON t.technician_id = u.id 
    WHERE t.id = ?
");
$stmtAsign->execute([$ticket_id]);
$asignaciones = $stmtAsign->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<div class="row pt-2 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 mb-2"><i class="bi bi-arrow-left me-1"></i> Volver</a>
            <h2 class="fw-bold mb-0 text-dark">
                Ticket #<?php echo str_pad($ticket['id'], 4, '0', STR_PAD_LEFT); ?>
                <span id="ticket-status-badge" class="badge status-badge <?php echo $badgeClass; ?> ms-2 mt-n2 align-middle" style="font-size: 0.5em;"><?php echo htmlspecialchars($current_status); ?></span>
            </h2>
        </div>
        <div class="text-end text-muted small">
            <div><strong>Creado:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></div>
            <div><strong>Categoría:</strong> <?php echo htmlspecialchars($ticket['category']); ?></div>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-auto-dismiss alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-auto-dismiss alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-5">
    
    <!-- LADO IZQUIERDO: Info y Formularios -->
    <div class="col-lg-8">
        <!-- Detalles Principales -->
        <div class="card glass-card border-0 mb-4 fade-in">
            <div class="card-body p-4 p-md-5">
                <h4 class="fw-bold mb-4 text-primary"><?php echo htmlspecialchars($ticket['title']); ?></h4>
                
                <div class="bg-light p-3 rounded-3 mb-4 border" style="white-space: pre-wrap; font-size:1.05rem;"><?php echo htmlspecialchars($ticket['description']); ?></div>

                <?php if (count($files) > 0): ?>
                <h6 class="fw-bold mb-3"><i class="bi bi-paperclip"></i> Archivos Adjuntos</h6>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php foreach($files as $f): 
                        $ext = pathinfo($f['file_path'], PATHINFO_EXTENSION);
                        $is_img = in_array(strtolower($ext), ['jpg','jpeg','png','gif']);
                    ?>
                        <?php if($is_img): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openImageViewer('<?php echo htmlspecialchars($f['file_path']); ?>', '<?php echo htmlspecialchars(basename($f['file_path'])); ?>')" title="Previsualizar Imagen">
                                <i class="bi bi-arrows-fullscreen me-1"></i> Previsualizar
                            </button>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="<?php echo htmlspecialchars(basename($f['file_path'])); ?>">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> Descargar Documento
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <hr class="text-muted opacity-25">

                <!-- Detalles del Solicitante -->
                <h6 class="fw-bold mb-3 mt-4 text-muted"><i class="bi bi-person-badge"></i> Datos del Solicitante</h6>
                <div class="row gx-4 gy-2 small">
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Nombre:</span>
                        <span class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Oficina:</span>
                        <span class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['office_name'] ?: 'No asignada'); ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Correo:</span>
                        <span class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['email'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Teléfono:</span>
                        <span class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['phone'] ?: 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCIONES (Admin o Técnico) -->
        <?php if ($user['role'] == 'admin'): ?>
            <div class="card glass-card border-0 mb-4 fade-in" style="background-color: #f8f9fa;">
                <div class="card-body p-4">
                    <?php if ($current_status == 'Pendiente' && !$ticket['technician_id']): ?>
                        <!-- 1. Opciones Iniciales de Admin: Realizar Atención o Rechazar -->
                        <div id="admin-initial-options" class="text-center">
                            <h5 class="fw-bold text-dark mb-4">Acciones para Nuevo Ticket</h5>
                            <button type="button" class="btn btn-primary px-4 py-2 me-2" onclick="document.getElementById('admin-assign-form-container').style.display='block'; document.getElementById('admin-initial-options').style.display='none';"><i class="bi bi-person-check me-2"></i> Realizar la atención</button>
                            <button type="button" class="btn btn-danger px-4 py-2" onclick="document.getElementById('admin-reject-form-container').style.display='block'; document.getElementById('admin-initial-options').style.display='none';"><i class="bi bi-x-circle me-2"></i> Rechazar</button>
                        </div>

                        <!-- Form de Asignar Técnico (Oculto por defecto) -->
                        <div id="admin-assign-form-container" style="display: none;">
                            <h5 class="fw-bold text-primary mb-3"><i class="bi bi-person-add me-2"></i> Asignar Técnico</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="asignar">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-9">
                                        <select class="form-select border-primary" name="technician_id" required>
                                            <option value="" selected disabled>Seleccione un técnico...</option>
                                            <?php foreach ($tecnicos as $t): ?>
                                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary flex-grow-1">Asignar</button>
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('admin-assign-form-container').style.display='none'; document.getElementById('admin-initial-options').style.display='block';"><i class="bi bi-x"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Form de Rechazar Ticket Admin (Oculto por defecto) -->
                        <div id="admin-reject-form-container" style="display: none;">
                            <h5 class="fw-bold text-danger mb-3"><i class="bi bi-x-octagon me-2"></i> Motivo de Rechazo</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="rechazar_admin">
                                <div class="mb-3">
                                    <textarea name="comment" class="form-control" rows="3" required placeholder="Especifique por qué no se puede atender este ticket..."></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('admin-reject-form-container').style.display='none'; document.getElementById('admin-initial-options').style.display='block';">Cancelar</button>
                                    <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
                                </div>
                            </form>
                        </div>

                    <?php elseif (in_array($current_status, ['En camino', 'En proceso']) && count($asignaciones) > 0): ?>
                        <!-- 2. Mostrar Asignación Actual y Opción de Modificar -->
                        <div id="admin-current-assignment">
                            <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-person-badge me-2"></i> Asignación Actual</h5>
                            <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded border">
                                <div>
                                    <span class="d-block text-muted small">Técnico encargado:</span>
                                    <span class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($asignaciones[0]['first_name'] . ' ' . $asignaciones[0]['last_name']); ?></span>
                                </div>
                                <button type="button" class="btn btn-outline-warning" onclick="document.getElementById('admin-reassign-form-container').style.display='block'; document.getElementById('admin-current-assignment').style.display='none';"><i class="bi bi-pencil-square me-2"></i> Modificar</button>
                                <button type="button" class="btn btn-outline-danger ms-2" onclick="document.getElementById('admin-reject-form-container-assigned').style.display='block'; document.getElementById('admin-current-assignment').style.display='none';"><i class="bi bi-x-circle me-2"></i> Rechazar</button>
                            </div>
                        </div>

                        <!-- Form de Rechazar Ticket Admin cuando ya está asignado (Oculto por defecto) -->
                        <div id="admin-reject-form-container-assigned" style="display: none;">
                            <h5 class="fw-bold text-danger mb-3"><i class="bi bi-x-octagon me-2"></i> Motivo de Rechazo</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="rechazar_admin">
                                <div class="mb-3">
                                    <textarea name="comment" class="form-control" rows="3" required placeholder="Especifique por qué no se puede atender este ticket..."></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('admin-reject-form-container-assigned').style.display='none'; document.getElementById('admin-current-assignment').style.display='block';">Cancelar</button>
                                    <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
                                </div>
                            </form>
                        </div>

                        <!-- Formulario de Re-Asignación (Oculto por defecto) -->
                        <div id="admin-reassign-form-container" style="display: none;">
                            <h5 class="fw-bold text-warning mb-3"><i class="bi bi-arrow-repeat me-2"></i> Reasignar Técnico</h5>
                            <div class="alert alert-warning py-2 mb-3 small"><i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Atención:</strong> Al reasignar el ticket, se borrará el historial de avance del técnico anterior y regresará al estado "En camino".</div>
                            <form method="POST">
                                <input type="hidden" name="action" value="reasignar">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-9">
                                        <select class="form-select border-warning" name="technician_id" required>
                                            <option value="" selected disabled>Seleccione nuevo técnico...</option>
                                            <?php foreach ($tecnicos as $t): ?>
                                                <?php if($t['first_name'].' '.$t['last_name'] !== $asignaciones[0]['first_name'].' '.$asignaciones[0]['last_name']): ?>
                                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-warning flex-grow-1">Reasignar</button>
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('admin-reassign-form-container').style.display='none'; document.getElementById('admin-current-assignment').style.display='block';"><i class="bi bi-x"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Si el estado es Atendido o Rechazado, no se puede modificar -->
                        <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-info-circle me-2"></i> Estado Finalizado</h5>
                        <p class="mb-0 text-muted">Este ticket ya se encuentra <strong><?php echo $current_status; ?></strong>, no se pueden realizar más ajustes de asignación.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($user['role'] == 'tecnico' && $is_assigned && !in_array($tech_status, ['Atendido', 'Rechazado'])): ?>
            <!-- Formulario de Respuesta del Técnico -->
            <div class="card glass-card border-0 mb-4 border-top border-4 border-info fade-in">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-info mb-3"><i class="bi bi-pencil-square me-2"></i> Gestionar Ticket</h5>
                    <form method="POST" id="form-tech-manage">
                        <input type="hidden" name="action" value="reaccionar">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nuevo Estado:</label>
                            <select class="form-select" name="status" id="tech-status-select" required onchange="toggleCommentRequired()">
                                <option value="" selected disabled>Seleccione el siguiente estado...</option>
                                <?php if ($tech_status == 'En camino'): ?>
                                    <option value="En proceso">En proceso</option>
                                    <option value="Rechazado">Rechazado / Fuera de alcance</option>
                                <?php elseif ($tech_status == 'En proceso'): ?>
                                    <option value="Atendido">Atendido / Resuelto</option>
                                    <option value="Rechazado">Rechazado / Fuera de alcance</option>
                                <?php else: ?>
                                    <option value="En proceso" <?php echo ($tech_status=='En proceso')?'selected':''; ?>>En proceso</option>
                                    <option value="Atendido" <?php echo ($tech_status=='Atendido')?'selected':''; ?>>Atendido / Resuelto</option>
                                    <option value="Rechazado" <?php echo ($tech_status=='Rechazado')?'selected':''; ?>>Rechazado / Fuera de alcance</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Comentario / Solución: <span id="comment-optional-text" class="text-muted fw-normal small">(Opcional)</span></label>
                            <textarea class="form-control" name="comment" id="tech-comment-input" rows="3" placeholder="Detalles de la actualización..."></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-info text-dark fw-bold px-4">Actualizar Ticket</button>
                        </div>
                    </form>
                    <script>
                        function toggleCommentRequired() {
                            var statusSel = document.getElementById('tech-status-select');
                            var commentInput = document.getElementById('tech-comment-input');
                            var optText = document.getElementById('comment-optional-text');
                            if (statusSel.value === 'Rechazado') {
                                commentInput.required = true;
                                optText.innerHTML = '<span class="text-danger fw-bold">(Obligatorio por rechazo)</span>';
                                commentInput.placeholder = 'Indique el motivo del rechazo...';
                            } else {
                                commentInput.required = false;
                                optText.innerHTML = '<span class="text-muted fw-normal">(Opcional)</span>';
                                commentInput.placeholder = 'Detalles de la actualización...';
                            }
                        }
                        // Call on load in case it starts on Rechazado
                        document.addEventListener("DOMContentLoaded", toggleCommentRequired);
                    </script>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- LADO DERECHO: Historial y Feedback -->
    <div class="col-lg-4">
        
        <div id="assigned-techs-wrapper">
        <?php if (count($asignaciones) > 0): ?>
            <!-- Técnicos Asignados Actualmente -->
            <div class="card glass-card border-0 mb-4 fade-in">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-people me-2"></i> Técnicos Asignados</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush rounded-3 border">
                        <?php foreach($asignaciones as $asig): ?>
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($asig['first_name'] . ' ' . $asig['last_name']); ?></span>
                            <span class="badge border border-secondary text-secondary rounded-pill"><?php echo htmlspecialchars($asig['status']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        </div>

        <!-- Registro Detallado (Historial Vertical Visual) -->
        <div class="card glass-card border-0 fade-in position-relative mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-uppercase text-muted mb-4"><i class="bi bi-clock-history me-1"></i> Historial del Ticket</h6>
                
                <?php 
                   // Orden de recorrido
                   $flow_steps = ['Pendiente', 'En camino', 'En proceso', 'Atendido'];
                   if ($current_status == 'Rechazado') {
                       $flow_steps = ['Pendiente', 'Rechazado'];
                   }

                   // Agrupar historial por estado
                   $historyByStatus = [];
                   foreach ($history as $h) {
                       if (!isset($historyByStatus[$h['status']])) {
                           $historyByStatus[$h['status']] = [];
                       }
                       $historyByStatus[$h['status']][] = $h;
                   }

                   $currentIndex = array_search($current_status, $flow_steps);
                   if ($currentIndex === false) $currentIndex = 0; // fallback
                ?>

                <div id="history-container" class="ps-3 border-start border-2 border-primary border-opacity-25 pb-2">
                    <?php foreach ($flow_steps as $stepIdx => $stepName): 
                        // Verificamos si este paso ya se alcanzó
                        $isReached = ($stepIdx <= $currentIndex);

                        // Colores por defecto (paso futuro => gris)
                        $bClass = 'text-muted';
                        $iconClass = 'bi-circle-fill';
                        $opacityClass = 'opacity-50';

                        // Si ya se alcanzó, darle su color "progresivamente"
                        if ($isReached) {
                            $opacityClass = 'opacity-100';
                            if ($stepName == 'Pendiente') { $bClass = 'text-warning'; $iconClass='bi-exclamation-circle-fill'; }
                            if ($stepName == 'En camino') { $bClass = 'text-purple'; $iconClass='bi-person-check-fill'; }
                            if ($stepName == 'En proceso') { $bClass = 'text-info'; $iconClass='bi-play-circle-fill'; }
                            if ($stepName == 'Atendido') { $bClass = 'text-success'; $iconClass='bi-check-circle-fill'; }
                            if ($stepName == 'Rechazado') { $bClass = 'text-danger'; $iconClass='bi-x-circle-fill'; }
                        }

                        // Si hay registros, los iteramos. Si no hay (ej. paso futuro), generamos un bloque vacío.
                        $records = isset($historyByStatus[$stepName]) ? $historyByStatus[$stepName] : [];
                        if (empty($records)) {
                            $records = [['empty' => true]]; // placeholder para el paso
                        }

                        foreach ($records as $h):
                    ?>
                    <div class="position-relative mb-4 <?php echo $opacityClass; ?>">
                        <div class="position-absolute bg-white text-center d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; left: -25px; top: 0px;">
                            <i class="bi <?php echo $iconClass; ?> <?php echo $bClass; ?> fs-5 bg-white"></i>
                        </div>
                        <div class="ms-1 pt-1">
                            <div class="fw-bold <?php echo $isReached ? 'text-dark' : 'text-muted'; ?> d-flex justify-content-between">
                                <?php echo htmlspecialchars($stepName); ?>
                            </div>
                            
                            <?php if (!isset($h['empty'])): ?>
                                <?php if ($user['role'] == 'admin' || $user['role'] == 'tecnico'): ?>
                                <div class="small fw-medium text-muted mb-1">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($h['first_name'] ?: 'Sistema'); ?> 
                                    <?php if(!empty($h['role'])): ?><span class="opacity-75 bg-light px-1 rounded mx-1"><?php echo htmlspecialchars($h['role']); ?></span><?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($h['comment']) && ($user['role'] == 'admin' || $user['role'] == 'tecnico')): ?>
                                    <div class="bg-light p-2 rounded-2 my-1 text-muted small border">
                                        <i class="bi bi-chat-quote-fill me-1 text-secondary opacity-50"></i> <?php echo htmlspecialchars($h['comment']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-calendar-event me-1"></i><?php echo date('d/m/Y H:i:s', strtotime($h['created_at'])); ?></div>
                            <?php else: ?>
                                <div class="small text-muted fst-italic mt-1" style="font-size: 0.75rem;">Pendiente de alcanzar...</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                        endforeach; // end records loop
                    endforeach; // end steps loop 
                    ?>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Modal Fullscreen Image Viewer -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content bg-dark bg-opacity-75" style="backdrop-filter: blur(10px);">
      <div class="modal-header border-0 position-absolute w-100" style="z-index: 1055;">
        <h5 class="modal-title text-white text-truncate pe-3 fw-bold" id="imageViewerTitle" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.8);">Preview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 d-flex justify-content-center align-items-center overflow-hidden" id="imageViewerBody">
        <img src="" id="imageViewerImg" class="img-fluid" style="cursor: grab; transition: transform 0.1s ease-out; max-height: 100vh; max-width: 100vw;" draggable="false">
      </div>
      <div class="position-absolute bottom-0 w-100 p-3 text-center pb-4" style="z-index: 1055; pointer-events: none;">
          <div class="bg-dark bg-opacity-75 d-inline-block rounded-pill px-4 py-2 text-white shadow-sm small">
              <i class="bi bi-zoom-in me-1"></i> Rueda del ratón para Zoom | Arrastrar para mover | Doble click para reiniciar
          </div>
      </div>
    </div>
  </div>
</div>

<script>
    let currentScale = 1;
    let isDragging = false;
    let startX, startY, translateX = 0, translateY = 0;
    const imgElement = document.getElementById('imageViewerImg');
    const imageViewerBody = document.getElementById('imageViewerBody');

    function openImageViewer(src, title) {
        document.getElementById('imageViewerTitle').innerText = title;
        currentScale = 1;
        translateX = 0;
        translateY = 0;
        updateTransform();
        imgElement.src = src;
        var myModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
        myModal.show();
    }

    imageViewerBody.addEventListener('wheel', (e) => {
        e.preventDefault();
        const zoomIntensity = 0.15;
        if(e.deltaY < 0) currentScale += zoomIntensity;
        else currentScale -= zoomIntensity;
        currentScale = Math.min(Math.max(.5, currentScale), 5);
        updateTransform();
    });

    imageViewerBody.addEventListener('mousedown', (e) => {
        isDragging = true;
        startX = e.clientX - translateX;
        startY = e.clientY - translateY;
        imgElement.style.cursor = 'grabbing';
    });

    imageViewerBody.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        translateX = e.clientX - startX;
        translateY = e.clientY - startY;
        updateTransform();
    });

    imageViewerBody.addEventListener('mouseup', () => { isDragging = false; imgElement.style.cursor = 'grab'; });
    imageViewerBody.addEventListener('mouseleave', () => { isDragging = false; imgElement.style.cursor = 'grab'; });

    // Double click actions for quick zooms
    imgElement.addEventListener('dblclick', () => {
        currentScale = currentScale > 1 ? 1 : 2.5;
        translateX = 0;
        translateY = 0;
        updateTransform();
    });

    function updateTransform() {
        imgElement.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentScale})`;
    }
</script>

<?php require 'includes/footer.php'; ?>
