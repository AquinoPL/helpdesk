        </div> <!-- End p-4 fade-in -->
    </div> <!-- End main -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Script para Sidebar y alertas -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
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
</body>
</html>
