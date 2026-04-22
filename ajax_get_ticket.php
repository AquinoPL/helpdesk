<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config/database.php';

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dni_param = isset($_GET['dni']) ? trim($_GET['dni']) : '';

if (!$ticket_id) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "No ID provided"]);
    exit();
}

$is_logged_in = isset($_SESSION['user']);
$user = $is_logged_in ? $_SESSION['user'] : null;

// Validar acceso
$stmt = $conn->prepare("
    SELECT t.*, u.dni, u.first_name, u.last_name 
    FROM tickets t 
    JOIN usuarios u ON t.user_id = u.id 
    WHERE t.id = ?
");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Ticket not found"]);
    exit();
}

if (!$is_logged_in) {
    if ($dni_param !== $ticket['dni']) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Unauthorized"]);
        exit();
    }
} else {
    // Si es tipo usuario registrado normal, validar q sea suyo
    if ($user['role'] == 'usuario' && $ticket['user_id'] != $user['id']) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Unauthorized"]);
        exit();
    }
}

$current_status = $ticket['status'] ?: 'Pendiente';

// Obtener historial
$stmtHist = $conn->prepare("
    SELECT th.*, w.first_name, w.last_name, w.role
    FROM ticket_history th
    LEFT JOIN trabajadores w ON th.changed_by = w.id
    WHERE th.ticket_id = ?
    ORDER BY th.created_at ASC
");
$stmtHist->execute([$ticket_id]);
$history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

// Render HTML
$flow_steps = ['Pendiente', 'En camino', 'En proceso', 'Atendido'];
if ($current_status == 'Rechazado') {
   $flow_steps = ['Pendiente', 'Rechazado'];
}

$historyByStatus = [];
foreach ($history as $h) {
   if (!isset($historyByStatus[$h['status']])) {
       $historyByStatus[$h['status']] = [];
   }
   $historyByStatus[$h['status']][] = $h;
}

$currentIndex = array_search($current_status, $flow_steps);
if ($currentIndex === false) $currentIndex = 0;

ob_start();

$is_advanced_role = $is_logged_in && in_array($user['role'], ['admin', 'tecnico']);

foreach ($flow_steps as $stepIdx => $stepName) {
    $isReached = ($stepIdx <= $currentIndex);
    
    $bClass = 'text-muted';
    $iconClass = 'bi-circle-fill';
    $opacityClass = 'opacity-50';

    if ($isReached) {
        $opacityClass = 'opacity-100';
        if ($stepName == 'Pendiente') { $bClass = 'text-warning'; $iconClass='bi-exclamation-circle-fill'; }
        if ($stepName == 'En camino') { $bClass = 'text-purple'; $iconClass='bi-person-check-fill'; }
        if ($stepName == 'En proceso') { $bClass = 'text-info'; $iconClass='bi-play-circle-fill'; }
        if ($stepName == 'Atendido') { $bClass = 'text-success'; $iconClass='bi-check-circle-fill'; }
        if ($stepName == 'Rechazado') { $bClass = 'text-danger'; $iconClass='bi-x-circle-fill'; }
    }

    $records = isset($historyByStatus[$stepName]) ? $historyByStatus[$stepName] : [];
    if (empty($records)) {
        $records = [['empty' => true]];
    }

    foreach ($records as $h) {
        echo '<div class="position-relative mb-4 ' . $opacityClass . '">';
        echo '<div class="position-absolute bg-white text-center d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; left: -25px; top: 0px;">';
        echo '<i class="bi ' . $iconClass . ' ' . $bClass . ' fs-5 bg-white"></i>';
        echo '</div>';
        echo '<div class="ms-1 pt-1">';
        echo '<div class="fw-bold ' . ($isReached ? 'text-dark' : 'text-muted') . ' d-flex justify-content-between">';
        echo htmlspecialchars($stepName);
        echo '</div>';

        if (!isset($h['empty'])) {
            if ($is_advanced_role) {
                echo '<div class="small fw-medium text-muted mb-1"><i class="bi bi-person"></i> ' . htmlspecialchars($h['first_name'] ?: 'Sistema') . ' ';
                if(!empty($h['role'])) { echo '<span class="opacity-75 bg-light px-1 rounded mx-1">' . htmlspecialchars($h['role']) . '</span>'; }
                echo '</div>';
            }
            
            if (!empty($h['comment']) && $is_advanced_role) {
                echo '<div class="bg-light p-2 rounded-2 my-1 text-muted small border"><i class="bi bi-chat-quote-fill me-1 text-secondary opacity-50"></i> ' . htmlspecialchars($h['comment']) . '</div>';
            }
            
            echo '<div class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-calendar-event me-1"></i>' . date('d/m/Y H:i:s', strtotime($h['created_at'])) . '</div>';
        } else {
            echo '<div class="small text-muted fst-italic mt-1" style="font-size: 0.75rem;">Pendiente de alcanzar...</div>';
        }
        
        echo '</div></div>';
    } 
} 
$html_history = ob_get_clean();

$badgeClass = 'badge-' . str_replace(' ', '-', $current_status);
if ($current_status == 'Pendiente') $badgeClass = 'bg-warning text-dark';
elseif ($current_status == 'En camino') $badgeClass = 'bg-primary';
elseif ($current_status == 'En proceso') $badgeClass = 'bg-info text-dark';
elseif ($current_status == 'Atendido') $badgeClass = 'bg-success';
elseif ($current_status == 'Rechazado') $badgeClass = 'bg-danger';

$response = [
    "status" => $current_status,
    "badge_class" => $badgeClass,
    "html" => $html_history,
    "tech_comment" => $ticket['tech_comment'] ?: ''
];

header('Content-Type: application/json');
echo json_encode($response);
?>
