</div> <!-- End container -->
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- TomSelect JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<!-- Custom JS -->
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
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-auto-dismiss');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Auto-refresh data every 5 seconds without reloading the page
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

                // 3. Update ticket details specific containers
                const idsToUpdate = ['ticket-status-badge', 'assigned-techs-wrapper', 'history-container'];
                idsToUpdate.forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if(el && newEl) {
                        el.innerHTML = newEl.innerHTML;
                        el.className = newEl.className; // Maintain dynamic classes like badges
                    }
                });
            })
            .catch(error => console.error('Error polling updates:', error));
        }, 5000);
    });
</script>
<!-- Botón Flotante para Instalar PWA -->
<button id="btnInstallPwa" class="btn btn-primary shadow-lg rounded-pill fade-in" style="display: none; position: fixed; bottom: 30px; right: 30px; z-index: 9999; padding: 12px 24px; font-weight: 600;">
    <i class="bi bi-phone me-2"></i>Instalar App
</button>

<!-- PWA Service Worker Registration -->
<script>const PWA_SW_URL = "<?php echo BASE_URL; ?>/service-worker.js";</script>
<script src="<?php echo BASE_URL; ?>/pwa/sw-register.js"></script>
</body>
</html>
