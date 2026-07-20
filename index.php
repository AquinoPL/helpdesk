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

if ($is_logged_in && $user["role"] == "admin") {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}

// Lógica para que el técnico se auto-asigne un ticket pendiente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_ticket_id']) && $is_logged_in && $user['role'] == 'tecnico') {
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
        header("Location: index.php?success=taken");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Error al auto-asignar el ticket: " . $e->getMessage();
    }
}

$error = '';
// Guardar valores del POST para repoblar el form si hay error
$post = [
    'dni'        => '',
    'phone'      => '',
    'first_name' => '',
    'last_name'  => '',
    'office_id'  => '',
    'office_name'=> '',
    'category'   => '',
    'title'      => '',
    'description'=> '',
];
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_logged_in) {
    $dni        = trim($_POST['dni']);
    $phone      = trim($_POST['phone']);
    $office_id  = !empty($_POST['office_id']) ? $_POST['office_id'] : null;
    $category   = $_POST['category'];
    $title      = trim($_POST['title']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $first_name  = !empty($_POST['first_name']) ? trim($_POST['first_name']) : 'No Especificado';
    $last_name   = !empty($_POST['last_name'])  ? trim($_POST['last_name'])  : 'No Especificado';

    // Guardar para repoblar
    $post['dni']         = htmlspecialchars($_POST['dni'] ?? '');
    $post['phone']       = htmlspecialchars($_POST['phone'] ?? '');
    $post['first_name']  = htmlspecialchars($_POST['first_name'] ?? '');
    $post['last_name']   = htmlspecialchars($_POST['last_name'] ?? '');
    $post['office_id']   = htmlspecialchars($_POST['office_id'] ?? '');
    $post['office_name'] = htmlspecialchars($_POST['office_name'] ?? '');
    $post['category']    = htmlspecialchars($_POST['category'] ?? '');
    $post['title']       = htmlspecialchars($_POST['title'] ?? '');
    $post['description'] = htmlspecialchars($_POST['description'] ?? '');


    if (empty($dni) || empty($phone) || empty($office_id) || empty($category) || empty($title)) {
        $error = "Por favor, completa todos los campos obligatorios.";
    } else {
        try {
            $conn->beginTransaction();
            $stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE dni = :dni");
            $stmtCheck->execute(['dni' => $dni]);
            $user_id = $stmtCheck->fetchColumn();

            if (!$user_id) {
                $stmtUser = $conn->prepare("INSERT INTO usuarios (dni, first_name, last_name, phone, office_id, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtUser->execute([$dni, $first_name, $last_name, $phone, $office_id, $dni]);
                $user_id = $conn->lastInsertId();
            } else {
                $stmtUpdate = $conn->prepare("UPDATE usuarios SET phone = ?, office_id = ? WHERE id = ?");
                $stmtUpdate->execute([$phone, $office_id, $user_id]);
            }

            $stmt = $conn->prepare("INSERT INTO tickets (user_id, category, title, description, office_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $category, $title, $description, $office_id]);

            // El trigger genera el ID; lo recuperamos con la misma logica: MAX(id) del mes actual
            $prefix = (int) date('Ym');
            $new_ticket_id = $conn->query("SELECT MAX(id) FROM tickets WHERE id DIV 1000 = $prefix")->fetchColumn();

            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, comment) VALUES (?, 'Pendiente', 'Ticket creado desde el portal público')");
            $stmtHist->execute([$new_ticket_id]);

            // Guardar archivos si los hay
            if (isset($_FILES['archivos']['name']) && is_array($_FILES['archivos']['name'])) {
                // Carpeta organizada por mes: uploads/YYYY-MM/
                $month_folder = date('Y-m') . '/';
                $upload_dir   = 'uploads/' . $month_folder;
                if (!is_dir('uploads/')) mkdir('uploads/', 0777, true);
                if (!is_dir($upload_dir))  mkdir($upload_dir,  0777, true);

                $total = count($_FILES['archivos']['name']);
                for ($i = 0; $i < $total; $i++) {
                    $tmp_name = $_FILES['archivos']['tmp_name'][$i];
                    if ($tmp_name != "") {
                        $name = $_FILES['archivos']['name'][$i];
                        $safe_name = preg_replace("/[^a-zA-Z0-9.]+/", "", basename($name));
                        $file_path = $upload_dir . 'ticket_' . $new_ticket_id . '_' . time() . '_' . $safe_name;

                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $stmtFile = $conn->prepare("INSERT INTO ticket_files (ticket_id, file_path) VALUES (?, ?)");
                            $stmtFile->execute([$new_ticket_id, $file_path]);
                        }
                    }
                }
            }

            $conn->commit();
            header("Location: ticket_status.php?id=" . $new_ticket_id . "&dni=" . urlencode($dni));
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error al crear el ticket: " . $e->getMessage();
        }
    }
}

// Obtener oficinas para la busqueda
$stmtOffices = $conn->query("SELECT id, name FROM oficina WHERE is_active = TRUE ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

// Paginacion compartida
$limit = 10;
$page_active = isset($_GET['pa']) ? max(1, (int)$_GET['pa']) : 1;
$page_finished = isset($_GET['pf']) ? max(1, (int)$_GET['pf']) : 1;
$offset_ac = ($page_active - 1) * $limit;
$offset_fi = ($page_finished - 1) * $limit;

// --- Consulta publica de ticket (usuario no registrado) ---
$pub_tab    = isset($_GET['tab']) && $_GET['tab'] === 'consultar' ? 'consultar' : 'reportar';
$pub_result = null;
$pub_msgs   = [];
$pub_error  = '';
if (!$is_logged_in && $pub_tab === 'consultar' && !empty($_GET['ticket_id']) && !empty($_GET['ticket_dni'])) {
    $pub_tid = intval($_GET['ticket_id']);
    $pub_dni = trim($_GET['ticket_dni']);
    try {
        $stmtPub = $conn->prepare("
            SELECT t.*, COALESCE(t.status,'Pendiente') as current_status,
                   u.first_name, u.last_name, u.dni
            FROM tickets t
            JOIN usuarios u ON t.user_id = u.id
            WHERE t.id = :id AND u.dni = :dni
        ");
        $stmtPub->execute(['id' => $pub_tid, 'dni' => $pub_dni]);
        $pub_result = $stmtPub->fetch(PDO::FETCH_ASSOC);
        if (!$pub_result) {
            $pub_error = "No se encontro el ticket #$pub_tid con ese DNI. Verifica los datos e intenta de nuevo.";
        } else {
            $stmtPubMsgs = $conn->prepare("
                SELECT th.comment, th.status, th.created_at AS changed_at,
                       COALESCE(w.first_name, 'Sistema') as actor_name,
                       COALESCE(w.role, 'sistema') as actor_role
                FROM ticket_history th
                LEFT JOIN trabajadores w ON th.changed_by = w.id
                WHERE th.ticket_id = :id
                ORDER BY th.created_at ASC
            ");
            $stmtPubMsgs->execute(['id' => $pub_tid]);
            $pub_msgs = $stmtPubMsgs->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $pub_result = null;
        $pub_error = "Error al consultar el ticket.";
    }
}

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

<?php if ($is_logged_in): ?>
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
<?php else: ?>
    <div class="hero-public">
        <h1 class="fw-bold mb-2">Soporte T&eacute;cnico Alianza</h1>
        <p class="mb-0" style="color:#c7d9e2">Reporta un problema sin necesidad de crear una cuenta, o consulta el estado de un ticket que ya enviaste.</p>
        <div class="steps-mini">
            <div class="step"><i class="bi bi-1-circle me-1"></i>Cu&eacute;ntanos qu&eacute; pasa</div>
            <div class="step"><i class="bi bi-2-circle me-1"></i>Recibe un ID de ticket</div>
            <div class="step"><i class="bi bi-3-circle me-1"></i>Da seguimiento cuando quieras</div>
        </div>
    </div>
<?php endif; ?>

<?php if ($is_logged_in && $user["role"] == "usuario"): ?>
    <?php
    // TCKTS ACTIVOS
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
    $stmtC->execute(['id' => $user['id']]);
    $total_ac = $stmtC->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status, u.first_name, u.last_name, o.name as office_name FROM tickets t JOIN usuarios u ON t.user_id = u.id LEFT JOIN oficina o ON t.office_id = o.id WHERE t.user_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- TABLA ACTIVOS: USUARIO -->
    <div class="card card-plain border-0 mb-4">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-ticket-detailed text-primary me-2"></i> Mis Tickets Activos</h5>
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
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['office_name'] ?? 'Sin oficina'); ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 text-end"><a href="ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Detalles</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No tienes tickets activos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', $page_finished); ?>
        </div>
    </div>



<?php elseif ($is_logged_in && $user["role"] == "tecnico"): ?>
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

    // TCKTS ACTIVOS TECNICO
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
    $stmtC->execute(['id' => $user['id']]);
    $total_ac = $stmtC->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status, u.first_name, u.last_name, o.name as office_name FROM tickets t JOIN usuarios u ON t.user_id = u.id LEFT JOIN oficina o ON t.office_id = o.id WHERE t.technician_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- BANDEJA SIN ASIGNAR: TECNICO -->
    <div class="card card-plain border-0 mb-4 shadow-sm">
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
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $tp['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo htmlspecialchars($tp['id']); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($tp['first_name'] . ' ' . $tp['last_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($tp['office_name'] ?? 'Sin oficina'); ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($tp['title']); ?></td>
                                <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($tp['category']); ?></span></td>
                                <td><span class="badge status-badge <?php echo $tpBadge; ?>"><?php echo htmlspecialchars($tp['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($tp['created_at'])); ?></td>
                                <td class="pe-4 text-end" onclick="event.stopPropagation();">
                                    <form method="POST" class="d-inline" action="index.php">
                                        <input type="hidden" name="take_ticket_id" value="<?php echo $tp['id']; ?>">
                                        <button type="submit" class="btn btn-sm text-white rounded-pill px-3 shadow-sm" style="background:var(--accent)" onclick="return confirm('¿Deseas auto-asignarte y comenzar la atención del ticket #<?php echo $tp['id']; ?>?')">
                                            <i class="bi bi-hand-index-thumb me-1"></i> Tomar Ticket
                                        </button>
                                    </form>
                                    <a href="ticket_detalle.php?id=<?php echo $tp['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1">Ver</a>
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

    <!-- TABLA ACTIVOS: TECNICO -->
    <div class="card card-plain border-0 mb-4">
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
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['office_name'] ?? 'Sin oficina'); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 text-end"><a href="ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Gestionar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No tienes tickets activos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo renderPagination($page_active, $pages_ac, 'pa', 'pf', $page_finished); ?>
        </div>
    </div>



<?php elseif (!$is_logged_in): ?>
    <div style="margin-top:-2.5rem; padding-bottom: 3rem;">
        <div class="card card-plain p-4 p-md-5 mx-auto" style="max-width: 860px;">

            <!-- Card con tabs publicas -->
            <ul class="nav nav-tabs-public mb-4" id="publicTabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo $pub_tab === 'reportar' ? 'active' : ''; ?>"
                       id="tab-reportar" data-bs-toggle="tab" href="#pane-reportar">
                        <i class="bi bi-plus-circle me-1"></i>Reportar un problema
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pub_tab === 'consultar' ? 'active' : ''; ?>"
                       id="tab-consultar" data-bs-toggle="tab" href="#pane-consultar">
                        <i class="bi bi-search me-1"></i>Consultar mi ticket
                    </a>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ====== TAB: REPORTAR ====== -->
                <div class="tab-pane fade <?php echo $pub_tab === 'reportar' ? 'show active' : ''; ?>" id="pane-reportar">

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="publicTicketForm">

                        <div class="row g-3 mb-3">
                            <!-- DNI y Oficina primero -->
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">DNI <span class="text-danger">*</span></label>
                                <input type="text" name="dni" id="f_dni" class="form-control" required
                                    placeholder="Tu DNI"
                                    value="<?php echo $post['dni']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Oficina <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="hidden" name="office_id"   id="public_office_id"   value="<?php echo $post['office_id']; ?>">
                                    <input type="hidden" name="office_name" id="public_office_name" value="<?php echo $post['office_name']; ?>">
                                    <input type="text" class="form-control bg-white" id="public_office_display"
                                        placeholder="Haz clic para buscar..."
                                        value="<?php echo $post['office_name']; ?>"
                                        readonly onclick="openOfficeSearch('public_office_id','public_office_display','public_office_name')"
                                        style="cursor:pointer;">
                                    <button type="button" class="btn btn-outline-secondary"
                                        onclick="openOfficeSearch('public_office_id','public_office_display','public_office_name')">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                                <div id="office_error" class="text-danger small mt-1" style="display:none;">Por favor selecciona una oficina.</div>
                            </div>

                            <!-- Nombres, Apellidos y Teléfono -->
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Nombres</label>
                                <input type="text" name="first_name" class="form-control" placeholder="Tus nombres"
                                    value="<?php echo $post['first_name']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Apellidos</label>
                                <input type="text" name="last_name" class="form-control" placeholder="Tus apellidos"
                                    value="<?php echo $post['last_name']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Tel&eacute;fono <span class="text-danger">*</span></label>
                                <input type="text" name="phone" id="f_phone" class="form-control" required
                                    placeholder="Nro para contactarte"
                                    value="<?php echo $post['phone']; ?>">
                            </div>

                            <!-- Categoría y Asunto -->
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Categor&iacute;a <span class="text-danger">*</span></label>
                                <select class="form-select" name="category" id="f_category" required>
                                    <option value="" <?php echo $post['category']==='' ? 'selected disabled' : ''; ?>>Selecciona...</option>
                                    <option value="Software"    <?php echo $post['category']==='Software'    ? 'selected':''; ?>>Software</option>
                                    <option value="Hardware"    <?php echo $post['category']==='Hardware'    ? 'selected':''; ?>>Hardware</option>
                                    <option value="Internet"    <?php echo $post['category']==='Internet'    ? 'selected':''; ?>>Internet</option>
                                    <option value="Instalacion" <?php echo $post['category']==='Instalacion' ? 'selected':''; ?>>Instalaci&oacute;n</option>
                                    <option value="Otro"        <?php echo $post['category']==='Otro'        ? 'selected':''; ?>>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-medium">Asunto <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="f_title" class="form-control" required
                                    placeholder="Describe el problema en pocas palabras"
                                    value="<?php echo $post['title']; ?>">
                            </div>

                            <!-- Descripción -->
                            <div class="col-12">
                                <label class="form-label small fw-medium">Descripci&oacute;n</label>
                                <textarea name="description" class="form-control" rows="4"
                                    placeholder="Cu&eacute;ntanos con detalle qu&eacute; sucede..."><?php echo $post['description']; ?></textarea>
                            </div>
                        </div>

                        <!-- Evidencias -->
                        <div class="card bg-light border-0 mb-3" style="border: 2px dashed var(--line) !important;">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-cloud-arrow-up-fill fs-2" style="color:var(--accent)"></i>
                                <h6 class="fw-bold mt-2">A&ntilde;ade fotos o documentos</h6>
                                <div class="d-flex justify-content-center gap-2 flex-wrap mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            onclick="document.getElementById('cameraInput').click()">
                                        <i class="bi bi-camera me-1"></i> Foto
                                    </button>
                                    <button type="button" class="btn btn-sm text-white" style="background:var(--accent)"
                                            onclick="document.getElementById('fileInput').click()">
                                        <i class="bi bi-folder me-1"></i> Explorar
                                    </button>
                                </div>
                                <input type="file" id="cameraInput" accept="image/*" capture="environment" class="d-none" multiple>
                                <input type="file" id="fileInput" class="d-none" multiple>
                                <input type="file" name="archivos[]" id="realInput" class="d-none" multiple>
                            </div>
                        </div>
                        <ul class="list-group list-group-flush border rounded-3 overflow-hidden" id="filePreviewList" style="display:none;"></ul>

                        <button type="submit" class="btn text-white w-100 mt-3 py-2 fw-medium" style="background:var(--deep)">
                            <i class="bi bi-send me-1"></i> Enviar reporte
                        </button>
                        <p class="small text-center mt-3 mb-0" style="color:var(--muted)">
                            Al enviar tu reporte recibir&aacute;s un ID de ticket para darle seguimiento.
                        </p>

                    </form>

                    <script>
                    (function(){
                        const cameraInput = document.getElementById('cameraInput');
                        const fileInput   = document.getElementById('fileInput');
                        const realInput   = document.getElementById('realInput');
                        const filePreviewList = document.getElementById('filePreviewList');
                        let selectedFiles = [];
                        const MAX_FILES = 5;

                        function handleFiles(files) {
                            for (let i = 0; i < files.length; i++) {
                                if (selectedFiles.length >= MAX_FILES) { alert('Limite de ' + MAX_FILES + ' archivos.'); break; }
                                if(!selectedFiles.some(f => f.name === files[i].name && f.size === files[i].size))
                                    selectedFiles.push(files[i]);
                            }
                            updateUI();
                        }
                        if (cameraInput) cameraInput.addEventListener('change', e => { handleFiles(e.target.files); e.target.value=''; });
                        if (fileInput)   fileInput.addEventListener('change',   e => { handleFiles(e.target.files); e.target.value=''; });

                        function updateUI() {
                            filePreviewList.innerHTML = '';
                            const dt = new DataTransfer();
                            selectedFiles.forEach((file, index) => {
                                dt.items.add(file);
                                let icon = 'bi-file-earmark';
                                if (file.type.startsWith('image/')) icon = 'bi-image text-primary';
                                else if (file.type === 'application/pdf') icon = 'bi-file-earmark-pdf text-danger';
                                const li = document.createElement('li');
                                li.className = 'list-group-item d-flex justify-content-between align-items-center bg-white py-2';
                                li.innerHTML = `<div class="d-flex align-items-center text-truncate pe-3"><i class="bi ${icon} me-3 opacity-75"></i><div class="text-truncate"><span class="d-block small fw-medium text-dark text-truncate">${file.name}</span><small class="text-muted" style="font-size:.7rem">${(file.size/1024/1024).toFixed(2)} MB</small></div></div><button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removeFile(${index})"><i class="bi bi-x"></i></button>`;
                                filePreviewList.appendChild(li);
                            });
                            realInput.files = dt.files;
                            filePreviewList.style.display = selectedFiles.length > 0 ? 'block' : 'none';
                        }
                        function removeFile(index) { selectedFiles.splice(index,1); updateUI(); }

                        const publicForm = document.getElementById('publicTicketForm');
                        if (publicForm) {
                            publicForm.addEventListener('submit', function(e) {
                                publicForm.classList.add('was-validated');
                                let valid = true;
                                const officeId  = document.getElementById('public_office_id');
                                const officeErr = document.getElementById('office_error');
                                if (!officeId.value) {
                                    officeErr.style.display = 'block';
                                    document.getElementById('public_office_display').classList.add('is-invalid');
                                    valid = false;
                                } else {
                                    officeErr.style.display = 'none';
                                    document.getElementById('public_office_display').classList.remove('is-invalid');
                                }
                                if (selectedFiles.length > MAX_FILES) { e.preventDefault(); return; }
                                if (!publicForm.checkValidity() || !valid) { e.preventDefault(); e.stopPropagation(); }
                            });
                        }
                    })();
                    </script>

                </div><!-- /pane-reportar -->

                <!-- ====== TAB: CONSULTAR ====== -->
                <div class="tab-pane fade <?php echo $pub_tab === 'consultar' ? 'show active' : ''; ?>" id="pane-consultar">

                    <form method="GET" action="index.php" class="mb-4">
                        <input type="hidden" name="tab" value="consultar">
                        <div class="row g-2 mb-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small fw-medium">ID de ticket</label>
                                <input type="number" name="ticket_id" class="form-control"
                                       placeholder="Ej. 4830"
                                       value="<?php echo htmlspecialchars($_GET['ticket_id'] ?? ''); ?>"
                                       min="1" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-medium">DNI con el que reportaste</label>
                                <input type="text" name="ticket_dni" class="form-control"
                                       placeholder="Tu DNI"
                                       value="<?php echo htmlspecialchars($_GET['ticket_dni'] ?? ''); ?>"
                                       required>
                            </div>
                            <div class="col-md-2 d-flex">
                                <button class="btn w-100 text-white" style="background:var(--deep)">
                                    <i class="bi bi-search me-1"></i> Ver
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($pub_error)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($pub_error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($pub_result): ?>
                        <?php
                        $cs = $pub_result['current_status'];
                        $badgeMap = [
                            'Pendiente'  => 'bg-secondary',
                            'En camino'  => 'bg-warning text-dark',
                            'En proceso' => 'bg-info text-dark',
                            'Atendido'   => 'bg-success',
                            'Rechazado'  => 'bg-danger',
                        ];
                        $badgeCls = $badgeMap[$cs] ?? 'bg-secondary';
                        ?>
                        <div class="card card-plain p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="small" style="color:var(--muted)">Ticket #<?php echo htmlspecialchars($pub_result['id']); ?> &middot; <?php echo htmlspecialchars($pub_result['category']); ?></div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($pub_result['title']); ?></h6>
                                </div>
                                <span class="badge <?php echo $badgeCls; ?> badge-status"><?php echo htmlspecialchars($cs); ?></span>
                            </div>
                            <div class="small mb-2" style="color:var(--muted)">
                                Reportado por <?php echo htmlspecialchars($pub_result['first_name'].' '.$pub_result['last_name']); ?>
                            </div>

                            <?php if (!empty($pub_msgs)): ?>
                                <div class="mb-3" style="max-height:200px; overflow-y:auto;">
                                    <?php foreach ($pub_msgs as $msg):
                                        $isAgent = in_array($msg['actor_role'] ?? '', ['admin','tecnico']);
                                        $msgClass = $isAgent ? 'msg-agent' : 'msg-client';
                                    ?>
                                    <div class="msg <?php echo $msgClass; ?>">
                                        <div class="fw-semibold small mb-1">
                                            <?php echo htmlspecialchars($msg['actor_name']); ?>
                                            <?php if ($isAgent): ?><span class="text-muted fw-normal">&middot; Agente</span><?php endif; ?>
                                            <span class="text-muted fw-normal float-end" style="font-size:.7rem">
                                                <?php echo date('d/m/Y H:i', strtotime($msg['changed_at'])); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($msg['comment'])): ?>
                                            <?php echo nl2br(htmlspecialchars($msg['comment'])); ?>
                                        <?php else: ?>
                                            <em class="text-muted">Estado cambiado a: <?php echo htmlspecialchars($msg['status']); ?></em>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="input-group input-group-sm">
                                <a href="ticket_status.php?id=<?php echo $pub_result['id']; ?>&dni=<?php echo urlencode($pub_result['dni']); ?>"
                                   class="btn btn-sm text-white" style="background:var(--accent)">
                                    <i class="bi bi-arrow-right-circle me-1"></i> Ver detalle completo
                                </a>
                            </div>
                        </div>
                    <?php elseif ($pub_tab === 'consultar' && !isset($_GET['ticket_id'])): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                            <p class="mb-0">Ingresa el numero de ticket y tu DNI para consultar su estado.</p>
                        </div>
                    <?php endif; ?>

                    <p class="small text-center mt-4 mb-0" style="color:var(--muted)">
                        &iquest;Ya tienes cuenta? <a href="login.php">Inicia sesi&oacute;n</a> para ver todo tu historial.
                    </p>

                </div><!-- /pane-consultar -->

            </div><!-- /tab-content -->
        </div><!-- /card -->
    </div>

    <!-- Office Search Modal -->
    <div class="modal fade" id="officeSearchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-building me-2"></i> Buscar Oficina</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="officeSearchInput" class="form-control form-control-lg"
                               placeholder="Escriba el nombre de la oficina..." autocomplete="off">
                    </div>
                    <div id="officeSearchResults" class="list-group overflow-auto" style="max-height:250px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    let targetOfficeIdInput = '';
    let targetOfficeDisplayInput = '';

    function openOfficeSearch(idField, displayField, nameField) {
        targetOfficeIdInput      = idField;
        targetOfficeDisplayInput = displayField;
        const modal = new bootstrap.Modal(document.getElementById('officeSearchModal'));
        document.getElementById('officeSearchInput').value = '';
        document.getElementById('officeSearchResults').innerHTML = '';
        modal.show();
        setTimeout(() => document.getElementById('officeSearchInput').focus(), 500);
    }

    document.getElementById('officeSearchInput').addEventListener('input', function(e) {
        const query = e.target.value.trim();
        if (query.length < 2) { document.getElementById('officeSearchResults').innerHTML = ''; return; }
        fetch('ajax_search_office.php?q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                const results = document.getElementById('officeSearchResults');
                results.innerHTML = '';
                if (data.length === 0) {
                    results.innerHTML = '<div class="list-group-item text-muted text-center py-3">No se encontraron oficinas</div>';
                } else {
                    data.forEach(of => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-3';
                        btn.innerHTML = `<div class="d-flex w-100 justify-content-between"><h6 class="mb-1 fw-bold text-dark">${of.name}</h6></div>`;
                        if (of.location) btn.innerHTML += `<small class="text-muted"><i class="bi bi-geo-alt me-1"></i>${of.location}</small>`;
                        btn.onclick = () => {
                            document.getElementById(targetOfficeIdInput).value  = of.id;
                            document.getElementById(targetOfficeDisplayInput).value = of.name;
                            const nameInput = document.getElementById('public_office_name');
                            if (nameInput) nameInput.value = of.name;
                            document.getElementById('public_office_display').classList.remove('is-invalid');
                            const errEl = document.getElementById('office_error');
                            if (errEl) errEl.style.display = 'none';
                            bootstrap.Modal.getInstance(document.getElementById('officeSearchModal')).hide();
                        };
                        results.appendChild(btn);
                    });
                }
            })
            .catch(err => console.error('Error:', err));
    });

    // Activar tab correcto desde URL
    (function() {
        const urlTab = new URLSearchParams(window.location.search).get('tab');
        if (urlTab) {
            const el = document.getElementById('tab-' + urlTab);
            if (el) new bootstrap.Tab(el).show();
        }
    })();
    </script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
