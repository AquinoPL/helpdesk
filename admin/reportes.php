<?php
require '../includes/auth.php';
require '../config/database.php';

restrict_access(['admin']);

ob_start(); // Buffer output to allow setting headers later for downloads
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

// Exportar JSON Logic para Excel en el cliente
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    // Columnas disponibles
    $available_columns = [
        'id' => 'ID', 'usuario' => 'Usuario', 'dni' => 'DNI Usuario', 'telefono' => 'Teléfono',
        'oficina' => 'Oficina', 'tecnico' => 'Técnico Asignado', 'categoria' => 'Categoría',
        'asunto' => 'Asunto', 'descripcion' => 'Descripción del Problema', 'comentario' => 'Comentario Final del Técnico',
        'estado' => 'Estado Actual', 'f_creacion' => 'Fecha Creación', 'f_atencion' => 'Fecha Atención',
        'f_cierre' => 'Fecha Cierre', 'calificacion' => 'Calificación', 'historial' => 'Historial de Estados'
    ];
    
    $selected_cols = isset($_GET['cols']) ? explode(',', $_GET['cols']) : array_keys($available_columns);
    $export_data = [];
    
    foreach ($tickets as $t) {
        $row = [];
        foreach ($selected_cols as $col_key) {
            $val = '';
            switch ($col_key) {
                case 'id': $val = $t['id']; break;
                case 'usuario': $val = trim($t['user_fname'] . ' ' . $t['user_lname']); break;
                case 'dni': $val = $t['user_dni']; break;
                case 'telefono': $val = $t['user_phone']; break;
                case 'oficina': $val = $t['office_name'] ?? 'N/A'; break;
                case 'tecnico': $val = $t['tech_fname'] ? trim($t['tech_fname'] . ' ' . $t['tech_lname']) : 'No Asignado'; break;
                case 'categoria': $val = $t['category']; break;
                case 'asunto': $val = $t['title']; break;
                case 'descripcion': $val = $t['description']; break;
                case 'comentario': $val = $t['tech_comment']; break;
                case 'estado': $val = $t['current_status']; break;
                case 'f_creacion': $val = $t['created_at']; break;
                case 'f_atencion': $val = $t['attended_at'] ?? 'N/A'; break;
                case 'f_cierre': $val = $t['closed_at'] ?? 'N/A'; break;
                case 'calificacion': $val = $t['rating'] ? $t['rating'] . ' Estrellas' : 'N/A'; break;
                case 'historial': $val = $t['history_str']; break;
            }
            $row[$col_key] = $val;
        }
        $export_data[] = $row;
    }
    
    $period_label = 'Todos';
    if ($periodo === 'semana') $period_label = 'Última Semana';
    elseif ($periodo === 'mes') {
        $partes = explode('-', $mes_seleccionado);
        $period_label = 'Mes: ' . (isset($meses_es[$partes[1]]) ? $meses_es[$partes[1]] . ' ' . $partes[0] : $mes_seleccionado);
    } elseif ($periodo === 'custom') {
        $period_label = 'Del ' . $fecha_inicio . ' al ' . $fecha_fin;
    }

    $tech_label = 'Todos los Técnicos';
    if ($tech_id) {
        foreach ($technicians as $tc) {
            if ($tc['id'] == $tech_id) {
                $tech_label = $tc['first_name'] . ' ' . $tc['last_name'];
                break;
            }
        }
    }
    
    echo json_encode([
        'columns' => $available_columns,
        'selected' => $selected_cols,
        'data' => $export_data,
        'filters' => [
            'period' => $period_label,
            'technician' => $tech_label
        ]
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    exit();
}
?>

<div class="card p-3 mt-4 mb-4 flex-row justify-content-between align-items-center"><div>
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


<!-- Modal para Exportación -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold text-primary" id="exportModalLabel"><i class="bi bi-box-arrow-up me-2"></i>Exportar Reporte</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Selecciona las columnas que deseas incluir y elige el formato de descarga.</p>
        
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
        <button type="button" class="btn btn-danger" onclick="exportPDF()"><i class="bi bi-file-earmark-pdf me-1"></i> Descargar PDF</button>
        <button type="button" class="btn btn-success" onclick="exportExcel()"><i class="bi bi-file-earmark-excel me-1"></i> Descargar Excel</button>
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
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">Detalle de Tickets</h6>
            <button type="button" class="btn btn-sm btn-primary text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="bi bi-box-arrow-up me-1"></i> Exportar Reporte
            </button>
        </div>
        <div class="card-body px-4 pt-3 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: .85rem;">
                    <thead class="text-muted" style="font-size:.75rem; text-transform:uppercase;">
                        <tr>
                            <th>ID de ticket</th>
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
</script>

<!-- Inyectar librerías necesarias -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
async function exportExcel() {
    const selected = Array.from(colChks).filter(c => c.checked).map(c => c.value);
    
    if (selected.length === 0) {
        alert("Por favor selecciona al menos una columna para exportar.");
        return;
    }

    const form = document.getElementById('filterForm');
    const url = new URL(window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    url.searchParams.append('export', 'json');
    url.searchParams.append('cols', selected.join(','));
    
    const btn = document.querySelector('button[onclick="exportExcel()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="spinner-border spinner-border-sm me-1"></i> Generando...';
    btn.disabled = true;

    try {
        const response = await fetch(url.toString());
        const json = await response.json();
        
        // Crear un nuevo libro de trabajo
        const wb = XLSX.utils.book_new();
        
        // Estilos
        const headerStyle = {
            font: { bold: true, color: { rgb: "FFFFFF" }, sz: 12 },
            fill: { fgColor: { rgb: "0C2232" } },
            alignment: { horizontal: "center", vertical: "center" },
            border: {
                top: {style: 'thin', color: {auto: 1}},
                bottom: {style: 'thin', color: {auto: 1}},
                left: {style: 'thin', color: {auto: 1}},
                right: {style: 'thin', color: {auto: 1}}
            }
        };
        const titleStyle = {
            font: { bold: true, color: { rgb: "000000" }, sz: 14 },
            alignment: { horizontal: "center", vertical: "center" }
        };
        const filterStyle = {
            font: { bold: true, color: { rgb: "000000" }, sz: 11 }
        };
        
        // Inicializar datos con el Título
        let ws_data = [];
        let row_title = new Array(json.selected.length).fill("");
        row_title[0] = "REPORTE DE TICKETS";
        ws_data.push(row_title);
        
        // Fila de Fecha
        let row_date = new Array(json.selected.length).fill("");
        row_date[0] = "Generado el: " + new Date().toLocaleDateString();
        ws_data.push(row_date);

        // Fila de Período
        let row_period = new Array(json.selected.length).fill("");
        row_period[0] = "Período: " + (json.filters && json.filters.period ? json.filters.period : "Todos");
        ws_data.push(row_period);

        // Fila de Técnico
        let row_tech = new Array(json.selected.length).fill("");
        row_tech[0] = "Técnico: " + (json.filters && json.filters.technician ? json.filters.technician : "Todos los Técnicos");
        ws_data.push(row_tech);

        // Fila vacía para separación
        ws_data.push(new Array(json.selected.length).fill(""));
        
        // Fila de Cabeceras (R=5)
        let headers = [];
        json.selected.forEach(col => {
            headers.push(json.columns[col]);
        });
        ws_data.push(headers);
        
        // Filas de Datos (R>5)
        json.data.forEach(item => {
            let row = [];
            json.selected.forEach(col => {
                row.push(item[col] !== null ? item[col] : '');
            });
            ws_data.push(row);
        });
        
        // Crear hoja
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        
        // Aplicar estilos
        const range = XLSX.utils.decode_range(ws['!ref']);
        // Merge para el título y los filtros para que ocupen al menos 3 columnas
        ws['!merges'] = [
            { s: { r: 0, c: 0 }, e: { r: 0, c: json.selected.length - 1 } },
            { s: { r: 1, c: 0 }, e: { r: 1, c: Math.max(2, json.selected.length - 1) } },
            { s: { r: 2, c: 0 }, e: { r: 2, c: Math.max(2, json.selected.length - 1) } },
            { s: { r: 3, c: 0 }, e: { r: 3, c: Math.max(2, json.selected.length - 1) } }
        ];
        
        for (let R = range.s.r; R <= range.e.r; ++R) {
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cellAddress = {c:C, r:R};
                const cellRef = XLSX.utils.encode_cell(cellAddress);
                if(!ws[cellRef]) continue;
                
                if (R === 0) {
                    ws[cellRef].s = titleStyle;
                } else if (R >= 1 && R <= 3) {
                    ws[cellRef].s = filterStyle;
                } else if (R === 5) {
                    ws[cellRef].s = headerStyle;
                } else if (R > 5) {
                    // Celdas normales con borde
                    ws[cellRef].s = {
                        border: {
                            top: {style: 'thin', color: {auto: 1}},
                            bottom: {style: 'thin', color: {auto: 1}},
                            left: {style: 'thin', color: {auto: 1}},
                            right: {style: 'thin', color: {auto: 1}}
                        }
                    };
                    
                    // Colorear estados
                    if (json.selected[C] === 'estado') {
                        let val = ws[cellRef].v;
                        if (val === 'Pendiente') ws[cellRef].s.font = { color: {rgb: "DC3545"}, bold: true };
                        if (val === 'Atendido') ws[cellRef].s.font = { color: {rgb: "198754"}, bold: true };
                    }
                }
            }
        }
        
        XLSX.utils.book_append_sheet(wb, ws, "Reporte");
        XLSX.writeFile(wb, 'reporte_tickets_' + new Date().toISOString().split('T')[0] + '.xlsx');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
        if (modal) modal.hide();
    } catch(err) {
        console.error(err);
        alert("Ocurrió un error al generar el Excel: " + err.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function exportPDF() {
    const selected = Array.from(colChks).filter(c => c.checked).map(c => c.value);
    
    if (selected.length === 0) {
        alert("Por favor selecciona al menos una columna para exportar.");
        return;
    }

    const form = document.getElementById('filterForm');
    const url = new URL(window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    url.searchParams.append('export', 'json');
    url.searchParams.append('cols', selected.join(','));
    
    const btn = document.querySelector('button[onclick="exportPDF()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="spinner-border spinner-border-sm me-1"></i> Generando...';
    btn.disabled = true;

    try {
        const response = await fetch(url.toString());
        const json = await response.json();
        
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', format: 'a4' });
        
        // Agregar Título
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text("REPORTE DE TICKETS", 14, 15);
        
        // Agregar Filtros
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text("Generado el: " + new Date().toLocaleDateString(), 14, 23);
        doc.text("Período: " + (json.filters && json.filters.period ? json.filters.period : "Todos"), 14, 28);
        doc.text("Técnico: " + (json.filters && json.filters.technician ? json.filters.technician : "Todos los Técnicos"), 14, 33);
        
        // Preparar Datos para la Tabla
        let headers = [];
        json.selected.forEach(col => {
            headers.push(json.columns[col]);
        });
        
        let data = [];
        json.data.forEach(item => {
            let row = [];
            json.selected.forEach(col => {
                row.push(item[col] !== null ? item[col] : '');
            });
            data.push(row);
        });
        
        // Generar Tabla con AutoTable
        doc.autoTable({
            startY: 38,
            head: [headers],
            body: data,
            theme: 'grid',
            headStyles: { fillColor: [12, 34, 50], fontSize: 8 },
            bodyStyles: { fontSize: 8 },
            styles: { overflow: 'linebreak', cellPadding: 2 }
        });
        
        doc.save('reporte_tickets_' + new Date().toISOString().split('T')[0] + '.pdf');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
        if (modal) modal.hide();
    } catch(err) {
        console.error(err);
        alert("Ocurrió un error al generar el PDF: " + err.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>

<?php require 'includes/admin_footer.php'; ?>
