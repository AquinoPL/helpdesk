<?php
// admin/usuarios.php
require '../includes/auth.php';
require '../config/database.php';
restrict_access(['admin']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action == 'create') {
        $dni = trim($_POST['dni']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $office_id = !empty($_POST['office_id']) ? $_POST['office_id'] : null;
        $password = $_POST['password'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO usuarios (dni, first_name, last_name, email, phone, office_id, password, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)");
            $stmt->execute([$dni, $first_name, $last_name, $email ?: null, $phone ?: null, $office_id, $password]);
            $success = "Usuario creado correctamente.";
        } catch(PDOException $e) {
            if ($e->getCode() == 23505) {
                $error = "Error: El DNI o Correo ya está registrado.";
            } else {
                $error = "Error al crear: " . $e->getMessage();
            }
        }
    } elseif ($action == 'edit') {
        $id = $_POST['id'];
        $dni = trim($_POST['dni']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $office_id = !empty($_POST['office_id']) ? $_POST['office_id'] : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'];
        
        try {
            if (!empty($password)) {
                $stmt = $conn->prepare("UPDATE usuarios SET dni=?, first_name=?, last_name=?, email=?, phone=?, office_id=?, is_active=?, password=? WHERE id=?");
                $stmt->execute([$dni, $first_name, $last_name, $email ?: null, $phone ?: null, $office_id, $is_active, $password, $id]);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET dni=?, first_name=?, last_name=?, email=?, phone=?, office_id=?, is_active=? WHERE id=?");
                $stmt->execute([$dni, $first_name, $last_name, $email ?: null, $phone ?: null, $office_id, $is_active, $id]);
            }
            $success = "Usuario actualizado correctamente.";
        } catch(PDOException $e) {
            if ($e->getCode() == 23505) {
                $error = "Error: El DNI o Correo ingresado pertenece a otro registro.";
            } else {
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("UPDATE usuarios SET is_active = FALSE WHERE id=?");
            $stmt->execute([$id]);
            $success = "Usuario deshabilitado.";
        } catch(PDOException $e) {
            $error = "Error al suspender usuario: " . $e->getMessage();
        }
    } elseif ($action == 'hard_delete') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
            $stmt->execute([$id]);
            $success = "Usuario eliminado permanentemente.";
        } catch(PDOException $e) {
            if ($e->getCode() == 23000 || $e->getCode() == '23000') {
                $error = "No se puede eliminar el usuario porque tiene tickets asociados.";
            } else {
                $error = "Error al eliminar: " . $e->getMessage();
            }
        }
    }
}

$stmt = $conn->query("
    SELECT u.*, o.name as office_name 
    FROM usuarios u 
    LEFT JOIN oficina o ON u.office_id = o.id 
    WHERE u.is_active = TRUE
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_inactive = $conn->query("
    SELECT u.*, o.name as office_name 
    FROM usuarios u 
    LEFT JOIN oficina o ON u.office_id = o.id 
    WHERE u.is_active = FALSE
    ORDER BY u.id DESC
");
$users_inactive = $stmt_inactive->fetchAll(PDO::FETCH_ASSOC);

$stmtOffices = $conn->query("SELECT id, name FROM oficina WHERE is_active = TRUE ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

require 'includes/admin_header.php';
?>
<div class="card p-3 mt-4 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 w-100">
        <h2 class="fw-bold mb-0">Directorio de Usuarios</h2>
        <div class="input-group shadow-sm" style="max-width: 300px;">
            <span class="input-group-text bg-white border-primary border-opacity-25 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="tableFilter" class="form-control border-start-0 border-primary border-opacity-25 ps-0" placeholder="Buscar usuario (DNI o Nombre)...">
        </div>
    </div>
    <div class="text-md-end text-start">
        <button class="btn btn-primary shadow-sm text-nowrap px-4" data-bs-toggle="modal" data-bs-target="#modalCreate">
            <i class="bi bi-person-plus me-2"></i> Añadir Usuario
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

<div class="card card-plain border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">DNI</th>
                        <th>Nombres Completos</th>
                        <th>Contacto</th>
                        <th>Oficina</th>
                        <th>Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                    <?php if(count($users) > 0): ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-4 fw-medium">
                                <i class="bi bi-person-badge text-muted me-1"></i> <?php echo htmlspecialchars($u['dni']); ?>
                            </td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></td>
                            <td class="small">
                                <?php if($u['email']): ?><div><i class="bi bi-envelope text-muted me-1"></i><?php echo htmlspecialchars($u['email']); ?></div><?php endif; ?>
                                <?php if($u['phone']): ?><div><i class="bi bi-telephone text-muted me-1"></i><?php echo htmlspecialchars($u['phone']); ?></div><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['office_name'] ?? 'Sin Oficina'); ?></td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-25 text-danger">Suspendido</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <!-- Botones de Opciones -->
                                <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='openEdit(<?php 
                                    // Removemos el password del JSON por seguridad
                                    $u_safe = $u; unset($u_safe['password']);
                                    echo json_encode($u_safe); 
                                ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($u['is_active']): ?>
                                <form method="POST" class="d-inline" onsubmit="confirmAction(event, this, 'suspender al usuario');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Suspender">
                                        <i class="bi bi-person-slash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="confirmAction(event, this, 'eliminar PERMANENTEMENTE al usuario');">
                                    <input type="hidden" name="action" value="hard_delete">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar Permanentemente">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No existen usuarios activos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-5 mb-3 d-flex align-items-center">
    <h4 class="fw-bold text-muted mb-0"><i class="bi bi-archive me-2"></i>Historial de Cuentas Suspendidas</h4>
    <hr class="flex-grow-1 ms-3">
</div>

<div class="card card-plain border-0 opacity-75">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4">DNI</th>
                        <th>Nombres Completos</th>
                        <th>Contacto</th>
                        <th>Oficina</th>
                        <th>Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody id="dataTableInactiveBody">
                    <?php if(count($users_inactive) > 0): ?>
                        <?php foreach ($users_inactive as $u): ?>
                        <tr>
                            <td class="ps-4 fw-medium text-muted">
                                <i class="bi bi-person-badge me-1"></i> <?php echo htmlspecialchars($u['dni']); ?>
                            </td>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></td>
                            <td class="small text-muted">
                                <?php if($u['email']): ?><div><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($u['email']); ?></div><?php endif; ?>
                                <?php if($u['phone']): ?><div><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($u['phone']); ?></div><?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars($u['office_name'] ?? 'Sin Oficina'); ?></td>
                            <td>
                                <span class="badge bg-danger bg-opacity-25 text-danger">Suspendido</span>
                            </td>
                            <td class="text-end pe-4">
                                <!-- Botones de Opciones -->
                                <button class="btn btn-sm btn-outline-secondary" title="Editar / Reactivar" onclick='openEdit(<?php 
                                    $u_safe = $u; unset($u_safe['password']);
                                    echo json_encode($u_safe); 
                                ?>)'>
                                    <i class="bi bi-arrow-counterclockwise"></i> Reactivar
                                </button>
                                <form method="POST" class="d-inline" onsubmit="confirmAction(event, this, 'eliminar PERMANENTEMENTE al usuario');">
                                    <input type="hidden" name="action" value="hard_delete">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar Permanentemente">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No existen usuarios suspendidos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Create -->
<div class="modal fade" id="modalCreate" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header text-white bg-primary">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Registrar Nuevo Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
              <input type="hidden" name="action" value="create">
              <div class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label fw-bold">DNI *</label>
                      <input type="text" class="form-control" name="dni" pattern="[0-9]{8}" maxlength="8" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Oficina *</label>
                      <select name="office_id" class="form-select" required>
                          <option value="">Seleccione...</option>
                          <?php foreach($offices as $of): ?>
                              <option value="<?php echo $of['id']; ?>"><?php echo htmlspecialchars($of['name']); ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Nombres *</label>
                      <input type="text" class="form-control" name="first_name" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Apellidos *</label>
                      <input type="text" class="form-control" name="last_name" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Teléfono *</label>
                      <input type="text" class="form-control" name="phone" pattern="[0-9]{9}" maxlength="9" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Correo (Opcional)</label>
                      <input type="email" class="form-control" name="email">
                  </div>
                  <div class="col-12 mt-4">
                      <label class="form-label fw-bold text-primary">Contraseña Inicial *</label>
                      <input type="password" class="form-control" name="password" required>
                  </div>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary fw-bold">Crear Usuario</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header text-white bg-info">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Información de Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" onsubmit="confirmAction(event, this, 'modificar y actualizar');">
          <div class="modal-body">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" id="edit_id">
              <div class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label fw-bold">DNI *</label>
                      <input type="text" class="form-control" name="dni" id="edit_dni" pattern="[0-9]{8}" maxlength="8" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Oficina *</label>
                      <select name="office_id" id="edit_office_id" class="form-select" required>
                          <option value="">Seleccione...</option>
                          <?php foreach($offices as $of): ?>
                              <option value="<?php echo $of['id']; ?>"><?php echo htmlspecialchars($of['name']); ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Nombres *</label>
                      <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Apellidos *</label>
                      <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Teléfono *</label>
                      <input type="text" class="form-control" name="phone" id="edit_phone" pattern="[0-9]{9}" maxlength="9" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">Correo (Opcional)</label>
                      <input type="email" class="form-control" name="email" id="edit_email">
                  </div>
                  <div class="col-md-6 mt-4">
                      <div class="form-check form-switch mt-3">
                          <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                          <label class="form-check-label fw-bold" for="edit_is_active">Cuenta Activa</label>
                      </div>
                  </div>
                  <div class="col-md-6 mt-4">
                      <label class="form-label fw-bold text-danger">Reemplazar Contraseña</label>
                      <input type="password" class="form-control" name="password" placeholder="Rellenar solo si se desea resetear">
                  </div>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-info text-white fw-bold">Guardar Cambios</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(u) {
    document.getElementById('edit_id').value = u.id;
    document.getElementById('edit_dni').value = u.dni;
    document.getElementById('edit_first_name').value = u.first_name;
    document.getElementById('edit_last_name').value = u.last_name;
    document.getElementById('edit_email').value = u.email || '';
    document.getElementById('edit_phone').value = u.phone || '';
    document.getElementById('edit_office_id').value = u.office_id || '';
    document.getElementById('edit_is_active').checked = u.is_active;
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
