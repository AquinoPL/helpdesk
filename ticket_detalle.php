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

// La edición se movió a admin/tickets.php

        if ($_POST['action'] == 'reaccionar' && ($user['role'] == 'tecnico' || $user['role'] == 'admin')) {
            $status = $_POST['status'];
            $tech_comment = trim($_POST['tech_comment'] ?? '');
            $operative_tech_id = isset($_POST['impersonate_tech']) ? (int)$_POST['impersonate_tech'] : $user['id'];
            
            $stmt = $conn->prepare("UPDATE tickets SET status = ?::ticket_status, tech_comment = ?, attended_at = CASE WHEN ? = 'Atendido' THEN NOW() ELSE attended_at END WHERE id = ? AND technician_id = ?");
            $stmt->execute([$status, $tech_comment, $status, $ticket_id, $operative_tech_id]);

            $comment = "El técnico actualizó el estado a " . $status;
            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, changed_by, comment) VALUES (?, ?::ticket_status, ?, ?)");
            $stmtHist->execute([$ticket_id, $status, $operative_tech_id, $comment]);

            $redirect_url = "ticket_detalle.php?id=$ticket_id&success=updated" . (isset($_POST['impersonate_tech']) ? "&impersonate_tech=" . $operative_tech_id : "");
            header("Location: $redirect_url");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error al procesar la solicitud: " . $e->getMessage();
    }
}

// Lógica Impersonation Admin -> Tecnico
$is_impersonating = false;
$impersonated_tech_id = null;
if ($user['role'] == 'admin' && isset($_GET['impersonate_tech'])) {
    $is_impersonating = true;
    $impersonated_tech_id = (int)$_GET['impersonate_tech'];
}


// Obtener datos principales del ticket
$stmt = $conn->prepare("
    SELECT t.*, u.first_name, u.last_name, u.email, u.phone, u.dni,
           COALESCE(tofc.name, uofc.name) as office_name
    FROM tickets t
    JOIN usuarios u ON t.user_id = u.id
    LEFT JOIN oficina tofc ON t.office_id = tofc.id
    LEFT JOIN oficina uofc ON u.office_id = uofc.id
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

// Check assignment
$is_assigned = false;
$tech_status = '';
$check_user_id = $is_impersonating ? $impersonated_tech_id : $user['id'];

if ($user['role'] == 'tecnico' || $is_impersonating) {
    $stmtCh = $conn->prepare("SELECT status FROM tickets WHERE id = ? AND technician_id = ?");
    $stmtCh->execute([$ticket_id, $check_user_id]);
    $det = $stmtCh->fetch(PDO::FETCH_ASSOC);
    if ($det) {
        $is_assigned = true;
        $tech_status = $det['status'];
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

if ($user['role'] == 'admin') {
    require 'admin/includes/admin_header.php';
} else {
    require 'includes/header.php';
}
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 mt-2">
    <div class="d-flex align-items-center mb-3 mb-md-0">
        <button type="button" onclick="history.back()" class="btn btn-outline-secondary rounded-circle me-3 flex-shrink-0" style="width: 40px; height: 40px; padding: 0; line-height:38px; text-align:center;" title="Volver atrás">
            <i class="bi bi-arrow-left"></i>
        </button>
        <div>
            <h2 class="fw-bold mb-0">Seguimiento de Ticket <span class="text-primary"><?php echo date('Y', strtotime($ticket['created_at'])) . str_pad($ticket['id'], 3, '0', STR_PAD_LEFT); ?></span></h2>
            <p class="text-muted mb-0"><i class="spinner-grow spinner-grow-sm text-success me-1" style="width: 0.8rem; height: 0.8rem;"></i> Actualizado en tiempo real</p>
        </div>
    </div>
    
    <div class="text-md-end d-flex align-items-center justify-content-md-end gap-3 mt-3 mt-md-0">
        <?php if ($user['role'] == 'admin' && !$is_impersonating): ?>
            <a href="admin/editar_ticket.php?id=<?php echo $ticket_id; ?>" class="btn btn-outline-primary fw-bold px-3 shadow-sm rounded-pill">
                <i class="bi bi-pencil me-1"></i> Editar Permisos
            </a>
        <?php endif; ?>
        <span id="ticket-status-badge" class="badge status-badge <?php echo $badgeClass; ?> fs-5 py-2 px-3 shadow-sm"><?php echo htmlspecialchars($current_status); ?></span>
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

<div class="row justify-content-center mb-5">
    <div class="col-lg-10 col-xl-9">
        
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
                        
                        <?php if($ticket['email'] || $ticket['phone']): ?>
                            <div class="mt-2 small">
                                <?php if($ticket['email']): ?><span class="text-muted d-block"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($ticket['email']); ?></span><?php endif; ?>
                                <?php if($ticket['phone']): ?><span class="text-muted d-block"><i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($ticket['phone']); ?></span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <h4 class="fw-bold mb-0 text-primary"><?php echo htmlspecialchars($ticket['title']); ?></h4>
                </div>
                
                <p class="text-dark bg-light p-3 rounded-3 mb-4 border" style="white-space: pre-wrap; font-size:1.05rem;"><?php echo htmlspecialchars($ticket['description']); ?></p>
                <?php if (count($files) > 0): 
                    $images = [];
                    $other_files = [];
                    foreach($files as $f) {
                        $ext = pathinfo($f['file_path'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'])) {
                            $images[] = $f;
                        } else {
                            $other_files[] = $f;
                        }
                    }
                ?>
                <h6 class="fw-bold mb-3"><i class="bi bi-paperclip"></i> Archivos Adjuntos</h6>
                
                <?php if(count($images) > 0): ?>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <?php foreach($images as $index => $img): ?>
                        <div class="image-thumbnail-container" onclick="openGallery(<?php echo $index; ?>)" style="width: 100px; height: 100px; overflow: hidden; border-radius: 8px; cursor: pointer; border: 2px solid rgba(0,0,0,0.1); transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.05);" title="Ver imagen completa">
                            <img src="<?php echo htmlspecialchars($img['file_path']); ?>" alt="<?php echo htmlspecialchars(basename($img['file_path'])); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;" onmouseover="this.style.transform='scale(1.1)'; this.parentElement.style.borderColor='#0d6efd';" onmouseout="this.style.transform='scale(1)'; this.parentElement.style.borderColor='rgba(0,0,0,0.1)';">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if(count($other_files) > 0): ?>
                <div class="list-group mb-4">
                    <?php foreach($other_files as $f): ?>
                        <a href="<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center mb-2 rounded-3 border text-decoration-none">
                            <div class="bg-light p-2 rounded me-3 d-flex align-items-center justify-content-center text-primary">
                                <i class="bi bi-file-earmark-text fs-4"></i>
                            </div>
                            <div class="flex-grow-1 text-truncate">
                                <span class="d-block fw-semibold text-dark text-truncate" title="<?php echo htmlspecialchars(basename($f['file_path'])); ?>"><?php echo htmlspecialchars(basename($f['file_path'])); ?></span>
                                <span class="d-block small text-muted text-uppercase"><?php echo htmlspecialchars(pathinfo($f['file_path'], PATHINFO_EXTENSION)); ?> Documento</span>
                            </div>
                            <div class="ms-2 text-secondary">
                                <i class="bi bi-download fs-5"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="mb-4"></div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ACCIONES (Admin o Técnico) -->
        <?php if ($user['role'] == 'admin' && !$is_impersonating): ?>
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

        <?php if (($user['role'] == 'tecnico' || $is_impersonating) && $is_assigned && !in_array($tech_status, ['Atendido', 'Rechazado'])): ?>
            <!-- Acciones Rápidas del Técnico -->
            <div class="card glass-card border-0 mb-4 border-top border-4 border-info fade-in">
                <div class="card-body p-4 text-center">
                    <?php if($is_impersonating): ?>
                        <div class="alert alert-warning py-2 small mb-3">Estás emitiendo estas acciones en nombre de este técnico.</div>
                    <?php endif; ?>
                    <h5 class="fw-bold text-info mb-4"><i class="bi bi-tools me-2"></i> Acciones del Ticket</h5>
                    
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <?php if ($tech_status == 'En camino'): ?>
                            <button type="button" class="btn btn-primary px-4 py-2 fw-bold" onclick="openTechStatusModal('En proceso')">
                                <i class="bi bi-play-circle me-2"></i> Iniciar Proceso (Diagnóstico)
                            </button>
                        <?php elseif ($tech_status == 'En proceso'): ?>
                            <button type="button" class="btn btn-success px-4 py-2 fw-bold" onclick="openTechStatusModal('Atendido')">
                                <i class="bi bi-check-circle me-1"></i> Marcar como Atendido
                            </button>
                            <button type="button" class="btn btn-danger px-4 py-2 fw-bold" onclick="openTechStatusModal('Rechazado')">
                                <i class="bi bi-x-circle me-1"></i> Rechazar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Modal Técnico (Confirmar Estado) -->
            <div class="modal fade" id="techStatusModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow text-start">
                        <div class="modal-header text-white" id="techStatusModalHeader">
                            <h5 class="modal-title fw-bold" id="techStatusModalTitle">Mover a...</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p id="techStatusModalText" class="mb-4">¿Estás seguro que deseas actualizar el estado?</p>
                            <form method="POST" id="form-tech-manage">
                                <input type="hidden" name="action" value="reaccionar">
                                <input type="hidden" name="status" id="tech-modal-status">
                                <?php if($is_impersonating): ?>
                                    <input type="hidden" name="impersonate_tech" value="<?php echo $impersonated_tech_id; ?>">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label fw-medium text-dark">Reporte del Técnico: <span id="tech-modal-comment-optional" class="text-muted fw-normal small">(Opcional)</span></label>
                                    <textarea class="form-control" name="tech_comment" id="tech-modal-comment-input" rows="4" placeholder="Escriba aquí el proceso, observaciones o la solución final que será visible para el solicitante..."><?php echo htmlspecialchars($ticket['tech_comment'] ?? ''); ?></textarea>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="tech-modal-submit-btn">Confirmar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            function openTechStatusModal(newStatus) {
                var modal = new bootstrap.Modal(document.getElementById('techStatusModal'));
                var header = document.getElementById('techStatusModalHeader');
                var title = document.getElementById('techStatusModalTitle');
                var textP = document.getElementById('techStatusModalText');
                var statusInput = document.getElementById('tech-modal-status');
                var btnSubmit = document.getElementById('tech-modal-submit-btn');

                statusInput.value = newStatus;

                // Reset styles
                header.classList.remove('bg-primary', 'bg-success', 'bg-danger');
                btnSubmit.classList.remove('btn-primary', 'btn-success', 'btn-danger');

                if (newStatus === 'En proceso') {
                    header.classList.add('bg-primary');
                    title.innerHTML = '<i class="bi bi-play-circle me-2"></i> Iniciar Proceso';
                    textP.innerHTML = 'El ticket cambiará a estado <strong>En proceso</strong>.';
                    btnSubmit.classList.add('btn-primary');
                    btnSubmit.innerText = 'Iniciar Proceso';
                    
                    document.getElementById('tech-modal-comment-input').required = false;
                    document.getElementById('tech-modal-comment-optional').innerHTML = '<span class="text-muted fw-normal">(Opcional)</span>';
                    
                } else if (newStatus === 'Atendido') {
                    header.classList.add('bg-success');
                    title.innerHTML = '<i class="bi bi-check-circle me-2"></i> Marcar como Atendido';
                    textP.innerHTML = 'El ticket será cerrado y marcado con éxito como <strong>Atendido / Resuelto</strong>.';
                    btnSubmit.classList.add('btn-success');
                    btnSubmit.innerText = 'Finalizar Ticket';

                    document.getElementById('tech-modal-comment-input').required = false;
                    document.getElementById('tech-modal-comment-optional').innerHTML = '<span class="text-muted fw-normal">(Resumen Opcional de la solución)</span>';

                } else if (newStatus === 'Rechazado') {
                    header.classList.add('bg-danger');
                    title.innerHTML = '<i class="bi bi-x-circle me-2"></i> Rechazar Ticket';
                    textP.innerHTML = 'Estás a punto de <strong>Rechazar</strong> este ticket.';
                    btnSubmit.classList.add('btn-danger');
                    btnSubmit.innerText = 'Confirmar Rechazo';
                    
                    document.getElementById('tech-modal-comment-input').required = true;
                    document.getElementById('tech-modal-comment-optional').innerHTML = '<span class="text-danger fw-bold">(Obligatorio)</span>';
                }

                modal.show();
            }
            </script>
        <?php endif; ?>

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

        <!-- REPORTE DEL TÉCNICO -->
        <?php if (!empty($ticket['tech_comment'])): ?>
            <div class="card glass-card border-0 mb-4 fade-in border-start border-4 border-dark">
                <div class="card-body p-4 p-md-5 bg-white rounded-3 shadow-sm">
                    <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-tools text-primary me-2"></i> Reporte y Diagnóstico Técnico</h5>
                    <div class="p-3 bg-light rounded border" style="white-space: pre-wrap; font-size:1.05rem; color: #333; line-height: 1.6;"><?php echo htmlspecialchars($ticket['tech_comment']); ?></div>
                </div>
            </div>
        <?php endif; ?>

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
      <div class="modal-header border-0 position-absolute w-100 d-flex justify-content-between align-items-center" style="z-index: 1055;">
        <h5 class="modal-title text-white text-truncate pe-3 fw-bold flex-grow-1" id="imageViewerTitle" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.8);">Preview</h5>
        <div class="text-white d-flex align-items-center gap-3">
            <span id="galleryCounter" class="fw-bold bg-dark bg-opacity-50 px-3 py-1 rounded-pill" style="font-size: 0.9rem;"></span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0 d-flex justify-content-center align-items-center overflow-hidden position-relative" id="imageViewerBody">
        <button id="btnPrevImage" class="btn btn-dark bg-opacity-50 text-white rounded-circle position-absolute start-0 ms-3 ms-md-5 top-50 translate-middle-y shadow" style="z-index: 1055; width: 50px; height: 50px; border: 1px solid rgba(255,255,255,0.2);" onclick="prevImage(event)"><i class="bi bi-chevron-left fs-4"></i></button>
        <img src="" id="imageViewerImg" class="img-fluid" style="cursor: grab; transition: transform 0.1s ease-out; max-height: 100vh; max-width: 100vw; box-shadow: 0 0 20px rgba(0,0,0,0.5);" draggable="false">
        <button id="btnNextImage" class="btn btn-dark bg-opacity-50 text-white rounded-circle position-absolute end-0 me-3 me-md-5 top-50 translate-middle-y shadow" style="z-index: 1055; width: 50px; height: 50px; border: 1px solid rgba(255,255,255,0.2);" onclick="nextImage(event)"><i class="bi bi-chevron-right fs-4"></i></button>
      </div>
      <div class="position-absolute bottom-0 w-100 p-3 text-center pb-4" style="z-index: 1055; pointer-events: none;">
          <div class="bg-dark bg-opacity-75 d-inline-block rounded-pill px-4 py-2 text-white shadow-sm small">
              <i class="bi bi-arrows-move me-1"></i> Arrastrar | Rueda para Zoom | Flechas teclado | Doble click
          </div>
      </div>
    </div>
  </div>
</div>

<script>
    const galleryImages = [
        <?php 
        if(!empty($files)):
            foreach($files as $f):
                $ext = pathinfo($f['file_path'], PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'])):
        ?>
            { src: '<?php echo addslashes(htmlspecialchars($f['file_path'])); ?>', title: '<?php echo addslashes(htmlspecialchars(basename($f['file_path']))); ?>' },
        <?php 
                endif;
            endforeach;
        endif;
        ?>
    ];
    let currentImageIndex = 0;

    let currentScale = 1;
    let isDragging = false;
    let startX, startY, translateX = 0, translateY = 0;
    const imgElement = document.getElementById('imageViewerImg');
    const imageViewerBody = document.getElementById('imageViewerBody');
    const btnNext = document.getElementById('btnNextImage');
    const btnPrev = document.getElementById('btnPrevImage');
    const galleryCounter = document.getElementById('galleryCounter');

    function openGallery(index) {
        if(galleryImages.length === 0) return;
        currentImageIndex = index;
        loadImage(currentImageIndex);
        var myModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
        myModal.show();
    }

    function loadImage(index) {
        document.getElementById('imageViewerTitle').innerText = galleryImages[index].title;
        currentScale = 1;
        translateX = 0;
        translateY = 0;
        updateTransform();
        imgElement.src = galleryImages[index].src;
        galleryCounter.innerText = (index + 1) + ' / ' + galleryImages.length;
        
        btnPrev.style.display = galleryImages.length > 1 ? 'block' : 'none';
        btnNext.style.display = galleryImages.length > 1 ? 'block' : 'none';
    }

    function nextImage(event) {
        if(event) event.stopPropagation();
        currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
        loadImage(currentImageIndex);
    }

    function prevImage(event) {
        if(event) event.stopPropagation();
        currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
        loadImage(currentImageIndex);
    }

    // Navegación por teclado
    document.addEventListener('keydown', function(event) {
        const modalEl = document.getElementById('imageViewerModal');
        if (modalEl.classList.contains('show') && galleryImages.length > 1) {
            if (event.key === 'ArrowRight') {
                nextImage();
            } else if (event.key === 'ArrowLeft') {
                prevImage();
            }
        }
    });

    imageViewerBody.addEventListener('wheel', (e) => {
        e.preventDefault();
        const zoomIntensity = 0.15;
        if(e.deltaY < 0) currentScale += zoomIntensity;
        else currentScale -= zoomIntensity;
        currentScale = Math.min(Math.max(.5, currentScale), 5);
        updateTransform();
    });

    imageViewerBody.addEventListener('mousedown', (e) => {
        if (e.target === btnNext || e.target === btnPrev || e.target.closest('button')) return;
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

    // AJAX Polling para el historial
    const ticketId = <?php echo $ticket_id; ?>;
    
    function pollTicketData() {
        fetch(`ajax_get_ticket.php?id=${ticketId}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return;
                
                // Actualizar Timeline
                document.getElementById('history-container').innerHTML = data.html;
                
                // Actualizar Badge flotante arriba
                const badge = document.getElementById('ticket-status-badge');
                if (badge) {
                    badge.className = `badge status-badge ${data.badge_class} ms-2 mt-n2 align-middle`;
                    badge.innerText = data.status;
                }
            })
            .catch(err => console.error("Polling error:", err));
    }

    setInterval(pollTicketData, 10000);
</script>

<?php 
if ($user['role'] == 'admin') {
    require 'admin/includes/admin_footer.php';
} else {
    require 'includes/footer.php'; 
}
?>


