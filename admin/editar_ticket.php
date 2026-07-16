<?php
require '../includes/auth.php';
require '../config/database.php';
restrict_access(['admin']);

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$ticket_id) {
    header("Location: tickets.php");
    exit();
}

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_ticket') {
    try {
        // Campos que SIEMPRE se pueden editar
        $title = trim($_POST['title']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $tech_comment = trim($_POST['tech_comment'] ?? '');

        // Obtener estado actual
        $stC = $conn->prepare("SELECT status FROM tickets WHERE id = ?");
        $stC->execute([$ticket_id]);
        $current_status = $stC->fetchColumn() ?: 'Pendiente';

        // Ahora el administrador siempre puede hacer una edición completa (foráneas incluidas) sin importar el estado
        $full_edit = true;

        $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
        $technician_id = !empty($_POST['technician_id']) ? $_POST['technician_id'] : null;
        $office_id = !empty($_POST['office_id']) ? $_POST['office_id'] : null;

        $stmt = $conn->prepare("UPDATE tickets SET title=?, category=?, description=?, tech_comment=?, user_id=?, technician_id=?, office_id=? WHERE id=?");
        $stmt->execute([$title, $category, $description, $tech_comment, $user_id, $technician_id, $office_id, $ticket_id]);

        $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, changed_by, comment) VALUES (?, ?, ?, 'El administrador modificó los atributos y contexto del ticket desde el panel.')");
        $stmtHist->execute([$ticket_id, $current_status, $_SESSION['user']['id']]);
        
        $success = "El ticket ha sido modificado y actualizado existosamente.";

    } catch (PDOException $e) {
        $error = "Falló la actualización: " . $e->getMessage();
    }
}

// Obtener detalles completos
$stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Ticket no existente o extraviado.");
}

$c_status = $ticket['status'] ?: 'Pendiente';
$is_full_edit = true; // Habilitado para editar atributos en todo momento

// Listas para los combo-box
$usuarios = $conn->query("SELECT id, dni, first_name, last_name, email FROM usuarios WHERE is_active = TRUE ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
$tecnicos = $conn->query("SELECT id, dni, first_name, last_name FROM trabajadores WHERE role='tecnico' AND is_active = TRUE ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
$oficinas = $conn->query("SELECT id, name FROM oficina WHERE is_active = TRUE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

require 'includes/admin_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 mt-2">
    <div class="d-flex align-items-center mb-3 mb-md-0">
        <!-- Navegación fluida: Retrocede sin recargar la tabla pre-filtrada anterior -->
        <button type="button" class="btn btn-outline-secondary rounded-circle me-3 flex-shrink-0" onclick="history.back()" style="width: 40px; height: 40px; padding: 0; line-height:38px; text-align:center;" title="Volver atrás">
            <i class="bi bi-arrow-left"></i>
        </button>
        <div>
            <h2 class="fw-bold mb-0">Edición de Ticket <span class="text-primary"><?php echo htmlspecialchars($ticket['id']); ?></span></h2>
            <p class="text-muted mb-0"><i class="bi bi-tools me-1"></i> Panel de sobreescritura administrativa</p>
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
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>



<div class="card card-plain border-0 mb-5 fade-in">
    <div class="card-body p-4 p-lg-5">
        <form method="POST">
            <input type="hidden" name="action" value="update_ticket">

            <div class="row g-4 mb-4">
                <h5 class="fw-bold border-bottom pb-2 text-primary"><i class="bi bi-card-heading me-2"></i>Información Analítica</h5>
                
                <div class="col-md-8">
                    <label class="form-label fw-bold text-dark">Título Remitido *</label>
                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($ticket['title']); ?>" required>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold text-dark">Área Específica *</label>
                    <select name="category" class="form-select" required>
                        <option value="Hardware" <?php echo $ticket['category'] == 'Hardware' ? 'selected' : ''; ?>>Hardware</option>
                        <option value="Software" <?php echo $ticket['category'] == 'Software' ? 'selected' : ''; ?>>Software</option>
                        <option value="Redes" <?php echo $ticket['category'] == 'Redes' ? 'selected' : ''; ?>>Redes / Conectividad</option>
                        <option value="Periféricos" <?php echo $ticket['category'] == 'Periféricos' ? 'selected' : ''; ?>>Periféricos (Impresoras, etc)</option>
                        <option value="Sistemas y Cuentas" <?php echo $ticket['category'] == 'Sistemas y Cuentas' ? 'selected' : ''; ?>>Sistemas, Accesos y Cuentas</option>
                        <option value="Otro" <?php echo $ticket['category'] == 'Otro' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold text-dark">Descripción Exacta *</label>
                    <textarea class="form-control" name="description" rows="5" required><?php echo htmlspecialchars($ticket['description']); ?></textarea>
                </div>

                <div class="col-12 border-top pt-4 mt-4">
                    <label class="form-label fw-bold text-info"><i class="bi bi-tools me-2"></i>Bitácora e Inspección del Técnico Asignado</label>
                    <textarea class="form-control bg-light border-info" name="tech_comment" rows="4" placeholder="Reportes y observaciones finales escritas por el técnico o sobrescritas por usted..."><?php echo htmlspecialchars($ticket['tech_comment'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- RELACIONES FORANEAS (Sensibles) -->
            <div class="row g-4 mb-4">
                <h5 class="fw-bold border-bottom pb-2 text-primary mt-4"><i class="bi bi-people me-2"></i>Entornos de Propiedad Jerárquica</h5>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-dark">Usuario Creador/Solicitante</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Seleccione o traspase titular...</option>
                        <?php foreach($usuarios as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $ticket['user_id'] == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?> (#<?php echo htmlspecialchars($u['dni']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-dark">Técnico Aprobado</label>
                    <select name="technician_id" class="form-select">
                        <option value="">Ninguno / Sin asignar</option>
                        <?php foreach($tecnicos as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($ticket['technician_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-dark">Oficina Derivada / Afectada</label>
                    <select name="office_id" class="form-select">
                        <option value="">Ninguna Registrada</option>
                        <?php foreach($oficinas as $of): ?>
                            <option value="<?php echo $of['id']; ?>" <?php echo ($ticket['office_id'] ?? '') == $of['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($of['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 mt-5">
                <button type="button" class="btn btn-secondary px-4 py-2" onclick="history.back()">Descartar Tareas</button>
                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold"><i class="bi bi-sd-card-fill me-2"></i> Sellar y Actualizar Ticket</button>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/admin_footer.php'; ?>
