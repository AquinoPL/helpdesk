<?php
require '../includes/auth.php';
require '../config/database.php';

restrict_access(['usuario']);

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $user_id = $_SESSION['user']['id'];
    // Si el usuario no cambió la oficina en el form, usar la de su perfil en sesión
    $office_id = !empty($_POST['office_id'])
        ? intval($_POST['office_id'])
        : (isset($_SESSION['user']['office_id']) ? intval($_SESSION['user']['office_id']) : null);

    if (empty($title) || empty($category)) {
        $error = "Todos los campos obligatorios deben ser completados.";
    } else {
        try {
            // CALL al procedure create_ticket (crea el ticket y registra el historial inicial)
            $conn->exec("CALL create_ticket($user_id, '$category', " . $conn->quote($title) . ", " . $conn->quote($description ?? '') . ", " . ($office_id ?? 'NULL') . ", @ticket_id)");
            $row = $conn->query("SELECT @ticket_id AS ticket_id")->fetch(PDO::FETCH_ASSOC);
            $new_ticket_id = $row['ticket_id'];

            // Guardar archivos si los hay
            if (isset($_FILES['archivos']['name']) && is_array($_FILES['archivos']['name'])) {
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

            $_SESSION['success_msg'] = "Ticket #$new_ticket_id creado exitosamente.";
            header("Location: ticket.php?tab=consultar&id=" . $new_ticket_id);
            exit();

        } catch(PDOException $e) {
            $error = "Hubo un error al crear el ticket: " . $e->getMessage();
        }
    }
}

// Fetch active offices for the dropdown
$stmtOffices = $conn->query("SELECT id, name FROM oficina WHERE is_active = TRUE ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

// --- Consultar ticket ---
$consulta_result = null;
$consulta_error  = '';
if (isset($_GET['tab']) && $_GET['tab'] === 'consultar' && !empty($_GET['id'])) {
    $ticket_id_buscar = intval($_GET['id']);
    $user_id_sess = $_SESSION['user']['id'];
    $stmtCons = $conn->prepare("
        SELECT t.*, COALESCE(t.status, 'Pendiente') as current_status
        FROM tickets t
        WHERE t.id = :id AND t.user_id = :uid
    ");
    $stmtCons->execute(['id' => $ticket_id_buscar, 'uid' => $user_id_sess]);
    $consulta_result = $stmtCons->fetch(PDO::FETCH_ASSOC);
    if (!$consulta_result) {
        $consulta_error = "No se encontró el ticket #$ticket_id_buscar o no te pertenece.";
    } else {
        // Cargar mensajes del ticket
        $stmtMsgs = $conn->prepare("
            SELECT th.comment, th.status, th.created_at AS changed_at,
                   COALESCE(w.first_name, 'Sistema') as actor_name,
                   COALESCE(w.role, 'sistema') as actor_role
            FROM ticket_history th
            LEFT JOIN trabajadores w ON th.changed_by = w.id
            WHERE th.ticket_id = :id
            ORDER BY th.created_at ASC
        ");
        $stmtMsgs->execute(['id' => $ticket_id_buscar]);
        $consulta_msgs = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Historial de tickets (finalizados) ---
$limit_h = 10;
$page_h  = isset($_GET['ph']) ? max(1, (int)$_GET['ph']) : 1;
$offset_h = ($page_h - 1) * $limit_h;
$uid = $_SESSION['user']['id'];

$stmtHC = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND status IN ('Atendido','Rechazado')");
$stmtHC->execute(['id' => $uid]);
$total_h  = $stmtHC->fetchColumn();
$pages_h  = ceil($total_h / $limit_h);

$stmtH = $conn->prepare("SELECT t.*, t.status as current_status, u.first_name, u.last_name, o.name as office_name FROM tickets t JOIN usuarios u ON t.user_id = u.id LEFT JOIN oficina o ON t.office_id = o.id WHERE t.user_id = :id AND status IN ('Atendido','Rechazado') ORDER BY t.created_at DESC LIMIT $limit_h OFFSET $offset_h");
$stmtH->execute(['id' => $uid]);
$tickets_hist = $stmtH->fetchAll(PDO::FETCH_ASSOC);

// Determinar qué tab abrir al cargar la página
$active_tab = 'crear';
if (isset($_GET['tab'])) {
    $active_tab = in_array($_GET['tab'], ['crear','consultar','historial']) ? $_GET['tab'] : 'crear';
}

require '../includes/header.php';

function renderPagH($current, $total, $extra = '') {
    if ($total <= 1) return "";
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
    for ($i = 1; $i <= $total; $i++) {
        $active = ($i == $current) ? 'active' : '';
        $html .= '<li class="page-item '.$active.'"><a class="page-link" href="?tab=historial&ph='.$i.$extra.'">'.$i.'</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}
?>

<!-- Hero -->
<div class="hero-public">
    <h2 class="fw-bold mb-2">Gestión de Tickets</h2>
    <p class="mb-0">Crea un reporte, consulta el estado de uno existente o revisa tu historial.</p>
    <div class="steps-mini">
        <div class="step"><i class="bi bi-1-circle me-1"></i>Crea tu ticket</div>
        <div class="step"><i class="bi bi-2-circle me-1"></i>Recibe seguimiento</div>
        <div class="step"><i class="bi bi-3-circle me-1"></i>Consulta cuando quieras</div>
    </div>
</div>

<div style="margin-top:-2.5rem; padding-bottom: 3rem;">
    <div class="card card-plain p-4 p-md-5 mx-auto" style="max-width: 860px;">

        <?php if (!empty($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-auto-dismiss alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs-public mb-4" id="ticketTabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'crear' ? 'active' : ''; ?>"
                   data-bs-toggle="tab" href="#pane-crear" id="tab-crear">
                    <i class="bi bi-plus-circle me-1"></i>Crear Ticket
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'consultar' ? 'active' : ''; ?>"
                   data-bs-toggle="tab" href="#pane-consultar" id="tab-consultar">
                    <i class="bi bi-search me-1"></i>Consultar Ticket
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'historial' ? 'active' : ''; ?>"
                   data-bs-toggle="tab" href="#pane-historial" id="tab-historial">
                    <i class="bi bi-clock-history me-1"></i>Historial
                    <?php if ($total_h > 0): ?>
                        <span class="badge rounded-pill ms-1" style="background:var(--accent); font-size:.65rem;"><?php echo $total_h; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- ===================== TAB: CREAR ===================== -->
            <div class="tab-pane fade <?php echo $active_tab === 'crear' ? 'show active' : ''; ?>" id="pane-crear">
                <form method="POST" enctype="multipart/form-data" id="createTicketForm">

                    <div class="row g-3 mb-3">
                        <!-- Oficina -->
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Ubicación / Oficina <span class="text-danger">*</span></label>
                            <?php
                            $userOffice = $_SESSION['user']['office_id'] ?? '';
                            $userOfficeName = '';
                            foreach($offices as $of) {
                                if ($of['id'] == $userOffice) {
                                    $userOfficeName = $of['name'];
                                    break;
                                }
                            }
                            ?>
                            <div class="input-group">
                                <input type="hidden" name="office_id" id="ticket_office_id" value="<?php echo htmlspecialchars($userOffice); ?>" required>
                                <input type="text" class="form-control bg-white" id="ticket_office_display"
                                       value="<?php echo htmlspecialchars($userOfficeName); ?>"
                                       placeholder="Haz clic para buscar..." readonly
                                       onclick="openOfficeSearch('ticket_office_id','ticket_office_display')"
                                       style="cursor:pointer;">
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="openOfficeSearch('ticket_office_id','ticket_office_display')">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Categoría -->
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required>
                                <option value="" selected disabled>Selecciona una categoría...</option>
                                <option value="Software">Software</option>
                                <option value="Hardware">Hardware</option>
                                <option value="Internet">Internet</option>
                                <option value="Instalacion">Instalación</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>

                        <!-- Asunto -->
                        <div class="col-12">
                            <label class="form-label small fw-medium">Asunto <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control"
                                   placeholder="Ej: Mi computadora no enciende" required>
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label class="form-label small fw-medium">Descripción <span class="text-muted fw-normal">(Opcional)</span></label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="Detalla lo más posible el problema..."></textarea>
                        </div>
                    </div>

                    <!-- Archivos -->
                    <div class="mb-4">
                        <label class="form-label small fw-medium mb-2">Evidencias Adjuntas (Máx 5)</label>
                        <div class="card bg-light border-0 mb-3" style="border: 2px dashed var(--line) !important;">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-cloud-arrow-up-fill fs-2" style="color:var(--accent)"></i>
                                <h6 class="fw-bold mt-2">Añade fotos o documentos</h6>
                                <div class="d-flex justify-content-center gap-2 flex-wrap mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            onclick="document.getElementById('cameraInput').click()">
                                        <i class="bi bi-camera me-1"></i> Foto
                                    </button>
                                    <button type="button" class="btn btn-sm text-white"
                                            style="background:var(--accent)"
                                            onclick="document.getElementById('fileInput').click()">
                                        <i class="bi bi-folder me-1"></i> Explorar
                                    </button>
                                </div>
                                <input type="file" id="cameraInput" accept="image/*" capture="environment" class="d-none" multiple>
                                <input type="file" id="fileInput" class="d-none" multiple>
                                <input type="file" name="archivos[]" id="realInput" class="d-none" multiple>
                            </div>
                        </div>
                        <ul class="list-group list-group-flush border rounded-3 overflow-hidden"
                            id="filePreviewList" style="display:none;"></ul>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="../index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn text-white px-4" style="background:var(--deep)">
                            <i class="bi bi-send me-1"></i> Enviar reporte
                        </button>
                    </div>
                </form>

                <script>
                    const cameraInput = document.getElementById('cameraInput');
                    const fileInput   = document.getElementById('fileInput');
                    const realInput   = document.getElementById('realInput');
                    const filePreviewList = document.getElementById('filePreviewList');
                    let selectedFiles = [];
                    const MAX_FILES = 5;

                    function handleFiles(files) {
                        for (let i = 0; i < files.length; i++) {
                            if (selectedFiles.length >= MAX_FILES) {
                                alert('⚠️ Solo puedes adjuntar un máximo de ' + MAX_FILES + ' evidencias.');
                                break;
                            }
                            if (!selectedFiles.some(f => f.name === files[i].name && f.size === files[i].size)) {
                                selectedFiles.push(files[i]);
                            }
                        }
                        updateFileUI();
                    }

                    cameraInput.addEventListener('change', (e) => { handleFiles(e.target.files); e.target.value = ''; });
                    fileInput.addEventListener('change',   (e) => { handleFiles(e.target.files); e.target.value = ''; });

                    function updateFileUI() {
                        filePreviewList.innerHTML = '';
                        const dt = new DataTransfer();
                        selectedFiles.forEach((file, index) => {
                            dt.items.add(file);
                            let icon = 'bi-file-earmark';
                            if (file.type.startsWith('image/')) icon = 'bi-image text-primary';
                            else if (file.type === 'application/pdf') icon = 'bi-file-earmark-pdf text-danger';
                            const li = document.createElement('li');
                            li.className = 'list-group-item d-flex justify-content-between align-items-center bg-white py-2';
                            li.innerHTML = `
                                <div class="d-flex align-items-center text-truncate pe-3">
                                    <i class="bi ${icon} me-3 opacity-75"></i>
                                    <div class="text-truncate">
                                        <span class="d-block small fw-medium text-dark text-truncate">${file.name}</span>
                                        <small class="text-muted" style="font-size:.7rem">${(file.size/1024/1024).toFixed(2)} MB</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removeFile(${index})">
                                    <i class="bi bi-x"></i>
                                </button>`;
                            filePreviewList.appendChild(li);
                        });
                        realInput.files = dt.files;
                        filePreviewList.style.display = selectedFiles.length > 0 ? 'block' : 'none';
                    }

                    function removeFile(index) { selectedFiles.splice(index, 1); updateFileUI(); }

                    document.getElementById('createTicketForm').addEventListener('submit', function(e) {
                        if (selectedFiles.length > MAX_FILES) {
                            e.preventDefault();
                            alert('Por favor remueve archivos para cumplir el límite de ' + MAX_FILES + '.');
                        }
                    });
                </script>
            </div>

            <!-- ===================== TAB: CONSULTAR ===================== -->
            <div class="tab-pane fade <?php echo $active_tab === 'consultar' ? 'show active' : ''; ?>" id="pane-consultar">

                <!-- Formulario de búsqueda -->
                <form method="GET" action="ticket.php" class="mb-4" id="consultarForm">
                    <input type="hidden" name="tab" value="consultar">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label small fw-medium">ID de ticket</label>
                            <input type="number" name="id" class="form-control"
                                   placeholder="Ej. 202507001"
                                   value="<?php echo htmlspecialchars($_GET['id'] ?? ''); ?>"
                                   min="1" required>
                        </div>
                        <div class="col-md-4 d-flex">
                            <button class="btn w-100 text-white" style="background:var(--deep)">
                                <i class="bi bi-search me-1"></i> Ver estado
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($consulta_error)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo $consulta_error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($consulta_result): ?>
                    <?php
                    $cs = $consulta_result['current_status'];
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
                        <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                            <div>
                                <div class="small" style="color:var(--muted)">
                                    Ticket #<?php echo htmlspecialchars($consulta_result['id']); ?>
                                    · <?php echo htmlspecialchars($consulta_result['category']); ?>
                                </div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($consulta_result['title']); ?></h6>
                            </div>
                            <span class="badge <?php echo $badgeCls; ?> badge-status"><?php echo htmlspecialchars($cs); ?></span>
                        </div>
                        <div class="small mb-3" style="color:var(--muted)">
                            <i class="bi bi-clock me-1"></i>
                            Creado: <?php echo date('d M Y, H:i', strtotime($consulta_result['created_at'])); ?>
                        </div>

                        <?php if (!empty($consulta_result['description'])): ?>
                            <div class="msg msg-client mb-3">
                                <div class="fw-semibold small mb-1">Tu descripción inicial</div>
                                <?php echo nl2br(htmlspecialchars($consulta_result['description'])); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Historial de cambios / mensajes -->
                        <?php if (!empty($consulta_msgs)): ?>
                            <div class="mb-3" style="max-height:280px; overflow-y:auto;">
                                <?php foreach ($consulta_msgs as $msg): ?>
                                    <?php
                                    $isAgent = in_array($msg['actor_role'] ?? '', ['admin','tecnico']);
                                    $msgClass = $isAgent ? 'msg-agent' : 'msg-client';
                                    ?>
                                    <div class="msg <?php echo $msgClass; ?>">
                                        <div class="fw-semibold small mb-1">
                                            <?php echo htmlspecialchars($msg['actor_name']); ?>
                                            <?php if ($isAgent): ?>
                                                <span class="text-muted fw-normal">· Agente</span>
                                            <?php endif; ?>
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

                        <div class="mt-2">
                            <a href="ticket_detalle.php?id=<?php echo $consulta_result['id']; ?>"
                               class="btn btn-sm text-white" style="background:var(--accent)">
                                <i class="bi bi-arrow-right-circle me-1"></i> Ver detalle completo
                            </a>
                        </div>
                    </div>
                <?php elseif (!isset($_GET['id'])): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                        <p class="mb-0">Ingresa el número de ticket para consultar su estado.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===================== TAB: HISTORIAL ===================== -->
            <div class="tab-pane fade <?php echo $active_tab === 'historial' ? 'show active' : ''; ?>" id="pane-historial">

                <?php if (count($tickets_hist) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                                <tr>
                                    <th class="ps-2">Ticket</th>
                                    <th>Usuario / Remitente</th>
                                    <th>Oficina</th>
                                    <th>Asunto</th>
                                    <th>Categoría</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th class="text-end pe-2">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets_hist as $t):
                                    $bc = 'badge-' . str_replace(' ', '-', $t['current_status']);
                                ?>
                                <tr class="ticket-row" onclick="window.location='ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                                    <td class="ps-2"><span class="text-muted fw-bold">#<?php echo htmlspecialchars($t['id']); ?></span></td>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['office_name'] ?? 'Sin oficina'); ?></span></td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                    <td><span class="badge status-badge <?php echo $bc; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                                    <td class="text-muted small"><i class="bi bi-clock me-1"></i><?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                                    <td class="pe-2 text-end">
                                        <a href="ticket_detalle.php?id=<?php echo $t['id']; ?>"
                                           class="btn btn-sm btn-outline-secondary rounded-pill px-3">Revisar</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo renderPagH($page_h, $pages_h); ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clock-history fs-1 d-block mb-2 opacity-50"></i>
                        <p class="mb-0">No tienes tickets finalizados aún.</p>
                    </div>
                <?php endif; ?>

            </div><!-- /tab historial -->

        </div><!-- /tab-content -->
    </div><!-- /card -->
</div><!-- /wrapper -->

<!-- Office Search Modal -->
<div class="modal fade" id="officeSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" style="color:var(--deep)"><i class="bi bi-building me-2"></i>Buscar Oficina</h5>
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

function openOfficeSearch(idField, displayField) {
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
                        bootstrap.Modal.getInstance(document.getElementById('officeSearchModal')).hide();
                    };
                    results.appendChild(btn);
                });
            }
        })
        .catch(err => console.error('Error:', err));
});

// Activar tab correcto según parámetro URL al cargar
(function() {
    const urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab) {
        const tabEl = document.getElementById('tab-' + urlTab);
        if (tabEl) { new bootstrap.Tab(tabEl).show(); }
    }
})();
</script>

<?php require '../includes/footer.php'; ?>
