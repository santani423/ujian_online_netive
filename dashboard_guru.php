<?php
// dashboard_guru.php
require_once 'config.php';

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

$guru_id = $_SESSION['guru_id'] ?? 0;
$nama_lengkap = $_SESSION['nama_lengkap'];

// Cek apakah guru ini adalah guru mapel
$is_guru_mapel = $_SESSION['is_guru_mapel'] ?? false;
$mapel_diampu = $_SESSION['mapel_diampu'] ?? [];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? [];

// Hitung statistik berdasarkan apakah guru mapel atau tidak
if ($is_guru_mapel) {
    // Statistik untuk GURU MAPEL berdasarkan mapel dan kelas yang diampu
    $mapel_ids = array_column($mapel_diampu, 'id');
    $kelas_list = array_column($kelas_diampu, 'kelas');
    
    // Buat placeholders untuk query
    $mapel_placeholders = str_repeat('?,', count($mapel_ids) - 1) . '?';
    $kelas_placeholders = str_repeat('?,', count($kelas_list) - 1) . '?';
    
    // Total soal untuk mapel yang diampu
    if (count($mapel_ids) > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE mapel_id IN ($mapel_placeholders)");
        $stmt->execute($mapel_ids);
        $total_soal = $stmt->fetchColumn();
    } else {
        $total_soal = 0;
    }
    
    // Total ujian untuk mapel dan kelas yang diampu
    if (count($mapel_ids) > 0 && count($kelas_list) > 0) {
        $params = array_merge($mapel_ids, $kelas_list);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian WHERE mapel_id IN ($mapel_placeholders) AND kelas_target IN ($kelas_placeholders)");
        $stmt->execute($params);
        $total_ujian = $stmt->fetchColumn();
    } else {
        $total_ujian = 0;
    }
    
    // Total siswa di kelas yang diampu
    if (count($kelas_list) > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE kelas IN ($kelas_placeholders)");
        $stmt->execute($kelas_list);
        $total_siswa = $stmt->fetchColumn();
    } else {
        $total_siswa = 0;
    }
    
    // Total hasil ujian untuk mapel dan kelas yang diampu
    if (count($mapel_ids) > 0 && count($kelas_list) > 0) {
        $params = array_merge($mapel_ids, $kelas_list);
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT hu.id) 
            FROM hasil_ujian hu
            JOIN ujian u ON hu.ujian_id = u.id
            JOIN siswa s ON hu.siswa_id = s.id
            WHERE u.mapel_id IN ($mapel_placeholders)
            AND s.kelas IN ($kelas_placeholders)
        ");
        $stmt->execute($params);
        $total_hasil_ujian = $stmt->fetchColumn();
    } else {
        $total_hasil_ujian = 0;
    }
} else {
    // Statistik untuk GURU BIASA (admin atau guru tanpa mapel khusus)
    // Total soal yang dibuat oleh guru ini
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE created_by = ?");
    $stmt->execute([$guru_id]);
    $total_soal = $stmt->fetchColumn();
    
    // Total ujian yang dibuat oleh guru ini
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian WHERE created_by = ?");
    $stmt->execute([$guru_id]);
    $total_ujian = $stmt->fetchColumn();
    
    // Total semua siswa
    $stmt = $pdo->query("SELECT COUNT(*) FROM siswa");
    $total_siswa = $stmt->fetchColumn();
    
    // Total hasil ujian untuk ujian yang dibuat oleh guru ini
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT hu.id) 
        FROM hasil_ujian hu
        JOIN ujian u ON hu.ujian_id = u.id
        WHERE u.created_by = ?
    ");
    $stmt->execute([$guru_id]);
    $total_hasil_ujian = $stmt->fetchColumn();
}

// Ambil aktivitas terbaru
if ($is_guru_mapel && count($mapel_ids) > 0 && count($kelas_list) > 0) {
    // Untuk guru mapel: ambil ujian untuk mapel dan kelas yang diampu
    $params = array_merge($mapel_ids, $kelas_list);
    $mapel_placeholders = str_repeat('?,', count($mapel_ids) - 1) . '?';
    $kelas_placeholders = str_repeat('?,', count($kelas_list) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT u.judul_ujian, u.created_at, mp.nama_mapel 
        FROM ujian u
        JOIN mata_pelajaran mp ON u.mapel_id = mp.id
        WHERE u.mapel_id IN ($mapel_placeholders) 
        AND u.kelas_target IN ($kelas_placeholders)
        ORDER BY u.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Untuk guru biasa: ambil ujian yang dibuatnya
    $stmt = $pdo->prepare("
        SELECT u.judul_ujian, u.created_at, mp.nama_mapel 
        FROM ujian u
        JOIN mata_pelajaran mp ON u.mapel_id = mp.id
        WHERE u.created_by = ? 
        ORDER BY u.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$guru_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$title = "Dashboard Guru";
include 'templates/header_guru.php';
?>

<!-- KONTEN UTAMA DASHBOARD GURU -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
    <div class="text-muted">
        Selamat datang, <span class="fw-bold"><?php echo htmlspecialchars($nama_lengkap); ?></span>
        <?php if ($is_guru_mapel): ?>
            <span class="badge bg-primary ms-2">Guru Mata Pelajaran</span>
        <?php endif; ?>
    </div>
</div>

<?php displayFlashMessage(); ?>

<!-- Informasi Mapel dan Kelas (jika guru mapel) -->
<?php if ($is_guru_mapel): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card stat-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Informasi Mengajar</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Mata Pelajaran:</h6>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ($mapel_diampu as $mapel): ?>
                                <span class="badge bg-primary p-2">
                                    <i class="fas fa-book me-1"></i>
                                    <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                                    <small class="ms-1">(<?php echo htmlspecialchars($mapel['kode_mapel']); ?>)</small>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Kelas:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($kelas_diampu as $kelas): ?>
                                <span class="badge bg-success p-2">
                                    <i class="fas fa-users me-1"></i>
                                    Kelas <?php echo htmlspecialchars($kelas['kelas']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Statistik -->
<div class="row">
    <?php if ($is_guru_mapel): ?>
    <!-- Statistik untuk GURU MAPEL -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-primary border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Total Soal</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_soal; ?></h3>
                        <small class="text-muted">Untuk mapel diampu</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-primary">
                            <i class="fas fa-question-circle fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-success border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Total Ujian</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_ujian; ?></h3>
                        <small class="text-muted">Untuk kelas diampu</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-success">
                            <i class="fas fa-clipboard-list fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-info border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Total Siswa</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_siswa; ?></h3>
                        <small class="text-muted">Di kelas diampu</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-info">
                            <i class="fas fa-users fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-warning border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Hasil Ujian</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_hasil_ujian; ?></h3>
                        <small class="text-muted">Telah dikerjakan</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-warning">
                            <i class="fas fa-chart-line fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Statistik untuk GURU BIASA -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-primary border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Soal Saya</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_soal; ?></h3>
                        <small class="text-muted">Dibuat oleh Anda</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-primary">
                            <i class="fas fa-question-circle fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-success border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Ujian Saya</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_ujian; ?></h3>
                        <small class="text-muted">Dibuat oleh Anda</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-success">
                            <i class="fas fa-clipboard-list fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-info border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Total Siswa</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_siswa; ?></h3>
                        <small class="text-muted">Seluruh sekolah</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-info">
                            <i class="fas fa-users fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-warning border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Hasil Ujian</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_hasil_ujian; ?></h3>
                        <small class="text-muted">Dari ujian Anda</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-warning">
                            <i class="fas fa-chart-line fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Activity dan Quick Actions -->
<div class="row">
    <div class="col-lg-8">
        <div class="card stat-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Aktivitas Terbaru</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php if (empty($activities)): ?>
                        <div class="list-group-item text-center py-4">
                            <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Belum ada aktivitas</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                        <div class="list-group-item d-flex align-items-center py-3">
                            <div class="icon-circle-sm bg-primary me-3">
                                <i class="fas fa-plus text-white"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?php echo htmlspecialchars($activity['judul_ujian']); ?></h6>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($activity['nama_mapel']); ?> • 
                                    Dibuat <?php echo timeAgo($activity['created_at']); ?>
                                </small>
                            </div>
                            <span class="badge bg-primary">Baru</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card stat-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($is_guru_mapel): ?>
                    <!-- Quick actions untuk GURU MAPEL - HANYA Input Nilai -->
                    <a href="input_nilai.php" class="btn btn-outline-primary text-start py-2">
                        <i class="fas fa-edit me-2"></i>Input Nilai
                    </a>
                    <?php else: ?>
                    <!-- Quick actions untuk GURU BIASA -->
                    <a href="kelola_soal.php" class="btn btn-outline-primary text-start py-2">
                        <i class="fas fa-plus me-2"></i>Tambah Soal
                    </a>
                    <a href="kelola_ujian.php" class="btn btn-outline-success text-start py-2">
                        <i class="fas fa-clipboard-list me-2"></i>Buat Ujian
                    </a>
                    <a href="hasil_ujian.php" class="btn btn-outline-info text-start py-2">
                        <i class="fas fa-chart-line me-2"></i>Lihat Hasil
                    </a>
                    <a href="absensi_ujian.php" class="btn btn-outline-warning text-start py-2">
                        <i class="fas fa-clipboard-check me-2"></i>Absensi Ujian
                    </a>
                    <?php endif; ?>
                    
                    <!-- Common actions -->
                    <a href="profile_guru.php" class="btn btn-outline-secondary text-start py-2">
                        <i class="fas fa-user me-2"></i>Profil Saya
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Info Sistem -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card stat-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Sistem</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%" class="text-muted">Nama Sistem</th>
                                <td>Sistem Ujian Online</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Role</th>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo $is_guru_mapel ? 'Guru Mata Pelajaran' : 'Guru'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Waktu Sistem</th>
                                <td><?php echo date('d/m/Y H:i:s'); ?> (<?php echo date_default_timezone_get(); ?>)</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%" class="text-muted">Nama Lengkap</th>
                                <td><?php echo htmlspecialchars($nama_lengkap); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Status</th>
                                <td><span class="badge bg-success">Online</span></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Versi</th>
                                <td>v1.0.0</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Fungsi untuk format waktu relatif
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $weeks   = round($seconds / 604800);
    $months  = round($seconds / 2629440);
    $years   = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "baru saja";
    } else if ($minutes <= 60) {
        if ($minutes == 1) {
            return "1 menit yang lalu";
        } else {
            return "$minutes menit yang lalu";
        }
    } else if ($hours <= 24) {
        if ($hours == 1) {
            return "1 jam yang lalu";
        } else {
            return "$hours jam yang lalu";
        }
    } else if ($days <= 7) {
        if ($days == 1) {
            return "kemarin";
        } else {
            return "$days hari yang lalu";
        }
    } else if ($weeks <= 4.3) {
        if ($weeks == 1) {
            return "1 minggu yang lalu";
        } else {
            return "$weeks minggu yang lalu";
        }
    } else if ($months <= 12) {
        if ($months == 1) {
            return "1 bulan yang lalu";
        } else {
            return "$months bulan yang lalu";
        }
    } else {
        if ($years == 1) {
            return "1 tahun yang lalu";
        } else {
            return "$years tahun yang lalu";
        }
    }
}
?>

<style>
/* Perbaikan khusus untuk dashboard */
.icon-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
}

.icon-circle-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-card {
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
}

.stat-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.stat-card .card-body h6 {
    font-size: 0.9rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .card-body h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #343a40;
}

.border-2 {
    border-width: 2px !important;
}

.list-group-item {
    border-left: none;
    border-right: none;
    transition: background-color 0.2s;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.btn-outline-primary, .btn-outline-success, .btn-outline-info, 
.btn-outline-warning, .btn-outline-secondary, .btn-outline-dark {
    border-width: 1.5px;
    transition: all 0.2s;
}

.btn-outline-primary:hover, .btn-outline-success:hover, .btn-outline-info:hover,
.btn-outline-warning:hover, .btn-outline-secondary:hover, .btn-outline-dark:hover {
    transform: translateX(5px);
}

.table-sm th, .table-sm td {
    padding: 0.75rem 0.5rem;
}

.table-sm th {
    font-weight: 600;
}

.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
}

/* Hilangkan animasi fade-in yang berlebihan */
.fade-in {
    animation: none;
}

/* Pastikan semua teks terlihat dengan jelas */
.text-dark {
    color: #212529 !important;
}

.text-muted {
    color: #6c757d !important;
}

.card-header h5 {
    color: #495057;
    font-weight: 600;
}

/* Perbaikan layout untuk profesionalisme */
.content-area {
    padding: 2rem !important;
}

.mb-4 {
    margin-bottom: 1.5rem !important;
}

.mt-4 {
    margin-top: 1.5rem !important;
}

/* Untuk badge di sidebar */
.sidebar .badge {
    font-size: 0.7em;
    padding: 0.25em 0.5em;
}
</style>

<script>
// Toggle sidebar di mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-active');
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>