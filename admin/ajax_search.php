<?php
require '../includes/auth.php';
require '../config/database.php';

// Solo el admin puede buscar acá
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    exit();
}

$query = $_GET['q'] ?? '';
$search = trim($query);

if (empty($search) || strlen($search) < 2) {
    exit();
}

// Búsqueda en Tickets por ID, Título
$paramsTickets = [];
$whereTickets = "1=1";

if (is_numeric($search)) {
    $whereTickets .= " AND t.id = ?";
    $paramsTickets[] = (int)$search;
} else {
    $whereTickets .= " AND t.title LIKE ?";
    $paramsTickets[] = "%$search%";
}

$stmtTickets = $conn->prepare("
    SELECT t.id, t.title, t.status, t.category 
    FROM tickets t 
    WHERE $whereTickets 
    ORDER BY t.created_at DESC 
    LIMIT 4
");
$stmtTickets->execute($paramsTickets);
$tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

// Búsqueda en Usuarios por Nombre o DNI
$stmtUsers = $conn->prepare("
    SELECT id, first_name, last_name, dni, email 
    FROM usuarios 
    WHERE first_name LIKE ? OR last_name LIKE ? OR dni LIKE ? 
    LIMIT 4
");
$wildcard = "%$search%";
$stmtUsers->execute([$wildcard, $wildcard, $wildcard]);
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Búsqueda en Trabajadores (Técnicos/Admins)
$stmtTech = $conn->prepare("
    SELECT id, first_name, last_name, dni, role 
    FROM trabajadores 
    WHERE first_name LIKE ? OR last_name LIKE ? OR dni LIKE ? 
    LIMIT 3
");
$stmtTech->execute([$wildcard, $wildcard, $wildcard]);
$techs = $stmtTech->fetchAll(PDO::FETCH_ASSOC);

// Renderizado del HTML
$hasResults = false;

if (count($tickets) > 0) {
    echo '<div class="px-3 py-2 bg-light border-bottom fw-bold text-muted small text-uppercase">Tickets</div>';
    foreach ($tickets as $t) {
        $st = $t['status'] ?: 'Pendiente';
        echo '<a href="'.BASE_URL.'/ticket_detalle.php?id='.$t['id'].'" class="list-group-item list-group-item-action border-0 px-3 py-2 d-flex justify-content-between align-items-center">';
        echo '<div class="text-truncate flex-grow-1"><i class="bi bi-ticket-perforated text-primary me-2"></i>';
        echo '<span class="fw-bold me-2">#'.htmlspecialchars($t['id']).'</span>';
        echo '<span class="text-dark small">'.htmlspecialchars($t['title']).'</span></div>';
        echo '<span class="badge bg-secondary bg-opacity-25 text-dark ms-2" style="font-size:0.7em;">'.$st.'</span>';
        echo '</a>';
    }
    $hasResults = true;
}

if (count($users) > 0) {
    echo '<div class="px-3 py-2 bg-light border-bottom border-top fw-bold text-muted small text-uppercase">Usuarios Registrados</div>';
    foreach ($users as $u) {
        echo '<a href="'.BASE_URL.'/admin/usuarios.php?search='.urlencode($u['dni']).'" class="list-group-item list-group-item-action border-0 px-3 py-2">';
        echo '<i class="bi bi-person text-success me-2"></i><span class="text-dark small">'.htmlspecialchars($u['first_name'].' '.$u['last_name']).'</span> <span class="text-muted small ms-1">('.htmlspecialchars($u['dni']).')</span>';
        echo '</a>';
    }
    $hasResults = true;
}

if (count($techs) > 0) {
    echo '<div class="px-3 py-2 bg-light border-bottom border-top fw-bold text-muted small text-uppercase">Directorio Técnico</div>';
    foreach ($techs as $u) {
        echo '<a href="'.BASE_URL.'/admin/trabajadores.php" class="list-group-item list-group-item-action border-0 px-3 py-2">';
        echo '<i class="bi bi-tools text-danger me-2"></i><span class="text-dark small">'.htmlspecialchars($u['first_name'].' '.$u['last_name']).'</span> <span class="text-muted small ms-1">('.htmlspecialchars($u['role']).')</span>';
        echo '</a>';
    }
    $hasResults = true;
}

if (!$hasResults) {
    echo '<div class="p-3 text-center text-muted small">No se encontraron resultados para "'.htmlspecialchars($search).'"</div>';
}
?>
