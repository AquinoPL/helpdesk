<?php
// admin/tickets.php
require '../includes/auth.php';
require '../config/database.php';
restrict_access(['admin']);

$success = '';
if (isset($_GET['success']) && $_GET['success'] == 'edited') {
    $success = "El ticket ha sido actualizado correctamente.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_ticket') {
    $edit_id = $_POST['ticket_id'];
    $new_title = trim($_POST['title']);
    $new_category = trim($_POST['category']);
    $new_description = trim($_POST['description']);
    $new_tech_comment = trim($_POST['tech_comment'] ?? '');
    
    $stmt = $conn->prepare("UPDATE tickets SET title = ?, category = ?::ticket_category, description = ?, tech_comment = ? WHERE id = ?");
    $stmt->execute([$new_title, $new_category, $new_description, $new_tech_comment, $edit_id]);
    
    $stC = $conn->prepare("SELECT status FROM tickets WHERE id = ?");
    $stC->execute([$edit_id]);
    $c_stat = $stC->fetchColumn() ?: 'Pendiente';
    
    $stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, changed_by, comment) VALUES (?, ?::ticket_status, ?, 'El administrador reescribió los detalles del ticket')");
    $stmtHist->execute([$edit_id, $c_stat, $_SESSION['user']['id']]);
    
    header("Location: tickets.php?success=edited");
    exit();
}

$search = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 20; // Tickets por página
$offset = ($page - 1) * $limit;

// Construir la consulta dinámicamente según filtros
$where = "1=1";
$params = [];

if ($search !== '') {
    if (is_numeric($search)) {
        $where .= " AND t.id = ?";
        $params[] = $search;
    } else {
        $where .= " AND (t.title ILIKE ? OR u.first_name ILIKE ? OR u.last_name ILIKE ?)";
        $wildcard = "%$search%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }
}

if ($status_filter !== '') {
    $where .= " AND COALESCE(t.status, 'Pendiente') = ?";
    $params[] = $status_filter;
}

// Obtener Total
$stmtCount = $conn->prepare("
    SELECT COUNT(*) 
    FROM tickets t 
    JOIN usuarios u ON t.user_id = u.id 
    WHERE $where
");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pages = ceil($total / $limit);

// Obtener Tickets
$stmtData = $conn->prepare("
    SELECT t.*, 
           u.first_name as user_fname, u.last_name as user_lname,
           tech.first_name as tech_fname, tech.last_name as tech_lname,
           COALESCE(t.status, 'Pendiente') as current_status
    FROM tickets t
    JOIN usuarios u ON t.user_id = u.id
    LEFT JOIN trabajadores tech ON t.technician_id = tech.id
    WHERE $where
    ORDER BY t.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmtData->execute($params);
$tickets = $stmtData->fetchAll(PDO::FETCH_ASSOC);

require 'includes/admin_header.php';

// Función auxiliar para mantener parámetros de búsqueda en la paginación
function getQueryStringParams($newPage) {
    $params = $_GET;
    $params['p'] = $newPage;
    return http_build_query($params);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Control Global de Tickets</h2>
        <p class="text-muted mb-0">Explora, busca y audita la totalidad de los tickets del sistema.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-auto-dismiss alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filtros de Búsqueda -->
<div class="card glass-card border-0 mb-4 fade-in">
    <div class="card-body p-4">
        <form method="GET" action="tickets.php" class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-bold text-muted small text-uppercase">Búsqueda General</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="ID de ticket, asunto o nombre de usuario..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-muted small text-uppercase">Filtrar por Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos los Estados</option>
                    <option value="Pendiente" <?php echo $status_filter == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="En camino" <?php echo $status_filter == 'En camino' ? 'selected' : ''; ?>>En camino</option>
                    <option value="En proceso" <?php echo $status_filter == 'En proceso' ? 'selected' : ''; ?>>En proceso</option>
                    <option value="Atendido" <?php echo $status_filter == 'Atendido' ? 'selected' : ''; ?>>Atendido</option>
                    <option value="Rechazado" <?php echo $status_filter == 'Rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-funnel me-1"></i> Filtrar Grilla</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla Exhaustiva -->
<div class="card glass-card border-0 fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4">Ticket ID</th>
                        <th>Creador</th>
                        <th>Técnico Asignado</th>
                        <th>Asunto / Categoría</th>
                        <th>Estado Actual</th>
                        <th>Fecha y Hora</th>
                        <th class="text-end pe-4">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tickets) > 0): ?>
                        <?php foreach ($tickets as $t): 
                            $badgeClass = 'bg-' . str_replace(' ', '-', strtolower($t['current_status']));
                            if($t['current_status'] == 'Pendiente') $badgeClass = 'bg-warning text-dark';
                            elseif($t['current_status'] == 'En camino') $badgeClass = 'bg-primary';
                            elseif($t['current_status'] == 'En proceso') $badgeClass = 'bg-info text-dark';
                            elseif($t['current_status'] == 'Atendido') $badgeClass = 'bg-success';
                            elseif($t['current_status'] == 'Rechazado') $badgeClass = 'bg-danger';
                            
                            $rowClass = '';
                            if ($t['current_status'] == 'Pendiente') $rowClass = 'table-warning';
                            elseif ($t['current_status'] == 'En camino') $rowClass = 'table-primary';
                            elseif ($t['current_status'] == 'En proceso') $rowClass = 'table-info';
                            elseif ($t['current_status'] == 'Atendido') $rowClass = 'table-success';
                            elseif ($t['current_status'] == 'Rechazado') $rowClass = 'table-danger';
                        ?>
                        <tr class="ticket-row <?php echo $rowClass; ?>" style="cursor: pointer;" onclick="window.location='../ticket_detalle.php?id=<?php echo $t['id']; ?>'">
                            <td class="ps-4"><span class="fw-bold fs-6">#<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                            <td class="fw-medium text-dark"><?php echo htmlspecialchars($t['user_fname'] . ' ' . $t['user_lname']); ?></td>
                            <td class="<?php echo $t['technician_id'] ? 'text-primary fw-medium' : 'text-muted fst-italic'; ?>">
                                <?php echo $t['technician_id'] ? htmlspecialchars($t['tech_fname'] . ' ' . $t['tech_lname']) : 'No asignado'; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($t['title']); ?>"><?php echo htmlspecialchars($t['title']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($t['category']); ?></div>
                            </td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['current_status']); ?></span></td>
                            <td class="small"><i class="bi bi-clock me-1"></i> <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                            <td class="text-end pe-4 position-relative" style="z-index: 2;">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" title="Editar detalles" onclick='event.stopPropagation(); openEditTicket(<?php echo json_encode(["id" => $t['id'], "title" => $t['title'], "category" => $t['category'], "description" => $t['description'], "tech_comment" => $t['tech_comment']]); ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="../ticket_detalle.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-dark rounded-pill px-3">Entrar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5">
                            <i class="bi bi-search text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No se encontraron tickets con los parámetros indicados.</p>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if ($pages > 1): ?>
        <div class="px-4 py-3 border-top bg-light text-center">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo getQueryStringParams($page - 1); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryStringParams($i); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo getQueryStringParams($page + 1); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/admin_footer.php'; ?>

<!-- Modal Editar Detalles del Ticket -->
<div class="modal fade" id="modalEditTicket" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header text-white bg-primary">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edición Rápida del Ticket</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" onsubmit="confirmAction(event, this, 'modificar y actualizar');">
          <div class="modal-body">
              <input type="hidden" name="action" value="edit_ticket">
              <input type="hidden" name="ticket_id" id="edit_ticket_id">
              <div class="mb-3">
                  <label class="form-label fw-bold display-block">Título del Ticket</label>
                  <input type="text" class="form-control" name="title" id="edit_title" required>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold">Categoría</label>
                  <select name="category" id="edit_category" class="form-select" required>
                      <option value="Hardware">Hardware</option>
                      <option value="Software">Software</option>
                      <option value="Redes">Redes / Conectividad</option>
                      <option value="Periféricos">Periféricos (Impresoras, etc)</option>
                      <option value="Sistemas y Cuentas">Sistemas, Accesos y Cuentas</option>
                      <option value="Otro">Otro</option>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold">Descripción / Problema</label>
                  <textarea class="form-control" name="description" id="edit_description" rows="4" required></textarea>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold">Reporte / Comentario del Técnico</label>
                  <textarea class="form-control" name="tech_comment" id="edit_tech_comment" rows="4"></textarea>
                  <small class="text-muted">Puedes editar el reporte técnico desde aquí directamente.</small>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Guardar Cambios</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditTicket(data) {
    document.getElementById('edit_ticket_id').value = data.id;
    document.getElementById('edit_title').value = data.title;
    document.getElementById('edit_category').value = data.category;
    document.getElementById('edit_description').value = data.description;
    document.getElementById('edit_tech_comment').value = data.tech_comment || '';
    new bootstrap.Modal(document.getElementById('modalEditTicket')).show();
}
</script>
