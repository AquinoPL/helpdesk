        </div> <!-- End p-4 fade-in -->
    </div> <!-- End main -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- TomSelect JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Script para Sidebar y alertas -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize TomSelect for searchable dropdowns
        document.querySelectorAll('.searchable-select').forEach(function(el) {
            new TomSelect(el, {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                },
                placeholder: 'Seleccione o escriba para buscar...'
            });
        });
        
        const btnToggle = document.getElementById('btnToggleSidebar');
        const sidebar = document.getElementById('sidebar');

        if(btnToggle) {
            btnToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
        }
        
        // Auto dismiss alerts
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-auto-dismiss');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });

    // Auto-refresh data every 5 seconds sin recargar la página entera (igual que en public)
    setInterval(function() {
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // 1. Update table bodies
            const tbody = document.querySelector('tbody');
            const newTbody = doc.querySelector('tbody');
            if(tbody && newTbody) {
                tbody.innerHTML = newTbody.innerHTML;
            }

            // 2. Update stat cards
            const statsCols = document.querySelectorAll('.stat-card-value');
            const newStatsCols = doc.querySelectorAll('.stat-card-value');
            if (statsCols.length > 0 && statsCols.length === newStatsCols.length) {
                statsCols.forEach((col, index) => {
                    col.innerHTML = newStatsCols[index].innerHTML;
                });
            }

            // 3. Update ticket details specific containers (por si acaso se usa el mismo footer)
            const idsToUpdate = ['ticket-status-badge', 'assigned-techs-wrapper', 'history-container'];
            idsToUpdate.forEach(id => {
                const el = document.getElementById(id);
                const newEl = doc.getElementById(id);
                if(el && newEl) {
                    el.innerHTML = newEl.innerHTML;
                    el.className = newEl.className; 
                }
            });
        })
        .catch(error => console.error('Error polling updates:', error));
    }, 5000);

    function confirmAction(e, form, actionName) {
        e.preventDefault();
        Swal.fire({
            title: 'Confirmación Requerida',
            text: '¿Deseas intentar ' + actionName + ' este registro?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, aplicar',
            cancelButtonText: 'Cancelar',
            backdrop: `rgba(0,0,0,0.4)`
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }
</script>
<!-- Botón Flotante para Instalar PWA -->
<button id="btnInstallPwa" class="btn btn-primary shadow-lg rounded-pill fade-in" style="display: none; position: fixed; bottom: 30px; left: 30px; z-index: 9999; padding: 12px 24px; font-weight: 600;">
    <i class="bi bi-phone me-2"></i>Instalar App
</button>

<!-- PWA Service Worker Registration -->
<script>const PWA_SW_URL = "<?php echo BASE_URL; ?>/service-worker.js";</script>
<script src="<?php echo BASE_URL; ?>/pwa/sw-register.js"></script>
</body>
</html>
