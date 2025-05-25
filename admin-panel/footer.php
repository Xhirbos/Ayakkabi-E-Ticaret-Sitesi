            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // DataTables initialization
            if ($('.data-table').length > 0) {
                $('.data-table').DataTable({
                    language: {
                        url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json"
                    },
                    responsive: true,
                    pageLength: 10,
                    order: [[0, 'desc']]
                });
            }
            
            // Modal handling
            $('[data-modal-target]').on('click', function(e) {
                e.preventDefault();
                const target = $(this).attr('data-modal-target');
                $(target).addClass('show');
                $('body').css('overflow', 'hidden');
            });
            
            $('.modal-close, .modal').on('click', function(e) {
                if (e.target === this) {
                    $('.modal').removeClass('show');
                    $('body').css('overflow', 'auto');
                }
            });
            
            // Alert auto-dismiss
            $('.alert').each(function() {
                const alert = $(this);
                setTimeout(() => {
                    alert.fadeOut();
                }, 5000);
            });
            
            // Form validation
            $('form').on('submit', function(e) {
                const form = $(this);
                const requiredFields = form.find('[required]');
                let isValid = true;
                
                requiredFields.each(function() {
                    const field = $(this);
                    if (!field.val().trim()) {
                        field.addClass('error');
                        isValid = false;
                    } else {
                        field.removeClass('error');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Lütfen tüm gerekli alanları doldurun.');
                }
            });
        });
    </script>
</body>
</html> 