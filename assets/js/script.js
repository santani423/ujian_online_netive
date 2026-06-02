// assets/js/script.js
class UjianOnline {
    constructor() {
        this.init();
    }

    init() {
        this.initSidebarToggle();
        this.initToastNotifications();
        this.initAutoLogout();
        this.initFormValidations();
    }

    // Toggle sidebar pada mobile
    initSidebarToggle() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }

        // Tutup sidebar ketika klik di luar pada mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('active')) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }

    // Toast notifications
    initToastNotifications() {
        // Auto-hide alerts setelah 5 detik
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    }

    // Auto logout setelah 30 menit inactivity
    initAutoLogout() {
        let timeout;
        
        function resetTimer() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                if (confirm('Sesi Anda akan berakhir karena tidak ada aktivitas. Apakah Anda ingin memperpanjang sesi?')) {
                    resetTimer();
                } else {
                    window.location.href = 'logout.php';
                }
            }, 30 * 60 * 1000); // 30 menit
        }

        // Reset timer pada event user
        ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetTimer);
        });

        resetTimer();
    }

    // Form validations
    initFormValidations() {
        // Validasi form dengan class 'needs-validation'
        const forms = document.querySelectorAll('.needs-validation');
        
        forms.forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

        // Konfirmasi sebelum hapus
        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
                    e.preventDefault();
                }
            });
        });
    }

    // Format tanggal
    formatDate(dateString) {
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return new Date(dateString).toLocaleDateString('id-ID', options);
    }

    // Format waktu
    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    // Show loading
    showLoading(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="loading-spinner"></span> Memproses...';
        button.disabled = true;
        
        return () => {
            button.innerHTML = originalText;
            button.disabled = false;
        };
    }

    // AJAX helper
    async ajaxRequest(url, data = null, method = 'POST') {
        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            };

            if (data && method === 'POST') {
                options.body = new URLSearchParams(data);
            }

            const response = await fetch(url, options);
            return await response.json();
        } catch (error) {
            console.error('AJAX Error:', error);
            return { success: false, message: 'Terjadi kesalahan saat memproses permintaan' };
        }
    }
}

// Inisialisasi ketika DOM ready
document.addEventListener('DOMContentLoaded', function() {
    window.ujianOnline = new UjianOnline();
});

// Utility functions
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Teks berhasil disalin!');
    });
}

function downloadFile(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}