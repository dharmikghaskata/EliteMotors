            </div><!-- /.container-fluid -->
        </div><!-- /#page-content-wrapper -->
    </div><!-- /#wrapper -->

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom scripts -->
    <script>
        // Toggle sidebar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('menu-toggle').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('wrapper').classList.toggle('toggled');
            });

            // Show active link
            const current = location.pathname.split('/').pop();
            const menuItems = document.querySelectorAll('.list-group-item');
            menuItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href === current) {
                    item.classList.add('active');
                    item.classList.remove('text-white');
                }
            });

            // Enable tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Auto-hide alerts after 5 seconds
        window.setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
