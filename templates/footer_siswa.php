<?php
// templates/footer_siswa.php
?>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav">
        <div class="container-fluid h-100">
            <div class="row h-100">
                <div class="col-3 h-100">
                    <a class="nav-link h-100 <?= basename($_SERVER['PHP_SELF']) == 'dashboard_siswa.php' ? 'active' : '' ?>" href="dashboard_siswa.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="col-3 h-100">
                    <a class="nav-link h-100 <?= basename($_SERVER['PHP_SELF']) == 'daftar_ujian.php' ? 'active' : '' ?>" href="daftar_ujian.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Ujian</span>
                    </a>
                </div>
                <div class="col-3 h-100">
                    <a class="nav-link h-100 <?= basename($_SERVER['PHP_SELF']) == 'riwayat_ujian.php' ? 'active' : '' ?>" href="riwayat_ujian.php">
                        <i class="fas fa-history"></i>
                        <span>Riwayat</span>
                    </a>
                </div>
                <div class="col-3 h-100">
                    <a class="nav-link h-100 <?= basename($_SERVER['PHP_SELF']) == 'profile_siswa.php' ? 'active' : '' ?>" href="profile_siswa.php">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainContent = document.querySelector('.main-content');
        const navbar = document.querySelector('.navbar-fixed-top');
        
        // Toggle sidebar on mobile
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                
                if (window.innerWidth < 768) {
                    if (sidebar.classList.contains('active')) {
                        mainContent.style.marginLeft = '220px';
                        navbar.style.left = '220px';
                    } else {
                        mainContent.style.marginLeft = '0';
                        navbar.style.left = '0';
                    }
                }
            });
        }
        
        // Auto-close sidebar when menu item is clicked (on mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    sidebar.classList.remove('active');
                    mainContent.style.marginLeft = '0';
                    navbar.style.left = '0';
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('active');
                mainContent.style.marginLeft = '220px';
                navbar.style.left = '220px';
            } else {
                mainContent.style.marginLeft = '0';
                navbar.style.left = '0';
            }
        });
        
        // Initialize on load
        if (window.innerWidth >= 768) {
            mainContent.style.marginLeft = '220px';
            navbar.style.left = '220px';
        }
        
        // Prevent body scroll when sidebar is open on mobile
        function toggleBodyScroll(enable) {
            if (window.innerWidth < 768) {
                document.body.style.overflow = enable ? 'auto' : 'hidden';
            }
        }
        
        // Observe sidebar state changes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    toggleBodyScroll(!sidebar.classList.contains('active'));
                }
            });
        });
        
        observer.observe(sidebar, { attributes: true });
    });
    </script>
</body>
</html>