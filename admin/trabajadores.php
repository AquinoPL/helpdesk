<?php
// admin/trabajadores.php
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
        $role = $_POST['role'];
        $password = $_POST['password'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO trabajadores (dni, first_name, last_name, email, phone, office_id, role, password, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)");
            $stmt->execute([$dni, $first_name, $last_name, $email ?: null, $phone ?: null, $office_id, $role, $password]);
            $success = "Trabajador creado correctamente.";
        } catch(PDOException $e) {
            if ($e->getCode() == 23505) {
                $error = "Error: El DNI o Correo ya está registrado como trabajador.";
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
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 'true' : 'false';
        $password = $_POST['password'];
        
        try {
            if (!empty($password)) {
                $stmt = $conn->prepare("UPDATE trabajadores SET dni=?, first_name=?, last_name=?, email=?, phone=?, office_id=?, role=?, is_active=?, password=? WHERE id=?");
                $stmt->execute([$dni, $first_name, $last_name, $email ?: null, $phone ?: null, $office_id, $role, $is_active, $password, $id]);
            } else {
                $stmt = $conn->prepare("UPDATE trabajadores SET dni=?, first_name=?, last_name=?, email=?, phone=?, office_id=?, role=?, is_active=? WHERE id=?");
                $stmt->execute([$dni, $first_name, $last_name, $email ?: null, $phone ?: null, $office_id, $role, $is_active, $id]);
            }
            $success = "Trabajador actualizado correctamente.";
        } catch(PDOException $e) {
            if ($e->getCode() == 23505) {
                $error = "Error: El DNI o Correo ingresado pertenece a otro trabajador.";
            } else {
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $id = $_POST['id'];
        
        // Evitarnos a nosotros mismos
        if ($id == $_SESSION['user']['id']) {
            $error = "No puedes deshabilitar tu propia cuenta mientras estés en sesión.";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE trabajadores SET is_active = FALSE WHERE id=?");
                $stmt->execute([$id]);
                $success = "Trabajador deshabilitado temporalmente.";
            } catch(PDOException $e) {
                $error = "Error al suspender trabajador: " . $e->getMessage();
            }
        }
    }
}

$stmt = $conn->query("
    SELECT t.*, o.name as office_name 
    FROM trabajadores t 
    LEFT JOIN oficina o ON t.office_id = o.id 
    WHERE t.is_active = TRUE
    ORDER BY t.role ASC, t.id DESC
");
$trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_inactive = $conn->query("
    SELECT t.*, o.name as office_name 
    FROM trabajadores t 
    LEFT JOIN oficina o ON t.office_id = o.id 
    WHERE t.is_active = FALSE
    ORDER BY t.role ASC, t.id DESC
");
$trabajadores_inactive = $stmt_inactive->fetchAll(PDO::FETCH_ASSOC);

$stmtOffices = $conn->query("SELECT id, name FROM oficina WHERE is_active = TRUE ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

require 'includes/admin_header.php';
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 w-100">
        <h2 class="fw-bold mb-0">Gestión de Personal</h2>
        <div class="input-group shadow-sm" style="max-width: 300px;">
            <span class="input-group-text bg-white border-primary border-opacity-25 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="tableFilter" class="form-control border-start-0 border-primary border-opacity-25 ps-0" placeholder="Buscar trabajador...">
        </div>
    </div>
    <div class="text-md-end text-start">
        <button class="btn btn-primary shadow-sm text-nowrap px-4" data-bs-toggle="modal" data-bs-target="#modalCreate">
            <i class="bi bi-person-plus-fill me-2"></i> Añadir Trabajador
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
                        <th class="ps-4">Rol / DNI</th>
                        <th>Nombres Completos</th>
                        <th>Contacto</th>
                        <th>Oficina Asignada</th>
                        <th>Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                    <?php if(count($trabajadores) > 0): ?>
                        <?php foreach ($trabajadores as $t): ?>
                        <tr>
                            <td class="ps-4">
                                <?php if($t['role'] == 'admin'): ?>
                                    <span class="badge bg-danger mb-1"><i class="bi bi-shield-lock me-1"></i> Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-primary mb-1"><i class="bi bi-tools me-1"></i> Técnico</span>
                                <?php endif; ?>
                                <div class="text-muted fw-bold small">#<?php echo htmlspecialchars($t['dni']); ?></div>
                            </td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></td>
                            <td class="small">
                                <?php if($t['email']): ?><div><i class="bi bi-envelope text-muted me-1"></i><?php echo htmlspecialchars($t['email']); ?></div><?php endif; ?>
                                <?php if($t['phone']): ?><div><i class="bi bi-telephone text-muted me-1"></i><?php echo htmlspecialchars($t['phone']); ?></div><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($t['office_name'] ?? 'General / Sin Asignar'); ?></td>
                            <td>
                                <?php if ($t['is_active']): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-25 text-secondary">Suspendido</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='openEdit(<?php 
                                    $t_safe = $t; unset($t_safe['password']);
                                    echo json_encode($t_safe); 
                                ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($t['is_active'] && $t['id'] != $_SESSION['user']['id']): ?>
                                <form method="POST" class="d-inline" onsubmit="confirmAction(event, this, 'revocar acceso al trabajador');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Evocar acceso">
                                        <i class="bi bi-person-x"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No existen trabajadores registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-5 mb-3 d-flex align-items-center">
    <h4 class="fw-bold text-muted mb-0"><i class="bi bi-archive me-2"></i>Historial de Personal Suspendido</h4>
    <hr class="flex-grow-1 ms-3">
</div>

<div class="card card-plain border-0 opacity-75">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4">Rol / DNI</th>
                        <th>Nombres Completos</th>
                        <th>Contacto</th>
                        <th>Oficina Asignada</th>
                        <th>Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody id="dataTableInactiveBody">
                    <?php if(count($trabajadores_inactive) > 0): ?>
                        <?php foreach ($trabajadores_inactive as $t): ?>
                        <tr>
                            <td class="ps-4 opacity-75">
                                <?php if($t['role'] == 'admin'): ?>
                                    <span class="badge bg-secondary mb-1"><i class="bi bi-shield-lock me-1"></i> Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary mb-1"><i class="bi bi-tools me-1"></i> Técnico</span>
                                <?php endif; ?>
                                <div class="text-muted fw-bold small">#<?php echo htmlspecialchars($t['dni']); ?></div>
                            </td>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></td>
                            <td class="small text-muted">
                                <?php if($t['email']): ?><div><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($t['email']); ?></div><?php endif; ?>
                                <?php if($t['phone']): ?><div><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($t['phone']); ?></div><?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars($t['office_name'] ?? 'General / Sin Asignar'); ?></td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-25 text-secondary">Suspendido</span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-secondary" title="Editar / Reactivar" onclick='openEdit(<?php 
                                    $t_safe = $t; unset($t_safe['password']);
                                    echo json_encode($t_safe); 
                                ?>)'>
                                    <i class="bi bi-arrow-counterclockwise"></i> Reactivar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No existe personal suspendido.</td></tr>
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
        <h5 class="modal-title"><i class="bi bi-person-lines-fill me-2"></i>Registrar Nuevo Trabajador</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
              <input type="hidden" name="action" value="create">
              <div class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label fw-bold text-primary">Nivel de Acceso *</label>
                      <select name="role" class="form-select border-primary" required>
                          <option value="tecnico">Técnico de Soporte</option>
                          <option value="admin">Administrador General</option>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">DNI *</label>
                      <input type="text" class="form-control" name="dni" pattern="[0-9]{8}" maxlength="8" required>
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
                  <div class="col-md-12">
                      <label class="form-label fw-bold">Oficina Base Recomendada (Opcional)</label>
                      <select name="office_id" class="form-select">
                          <option value="">Ninguna / Alcance General</option>
                          <?php foreach($offices as $of): ?>
                              <option value="<?php echo $of['id']; ?>"><?php echo htmlspecialchars($of['name']); ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-12 mt-4">
                      <label class="form-label fw-bold text-danger">Contraseña Inicial de Acceso *</label>
                      <input type="password" class="form-control border-danger" name="password" required>
                  </div>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary fw-bold">Dar de Alta</button>
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
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Trabajador</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" onsubmit="confirmAction(event, this, 'modificar y actualizar');">
          <div class="modal-body">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" id="edit_id">
              <div class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label fw-bold text-info">Nivel de Acceso *</label>
                      <select name="role" id="edit_role" class="form-select" required>
                          <option value="tecnico">Técnico de Soporte</option>
                          <option value="admin">Administrador General</option>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">DNI *</label>
                      <input type="text" class="form-control" name="dni" id="edit_dni" pattern="[0-9]{8}" maxlength="8" required>
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
                  <div class="col-md-12">
                      <label class="form-label fw-bold">Oficina Base Recomendada</label>
                      <select name="office_id" id="edit_office_id" class="form-select">
                          <option value="">Ninguna / Alcance General</option>
                          <?php foreach($offices as $of): ?>
                              <option value="<?php echo $of['id']; ?>"><?php echo htmlspecialchars($of['name']); ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-6 mt-4">
                      <div class="form-check form-switch mt-3">
                          <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                          <label class="form-check-label fw-bold" for="edit_is_active">Permisos de Acceso Activos</label>
                      </div>
                  </div>
                  <div class="col-md-6 mt-4">
                      <label class="form-label fw-bold text-danger">Resetear Contraseña (Opcional)</label>
                      <input type="password" class="form-control" name="password" placeholder="Rellenar solo para sobrescribir">
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
function openEdit(t) {
    document.getElementById('edit_id').value = t.id;
    document.getElementById('edit_role').value = t.role;
    document.getElementById('edit_dni').value = t.dni;
    document.getElementById('edit_first_name').value = t.first_name;
    document.getElementById('edit_last_name').value = t.last_name;
    document.getElementById('edit_email').value = t.email || '';
    document.getElementById('edit_phone').value = t.phone || '';
    document.getElementById('edit_office_id').value = t.office_id || '';
    document.getElementById('edit_is_active').checked = t.is_active;
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
