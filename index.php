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

if ($is_logged_in && $user["role"] == "tecnico") {
    header("Location: " . BASE_URL . "/technical/dashboard.php");
    exit();
}

$error = '';
$login_error = '';
$register_error = '';
$fieldErrors = [];
$register_val = [
    'doc_type'   => 'DNI',
    'dni'        => '',
    'phone'      => '',
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'office_id'  => '',
];

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
    $action = $_POST['action'] ?? 'create_ticket';

    if ($action === 'delete_public_ticket') {
        $ticket_id = (int)$_POST['ticket_id'];
        $dni = trim($_POST['dni']);
        try {
            $stmt = $conn->prepare("
                SELECT t.id 
                FROM tickets t
                JOIN usuarios u ON t.user_id = u.id
                WHERE t.id = ? AND u.dni = ? AND COALESCE(t.status, 'Pendiente') = 'Pendiente'
            ");
            $stmt->execute([$ticket_id, $dni]);
            if ($stmt->fetchColumn()) {
                $conn->beginTransaction();
                $conn->prepare("DELETE FROM ticket_files WHERE ticket_id = ?")->execute([$ticket_id]);
                $conn->prepare("DELETE FROM ticket_history WHERE ticket_id = ?")->execute([$ticket_id]);
                $conn->prepare("DELETE FROM tickets WHERE id = ?")->execute([$ticket_id]);
                $conn->commit();
                header("Location: index.php?tab=consultar&deleted=1");
                exit();
            } else {
                $pub_error = "No se puede eliminar el ticket. Verifique que exista y que aún esté Pendiente.";
            }
        } catch(PDOException $e) {
            $pub_error = "Error al intentar eliminar el ticket.";
        }
    } elseif ($action === 'login') {
        $dni = trim($_POST["dni"] ?? '');
        $password = trim($_POST["password"] ?? '');

        try {
            $stmt = $conn->prepare("CALL login_user(?, ?)");
            $stmt->execute([$dni, $password]);
            $login_user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            // DEBUG LOGGING
            error_log("LOGIN ATTEMPT: DNI=" . print_r($dni, true) . ", PASS=" . print_r($password, true) . ", RESULT=" . print_r($login_user, true));

            if ($login_user && isset($login_user['id']) && !empty($login_user['id'])) {
                $login_user['dni'] = $dni;
                if (!isset($login_user['office_id'])) {
                    $table = ($login_user['role'] === 'usuario') ? 'usuarios' : 'trabajadores';
                    $stmtOfc = $conn->prepare("SELECT office_id FROM $table WHERE id = ?");
                    $stmtOfc->execute([$login_user['id']]);
                    $login_user['office_id'] = $stmtOfc->fetchColumn();
                }
                $_SESSION["user"] = $login_user;
                if ($login_user["role"] == "admin") {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $login_error = "Credenciales incorrectas o usuario no encontrado.";
            }
        } catch(PDOException $e) {
            $login_error = "Error al intentar iniciar sesión: " . $e->getMessage();
        }

    } elseif ($action === 'register') {
        $doc_type         = isset($_POST['doc_type']) && $_POST['doc_type'] === 'CE' ? 'CE' : 'DNI';
        $dni              = trim($_POST["dni"] ?? '');
        $first_name       = trim($_POST["first_name"] ?? '');
        $last_name        = trim($_POST["last_name"] ?? '');
        $email            = trim($_POST["email"] ?? '');
        $phone            = trim($_POST["phone"] ?? '');
        $office_id        = $_POST["office_id"] ?? '';
        $password         = $_POST["password"] ?? '';
        $password_confirm = $_POST["password_confirm"] ?? '';

        $register_val = compact('doc_type', 'dni', 'phone', 'first_name', 'last_name', 'email', 'office_id');

        if ($doc_type === 'CE') {
            if (empty($dni))                               $fieldErrors['dni'] = "El número de Carnet de Extranjería es obligatorio.";
            elseif (!preg_match('/^[0-9]{9}$/', $dni))     $fieldErrors['dni'] = "El Carnet de Extranjería debe tener exactamente 9 dígitos.";
        } else {
            if (empty($dni))                               $fieldErrors['dni'] = "El DNI es obligatorio.";
            elseif (!preg_match('/^[0-9]{8}$/', $dni))     $fieldErrors['dni'] = "El DNI debe tener exactamente 8 dígitos.";
        }

        if (empty($phone))            $fieldErrors['phone']            = "El teléfono es obligatorio.";
        elseif (!preg_match('/^[0-9]{9}$/', $phone)) $fieldErrors['phone'] = "Debe contener exactamente 9 dígitos.";

        if (empty($first_name))       $fieldErrors['first_name']       = "Los nombres son obligatorios.";
        if (empty($last_name))        $fieldErrors['last_name']        = "Los apellidos son obligatorios.";
        if (empty($office_id))        $fieldErrors['office_id']        = "Selecciona una oficina.";
        if (empty($password))         $fieldErrors['password']         = "La contraseña es obligatoria.";
        elseif ($password !== $password_confirm)
                                      $fieldErrors['password_confirm'] = "Las contraseñas no coinciden.";

        if (empty($fieldErrors)) {
            try {
                // Verificar si existe un usuario "invitado" (is_registered = 0)
                $stmtCheck = $conn->prepare("SELECT id, is_registered FROM usuarios WHERE dni = ?");
                $stmtCheck->execute([$dni]);
                $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingUser) {
                    if ($existingUser['is_registered'] == 1) {
                        // Forzar el error para que caiga en el catch o manejarlo aquí
                        throw new PDOException("DNI_EXISTS", 23000);
                    } else {
                        // Actualizar el usuario invitado a registrado
                        $stmt = $conn->prepare("UPDATE usuarios SET doc_type=?, first_name=?, last_name=?, email=?, phone=?, office_id=?, password=?, is_registered=1 WHERE id=?");
                        $stmt->execute([
                            $doc_type, $first_name, $last_name, $email ?: null, $phone ?: null, $office_id ?: null, $password, $existingUser['id']
                        ]);
                        $newId = $existingUser['id'];
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO usuarios (doc_type, dni, first_name, last_name, email, phone, office_id, password, is_registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([
                        $doc_type, $dni, $first_name, $last_name, $email ?: null, $phone ?: null, $office_id ?: null, $password
                    ]);
                    $newId = $conn->lastInsertId();
                }

                $_SESSION["user"] = [
                    'id'         => $newId,
                    'dni'        => $dni,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'office_id'  => $office_id ?: null,
                    'role'       => 'usuario',
                ];
                header("Location: index.php");
                exit();
            } catch(PDOException $e) {
                if ($e->getCode() == '23000' || $e->getCode() == 23000) {
                    $label = $register_val['doc_type'] === 'CE' ? 'Carnet de Extranjería' : 'DNI';
                    $fieldErrors['dni'] = "Este $label ya se encuentra registrado.";
                    $register_error = "El $label ingresado ya está registrado. Si olvidaste tu contraseña, comunícate con el administrador.";
                } else {
                    $register_error = "Hubo un error al registrar el usuario: " . $e->getMessage();
                }
            }
        } else {
            $register_error = "Por favor, corrige los campos marcados en rojo.";
        }

    } elseif ($action === 'create_ticket') {
        $dni        = trim($_POST['dni']);
        $phone      = trim($_POST['phone']);
        $office_id  = !empty($_POST['office_id']) ? $_POST['office_id'] : null;
        $category   = $_POST['category'];
        $title      = trim($_POST['title']);
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $first_name  = !empty($_POST['first_name']) ? trim($_POST['first_name']) : 'No Especificado';
        $last_name   = !empty($_POST['last_name'])  ? trim($_POST['last_name'])  : 'No Especificado';

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
                    $stmtUser = $conn->prepare("INSERT INTO usuarios (dni, first_name, last_name, phone, office_id, password, is_registered) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $stmtUser->execute([$dni, $first_name, $last_name, $phone, $office_id, $dni]);
                    $user_id = $conn->lastInsertId();
                } else {
                    $stmtUpdate = $conn->prepare("UPDATE usuarios SET first_name = ?, last_name = ?, phone = ?, office_id = ? WHERE id = ?");
                    $stmtUpdate->execute([$first_name, $last_name, $phone, $office_id, $user_id]);
                }

                $stmt = $conn->prepare("INSERT INTO tickets (user_id, category, title, description, office_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $category, $title, $description, $office_id]);

                $prefix = (int) date('Ym');
                $new_ticket_id = $conn->query("SELECT MAX(id) FROM tickets WHERE id DIV 1000 = $prefix")->fetchColumn();

                $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, comment) VALUES (?, 'Pendiente', 'Ticket creado desde el portal público')");
                $stmtHist->execute([$new_ticket_id]);

                if (isset($_FILES['archivos']['name']) && is_array($_FILES['archivos']['name'])) {
                    $month_folder = date('Y-m') . '/';
                    $physical_dir = __DIR__ . '/ticket/uploads/' . $month_folder;
                    $db_dir       = 'uploads/' . $month_folder;

                    if (!is_dir(__DIR__ . '/ticket/uploads/')) mkdir(__DIR__ . '/ticket/uploads/', 0777, true);
                    if (!is_dir($physical_dir)) mkdir($physical_dir, 0777, true);

                    $total = count($_FILES['archivos']['name']);
                    for ($i = 0; $i < $total; $i++) {
                        $tmp_name = $_FILES['archivos']['tmp_name'][$i];
                        if ($tmp_name != "") {
                            $name = $_FILES['archivos']['name'][$i];
                            
                            // Validar extensión
                            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif'];
                            $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            if (!in_array($file_ext, $allowed_exts)) {
                                continue;
                            }
                            
                            $safe_name = preg_replace("/[^a-zA-Z0-9.]+/", "", basename($name));
                            $filename  = 'ticket_' . $new_ticket_id . '_' . time() . '_' . $safe_name;
                            $target_file  = $physical_dir . $filename;
                            $db_file_path = $db_dir . $filename;

                            if (move_uploaded_file($tmp_name, $target_file)) {
                                $stmtFile = $conn->prepare("INSERT INTO ticket_files (ticket_id, file_path) VALUES (?, ?)");
                                $stmtFile->execute([$new_ticket_id, $db_file_path]);
                            }
                        }
                    }
                }

                $conn->commit();
                if (isset($_SESSION['user'])) {
                    header("Location: ticket/ticket_detalle.php?id=" . $new_ticket_id);
                } else {
                    header("Location: ticket/ticket_status.php?id=" . $new_ticket_id . "&dni=" . urlencode($dni));
                }
                exit();
            } catch (PDOException $e) {
                $conn->rollBack();
                $error = "Error al crear el ticket: " . $e->getMessage();
            }
        }
    }
}

// Helpers
function hasErr(string $field, array $fieldErrors): bool {
    return isset($fieldErrors[$field]);
}
function fieldClass(string $field, array $fieldErrors): string {
    return hasErr($field, $fieldErrors) ? 'is-invalid' : '';
}
function fieldMsg(string $field, array $fieldErrors): string {
    if (hasErr($field, $fieldErrors)) {
        return '<div class="invalid-feedback d-block text-start"><i class="bi bi-exclamation-circle me-1"></i>' . htmlspecialchars($fieldErrors[$field]) . '</div>';
    }
    return '';
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
$pub_success = '';
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $pub_success = "El ticket ha sido eliminado exitosamente.";
}
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
    <div class="card p-3 mt-4 mb-4 flex-row justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-1">Hola, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
            <p class="text-muted mb-0">Bienvenido al sistema de soporte.</p>
        </div>
        <?php if ($user["role"] == "usuario"): ?>
        <div>
            <a href="ticket/ticket.php" class="btn btn-primary d-flex align-items-center shadow-sm">
                <i class="bi bi-plus-lg me-2"></i> Crear Ticket
            </a>
        </div>
        <?php endif; ?>
    </div>

    <style>
    .stat-card-clickable { text-decoration: none; display: block; color: inherit; }
    .stat-card-clickable .kpi-card { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; border-radius: 12px; }
    .stat-card-clickable:hover .kpi-card { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    </style>
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

    // TCKTS HISTORIAL
    $stmtH = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND status IN ('Atendido', 'Rechazado')");
    $stmtH->execute(['id' => $user['id']]);
    $total_hist = $stmtH->fetchColumn();

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status, u.first_name, u.last_name, o.name as office_name FROM tickets t JOIN usuarios u ON t.user_id = u.id LEFT JOIN oficina o ON t.office_id = o.id WHERE t.user_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card kpi-card p-4 border-0 shadow-sm h-100 glass-card">
                <div class="text-muted fw-bold mb-2 text-uppercase" style="font-size: 0.85rem;">Mis Tickets Activos</div>
                <div class="d-flex align-items-end justify-content-between">
                    <h2 class="mb-0 fw-bolder text-warning"><?php echo $total_ac; ?></h2>
                    <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-inbox-fill fs-4 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <a href="ticket/ticket.php" class="stat-card-clickable">
                <div class="card kpi-card p-4 border-0 shadow-sm h-100 glass-card">
                    <div class="text-muted fw-bold mb-2 text-uppercase" style="font-size: 0.85rem;">Nuevo Ticket</div>
                    <div class="d-flex align-items-end justify-content-between">
                        <h2 class="mb-0 fw-bolder text-primary"><i class="bi bi-plus"></i></h2>
                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-pencil-square fs-4 text-primary"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="ticket/historial.php" class="stat-card-clickable">
                <div class="card kpi-card p-4 border-0 shadow-sm h-100 glass-card">
                    <div class="text-muted fw-bold mb-2 text-uppercase" style="font-size: 0.85rem;">Mi Historial</div>
                    <div class="d-flex align-items-end justify-content-between">
                        <h2 class="mb-0 fw-bolder text-info"><?php echo $total_hist; ?></h2>
                        <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-clock-history fs-4 text-info"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="perfil.php" class="stat-card-clickable">
                <div class="card kpi-card p-4 border-0 shadow-sm h-100 glass-card">
                    <div class="text-muted fw-bold mb-2 text-uppercase" style="font-size: 0.85rem;">Editar Perfil</div>
                    <div class="d-flex align-items-end justify-content-between">
                        <h2 class="mb-0 fw-bolder text-secondary"><i class="bi bi-gear"></i></h2>
                        <div class="bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-person-circle fs-4 text-secondary"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- TABLA ACTIVOS: USUARIO -->
    <div class="card card-plain border-0 mb-4 glass-card">
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
                            <tr class="ticket-row" onclick="window.location='ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold">#<?php echo htmlspecialchars($t['id']); ?></span></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['office_name'] ?? 'Sin oficina'); ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                <td><span class="badge status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                <td class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                                <td class="pe-4 text-end"><a href="ticket/ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Detalles</a></td>
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
    <div style="margin-top:-1rem; padding-bottom: 3rem; position:relative; z-index:10;">
        <div class="card card-plain p-3 p-md-4 mx-auto glass-card" style="max-width: 1050px;">

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
                            <!-- Fila 1: Datos Personales -->
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label small fw-medium" id="pub_dni_label">DNI <span class="text-danger">*</span></label>
                                    <select id="pub_doc_type" class="form-select form-select-sm" style="width: auto; padding: 0 1.5rem 0 .5rem; height: 20px; font-size: 0.7rem; border: none; background-color: transparent;" onchange="updatePubDniField()">
                                        <option value="DNI">DNI</option>
                                        <option value="CE">CE</option>
                                    </select>
                                </div>
                                <input type="text" name="dni" id="f_dni" class="form-control" required
                                    pattern="\d{8}" title="Debe tener 8 dígitos numéricos" placeholder="Ej: 12345678"
                                    value="<?php echo $post['dni']; ?>">
                                <div id="publicDniStatus" class="form-text mt-1 text-muted" style="font-size: 0.75rem;">Ingresa tu N° Documento.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-medium">Nombres</label>
                                <input type="text" name="first_name" class="form-control" placeholder="Tus nombres"
                                    value="<?php echo $post['first_name']; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-medium">Apellidos</label>
                                <input type="text" name="last_name" class="form-control" placeholder="Tus apellidos"
                                    value="<?php echo $post['last_name']; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-medium">Tel&eacute;fono <span class="text-danger">*</span></label>
                                <input type="text" name="phone" id="f_phone" class="form-control" required
                                    pattern="[0-9]{9}" maxlength="9" title="Debe contener exactamente 9 dígitos numéricos"
                                    placeholder="Nro contacto"
                                    value="<?php echo $post['phone']; ?>">
                            </div>

                            <!-- Fila 2: Detalles del Ticket -->
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Oficina <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select name="office_id" class="form-select searchable-select <?php echo isset($fieldErrors['office_id']) ? 'is-invalid' : ''; ?>" required>
                                        <option value="">Seleccione una oficina...</option>
                                        <?php foreach ($offices as $of): ?>
                                            <option value="<?php echo $of['id']; ?>" <?php echo (isset($post['office_id']) && $post['office_id'] == $of['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($of['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (isset($fieldErrors['office_id'])): ?>
                                    <div class="text-danger small mt-1"><?php echo htmlspecialchars($fieldErrors['office_id']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
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
                            <div class="col-md-5">
                                <label class="form-label small fw-medium">Asunto <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="f_title" class="form-control" required
                                    placeholder="Breve descripción del problema"
                                    value="<?php echo $post['title']; ?>">
                            </div>

                            <!-- Fila 3: Descripción -->
                            <div class="col-12">
                                <label class="form-label small fw-medium">Descripci&oacute;n</label>
                                <textarea name="description" class="form-control" rows="2"
                                    placeholder="Cu&eacute;ntanos con más detalle qu&eacute; sucede..."><?php echo $post['description']; ?></textarea>
                            </div>
                        </div>

                        <!-- Evidencias -->
                        <div class="card bg-light border-0 mb-2" id="dropZoneIndex" style="border: 1.5px dashed var(--line) !important; transition: all 0.2s ease; cursor: pointer;" onclick="if(event.target.tagName !== 'BUTTON' && !event.target.closest('button')) document.getElementById('fileInput').click();">
                            <div class="card-body p-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-cloud-arrow-up-fill fs-4" style="color:var(--accent)"></i>
                                    <div>
                                        <span class="fw-semibold small d-block mb-0 text-dark">A&ntilde;ade o arrastra fotos / documentos</span>
                                        <span class="small text-muted" style="font-size: 0.75rem;">Arrastra aqu&iacute; o usa los botones (M&aacute;x 5)</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 ms-auto dropzone-buttons">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 btn-upload-action"
                                            onclick="event.stopPropagation(); openFotoIndex()">
                                        <i class="bi bi-camera me-1"></i> Foto
                                    </button>
                                    <button type="button" class="btn btn-sm text-white py-1 px-2 btn-upload-action" style="background:var(--accent)"
                                            onclick="event.stopPropagation(); document.getElementById('fileInput').click()">
                                        <i class="bi bi-folder me-1"></i> Explorar
                                    </button>
                                </div>
                                <input type="file" id="fotoInput" accept="image/*" class="d-none">
                                <input type="file" id="fileInput" accept="image/*" class="d-none" multiple>
                                <input type="file" name="archivos[]" id="realInput" accept="image/*" class="d-none" multiple>
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
                        const fotoInput   = document.getElementById('fotoInput');
                        const fileInput   = document.getElementById('fileInput');
                        const realInput   = document.getElementById('realInput');
                        const filePreviewList = document.getElementById('filePreviewList');
                        const dropZoneIndex = document.getElementById('dropZoneIndex');
                        let selectedFiles = [];
                        const MAX_FILES = 5;

                        function isMobile() {
                            return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
                        }
                        window.openFotoIndex = function() {
                            if (isMobile()) {
                                fotoInput.setAttribute('capture', 'environment');
                            } else {
                                fotoInput.removeAttribute('capture');
                            }
                            fotoInput.click();
                        };

                        function handleFiles(files) {
                            for (let i = 0; i < files.length; i++) {
                                if (selectedFiles.length >= MAX_FILES) { alert('Limite de ' + MAX_FILES + ' archivos.'); break; }
                                if(!selectedFiles.some(f => f.name === files[i].name && f.size === files[i].size))
                                    selectedFiles.push(files[i]);
                            }
                            updateUI();
                        }
                        if (fotoInput) fotoInput.addEventListener('change', e => { handleFiles(e.target.files); e.target.value=''; });
                        if (fileInput) fileInput.addEventListener('change', e => { handleFiles(e.target.files); e.target.value=''; });

                        if (dropZoneIndex) {
                            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
                                dropZoneIndex.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }, false);
                            });
                            ['dragenter', 'dragover'].forEach(evt => {
                                dropZoneIndex.addEventListener(evt, () => {
                                    dropZoneIndex.style.background = '#e7f1f4';
                                    dropZoneIndex.style.borderColor = 'var(--accent)';
                                }, false);
                            });
                            ['dragleave', 'drop'].forEach(evt => {
                                dropZoneIndex.addEventListener(evt, () => {
                                    dropZoneIndex.style.background = '';
                                    dropZoneIndex.style.borderColor = 'var(--line)';
                                }, false);
                            });
                            dropZoneIndex.addEventListener('drop', e => {
                                if (e.dataTransfer && e.dataTransfer.files) {
                                    handleFiles(e.dataTransfer.files);
                                }
                            }, false);
                        }

                        function updateUI() {
                            filePreviewList.innerHTML = '';
                            const dt = new DataTransfer();
                            selectedFiles.forEach((file, index) => {
                                dt.items.add(file);
                                let icon = 'bi-file-earmark';
                                if (file.type.startsWith('image/')) icon = 'bi-image text-primary';
                                const li = document.createElement('li');
                                li.className = 'list-group-item d-flex justify-content-between align-items-center bg-white py-2';
                                li.innerHTML = `<div class="d-flex align-items-center text-truncate pe-3"><i class="bi ${icon} me-3 opacity-75"></i><div class="text-truncate"><span class="d-block small fw-medium text-dark text-truncate">${file.name}</span><small class="text-muted" style="font-size:.7rem">${(file.size/1024/1024).toFixed(2)} MB</small></div></div><button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removeFile(${index})"><i class="bi bi-x"></i></button>`;
                                filePreviewList.appendChild(li);
                            });
                            realInput.files = dt.files;
                            filePreviewList.style.display = selectedFiles.length > 0 ? 'block' : 'none';
                        }
                        window.removeFile = function(index) { selectedFiles.splice(index,1); updateUI(); };

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

                    <?php if (!empty($pub_success)): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($pub_success); ?>
                        </div>
                    <?php endif; ?>

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
                                            <?php 
                                            if ($isAgent) {
                                                echo 'Técnico';
                                            } else {
                                                echo htmlspecialchars($msg['actor_name']); 
                                            }
                                            ?>
                                            <?php if ($isAgent): ?><span class="text-muted fw-normal">&middot; Soporte</span><?php endif; ?>
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
                                <a href="ticket/ticket_status.php?id=<?php echo $pub_result['id']; ?>&dni=<?php echo urlencode($pub_result['dni']); ?>"
                                   class="btn btn-sm text-white" style="background:var(--accent)">
                                    <i class="bi bi-arrow-right-circle me-1"></i> Ver detalle completo
                                </a>
                                <?php if ($cs === 'Pendiente'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" data-bs-toggle="modal" data-bs-target="#deletePublicModal">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>

                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($pub_tab === 'consultar' && !isset($_GET['ticket_id'])): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                            <p class="mb-0">Ingresa el numero de ticket y tu DNI para consultar su estado.</p>
                        </div>
                    <?php endif; ?>

                    <p class="small text-center mt-4 mb-0" style="color:var(--muted)">
                        &iquest;Ya tienes cuenta? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Inicia sesi&oacute;n</a> para ver todo tu historial.
                    </p>

                </div><!-- /pane-consultar -->

            </div><!-- /tab-content -->
        </div><!-- /card -->
    </div>

    <!-- Delete Public Modal -->
    <?php if ($pub_result && $cs === 'Pendiente'): ?>
    <div class="modal fade" id="deletePublicModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center pt-0">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-circle" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">¿Eliminar Ticket?</h4>
                    <p class="text-muted mb-4">Esta acción no se puede deshacer. ¿Estás seguro de que deseas cancelar este ticket?</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_public_ticket">
                        <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($pub_result['id']); ?>">
                        <input type="hidden" name="dni" value="<?php echo htmlspecialchars($pub_result['dni']); ?>">
                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Mantener ticket</button>
                            <button type="submit" class="btn btn-danger px-4">Sí, eliminar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

<?php if (!$is_logged_in): ?>
<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5 pt-0">
                <div class="text-center mb-4">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px;">
                        <i class="bi bi-headset fs-1"></i>
                    </div>
                    <h3 class="fw-bold text-dark">Soporte Alianza</h3>
                    <p class="text-muted">Inicia sesión para continuar</p>
                </div>
                <?php if (!empty($login_error)): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-dark">DNI</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" name="dni" class="form-control" placeholder="Ingresa tu DNI" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-medium text-dark">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Tu contraseña" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fs-5 text-white">
                        Ingresar <i class="bi bi-box-arrow-in-right ms-1"></i>
                    </button>
                    <div class="mt-3 text-center">
                        <span class="text-muted">¿No tienes cuenta?</span> <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="modal" class="text-primary text-decoration-none fw-medium">Regístrate aquí</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Register Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5 pt-0">
                <div class="text-center mb-4">
                    <h3 class="fw-bold text-dark">Registro de Usuario</h3>
                    <p class="text-muted">Crea tu cuenta en Soporte Alianza</p>
                </div>
                <?php if (!empty($register_error)): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($register_error); ?></div>
                <?php endif; ?>
                <form method="POST" novalidate onsubmit="return validateRegisterPasswords(event);">
                    <input type="hidden" name="action" value="register">
                    <div class="row g-3">
                        <!-- Fila 1: Documento, Nombres y Apellidos -->
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-medium text-dark">Tipo <span class="text-danger">*</span></label>
                            <select name="doc_type" id="doc_type" class="form-select" onchange="updateDniField()">
                                <option value="DNI" <?php echo $register_val['doc_type']==='DNI' ? 'selected':''; ?>>DNI</option>
                                <option value="CE"  <?php echo $register_val['doc_type']==='CE'  ? 'selected':''; ?>>Carnet Ext.</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-medium text-dark" id="dni_label">N° Documento <span class="text-danger">*</span></label>
                            <input type="text" name="dni" id="dni_input" class="form-control <?php echo fieldClass('dni', $fieldErrors); ?>" placeholder="Ej: 70000000" maxlength="8" pattern="[0-9]{8}" required value="<?php echo htmlspecialchars($register_val['dni']); ?>">
                            <?php echo fieldMsg('dni', $fieldErrors); ?>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-medium text-dark">Nombres <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control <?php echo fieldClass('first_name', $fieldErrors); ?>" required value="<?php echo htmlspecialchars($register_val['first_name']); ?>">
                            <?php echo fieldMsg('first_name', $fieldErrors); ?>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-medium text-dark">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control <?php echo fieldClass('last_name', $fieldErrors); ?>" required value="<?php echo htmlspecialchars($register_val['last_name']); ?>">
                            <?php echo fieldMsg('last_name', $fieldErrors); ?>
                        </div>

                        <!-- Fila 2: Contacto y Oficina -->
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-medium text-dark">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control <?php echo fieldClass('phone', $fieldErrors); ?>" placeholder="Ej: 999888777" pattern="[0-9]{9}" maxlength="9" title="Debe contener exactamente 9 dígitos" required value="<?php echo htmlspecialchars($register_val['phone']); ?>">
                            <?php echo fieldMsg('phone', $fieldErrors); ?>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label fw-medium text-dark">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control <?php echo fieldClass('email', $fieldErrors); ?>" placeholder="correo@empresa.com" value="<?php echo htmlspecialchars($register_val['email']); ?>">
                            <?php echo fieldMsg('email', $fieldErrors); ?>
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="form-label fw-medium text-dark">Oficina <span class="text-danger">*</span></label>
                            <select name="office_id" class="form-select searchable-select <?php echo fieldClass('office_id', $fieldErrors); ?>" required>
                                <option value="">Seleccione una oficina...</option>
                                <?php foreach($offices as $of): ?>
                                    <option value="<?php echo $of['id']; ?>" <?php echo ($register_val['office_id'] == $of['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($of['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo fieldMsg('office_id', $fieldErrors); ?>
                        </div>

                        <!-- Fila 3: Contraseña -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium text-dark">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="reg_password" class="form-control <?php echo fieldClass('password', $fieldErrors); ?>" required>
                            <?php echo fieldMsg('password', $fieldErrors); ?>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-medium text-dark">Confirmar Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirm" id="reg_password_confirm" class="form-control <?php echo fieldClass('password_confirm', $fieldErrors); ?>" required>
                            <?php echo fieldMsg('password_confirm', $fieldErrors); ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fs-5 text-white fw-bold">
                        Registrar Cuenta <i class="bi bi-person-plus-fill ms-1"></i>
                    </button>
                    <div class="mt-3 text-center">
                        <span class="text-muted">¿Ya tienes una cuenta?</span> <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal" class="text-primary text-decoration-none fw-medium">Ingresa aquí</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos para los modales */
.modal-backdrop.login-blur {
    backdrop-filter: blur(5px);
    background-color: rgba(0, 0, 0, 0.4);
}
.modal-backdrop.register-transparent {
    backdrop-filter: blur(5px);
    background-color: rgba(0, 0, 0, 0.4);
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($login_error)): ?>
        var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    <?php elseif (!empty($register_error) || !empty($fieldErrors)): ?>
        var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
        registerModal.show();
    <?php endif; ?>

    const loginModalEl = document.getElementById('loginModal');
    const registerModalEl = document.getElementById('registerModal');

    if (loginModalEl) {
        loginModalEl.addEventListener('show.bs.modal', function () {
            setTimeout(() => {
                const backdrop = document.querySelector('.modal-backdrop:last-child');
                if (backdrop) {
                    backdrop.classList.add('login-blur');
                    backdrop.classList.remove('register-transparent');
                }
            }, 10);
        });
    }

    if (registerModalEl) {
        registerModalEl.addEventListener('show.bs.modal', function () {
            setTimeout(() => {
                const backdrop = document.querySelector('.modal-backdrop:last-child');
                if (backdrop) {
                    backdrop.classList.add('register-transparent');
                    backdrop.classList.remove('login-blur');
                }
            }, 10);
        });
    }

    window.validateRegisterPasswords = function(e) {
        const p1 = document.getElementById('reg_password') ? document.getElementById('reg_password').value : '';
        const p2 = document.getElementById('reg_password_confirm') ? document.getElementById('reg_password_confirm').value : '';
        if (!p1 || p1.trim() === '') {
            e.preventDefault();
            alert('⚠️ La contraseña no puede estar vacía.');
            return false;
        }
        if (p1 !== p2) {
            e.preventDefault();
            alert('⚠️ Las contraseñas no coinciden. Por favor verifíquelas.');
            return false;
        }
        return true;
    };

    window.updateDniField = function() {
        const sel   = document.getElementById('doc_type');
        const input = document.getElementById('dni_input');
        const label = document.getElementById('dni_label');
        if (!sel || !input) return;

        if (sel.value === 'CE') {
            input.removeAttribute('maxLength');
            input.removeAttribute('pattern');
            input.title = "Ingrese su Carnet de Extranjería";
            input.placeholder = 'Ej: 123456789';
            if (label) label.innerHTML = 'N° Carnet Ext. <span class="text-danger">*</span>';
        } else {
            input.maxLength   = 8;
            input.pattern     = '[0-9]{8}';
            input.title = "Debe tener 8 dígitos numéricos";
            input.placeholder = 'Ej: 70000000';
            if (label) label.innerHTML = 'N° Documento <span class="text-danger">*</span>';
        }
    }

    window.updatePubDniField = function() {
        const sel = document.getElementById('pub_doc_type');
        const input = document.getElementById('f_dni');
        const label = document.getElementById('pub_dni_label');
        if (!sel || !input) return;
        if (sel.value === 'CE') {
            input.removeAttribute('maxLength');
            input.removeAttribute('pattern');
            input.title = "Ingrese su Carnet de Extranjería";
            if(label) label.innerHTML = 'Carnet Ext. <span class="text-danger">*</span>';
        } else {
            input.maxLength = 8;
            input.pattern = '\\d{8}';
            input.title = "Debe tener 8 dígitos numéricos";
            if(label) label.innerHTML = 'DNI <span class="text-danger">*</span>';
        }
    };

    if(document.getElementById('doc_type')) {
        updateDniField();
    }


});
</script>
<?php endif; ?>

<?php if ($pub_result && $pub_result['current_status'] === 'Pendiente'): ?>
<!-- Modal de confirmación de eliminación (Consultar Ticket Público) -->
<div class="modal fade" id="deletePublicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white bg-danger border-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i> Confirmar Cancelación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-start py-4 text-dark fs-6" style="white-space: normal;">
                ¿Estás seguro de que deseas cancelar y eliminar este ticket permanentemente? Esta acción no se puede deshacer.
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete_public_ticket">
                    <input type="hidden" name="ticket_id" value="<?php echo $pub_result['id']; ?>">
                    <input type="hidden" name="dni" value="<?php echo htmlspecialchars($pub_result['dni']); ?>">
                    <button type="submit" class="btn btn-danger fw-bold">Sí, eliminar ticket</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
