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

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_logged_in) {
    $dni = trim($_POST['dni']);
    $phone = trim($_POST['phone']);
    $office_id = !empty($_POST['office_id']) ? $_POST['office_id'] : null;
    $category = $_POST['category'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $first_name = !empty($_POST['first_name']) ? trim($_POST['first_name']) : 'No Especificado';
    $last_name = !empty($_POST['last_name']) ? trim($_POST['last_name']) : 'No Especificado';

    if (empty($dni) || empty($phone) || empty($office_id) || empty($category) || empty($title) || empty($description)) {
        $error = "Por favor, completa todos los campos obligatorios.";
    } else {
        try {
            $conn->beginTransaction();
            $stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE dni = :dni");
            $stmtCheck->execute(['dni' => $dni]);
            $user_id = $stmtCheck->fetchColumn();

            if (!$user_id) {
                $stmtUser = $conn->prepare("INSERT INTO usuarios (dni, first_name, last_name, phone, office_id, password) VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
                $stmtUser->execute([$dni, $first_name, $last_name, $phone, $office_id, $dni]);
                $user_id = $stmtUser->fetchColumn();
            } else {
                $stmtUpdate = $conn->prepare("UPDATE usuarios SET phone = ?, office_id = ? WHERE id = ?");
                $stmtUpdate->execute([$phone, $office_id, $user_id]);
            }

            $stmt = $conn->prepare("INSERT INTO tickets (user_id, category, title, description, office_id) VALUES (?, ?::ticket_category, ?, ?, ?) RETURNING id");
            $stmt->execute([$user_id, $category, $title, $description, $office_id]);
            $new_ticket_id = $stmt->fetchColumn();

            $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, comment) VALUES (?, 'Pendiente', 'Ticket creado desde el portal público')");
            $stmtHist->execute([$new_ticket_id]);

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
    <div class="row mb-4 align-items-center text-center">
        <div class="col">
            <h2 class="fw-bold text-primary mb-2">Soporte Técnico Alianza</h2>
            <p class="text-muted fs-5">Genera tu ticket de atención sin necesidad de iniciar sesión.</p>
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

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status FROM tickets t WHERE t.user_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                            ?>
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold"><?php echo date('Y', strtotime($t['created_at'])) . str_pad($t['id'], 3, '0', STR_PAD_LEFT); ?></span></td>
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



<?php elseif ($is_logged_in && $user["role"] == "tecnico"): ?>
    <?php
    // TCKTS ACTIVOS TECNICO
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE technician_id = :id AND (status NOT IN ('Atendido', 'Rechazado') OR status IS NULL)");
    $stmtC->execute(['id' => $user['id']]);
    $total_ac = $stmtC->fetchColumn();
    $pages_ac = ceil($total_ac / $limit);

    $stmt = $conn->prepare("SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status, u.first_name, u.last_name FROM tickets t JOIN usuarios u ON t.user_id = u.id WHERE t.technician_id = :id AND (t.status NOT IN ('Atendido', 'Rechazado') OR t.status IS NULL) ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset_ac");
    $stmt->execute(['id' => $user['id']]);
    $tickets_ac = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                $badgeClass = 'badge-' . str_replace(' ', '-', $t['current_status']);
                            ?>
                            <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                <td class="ps-4"><span class="text-muted fw-bold"><?php echo date('Y', strtotime($t['created_at'])) . str_pad($t['id'], 3, '0', STR_PAD_LEFT); ?></span></td>
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



<?php elseif (!$is_logged_in): ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="card glass-card border-0 p-4 shadow-sm">
                <form method="POST">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-dark">DNI <span class="text-danger">*</span></label>
                            <input type="text" name="dni" class="form-control" required placeholder="Tu Documento de Identidad">
                        </div>
                        <div class="col-md-6 mt-3 mt-md-0">
                            <label class="form-label fw-medium text-dark">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" required placeholder="Nro para contactarte">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-dark">Nombres (Opcional)</label>
                            <input type="text" name="first_name" class="form-control" placeholder="Ej: Juan">
                        </div>
                        <div class="col-md-6 mt-3 mt-md-0">
                            <label class="form-label fw-medium text-dark">Apellidos (Opcional)</label>
                            <input type="text" name="last_name" class="form-control" placeholder="Ej: Perez">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-dark">Oficina <span class="text-danger">*</span></label>
                            
                            <div class="input-group">
                                <input type="hidden" name="office_id" id="public_office_id" required>
                                <input type="text" class="form-control bg-white" id="public_office_display" placeholder="Haz clic para buscar oficina..." readonly onclick="openOfficeSearch('public_office_id', 'public_office_display')" style="cursor: pointer;">
                                <button type="button" class="btn btn-outline-primary" onclick="openOfficeSearch('public_office_id', 'public_office_display')">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                            </div>

                        </div>
                        <div class="col-md-6 mt-3 mt-md-0">
                            <label class="form-label fw-medium text-dark">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required>
                                <option value="" selected disabled>Selecciona una categoría...</option>
                                <option value="Software">Software</option>
                                <option value="Hardware">Hardware</option>
                                <option value="Internet">Internet</option>
                                <option value="Instalacion">Instalación</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium text-dark">Asunto / Título <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Ej: Problema con mi equipo">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium text-dark">Descripción <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="4" required placeholder="Detalle su solicitud"></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2 fw-bold fs-5 shadow-sm">
                            <i class="bi bi-send-fill me-2"></i> Reportar Problema
                        </button>
                    </div>

                </form>
            </div>
        </div>
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
                        <input type="text" id="officeSearchInput" class="form-control form-control-lg" placeholder="Escriba el nombre de la oficina..." autocomplete="off">
                    </div>
                    <div id="officeSearchResults" class="list-group overflow-auto" style="max-height: 250px;">
                        <!-- Results will be placed here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    let targetOfficeIdInput = '';
    let targetOfficeDisplayInput = '';
    
    function openOfficeSearch(idField, displayField) {
        targetOfficeIdInput = idField;
        targetOfficeDisplayInput = displayField;
        const modal = new bootstrap.Modal(document.getElementById('officeSearchModal'));
        document.getElementById('officeSearchInput').value = '';
        document.getElementById('officeSearchResults').innerHTML = '';
        modal.show();
        setTimeout(() => document.getElementById('officeSearchInput').focus(), 500);
    }

    document.getElementById('officeSearchInput').addEventListener('input', function(e) {
        const query = e.target.value.trim();
        if (query.length < 2) {
            document.getElementById('officeSearchResults').innerHTML = '';
            return;
        }
        
        fetch('ajax_search_office.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
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
                        btn.innerHTML = `<div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold text-dark">${of.name}</h6>
                        </div>`;
                        if (of.location) btn.innerHTML += `<small class="text-muted"><i class="bi bi-geo-alt me-1"></i> ${of.location}</small>`;
                        
                        btn.onclick = () => {
                            document.getElementById(targetOfficeIdInput).value = of.id;
                            document.getElementById(targetOfficeDisplayInput).value = of.name;
                            bootstrap.Modal.getInstance(document.getElementById('officeSearchModal')).hide();
                        };
                        results.appendChild(btn);
                    });
                }
            })
            .catch(error => console.error('Error:', error));
    });
    </script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>