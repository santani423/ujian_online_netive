<?php
// dashboard.php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$role = $_SESSION['role'];
$nama_lengkap = $_SESSION['nama_lengkap'];

// Hitung statistik berdasarkan role
if ($role == 'guru') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa");
    $stmt->execute();
    $total_siswa = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian WHERE created_by = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_ujian = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE created_by = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_soal = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mata_pelajaran");
    $stmt->execute();
    $total_mapel = $stmt->fetchColumn();
} else {
    $current_time = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian WHERE status = 'published' AND waktu_mulai <= ? AND waktu_selesai >= ? AND kelas_target = (SELECT kelas FROM siswa WHERE user_id = ?)");
    $stmt->execute([$current_time, $current_time, $_SESSION['user_id']]);
    $total_ujian = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_ujian WHERE siswa_id = (SELECT id FROM siswa WHERE user_id = ?)");
    $stmt->execute([$_SESSION['user_id']]);
    $ujian_selesai = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT MAX(nilai) FROM hasil_ujian WHERE siswa_id = (SELECT id FROM siswa WHERE user_id = ?)");
    $stmt->execute([$_SESSION['user_id']]);
    $nilai_tertinggi = $stmt->fetchColumn();
    $nilai_tertinggi = $nilai_tertinggi ? $nilai_tertinggi : 0;
}

// Ambil aktivitas terbaru
if ($role == 'guru') {
    // Untuk guru: ambil ujian yang dibuatnya
    $stmt = $pdo->prepare("SELECT u.judul_ujian, u.created_at FROM ujian u WHERE u.created_by = ? ORDER BY u.created_at DESC LIMIT 3");
    $stmt->execute([$_SESSION['user_id']]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Untuk siswa: ambil ujian yang tersedia atau hasil ujian
    $siswa_id_stmt = $pdo->prepare("SELECT id FROM siswa WHERE user_id = ?");
    $siswa_id_stmt->execute([$_SESSION['user_id']]);
    $siswa = $siswa_id_stmt->fetch(PDO::FETCH_ASSOC);
    $siswa_id = $siswa['id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            u.judul_ujian,
            hu.nilai,
            hu.waktu_selesai,
            CASE 
                WHEN hu.id IS NOT NULL THEN 'selesai'
                WHEN u.waktu_mulai <= ? AND u.waktu_selesai >= ? THEN 'tersedia'
                WHEN u.waktu_mulai > ? THEN 'akan_datang'
                ELSE 'lewat'
            END as status_ujian
        FROM ujian u
        LEFT JOIN hasil_ujian hu ON u.id = hu.ujian_id AND hu.siswa_id = ?
        WHERE u.status = 'published' AND u.kelas_target = (SELECT kelas FROM siswa WHERE id = ?)
        ORDER BY u.waktu_mulai DESC
        LIMIT 3
    ");
    $stmt->execute([$current_time, $current_time, $current_time, $siswa_id, $siswa_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$title = "Dashboard";
include 'templates/header.php';
?>

<!-- KONTEN UTAMA DASHBOARD -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
    <div class="text-muted">
        Selamat datang, <span class="fw-bold"><?php echo htmlspecialchars($nama_lengkap); ?></span>
    </div>
</div>

<?php displayFlashMessage(); ?>

<!-- Statistik -->
<div class="row">
    <?php if ($role == 'guru'): ?>
    <!-- Statistik Guru -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-primary border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Siswa</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_siswa; ?></h3>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-primary">
                            <i class="fas fa-users fa-2x text-white"></i>
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
                        <h6 class="text-muted mb-1">Total Soal</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_soal; ?></h3>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-info">
                            <i class="fas fa-question-circle fa-2x text-white"></i>
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
                        <h6 class="text-muted mb-1">Mata Pelajaran</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_mapel; ?></h3>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-warning">
                            <i class="fas fa-book fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Statistik Siswa -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card stat-card border-primary border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Ujian Tersedia</h6>
                        <h3 class="fw-bold text-dark"><?php echo $total_ujian; ?></h3>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-primary">
                            <i class="fas fa-clipboard-list fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card stat-card border-success border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Ujian Selesai</h6>
                        <h3 class="fw-bold text-dark"><?php echo $ujian_selesai; ?></h3>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-success">
                            <i class="fas fa-check-circle fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card stat-card border-info border-2">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h6 class="text-muted mb-1">Nilai Tertinggi</h6>
                        <h3 class="fw-bold text-dark"><?php echo number_format($nilai_tertinggi, 1); ?></h3>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon-circle bg-info">
                            <i class="fas fa-trophy fa-2x text-white"></i>
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
                <a href="<?php echo $role == 'guru' ? 'kelola_ujian.php' : 'riwayat_ujian.php'; ?>" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php if ($role == 'guru'): ?>
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
                                    <small class="text-muted">Dibuat <?php echo timeAgo($activity['created_at']); ?></small>
                                </div>
                                <span class="badge bg-primary">Baru</span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (empty($activities)): ?>
                            <div class="list-group-item text-center py-4">
                                <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Belum ada aktivitas ujian</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                            <div class="list-group-item d-flex align-items-center py-3">
                                <?php if ($activity['status_ujian'] == 'selesai'): ?>
                                <div class="icon-circle-sm bg-success me-3">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($activity['judul_ujian']); ?></h6>
                                    <small class="text-muted">Selesai - Nilai: <?php echo $activity['nilai'] ? number_format($activity['nilai'], 1) : '0'; ?></small>
                                </div>
                                <?php elseif ($activity['status_ujian'] == 'tersedia'): ?>
                                <div class="icon-circle-sm bg-primary me-3">
                                    <i class="fas fa-play text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($activity['judul_ujian']); ?></h6>
                                    <small class="text-muted">Tersedia untuk dikerjakan</small>
                                </div>
                                <span class="badge bg-primary">Mulai</span>
                                <?php elseif ($activity['status_ujian'] == 'akan_datang'): ?>
                                <div class="icon-circle-sm bg-warning me-3">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($activity['judul_ujian']); ?></h6>
                                    <small class="text-muted">Akan datang</small>
                                </div>
                                <?php else: ?>
                                <div class="icon-circle-sm bg-secondary me-3">
                                    <i class="fas fa-times text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($activity['judul_ujian']); ?></h6>
                                    <small class="text-muted">Waktu ujian telah lewat</small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
                    <?php if ($role == 'guru'): ?>
                    <a href="kelola_soal.php" class="btn btn-outline-primary text-start py-2">
                        <i class="fas fa-plus me-2"></i>Tambah Soal
                    </a>
                    <a href="kelola_ujian.php" class="btn btn-outline-success text-start py-2">
                        <i class="fas fa-clipboard-list me-2"></i>Buat Ujian
                    </a>
                    <a href="absensi_ujian.php" class="btn btn-outline-info text-start py-2">
                        <i class="fas fa-clipboard-check me-2"></i>Absensi Ujian
                    </a>
                    <a href="berita_acara.php" class="btn btn-outline-warning text-start py-2">
                        <i class="fas fa-file-alt me-2"></i>Berita Acara
                    </a>
                    <a href="hasil_ujian.php" class="btn btn-outline-secondary text-start py-2">
                        <i class="fas fa-chart-bar me-2"></i>Lihat Hasil
                    </a>
                    <a href="pengaturan.php" class="btn btn-outline-dark text-start py-2">
                        <i class="fas fa-cog me-2"></i>Pengaturan
                    </a>
                    <?php else: ?>
                    <a href="daftar_ujian.php" class="btn btn-outline-primary text-start py-2">
                        <i class="fas fa-play me-2"></i>Ikuti Ujian
                    </a>
                    <a href="riwayat_ujian.php" class="btn btn-outline-success text-start py-2">
                        <i class="fas fa-history me-2"></i>Riwayat Ujian
                    </a>
                    <a href="profile.php" class="btn btn-outline-info text-start py-2">
                        <i class="fas fa-user me-2"></i>Profil Saya
                    </a>
                    <?php endif; ?>
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
                                <td><span class="badge bg-<?php echo $role == 'guru' ? 'primary' : 'success'; ?>"><?php echo ucfirst($role); ?></span></td>
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
    
    $minutes = round($seconds / 60);           // value 60 is seconds
    $hours   = round($seconds / 3600);         // value 3600 is 60 minutes * 60 sec
    $days    = round($seconds / 86400);        // 86400 = 24 * 60 * 60;
    $weeks   = round($seconds / 604800);       // 7*24*60*60;
    $months  = round($seconds / 2629440);      // ((365+365+365+365+366)/5/12)*24*60*60
    $years   = round($seconds / 31553280);     // (365+365+365+365+366)/5 * 24 * 60 * 60
    
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
.container-fluid.p-4 {
    padding: 2rem !important;
}

.mb-4 {
    margin-bottom: 1.5rem !important;
}

.mt-4 {
    margin-top: 1.5rem !important;
}
</style>

<?php include 'templates/footer.php'; ?>