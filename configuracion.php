<?php
require 'includes/auth.php';
require 'config/database.php';

restrict_access(['usuario', 'tecnico']);

$user_session = $_SESSION["user"];
$role = $user_session['role'];
$table = ($role == 'tecnico') ? 'trabajadores' : 'usuarios';

// Cargar todos los datos del usuario de forma completa usando el ID (clave primaria)
$stmtFullData = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$stmtFullData->execute([$user_session['id']]);
$fullData = $stmtFullData->fetch(PDO::FETCH_ASSOC);

if ($fullData) {
    // Sobrescribir con los datos frescos de la BD para garantizar que todo esté actualizado
    $user_session = array_merge($user_session, $fullData);
}

$error = '';
$success = '';

// Obtener oficinas
$stmtOffices = $conn->query("SELECT id, name FROM oficina ORDER BY name ASC");
$offices = $stmtOffices->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $section = $_POST['section'] ?? '';

    if ($section === 'contacto') {
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $office_id = !empty($_POST['office_id']) ? $_POST['office_id'] : null;

        if (empty($phone) || empty($office_id)) {
            $error = "Teléfono y Oficina son obligatorios.";
        } elseif (!preg_match('/^[0-9]{9}$/', $phone)) {
            $error = "El teléfono debe contener exactamente 9 dígitos numéricos.";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE $table SET phone = ?, email = ?, office_id = ? WHERE id = ?");
                $stmt->execute([$phone, $email ?: null, $office_id, $user_session['id']]);
                
                $success = "Datos de contacto actualizados correctamente.";
            } catch(PDOException $e) {
                if ($e->getCode() == 23505) {
                    $error = "El correo electrónico ya está en uso por otra cuenta.";
                } else {
                    $error = "Error al actualizar contacto: " . $e->getMessage();
                }
            }
        }
    } elseif ($section === 'seguridad') {
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($password)) {
            $error = "Debes ingresar una contraseña.";
        } elseif ($password !== $password_confirm) {
            $error = "Las contraseñas no coinciden.";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
                $stmt->execute([$password, $user_session['id']]);
                $success = "Contraseña guardada correctamente.";
            } catch (PDOException $e) {
                $error = "Error al actualizar contraseña: " . $e->getMessage();
            }
        }
    } elseif ($section === 'perfil') {
        $dni = trim($_POST['dni']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);

        if (empty($dni) || empty($first_name) || empty($last_name)) {
            $error = "El DNI, Nombres y Apellidos son obligatorios.";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE $table SET dni = ?, first_name = ?, last_name = ? WHERE id = ?");
                $stmt->execute([$dni, $first_name, $last_name, $user_session['id']]);
                $success = "Información personal actualizada correctamente.";
            } catch(PDOException $e) {
                if ($e->getCode() == 23505) {
                    $error = "El DNI ingresado ya está en uso por otra cuenta.";
                } else {
                    $error = "Error al actualizar información personal: " . $e->getMessage();
                }
            }
        }
    }

    if ($success) {
        $stmtUser = $conn->prepare("SELECT * FROM $table WHERE id = ?");
        $stmtUser->execute([$user_session['id']]);
        $updated_user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($role == 'usuario') {
            $updated_user['role'] = 'usuario';
        }
        
        $_SESSION["user"] = $updated_user;
        $user_session = $_SESSION["user"];
    }
}

require 'includes/header.php';
?>

<div class="row pt-4 mb-5">
    <div class="col-12 mb-4 d-flex align-items-center">
        <button type="button" class="btn btn-outline-secondary rounded-circle me-3 flex-shrink-0" onclick="history.back()" style="width: 40px; height: 40px; padding: 0; line-height:38px; text-align:center;" title="Volver atrás">
            <i class="bi bi-arrow-left"></i>
        </button>
        <h2 class="fw-bold mb-0 text-dark"> Configuración de la Cuenta</h2>
    </div>

    <!-- Panel Izquierdo: Menú de Pestañas Verticales -->
    <div class="col-md-4 col-lg-3 mb-4">
        <div class="card glass-card border-0 p-2 h-100 fade-in shadow-sm">
            <div class="nav flex-column nav-pills" id="settings-tabs" role="tablist" aria-orientation="vertical">
                <button class="nav-link active text-start py-3 mb-1 rounded-3 fw-medium d-flex align-items-center" id="tab-perfil-btn" data-bs-toggle="pill" data-bs-target="#panel-perfil" type="button" role="tab" aria-selected="true">
                    <i class="bi bi-person-badge fs-5 me-3"></i> Información Personal
                </button>
                <button class="nav-link text-start py-3 mb-1 rounded-3 fw-medium d-flex align-items-center" id="tab-contacto-btn" data-bs-toggle="pill" data-bs-target="#panel-contacto" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-telephone fs-5 me-3"></i> Opciones de Contacto
                </button>
                <button class="nav-link text-start py-3 mb-1 rounded-3 fw-medium d-flex align-items-center" id="tab-seguridad-btn" data-bs-toggle="pill" data-bs-target="#panel-seguridad" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-shield-lock fs-5 me-3"></i> Seguridad
                </button>
                <button class="nav-link text-start py-3 mb-1 rounded-3 fw-medium d-flex align-items-center" id="tab-tema-btn" data-bs-toggle="pill" data-bs-target="#panel-tema" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-palette fs-5 me-3"></i> Apariencia Visual
                </button>
            </div>
        </div>
    </div>

    <!-- Panel Derecho: Contenido de las Pestañas -->
    <div class="col-md-8 col-lg-9">
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

        <div class="tab-content w-100 fade-in" id="settings-tabContent">
            
            <!-- PANEL: INFORMACIÓN PERSONAL -->
            <div class="tab-pane fade show active" id="panel-perfil" role="tabpanel" aria-labelledby="tab-perfil-btn">
                <div class="card glass-card border-0 p-4 p-md-5">
                    <h4 class="fw-bold text-dark mb-4 border-bottom pb-2">Información Personal</h4>
                    <p class="text-muted mb-4">Actualice sus datos personales. El nivel de acceso (rol) no puede ser modificado desde esta sección.</p>
                    <form method="POST">
                        <input type="hidden" name="section" value="perfil">
                        <div class="row g-3">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-medium text-dark">Documento Identidad (DNI) <span class="text-danger">*</span></label>
                                <input type="text" name="dni" class="form-control" pattern="[0-9]{8}" maxlength="8" title="Debe contener exactamente 8 dígitos numéricos" required value="<?php echo htmlspecialchars($user_session['dni'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-medium text-muted">Nivel de Acceso (Rol)</label>
                                <input type="text" class="form-control bg-light text-capitalize" value="<?php echo htmlspecialchars($user_session['role']); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-medium text-dark">Nombres <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($user_session['first_name']); ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-medium text-dark">Apellidos <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($user_session['last_name']); ?>">
                            </div>
                        </div>
                        <div class="text-end mt-4 pt-4 border-top">
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PANEL: OPCIONES DE CONTACTO -->
            <div class="tab-pane fade" id="panel-contacto" role="tabpanel" aria-labelledby="tab-contacto-btn">
                <div class="card glass-card border-0 p-4 p-md-5">
                    <h4 class="fw-bold text-dark mb-4 border-bottom pb-2">Opciones de Contacto</h4>
                    <form method="POST">
                        <input type="hidden" name="section" value="contacto">
                        <div class="row g-3">
                            <div class="col-12 mb-2">
                                <label class="form-label fw-medium text-dark">Correo Electrónico</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_session['email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-medium text-dark">Teléfono Celular <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" pattern="[0-9]{9}" maxlength="9" title="Debe contener exactamente 9 dígitos numéricos" required value="<?php echo htmlspecialchars($user_session['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-medium text-dark">Oficina / Sucursal Asignada <span class="text-danger">*</span></label>
                                <select name="office_id" class="form-select" required>
                                    <option value="">Seleccione una oficina...</option>
                                    <?php foreach($offices as $of): ?>
                                        <option value="<?php echo $of['id']; ?>" <?php echo (($user_session['office_id'] ?? null) == $of['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($of['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="text-end mt-4 pt-4 border-top">
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PANEL: SEGURIDAD -->
            <div class="tab-pane fade" id="panel-seguridad" role="tabpanel" aria-labelledby="tab-seguridad-btn">
                <div class="card glass-card border-0 p-4 p-md-5">
                    <h4 class="fw-bold text-dark mb-4 border-bottom pb-2">Cambiar Contraseña</h4>
                    <form method="POST">
                        <input type="hidden" name="section" value="seguridad">
                        <div class="row g-3">
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-medium text-dark">Nueva Contraseña <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required placeholder="Escriba su nueva clave">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-medium text-dark">Confirmar Contraseña <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirm" class="form-control" required placeholder="Vuelva a escribirla">
                            </div>
                        </div>
                        <div class="text-end mt-4 pt-4 border-top">
                            <button type="submit" class="btn btn-danger px-4 py-2 fw-bold shadow-sm">
                                <i class="bi bi-shield-lock-fill me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PANEL: TEMA -->
            <div class="tab-pane fade" id="panel-tema" role="tabpanel" aria-labelledby="tab-tema-btn">
                <div class="card glass-card border-0 p-4 p-md-5">
                    <h4 class="fw-bold text-dark mb-4 border-bottom pb-2">Apariencia Visual</h4>
                    <form method="POST">
                        <input type="hidden" name="section" value="tema">
                        <div class="row g-3">
                            <div class="col-12 col-md-6 mb-2">
                                <label class="form-label fw-medium text-dark">Esquema de Colores</label>
                                <select class="form-select form-select-lg" id="themeSelector">
                                    <option value="light">☀️ Interfaz Clara</option>
                                    <option value="dark">🌙 Interfaz Oscura</option>
                                </select>
                                <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Esta preferencia se guarda localmente en su dispositivo no altera la base de datos ni es global para otros de sus ordenadores.</div>
                            </div>
                        </div>
                        <div class="text-end mt-4 pt-4 border-top">
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Alertas automáticas
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-auto-dismiss');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Theme Switcher Logic
        const themeSelector = document.getElementById('themeSelector');
        const currentTheme = localStorage.getItem('theme') || 'light';
        themeSelector.value = currentTheme;

        themeSelector.addEventListener('change', function() {
            const selectedTheme = this.value;
            localStorage.setItem('theme', selectedTheme);
            document.documentElement.setAttribute('data-bs-theme', selectedTheme);
        });

        // Sticky Tabs: Remember the last opened tab
        const tabKey = 'activeConfigTab';
        const lastTabId = localStorage.getItem(tabKey);

        if (lastTabId) {
            const tabButton = document.querySelector(`[data-bs-target="${lastTabId}"]`);
            if (tabButton) {
                const triggerEl = new bootstrap.Tab(tabButton);
                triggerEl.show();
            }
        }

        // Listens to every tab change and saves it
        var tabElements = document.querySelectorAll('button[data-bs-toggle="pill"]');
        tabElements.forEach(function(tab) {
            tab.addEventListener('shown.bs.tab', function (event) {
                localStorage.setItem(tabKey, event.target.getAttribute('data-bs-target'));
            });
        });
    });
</script>

<?php require 'includes/footer.php'; ?>
