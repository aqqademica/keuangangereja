                </div> <!-- End container-fluid -->
            </div> <!-- End app-content -->
        </main> <!-- End app-main -->
        
        <!-- Footer -->
        <footer class="app-footer bg-white border-top mt-auto">
            <div class="float-end d-none d-sm-inline text-muted text-sm">Versi Beta 1.0</div>
            <strong class="text-muted text-sm">Copyright &copy; <?= date('Y') ?> <a href="#" class="text-decoration-none">Sistem Informasi Gereja - Dikembangkan Untuk Skripsi Desi Gracia Simanungkalit</a>.</strong>
        </footer>
    </div> <!-- End app-wrapper -->

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js" crossorigin="anonymous"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const formatRupiahInputs = document.querySelectorAll('.format-rupiah');
        
        formatRupiahInputs.forEach(function(input) {
            const enforceCursor = function(e) {
                let val = e.target.value;
                if (val.endsWith(',00')) {
                    if (e.target.selectionStart > val.length - 3) {
                        e.target.setSelectionRange(val.length - 3, val.length - 3);
                    }
                }
            };
            
            input.addEventListener('keydown', function(e) {
                if (['ArrowLeft', 'ArrowRight', 'Backspace', 'Delete'].includes(e.key)) {
                    return; 
                }
                enforceCursor(e);
            });
            
            input.addEventListener('click', enforceCursor);
            input.addEventListener('focus', enforceCursor);
            
            input.addEventListener('input', function(e) {
                let val = e.target.value;
                let raw = val.replace(/,00$/g, '');
                let numbers = raw.replace(/[^0-9]/g, '');
                
                if (numbers === '') {
                    e.target.value = '';
                    return;
                }
                
                let formatted = new Intl.NumberFormat('en-US').format(numbers);
                e.target.value = formatted + ',00';
                
                e.target.setSelectionRange(e.target.value.length - 3, e.target.value.length - 3);
            });
        });
    });
    </script>
</body>
</html>
