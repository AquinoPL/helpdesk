<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config/database.php';

if (!defined('BASE_URL')) {
    if (strpos($_SERVER['SCRIPT_NAME'], '/Soporte-Alianza') !== false) {
        define('BASE_URL', '/Soporte-Alianza');
    } else {
        define('BASE_URL', '');
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';

if (!$id || !$dni) {
    header("Location: index.php");
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT t.*, u.dni, u.first_name, u.last_name, u.email, u.phone, COALESCE(o.name, o2.name) as office_name 
        FROM tickets t
        JOIN usuarios u ON t.user_id = u.id
        LEFT JOIN oficina o ON t.office_id = o.id
        LEFT JOIN oficina o2 ON u.office_id = o2.id
        WHERE t.id = ? AND u.dni = ?
    ");
    $stmt->execute([$id, $dni]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $error = "No pudimos encontrar un ticket válido con esos datos.";
    } else {
        $stmtHistory = $conn->prepare("
            SELECT th.*, tr.first_name, tr.last_name, tr.role 
            FROM ticket_history th
            LEFT JOIN trabajadores tr ON th.changed_by = tr.id
            WHERE th.ticket_id = ?
            ORDER BY th.created_at ASC
        ");
        $stmtHistory->execute([$id]);
        $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

        // Archivos
        $stmtFiles = $conn->prepare("SELECT * FROM ticket_files WHERE ticket_id = ?");
        $stmtFiles->execute([$id]);
        $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

        // Técnico asignado (si existe)
        $tecnico_asignado = null;
        if (!empty($ticket['technician_id'])) {
            $stmtTec = $conn->prepare("
                SELECT first_name, last_name, phone
                FROM trabajadores
                WHERE id = ?
            ");
            $stmtTec->execute([$ticket['technician_id']]);
            $tecnico_asignado = $stmtTec->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $error = "Error al consultar los datos del ticket.";
}

require 'includes/header.php';

if (!isset($error)):
    $current_status = $ticket['status'] ?: 'Pendiente';
    $badgeClass = 'badge-' . str_replace(' ', '-', $current_status);
    if ($current_status == 'Pendiente') $badgeClass = 'bg-warning text-dark';
    elseif ($current_status == 'En camino') $badgeClass = 'bg-primary';
    elseif ($current_status == 'En proceso') $badgeClass = 'bg-info text-dark';
    elseif ($current_status == 'Atendido') $badgeClass = 'bg-success';
    elseif ($current_status == 'Rechazado') $badgeClass = 'bg-danger';
endif;
?>

<div class="row justify-content-center mb-5">
    <div class="col-lg-9">
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger fw-bold text-center p-4">
                <i class="bi bi-x-circle fs-3 d-block mb-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <br>
                <a href="index.php" class="btn btn-outline-danger mt-3">Volver al inicio</a>
            </div>
        <?php else: ?>
        
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
            <div class="d-flex align-items-center mb-3 mb-md-0">
                <button type="button" onclick="history.back()" class="btn btn-outline-secondary rounded-circle me-3 flex-shrink-0" style="width: 40px; height: 40px; padding: 0; line-height:38px; text-align:center;" title="Volver atrás">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div>
                    <h2 class="fw-bold mb-0 text-dark">
                        Ticket <?php echo htmlspecialchars($ticket['id']); ?>
                        <span id="ticket-status-badge" class="badge status-badge <?php echo $badgeClass; ?> ms-2 mt-n2 align-middle" style="font-size: 0.5em;"><?php echo htmlspecialchars($current_status); ?></span>
                    </h2>
                    <p class="text-muted mb-0"><i class="spinner-grow spinner-grow-sm text-success me-1" style="width: 0.8rem; height: 0.8rem;"></i> Actualizado en tiempo real</p>
                </div>
            </div>
        </div>

        <?php if (!empty($tecnico_asignado)): ?>
        <div class="card border-0 shadow-sm mb-4" style="border-left: 5px solid #2b8f9e !important; background: linear-gradient(135deg, #f0f9fb 0%, #fff 100%);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:52px; height:52px; background: linear-gradient(135deg, #12324a, #2b8f9e); color:#fff; font-size:1.3rem; font-weight:700;">
                            <?php echo strtoupper(substr($tecnico_asignado['first_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase fw-bold" style="font-size:.68rem; letter-spacing:.06em;">
                                <i class="bi bi-person-badge me-1"></i>Técnico asignado a tu ticket
                            </div>
                            <div class="fw-bold text-dark fs-5 mb-0">
                                <?php echo htmlspecialchars($tecnico_asignado['first_name'] . ' ' . $tecnico_asignado['last_name']); ?>
                            </div>
                            <?php if (!empty($tecnico_asignado['phone'])): ?>
                            <div class="text-muted small mt-1">
                                <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($tecnico_asignado['phone']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($tecnico_asignado['phone'])): ?>
                    <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $tecnico_asignado['phone']); ?>"
                       class="btn btn-success fw-semibold px-4 py-2 d-flex align-items-center gap-2"
                       style="border-radius: 2rem; font-size:.9rem;">
                        <i class="bi bi-telephone-fill fs-5"></i>
                        <span>Llamar ahora</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 bg-light">
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.70rem;">Información del Creador</small>
                        <div class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                        <div class="small text-muted"><i class="bi bi-person-vcard me-1"></i> DNI: <?php echo htmlspecialchars($ticket['dni']); ?></div>
                        <?php if($ticket['office_name']): ?>
                            <div class="small text-muted"><i class="bi bi-building me-1"></i> Oficina: <?php echo htmlspecialchars($ticket['office_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.70rem;">Fecha de Creación</small>
                        <div class="fw-medium text-dark"><i class="bi bi-calendar-check me-1"></i> <?php echo date('d M Y, h:i A', strtotime($ticket['created_at'])); ?></div>
                        <div class="mt-2 text-dark"><span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['category']); ?></span></div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4 p-md-5">
                <h4 class="fw-bold mb-3 text-primary"><?php echo htmlspecialchars($ticket['title']); ?></h4>
                <p class="text-dark bg-light p-3 rounded border" style="white-space: pre-wrap; font-size: 1.05rem;"><?php echo htmlspecialchars($ticket['description']); ?></p>
                
                <?php if (count($files) > 0): 
                    $images = [];
                    $other_files = [];
                    foreach($files as $f) {
                        $ext = pathinfo($f['file_path'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'])) {
                            $images[] = $f;
                        } else {
                            $other_files[] = $f;
                        }
                    }
                ?>
                <h6 class="fw-bold mb-3 mt-4"><i class="bi bi-paperclip"></i> Archivos Adjuntos</h6>
                
                <?php if(count($images) > 0): ?>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <?php foreach($images as $index => $img): ?>
                        <div class="image-thumbnail-container" onclick="openGallery(<?php echo $index; ?>)" style="width: 100px; height: 100px; overflow: hidden; border-radius: 8px; cursor: pointer; border: 2px solid rgba(0,0,0,0.1); transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.05);" title="Ver imagen completa">
                            <img src="<?php echo htmlspecialchars($img['file_path']); ?>" alt="Evidencia" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;" onmouseover="this.style.transform='scale(1.1)'; this.parentElement.style.borderColor='#0d6efd';" onmouseout="this.style.transform='scale(1)'; this.parentElement.style.borderColor='rgba(0,0,0,0.1)';">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if(count($other_files) > 0): ?>
                <div class="list-group mb-4">
                    <?php foreach($other_files as $f): ?>
                        <div class="list-group-item list-group-item-action d-flex align-items-center mb-2 rounded-3 border text-decoration-none">
                            <div class="bg-light p-2 rounded me-3 d-flex align-items-center justify-content-center text-primary">
                                <i class="bi bi-file-earmark-text fs-4"></i>
                            </div>
                            <div class="flex-grow-1 text-truncate">
                                <span class="d-block fw-semibold text-dark text-truncate"><?php echo htmlspecialchars(basename($f['file_path'])); ?></span>
                            </div>
                            <div class="ms-2 text-secondary">
                                <a href="<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank"><i class="bi bi-download fs-5"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
            </div>
        </div>

        <div id="tech-comment-container" style="display: <?php echo !empty($ticket['tech_comment']) ? 'block' : 'none'; ?>">
            <div class="card card-plain border-0 mb-4 fade-in border-start border-4 border-dark">
                <div class="card-body p-4 bg-white rounded-3 shadow-sm">
                    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-tools text-primary me-2"></i> Reporte del Técnico</h5>
                    <div id="tech-comment-content" class="p-3 bg-light rounded border" style="white-space: pre-wrap; font-size:1.05rem; color: #333; line-height: 1.6;"><?php echo htmlspecialchars($ticket['tech_comment'] ?? ''); ?></div>
                </div>
            </div>
        </div>

        <div class="card card-plain border-0 position-relative mb-4">
            <div class="card-body p-4 p-md-5">
                <h5 class="fw-bold mb-4 mt-1"><i class="bi bi-hourglass-split text-primary me-2"></i> Línea de Tiempo</h5>
                
                <div id="history-container" class="ps-3 border-start border-2 border-primary border-opacity-25 pb-2">
                    <?php 
                    $flow_steps = ['Pendiente', 'En camino', 'En proceso', 'Atendido'];
                    if ($current_status == 'Rechazado') {
                        $flow_steps = ['Pendiente', 'Rechazado'];
                    }

                    $historyByStatus = [];
                    foreach ($history as $h) {
                        if (!isset($historyByStatus[$h['status']])) $historyByStatus[$h['status']] = [];
                        $historyByStatus[$h['status']][] = $h;
                    }

                    $currentIndex = array_search($current_status, $flow_steps);
                    if ($currentIndex === false) $currentIndex = 0;

                    foreach ($flow_steps as $stepIdx => $stepName): 
                        $isReached = ($stepIdx <= $currentIndex);
                        $bClass = 'text-muted'; $iconClass = 'bi-circle-fill'; $opacityClass = 'opacity-50';

                        if ($isReached) {
                            $opacityClass = 'opacity-100';
                            if ($stepName == 'Pendiente') { $bClass = 'text-warning'; $iconClass='bi-exclamation-circle-fill'; }
                            if ($stepName == 'En camino') { $bClass = 'text-purple'; $iconClass='bi-person-check-fill'; }
                            if ($stepName == 'En proceso') { $bClass = 'text-info'; $iconClass='bi-play-circle-fill'; }
                            if ($stepName == 'Atendido') { $bClass = 'text-success'; $iconClass='bi-check-circle-fill'; }
                            if ($stepName == 'Rechazado') { $bClass = 'text-danger'; $iconClass='bi-x-circle-fill'; }
                        }

                        $records = isset($historyByStatus[$stepName]) ? $historyByStatus[$stepName] : [['empty' => true]];

                        foreach ($records as $h):
                    ?>
                    <div class="position-relative mb-4 <?php echo $opacityClass; ?>">
                        <div class="position-absolute bg-white text-center d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; left: -25px; top: 0px;">
                            <i class="bi <?php echo $iconClass; ?> <?php echo $bClass; ?> fs-5 bg-white"></i>
                        </div>
                        <div class="ms-1 pt-1">
                            <div class="fw-bold <?php echo $isReached ? 'text-dark' : 'text-muted'; ?> d-flex justify-content-between">
                                <?php echo htmlspecialchars($stepName); ?>
                            </div>
                            
                            <?php if (!isset($h['empty'])): ?>
                                <div class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-calendar-event me-1"></i><?php echo date('d/m/Y H:i:s', strtotime($h['created_at'])); ?></div>
                            <?php else: ?>
                                <div class="small text-muted fst-italic mt-1" style="font-size: 0.75rem;">Pendiente de alcanzar...</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                        endforeach; 
                    endforeach; 
                    ?>
                </div>

            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<!-- Modal Fullscreen Image Viewer -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content bg-dark bg-opacity-75" style="backdrop-filter: blur(10px);">
      <div class="modal-header border-0 position-absolute w-100 d-flex justify-content-between align-items-center" style="z-index: 1055;">
        <h5 class="modal-title text-white text-truncate pe-3 fw-bold flex-grow-1" id="imageViewerTitle" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.8);">Preview</h5>
        <div class="text-white d-flex align-items-center gap-3">
            <span id="galleryCounter" class="fw-bold bg-dark bg-opacity-50 px-3 py-1 rounded-pill" style="font-size: 0.9rem;"></span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0 d-flex justify-content-center align-items-center overflow-hidden position-relative" id="imageViewerBody">
        <button id="btnPrevImage" class="btn btn-dark bg-opacity-50 text-white rounded-circle position-absolute start-0 ms-3 ms-md-5 top-50 translate-middle-y shadow" style="z-index: 1055; width: 50px; height: 50px; border: 1px solid rgba(255,255,255,0.2);" onclick="prevImage(event)"><i class="bi bi-chevron-left fs-4"></i></button>
        <img src="" id="imageViewerImg" class="img-fluid" style="cursor: grab; transition: transform 0.1s ease-out; max-height: 100vh; max-width: 100vw; box-shadow: 0 0 20px rgba(0,0,0,0.5);" draggable="false">
        <button id="btnNextImage" class="btn btn-dark bg-opacity-50 text-white rounded-circle position-absolute end-0 me-3 me-md-5 top-50 translate-middle-y shadow" style="z-index: 1055; width: 50px; height: 50px; border: 1px solid rgba(255,255,255,0.2);" onclick="nextImage(event)"><i class="bi bi-chevron-right fs-4"></i></button>
      </div>
    </div>
  </div>
</div>

<script>
    // Gallery JS
    const galleryImages = [
        <?php 
        if(!empty($files)):
            foreach($files as $f):
                $ext = pathinfo($f['file_path'], PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'])):
        ?>
            { src: '<?php echo addslashes(htmlspecialchars($f['file_path'])); ?>', title: '<?php echo addslashes(htmlspecialchars(basename($f['file_path']))); ?>' },
        <?php 
                endif;
            endforeach;
        endif;
        ?>
    ];
    let currentImageIndex = 0;
    let currentScale = 1;
    let isDragging = false;
    let startX, startY, translateX = 0, translateY = 0;
    const imgElement = document.getElementById('imageViewerImg');
    const imageViewerBody = document.getElementById('imageViewerBody');
    const btnNext = document.getElementById('btnNextImage');
    const btnPrev = document.getElementById('btnPrevImage');

    function openGallery(index) {
        if(galleryImages.length === 0) return;
        currentImageIndex = index;
        loadImage(currentImageIndex);
        var myModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
        myModal.show();
    }

    function loadImage(index) {
        document.getElementById('imageViewerTitle').innerText = galleryImages[index].title;
        currentScale = 1; translateX = 0; translateY = 0; updateTransform();
        imgElement.src = galleryImages[index].src;
        document.getElementById('galleryCounter').innerText = (index + 1) + ' / ' + galleryImages.length;
        btnPrev.style.display = galleryImages.length > 1 ? 'block' : 'none';
        btnNext.style.display = galleryImages.length > 1 ? 'block' : 'none';
    }

    function nextImage(event) { if(event) event.stopPropagation(); currentImageIndex = (currentImageIndex + 1) % galleryImages.length; loadImage(currentImageIndex); }
    function prevImage(event) { if(event) event.stopPropagation(); currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length; loadImage(currentImageIndex); }

    document.addEventListener('keydown', function(event) {
        if (document.getElementById('imageViewerModal').classList.contains('show') && galleryImages.length > 1) {
            if (event.key === 'ArrowRight') nextImage();
            else if (event.key === 'ArrowLeft') prevImage();
        }
    });

    imageViewerBody.addEventListener('wheel', (e) => {
        e.preventDefault();
        currentScale += (e.deltaY < 0) ? 0.15 : -0.15;
        currentScale = Math.min(Math.max(.5, currentScale), 5);
        updateTransform();
    });

    imageViewerBody.addEventListener('mousedown', (e) => {
        if (e.target === btnNext || e.target === btnPrev || e.target.closest('button')) return;
        isDragging = true; startX = e.clientX - translateX; startY = e.clientY - translateY;
        imgElement.style.cursor = 'grabbing';
    });

    imageViewerBody.addEventListener('mousemove', (e) => {
        if (!isDragging) return; e.preventDefault();
        translateX = e.clientX - startX; translateY = e.clientY - startY; updateTransform();
    });

    imageViewerBody.addEventListener('mouseup', () => { isDragging = false; imgElement.style.cursor = 'grab'; });
    imageViewerBody.addEventListener('mouseleave', () => { isDragging = false; imgElement.style.cursor = 'grab'; });

    imgElement.addEventListener('dblclick', () => {
        currentScale = currentScale > 1 ? 1 : 2.5; translateX = 0; translateY = 0; updateTransform();
    });

    function updateTransform() { imgElement.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentScale})`; }

    // AJAX Polling
    const ticketId = <?php echo $id; ?>;
    const ticketDni = "<?php echo urlencode($dni); ?>";
    
    function pollTicketData() {
        fetch(`ajax_get_ticket.php?id=${ticketId}&dni=${ticketDni}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return; // Ignore on error or unauthorized
                
                // Update Timeline
                document.getElementById('history-container').innerHTML = data.html;
                
                // Update Main Badge
                const badge = document.getElementById('main-status-badge');
                badge.className = `badge ${data.badge_class} fs-5 py-2 px-3 shadow-sm`;
                badge.innerText = data.status;

                // Update Tech Comment Form if it exists
                const techCommentWrapper = document.getElementById('tech-comment-container');
                if (data.tech_comment.trim() !== '') {
                    techCommentWrapper.style.display = 'block';
                    document.getElementById('tech-comment-content').innerText = data.tech_comment;
                }
            })
            .catch(err => console.error("Polling error:", err));
    }

    // Refresh every 10 seconds
    setInterval(pollTicketData, 10000);
</script>

<?php require 'includes/footer.php'; ?>
