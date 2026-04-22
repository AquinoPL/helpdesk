<?php
// admin/oficinas.php
require '../includes/auth.php';
require '../config/database.php';
restrict_access(['admin']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action == 'create') {
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        $location_detail = trim($_POST['location_detail']);
        
        try {
            $stmt = $conn->prepare("INSERT INTO oficina (name, location, location_detail, is_active) VALUES (?, ?, ?, TRUE)");
            $stmt->execute([$name, $location, $location_detail]);
            $success = "Oficina creada correctamente.";
        } catch(PDOException $e) {
            $error = "Error al crear: " . $e->getMessage();
        }
    } elseif ($action == 'edit') {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        $location_detail = trim($_POST['location_detail']);
        $is_active = isset($_POST['is_active']) ? 'true' : 'false';
        
        try {
            $stmt = $conn->prepare("UPDATE oficina SET name=?, location=?, location_detail=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $location, $location_detail, $is_active, $id]);
            $success = "Oficina actualizada correctamente.";
        } catch(PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    } elseif ($action == 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("UPDATE oficina SET is_active = FALSE WHERE id=?");
            $stmt->execute([$id]);
            $success = "Oficina deshabilitada.";
        } catch(PDOException $e) {
            $error = "Error al eliminar: " . $e->getMessage();
        }
    }
}

$stmt = $conn->query("SELECT * FROM oficina WHERE is_active = TRUE ORDER BY id DESC");
$offices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_inactive = $conn->query("SELECT * FROM oficina WHERE is_active = FALSE ORDER BY id DESC");
$offices_inactive = $stmt_inactive->fetchAll(PDO::FETCH_ASSOC);

require 'includes/admin_header.php';
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 w-100">
        <h2 class="fw-bold mb-0">Gestión de Oficinas</h2>
        <div class="input-group shadow-sm" style="max-width: 300px;">
            <span class="input-group-text bg-white border-primary border-opacity-25 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="tableFilter" class="form-control border-start-0 border-primary border-opacity-25 ps-0" placeholder="Buscar oficina...">
        </div>
    </div>
    <div class="text-md-end text-start">
        <button class="btn btn-primary shadow-sm text-nowrap px-4" data-bs-toggle="modal" data-bs-target="#modalCreate">
            <i class="bi bi-plus-circle me-2"></i> Añadir Oficina
        </button>
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

<div class="card glass-card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nombre</th>
                        <th>Ubicación</th>
                        <th>Detalle</th>
                        <th>Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                    <?php if(count($offices) > 0): ?>
                        <?php foreach ($offices as $o): ?>
                        <tr>
                            <td class="ps-4 text-muted">#<?php echo $o['id']; ?></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($o['name']); ?></td>
                            <td><?php echo htmlspecialchars($o['location'] ?? ''); ?></td>
                            <td class="text-muted small text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($o['location_detail'] ?? ''); ?></td>
                            <td>
                                <?php if ($o['is_active']): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success">Activa</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-25 text-secondary">Inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='openEdit(<?php echo json_encode($o); ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($o['is_active']): ?>
                                <form method="POST" class="d-inline" onsubmit="confirmAction(event, this, 'deshabilitar la oficina');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Deshabilitar">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No existen oficinas registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-5 mb-3 d-flex align-items-center">
    <h4 class="fw-bold text-muted mb-0"><i class="bi bi-archive me-2"></i>Historial de Oficinas Suspendidas</h4>
    <hr class="flex-grow-1 ms-3">
</div>

<div class="card glass-card border-0 opacity-75">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nombre</th>
                        <th>Ubicación</th>
                        <th>Detalle</th>
                        <th>Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody id="dataTableInactiveBody">
                    <?php if(count($offices_inactive) > 0): ?>
                        <?php foreach ($offices_inactive as $o): ?>
                        <tr>
                            <td class="ps-4 text-muted opacity-75">#<?php echo $o['id']; ?></td>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($o['name']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($o['location'] ?? ''); ?></td>
                            <td class="text-muted small text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($o['location_detail'] ?? ''); ?></td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-25 text-secondary">Inactiva</span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-secondary" title="Editar / Reactivar" onclick='openEdit(<?php echo json_encode($o); ?>)'>
                                    <i class="bi bi-arrow-counterclockwise"></i> Reactivar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No existen oficinas suspendidas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Create -->
<div class="modal fade" id="modalCreate" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header text-white bg-primary">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nueva Oficina</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
              <input type="hidden" name="action" value="create">
              <div class="mb-3">
                  <label class="form-label fw-bold">Nombre</label>
                  <input type="text" class="form-control" name="name" required>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold">Ubicación Breve (Opcional)</label>
                  <input type="text" class="form-control" name="location">
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold">Detalles Adicionales (Opcional)</label>
                  <textarea class="form-control" name="location_detail" rows="2"></textarea>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary fw-bold">Guardar</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header text-white bg-info">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Oficina</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" onsubmit="confirmAction(event, this, 'modificar y actualizar');">
          <div class="modal-body">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" id="edit_id">
              <div class="mb-3">
                  <label class="form-label fw-bold">Nombre</label>
                  <input type="text" class="form-control" name="name" id="edit_name" required>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold">Ubicación Breve (Opcional)</label>
                  <input type="text" class="form-control" name="location" id="edit_location">
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold">Detalles Adicionales (Opcional)</label>
                  <textarea class="form-control" name="location_detail" id="edit_location_detail" rows="2"></textarea>
              </div>
              <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                  <label class="form-check-label fw-bold" for="edit_is_active">Activa en el sistema</label>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-info text-white fw-bold">Actualizar</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(o) {
    document.getElementById('edit_id').value = o.id;
    document.getElementById('edit_name').value = o.name;
    document.getElementById('edit_location').value = o.location;
    document.getElementById('edit_location_detail').value = o.location_detail;
    document.getElementById('edit_is_active').checked = o.is_active;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

// Búsqueda JS en vivo para la tabla
document.getElementById('tableFilter').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    
    // Activos
    let rowsAct = document.querySelectorAll('#dataTableBody tr');
    rowsAct.forEach(row => {
        if(row.innerText.toLowerCase().includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Inactivos
    let rowsIna = document.querySelectorAll('#dataTableInactiveBody tr');
    rowsIna.forEach(row => {
        if(row.innerText.toLowerCase().includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

<?php require 'includes/admin_footer.php'; ?>
