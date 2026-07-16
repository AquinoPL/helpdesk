<?php
require '../includes/auth.php';
require '../config/database.php';

restrict_access(['admin']);

require 'includes/admin_header.php';

// Obtener técnicos para el filtro
$stmtTech = $conn->query("SELECT id, first_name, last_name FROM trabajadores WHERE role = 'tecnico' ORDER BY first_name");
$technicians = $stmtTech->fetchAll(PDO::FETCH_ASSOC);

// Obtener meses con datos
$stmtMonths = $conn->query("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as mes_val FROM tickets ORDER BY mes_val DESC");
$months = $stmtMonths->fetchAll(PDO::FETCH_ASSOC);

$meses_es = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

$current_month = date('Y-m');
// Si no hay periodo seteado, y existe el current_month en los datos, usamos current_month. 
// Si no existe, usamos el primer mes disponible, o un fallback.
$default_periodo = count($months) > 0 ? $months[0]['mes_val'] : $current_month;

// Manejo de filtros
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
$mes_seleccionado = isset($_GET['mes_seleccionado']) ? $_GET['mes_seleccionado'] : $default_periodo;
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$tech_id = isset($_GET['tech_id']) ? $_GET['tech_id'] : '';

$dateCondition = "";
$params = [];

if ($periodo === 'semana') {
    $dateCondition = "t.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
} elseif ($periodo === 'mes') {
    if (preg_match('/^\d{4}-\d{2}$/', $mes_seleccionado)) {
        $dateCondition = "DATE_FORMAT(t.created_at, '%Y-%m') = :mes";
        $params['mes'] = $mes_seleccionado;
    } else {
        $dateCondition = "DATE_FORMAT(t.created_at, '%Y-%m') = :mes";
        $params['mes'] = $default_periodo;
        $mes_seleccionado = $default_periodo;
    }
} elseif ($periodo === 'custom' && $fecha_inicio && $fecha_fin) {
    $dateCondition = "DATE(t.created_at) >= :start AND DATE(t.created_at) <= :end";
    $params['start'] = $fecha_inicio;
    $params['end'] = $fecha_fin;
} else {
    // Fallback
    $dateCondition = "DATE_FORMAT(t.created_at, '%Y-%m') = :mes";
    $params['mes'] = $default_periodo;
    $mes_seleccionado = $default_periodo;
    $periodo = 'mes';
}

$techCondition = "";
if ($tech_id) {
    $techCondition = "AND t.technician_id = :tech_id";
    $params['tech_id'] = $tech_id;
}

$queryBase = "FROM tickets t 
              JOIN usuarios u ON t.user_id = u.id 
              LEFT JOIN trabajadores tr ON t.technician_id = tr.id 
              LEFT JOIN oficina o ON t.office_id = o.id
              WHERE $dateCondition $techCondition";

// 1. Total tickets
$stmtTotal = $conn->prepare("SELECT COUNT(*) " . $queryBase);
$stmtTotal->execute($params);
$total_tickets = $stmtTotal->fetchColumn();

// 2. Estado
$stmtStatus = $conn->prepare("SELECT COALESCE(t.status, 'Pendiente') as current_status, COUNT(*) as count " . $queryBase . " GROUP BY current_status");
$stmtStatus->execute($params);
$status_counts = [];
while ($row = $stmtStatus->fetch(PDO::FETCH_ASSOC)) {
    $status_counts[$row['current_status']] = $row['count'];
}

$atendidos = $status_counts['Atendido'] ?? 0;
$pendientes = ($status_counts['Pendiente'] ?? 0) + ($status_counts['En camino'] ?? 0) + ($status_counts['En proceso'] ?? 0);

// 3. Tiempo promedio (horas)
$stmtAvgTime = $conn->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)) " . $queryBase . " AND t.status = 'Atendido' AND t.closed_at IS NOT NULL");
$stmtAvgTime->execute($params);
$avg_resolution_hours = $stmtAvgTime->fetchColumn();
$avg_resolution_hours = $avg_resolution_hours ? round($avg_resolution_hours, 1) : '-';

// 4. Ranking de Técnicos
$stmtTechRanking = $conn->prepare("SELECT tr.first_name, tr.last_name,
                                    COUNT(*) as asignados,
                                    SUM(CASE WHEN t.status = 'Atendido' THEN 1 ELSE 0 END) as resueltos,
                                    AVG(t.rating) as prom_rating
                                   " . $queryBase . " AND t.technician_id IS NOT NULL 
                                   GROUP BY tr.id ORDER BY resueltos DESC");
$stmtTechRanking->execute($params);
$tech_ranking = $stmtTechRanking->fetchAll(PDO::FETCH_ASSOC);

// 5. Distribución por Categoría
$stmtCategory = $conn->prepare("SELECT COALESCE(t.category, 'Sin Categoría') as category, COUNT(*) as count " . $queryBase . " GROUP BY category ORDER BY count DESC");
$stmtCategory->execute($params);
$cat_data = [];
$cat_labels = [];
while ($row = $stmtCategory->fetch(PDO::FETCH_ASSOC)) {
    $cat_labels[] = $row['category'];
    $cat_data[] = $row['count'];
}

// 6. Tickets data (para la tabla)
$stmtTickets = $conn->prepare("SELECT 
                                t.id, t.title, t.description, t.tech_comment, t.category, 
                                t.created_at, t.attended_at, t.closed_at, t.rating,
                                u.first_name as user_fname, u.last_name as user_lname, u.dni as user_dni, u.phone as user_phone,
                                tr.first_name as tech_fname, tr.last_name as tech_lname,
                                o.name as office_name,
                                COALESCE(t.status, 'Pendiente') as current_status,
                                (SELECT GROUP_CONCAT(CONCAT(th.status, ' (', DATE_FORMAT(th.created_at, '%d/%m %H:%i'), ')') ORDER BY th.created_at ASC SEPARATOR ' -> ') 
                                 FROM ticket_history th WHERE th.ticket_id = t.id) as history_str
                               " . $queryBase . " ORDER BY t.created_at DESC");
$stmtTickets->execute($params);
$tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

// Exportar CSV Logic
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reporte_tickets_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Columnas disponibles (Clave => Nombre a mostrar en CSV)
    $available_columns = [
        'id' => 'ID',
        'usuario' => 'Usuario',
        'dni' => 'DNI Usuario',
        'telefono' => 'Teléfono',
        'oficina' => 'Oficina',
        'tecnico' => 'Técnico Asignado',
        'categoria' => 'Categoría',
        'asunto' => 'Asunto',
        'descripcion' => 'Descripción del Problema',
        'comentario' => 'Comentario Final del Técnico',
        'estado' => 'Estado Actual',
        'f_creacion' => 'Fecha Creación',
        'f_atencion' => 'Fecha Atención',
        'f_cierre' => 'Fecha Cierre',
        'calificacion' => 'Calificación (Estrellas)',
        'historial' => 'Historial de Estados'
    ];
    
    // Leer columnas seleccionadas o usar todas por defecto si no vienen
    $selected_cols = isset($_GET['cols']) ? explode(',', $_GET['cols']) : array_keys($available_columns);
    
    // Construir headers dinámicos
    $headers = [];
    foreach ($selected_cols as $col_key) {
        if (isset($available_columns[$col_key])) {
            $headers[] = $available_columns[$col_key];
        }
    }
    fputcsv($output, $headers, ';');
    
    // Construir filas dinámicas
    foreach ($tickets as $t) {
        $row = [];
        foreach ($selected_cols as $col_key) {
            switch ($col_key) {
                case 'id': $row[] = $t['id']; break;
                case 'usuario': $row[] = $t['user_fname'] . ' ' . $t['user_lname']; break;
                case 'dni': $row[] = $t['user_dni']; break;
                case 'telefono': $row[] = $t['user_phone']; break;
                case 'oficina': $row[] = $t['office_name'] ?? 'N/A'; break;
                case 'tecnico': $row[] = $t['tech_fname'] ? $t['tech_fname'] . ' ' . $t['tech_lname'] : 'No Asignado'; break;
                case 'categoria': $row[] = $t['category']; break;
                case 'asunto': $row[] = $t['title']; break;
                case 'descripcion': $row[] = $t['description']; break;
                case 'comentario': $row[] = $t['tech_comment']; break;
                case 'estado': $row[] = $t['current_status']; break;
                case 'f_creacion': $row[] = $t['created_at']; break;
                case 'f_atencion': $row[] = $t['attended_at'] ?? 'N/A'; break;
                case 'f_cierre': $row[] = $t['closed_at'] ?? 'N/A'; break;
                case 'calificacion': $row[] = $t['rating'] ? $t['rating'] . ' Estrellas' : 'N/A'; break;
                case 'historial': $row[] = $t['history_str']; break;
            }
        }
        fputcsv($output, $row, ';');
    }
    fclose($output);
    exit();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Reportes y Estadísticas</h4>
        <div class="text-muted small">Visualiza y exporta métricas de atención de tickets.</div>
    </div>
</div>

<div class="card card-plain mb-4">
    <div class="card-body p-4">
        <form method="GET" action="reportes.php" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-2">
                <label class="form-label fw-medium text-dark small">Período</label>
                <select name="periodo" id="periodoSelect" class="form-select">
                    <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Última Semana</option>
                    <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Mes Específico</option>
                    <option value="custom" <?= $periodo === 'custom' ? 'selected' : '' ?>>Fechas Personalizadas</option>
                </select>
            </div>
            <div class="col-md-2 selector-mes <?= $periodo !== 'mes' ? 'd-none' : '' ?>">
                <label class="form-label fw-medium text-dark small">Elegir Mes</label>
                <select name="mes_seleccionado" class="form-select">
                    <?php 
                    if (count($months) == 0) {
                        echo '<option value="">No hay datos disponibles</option>';
                    } else {
                        foreach ($months as $m) {
                            $partes = explode('-', $m['mes_val']); // 2026-07
                            $mes_str = $meses_es[$partes[1]] . ' ' . $partes[0];
                            $selected = ($mes_seleccionado === $m['mes_val']) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($m['mes_val']) . '" ' . $selected . '>' . $mes_str . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2 custom-date <?= $periodo !== 'custom' ? 'd-none' : '' ?>">
                <label class="form-label fw-medium text-dark small">Desde</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
            </div>
            <div class="col-md-2 custom-date <?= $periodo !== 'custom' ? 'd-none' : '' ?>">
                <label class="form-label fw-medium text-dark small">Hasta</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium text-dark small">Técnico</label>
                <select name="tech_id" class="form-select">
                    <option value="">Todos los Técnicos</option>
                    <?php foreach($technicians as $tc): ?>
                        <option value="<?= $tc['id'] ?>" <?= $tech_id == $tc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tc['first_name'] . ' ' . $tc['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-end mb-3 gap-2">
    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#exportModal">
        <i class="bi bi-filetype-csv me-1"></i> Exportar CSV
    </button>
</div>

<!-- Modal para Exportación CSV -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold text-success" id="exportModalLabel"><i class="bi bi-filetype-csv me-2"></i>Exportar Reporte a CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Selecciona las columnas que deseas incluir en tu archivo descargable.</p>
        
        <div class="form-check mb-3 pb-2 border-bottom">
          <input class="form-check-input" type="checkbox" id="selectAllCols" checked>
          <label class="form-check-label fw-bold" for="selectAllCols">
            Seleccionar Todo
          </label>
        </div>

        <div class="row g-2" id="colsContainer">
          <!-- Datos Principales -->
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="id" id="c_id" checked><label class="form-check-label small" for="c_id">ID</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="usuario" id="c_us" checked><label class="form-check-label small" for="c_us">Usuario</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="dni" id="c_dn" checked><label class="form-check-label small" for="c_dn">DNI Usuario</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="telefono" id="c_tel" checked><label class="form-check-label small" for="c_tel">Teléfono</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="oficina" id="c_ofi" checked><label class="form-check-label small" for="c_ofi">Oficina</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="tecnico" id="c_tec" checked><label class="form-check-label small" for="c_tec">Técnico Asignado</label></div></div>
          
          <!-- Datos del Ticket -->
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="categoria" id="c_cat" checked><label class="form-check-label small" for="c_cat">Categoría</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="asunto" id="c_asu" checked><label class="form-check-label small" for="c_asu">Asunto</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="descripcion" id="c_des"><label class="form-check-label small" for="c_des">Descripción del Problema</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="comentario" id="c_com"><label class="form-check-label small" for="c_com">Comentario del Técnico</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="estado" id="c_est" checked><label class="form-check-label small" for="c_est">Estado Actual</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="historial" id="c_his"><label class="form-check-label small" for="c_his">Historial de Estados</label></div></div>
          
          <!-- Tiempos y Calificación -->
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="f_creacion" id="c_fcr" checked><label class="form-check-label small" for="c_fcr">Fecha Creación</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="f_atencion" id="c_fat" checked><label class="form-check-label small" for="c_fat">Fecha Atención</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="f_cierre" id="c_fci" checked><label class="form-check-label small" for="c_fci">Fecha Cierre</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input col-chk" type="checkbox" value="calificacion" id="c_cal" checked><label class="form-check-label small" for="c_cal">Calificación</label></div></div>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" onclick="exportCSV()"><i class="bi bi-download me-1"></i> Descargar</button>
      </div>
    </div>
  </div>
</div>

<div id="reportContainer">
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card p-3 h-100">
                <div class="text-muted small fw-medium mb-1">Total Tickets</div>
                <div class="d-flex align-items-end justify-content-between mt-auto">
                    <h3 class="mb-0 fw-bold" style="color:var(--deep)"><?= $total_tickets ?></h3>
                    <i class="bi bi-ticket fs-3 text-muted opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card p-3 h-100">
                <div class="text-muted small fw-medium mb-1">Atendidos</div>
                <div class="d-flex align-items-end justify-content-between mt-auto">
                    <h3 class="mb-0 fw-bold text-success"><?= $atendidos ?></h3>
                    <i class="bi bi-check-circle fs-3 text-success opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card p-3 h-100">
                <div class="text-muted small fw-medium mb-1">Pendientes / En Proceso</div>
                <div class="d-flex align-items-end justify-content-between mt-auto">
                    <h3 class="mb-0 fw-bold text-warning"><?= $pendientes ?></h3>
                    <i class="bi bi-clock-history fs-3 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card p-3 h-100">
                <div class="text-muted small fw-medium mb-1">Tiempo Promedio Resolución</div>
                <div class="d-flex align-items-end justify-content-between mt-auto">
                    <h3 class="mb-0 fw-bold text-info"><?= $avg_resolution_hours ?> <span class="fs-6 text-muted">hrs</span></h3>
                    <i class="bi bi-stopwatch fs-3 text-info opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- NUEVOS REPORTES -->
    <div class="row g-3 mb-4">
        <!-- Ranking Tecnicos -->
        <div class="col-md-5">
            <div class="card card-plain h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h6 class="fw-bold mb-0 text-dark">Rendimiento por Técnico</h6>
                </div>
                <div class="card-body px-4 pt-3 pb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: .85rem;">
                            <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                                <tr>
                                    <th>Técnico</th>
                                    <th class="text-center">Atendidos</th>
                                    <th class="text-center">Asignados</th>
                                    <th class="text-center">Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tech_ranking) > 0): ?>
                                    <?php foreach ($tech_ranking as $tr): ?>
                                    <tr>
                                        <td class="fw-medium text-dark"><?= htmlspecialchars($tr['first_name'] . ' ' . $tr['last_name']) ?></td>
                                        <td class="text-center fw-bold text-success"><?= $tr['resueltos'] ?></td>
                                        <td class="text-center"><?= $tr['asignados'] ?></td>
                                        <td class="text-center">
                                            <?= $tr['prom_rating'] ? round($tr['prom_rating'], 1) . ' <i class="bi bi-star-fill text-warning"></i>' : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No hay datos.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Categorias -->
        <div class="col-md-4">
            <div class="card card-plain h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h6 class="fw-bold mb-0 text-dark">Tickets por Categoría</h6>
                </div>
                <div class="card-body px-4 pt-3 pb-4 d-flex justify-content-center align-items-center">
                    <canvas id="categoryChart" style="max-height: 220px;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Estados -->
        <div class="col-md-3">
            <div class="card card-plain h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h6 class="fw-bold mb-0 text-dark">Resumen de Estados</h6>
                </div>
                <div class="card-body px-4 pt-3 pb-4 d-flex justify-content-center align-items-center">
                    <canvas id="statusChart" style="max-height: 220px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- FIN NUEVOS REPORTES -->

    <div class="card card-plain mb-4">
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
            <h6 class="fw-bold mb-0 text-dark">Detalle de Tickets</h6>
        </div>
        <div class="card-body px-4 pt-3 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: .85rem;">
                    <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                        <tr>
                            <th>Folio</th>
                            <th>Usuario</th>
                            <th>Técnico Asignado</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Creación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tickets) > 0): ?>
                            <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td class="fw-bold text-dark">#<?= htmlspecialchars($t['id']) ?></td>
                                <td><?= htmlspecialchars($t['user_fname'] . ' ' . $t['user_lname']) ?></td>
                                <td><?= $t['tech_fname'] ? htmlspecialchars($t['tech_fname'] . ' ' . $t['tech_lname']) : '<span class="text-muted">No asignado</span>' ?></td>
                                <td class="fw-medium text-dark"><?= htmlspecialchars($t['title']) ?></td>
                                <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($t['category']) ?></span></td>
                                <td><span class="badge badge-status badge-<?= str_replace(' ', '-', $t['current_status']) ?>"><?= htmlspecialchars($t['current_status']) ?></span></td>
                                <td class="text-muted"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron tickets en el período seleccionado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Datos de Estados
    const ctxStatus = document.getElementById('statusChart');
    if (ctxStatus) {
        new Chart(ctxStatus.getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Atendidos', 'Pendientes/En Proceso'],
                datasets: [{
                    data: [<?= $atendidos ?>, <?= $pendientes ?>],
                    backgroundColor: ['#198754', '#ffc107'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: {size: 11} } }
                }
            }
        });
    }

    // Datos de Categorias
    const ctxCat = document.getElementById('categoryChart');
    if (ctxCat) {
        new Chart(ctxCat.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($cat_labels) ?>,
                datasets: [{
                    data: <?= json_encode($cat_data) ?>,
                    backgroundColor: ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#fd7e14', '#20c997', '#adb5bd'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: {size: 11} } }
                }
            }
        });
    }
});
</script>
<script>
document.getElementById('periodoSelect').addEventListener('change', function() {
    const customDates = document.querySelectorAll('.custom-date');
    const selectorMes = document.querySelectorAll('.selector-mes');
    
    if (this.value === 'custom') {
        customDates.forEach(el => el.classList.remove('d-none'));
        selectorMes.forEach(el => el.classList.add('d-none'));
    } else if (this.value === 'mes') {
        customDates.forEach(el => el.classList.add('d-none'));
        selectorMes.forEach(el => el.classList.remove('d-none'));
    } else {
        customDates.forEach(el => el.classList.add('d-none'));
        selectorMes.forEach(el => el.classList.add('d-none'));
    }
});

// Lógica Seleccionar Todo
const selectAllChk = document.getElementById('selectAllCols');
const colChks = document.querySelectorAll('.col-chk');

selectAllChk.addEventListener('change', function() {
    colChks.forEach(chk => chk.checked = this.checked);
});

colChks.forEach(chk => {
    chk.addEventListener('change', function() {
        const allChecked = Array.from(colChks).every(c => c.checked);
        const noneChecked = Array.from(colChks).every(c => !c.checked);
        selectAllChk.checked = allChecked;
        selectAllChk.indeterminate = !allChecked && !noneChecked;
    });
});

function exportCSV() {
    // Recolectar columnas seleccionadas
    const selected = Array.from(colChks).filter(c => c.checked).map(c => c.value);
    
    if (selected.length === 0) {
        alert("Por favor selecciona al menos una columna para exportar.");
        return;
    }

    const form = document.getElementById('filterForm');
    const url = new URL(window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    url.searchParams.append('export', 'csv');
    url.searchParams.append('cols', selected.join(','));
    
    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
    if (modal) modal.hide();

    window.location.href = url.toString();
}
</script>

<?php require 'includes/admin_footer.php'; ?>
