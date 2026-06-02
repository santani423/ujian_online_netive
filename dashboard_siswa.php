<?php
// dashboard_siswa.php - MOBILE FRIENDLY (TANPA ANIMASI)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header("Location: login.php");
    exit();
}

$title = "Dashboard Siswa";
include 'templates/header_siswa.php';

// Ambil data siswa
$stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    flashMessage('danger', 'Data siswa tidak ditemukan!');
    redirect('logout.php');
}

// Hitung statistik dengan KKM per mata pelajaran
$sql_stat = "SELECT 
             COUNT(hu.id) as total_ujian,
             COUNT(CASE WHEN hu.status = 'sedang_ujian' THEN 1 END) as ujian_berlangsung,
             AVG(CASE WHEN hu.status IN ('selesai', 'waktu_habis') THEN hu.nilai ELSE NULL END) as rata_rata,
             MAX(CASE WHEN hu.status IN ('selesai', 'waktu_habis') THEN hu.nilai ELSE NULL END) as nilai_tertinggi,
             MIN(CASE WHEN hu.status IN ('selesai', 'waktu_habis') THEN hu.nilai ELSE NULL END) as nilai_terendah,
             COUNT(CASE WHEN hu.status IN ('selesai', 'waktu_habis') AND hu.nilai >= mp.kkm THEN 1 END) as lulus,
             COUNT(CASE WHEN hu.status IN ('selesai', 'waktu_habis') AND hu.nilai < mp.kkm THEN 1 END) as tidak_lulus
             FROM hasil_ujian hu
             JOIN ujian u ON hu.ujian_id = u.id
             JOIN mata_pelajaran mp ON u.mapel_id = mp.id
             WHERE hu.siswa_id = ?";
$stmt_stat = $pdo->prepare($sql_stat);
$stmt_stat->execute([$siswa['id']]);
$statistik = $stmt_stat->fetch();

// Ambil ujian aktif
$sql_ujian_aktif = "SELECT COUNT(*) as total 
                    FROM ujian 
                    WHERE status = 'published'
                    AND (kelas_target = ? OR kelas_target = '' OR kelas_target IS NULL)
                    AND waktu_mulai <= NOW()
                    AND waktu_selesai >= NOW()";
$stmt_ujian_aktif = $pdo->prepare($sql_ujian_aktif);
$stmt_ujian_aktif->execute([$siswa['kelas']]);
$ujian_aktif = $stmt_ujian_aktif->fetchColumn();

// Ambil 5 ujian terakhir
$sql_terbaru = "SELECT hu.*, u.judul_ujian, mp.nama_mapel, mp.kode_mapel, mp.kkm
                FROM hasil_ujian hu
                JOIN ujian u ON hu.ujian_id = u.id
                JOIN mata_pelajaran mp ON u.mapel_id = mp.id
                WHERE hu.siswa_id = ? AND hu.status IN ('selesai', 'waktu_habis')
                ORDER BY hu.waktu_selesai DESC
                LIMIT 5";
$stmt_terbaru = $pdo->prepare($sql_terbaru);
$stmt_terbaru->execute([$siswa['id']]);
$ujian_terbaru = $stmt_terbaru->fetchAll();
?>

<!-- Mobile Friendly CSS - Tanpa Animasi -->
<style>
/* Reset & Base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background-color: #f0f2f5;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
}

/* Card Styles */
.card-custom {
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    overflow: hidden;
}

/* Stat Card */
.stat-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    overflow: hidden;
}

.stat-card .stat-body {
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-card .stat-number {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 4px;
}

.stat-card .stat-label {
    font-size: 12px;
    color: #6c757d;
}

.stat-card .stat-icon {
    font-size: 32px;
    opacity: 0.7;
}

.stat-card .stat-footer {
    background: #f8f9fa;
    padding: 10px 16px;
    border-top: 1px solid #e0e0e0;
}

.stat-card .stat-footer a {
    font-size: 12px;
    text-decoration: none;
    color: #007bff;
}

/* Stat Card Colors */
.stat-card.bg-primary .stat-body { background-color: #007bff; color: white; }
.stat-card.bg-primary .stat-footer { background-color: #0069d9; }
.stat-card.bg-primary .stat-footer a { color: white; }

.stat-card.bg-success .stat-body { background-color: #28a745; color: white; }
.stat-card.bg-success .stat-footer { background-color: #218838; }
.stat-card.bg-success .stat-footer a { color: white; }

.stat-card.bg-info .stat-body { background-color: #17a2b8; color: white; }
.stat-card.bg-warning .stat-body { background-color: #ffc107; color: #212529; }

/* Welcome Section */
.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
}

.welcome-section h1 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 6px;
}

.welcome-section p {
    font-size: 13px;
    opacity: 0.9;
    margin-bottom: 0;
}

.welcome-section .badge-kelas {
    background: rgba(255,255,255,0.2);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
}

/* Info Card */
.info-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    overflow: hidden;
    height: 100%;
}

.info-card .info-header {
    background: #28a745;
    padding: 14px 16px;
    color: white;
}

.info-card .info-header h5 {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
}

.info-card .info-body {
    padding: 16px;
}

.info-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 10px;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-label {
    font-size: 11px;
    color: #6c757d;
    display: block;
    margin-bottom: 4px;
}

.info-value {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-action {
    display: block;
    width: 100%;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    text-decoration: none;
    border: none;
    cursor: pointer;
}

.btn-primary-custom {
    background-color: #007bff;
    color: white;
}

.btn-info-custom {
    background-color: #17a2b8;
    color: white;
}

.btn-warning-custom {
    background-color: #ffc107;
    color: #212529;
}

/* Table */
.table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table-responsive-custom {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.table-responsive-custom th,
.table-responsive-custom td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.table-responsive-custom th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

/* Badge */
.badge-mapel {
    background-color: #6c757d;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    display: inline-block;
}

.badge-kkm {
    background-color: #17a2b8;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    display: inline-block;
}

.badge-lulus {
    background-color: #d4edda;
    color: #155724;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.badge-tidak-lulus {
    background-color: #f8d7da;
    color: #721c24;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.badge-sedang {
    background-color: #fff3cd;
    color: #856404;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

/* Stat Kelulusan Card */
.stat-lulus-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    text-align: center;
    padding: 16px;
}

.stat-lulus-number {
    font-size: 32px;
    font-weight: 700;
}

.stat-lulus-number.text-success { color: #28a745; }
.stat-lulus-number.text-danger { color: #dc3545; }

.stat-lulus-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

/* Section Header */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 8px;
}

.section-header h5 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    color: #2c3e50;
}

.section-header a {
    font-size: 12px;
    text-decoration: none;
    color: #007bff;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border-radius: 12px;
}

.empty-state i {
    font-size: 48px;
    color: #adb5bd;
    margin-bottom: 12px;
}

.empty-state p {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 16px;
}

/* Tips Card */
.tips-card {
    background: #e8f4f8;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #cce5ff;
}

.tips-card i {
    font-size: 28px;
    color: #ffc107;
}

.tips-card h6 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.tips-card p {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 0;
}

/* Grid System */
.row-custom {
    display: flex;
    flex-wrap: wrap;
    margin: -8px;
}

.col-custom {
    padding: 8px;
    flex: 1 1 auto;
}

.col-6-custom {
    width: 50%;
    padding: 8px;
}

.col-12-custom {
    width: 100%;
    padding: 8px;
}

/* Spacing */
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }

/* Container */
.container-fluid-custom {
    padding: 16px;
}

/* Responsive */
@media (min-width: 768px) {
    .col-md-3-custom {
        width: 25%;
        padding: 8px;
    }
    
    .col-md-6-custom {
        width: 50%;
        padding: 8px;
    }
    
    .container-fluid-custom {
        padding: 20px;
    }
    
    .stat-card .stat-number {
        font-size: 32px;
    }
}

@media (max-width: 576px) {
    .stat-card .stat-number {
        font-size: 24px;
    }
    
    .stat-card .stat-icon {
        font-size: 28px;
    }
    
    .welcome-section h1 {
        font-size: 18px;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .table-responsive-custom th,
    .table-responsive-custom td {
        padding: 10px 8px;
        font-size: 12px;
    }
}
</style>

<div class="container-fluid-custom">
    
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1>Halo, <?= htmlspecialchars($siswa['nama']) ?>! 👋</h1>
                <p>Selamat datang di dashboard ujian online</p>
            </div>
            <div>
                <span class="badge-kelas">
                    <i class="fas fa-users me-1"></i> Kelas <?= htmlspecialchars($siswa['kelas']) ?>
                </span>
            </div>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Statistik Cards -->
    <div class="row-custom">
        <div class="col-6-custom col-md-3-custom">
            <div class="stat-card bg-primary">
                <div class="stat-body">
                    <div>
                        <div class="stat-number"><?= $ujian_aktif ?></div>
                        <div class="stat-label">Ujian Aktif</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <a href="daftar_ujian.php">Lihat Daftar <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        
        <div class="col-6-custom col-md-3-custom">
            <div class="stat-card bg-success">
                <div class="stat-body">
                    <div>
                        <div class="stat-number"><?= $statistik['total_ujian'] ?></div>
                        <div class="stat-label">Total Ujian</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-history"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <a href="riwayat_ujian.php">Lihat Riwayat <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        
        <div class="col-6-custom col-md-3-custom">
            <div class="stat-card bg-info">
                <div class="stat-body">
                    <div>
                        <div class="stat-number"><?= number_format($statistik['rata_rata'] ?? 0, 1) ?></div>
                        <div class="stat-label">Rata-rata Nilai</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-6-custom col-md-3-custom">
            <div class="stat-card bg-warning">
                <div class="stat-body">
                    <div>
                        <div class="stat-number"><?= $statistik['ujian_berlangsung'] ?></div>
                        <div class="stat-label">Sedang Ujian</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistik Kelulusan -->
    <?php if ($statistik['total_ujian'] > 0): ?>
    <div class="row-custom mb-3">
        <div class="col-6-custom col-md-6-custom">
            <div class="stat-lulus-card">
                <div class="stat-lulus-number text-success"><?= $statistik['lulus'] ?? 0 ?></div>
                <div class="stat-lulus-label">Lulus</div>
            </div>
        </div>
        <div class="col-6-custom col-md-6-custom">
            <div class="stat-lulus-card">
                <div class="stat-lulus-number text-danger"><?= $statistik['tidak_lulus'] ?? 0 ?></div>
                <div class="stat-lulus-label">Tidak Lulus</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Row -->
    <div class="row-custom">
        <!-- Quick Actions -->
        <div class="col-12-custom col-md-6-custom">
            <div class="info-card">
                <div class="info-header">
                    <h5><i class="fas fa-bolt me-2"></i>Akses Cepat</h5>
                </div>
                <div class="info-body">
                    <div class="quick-actions">
                        <a href="daftar_ujian.php" class="btn-action btn-primary-custom">
                            <i class="fas fa-clipboard-list me-2"></i>Lihat Daftar Ujian
                        </a>
                        <a href="riwayat_ujian.php" class="btn-action btn-info-custom">
                            <i class="fas fa-history me-2"></i>Lihat Riwayat Ujian
                        </a>
                        <a href="profile_siswa.php" class="btn-action btn-warning-custom">
                            <i class="fas fa-user me-2"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informasi Siswa -->
        <div class="col-12-custom col-md-6-custom">
            <div class="info-card">
                <div class="info-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Data Diri</h5>
                </div>
                <div class="info-body">
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user me-1"></i>Nama Lengkap</span>
                        <span class="info-value"><?= htmlspecialchars($siswa['nama']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-id-card me-1"></i>NISN</span>
                        <span class="info-value"><?= htmlspecialchars($siswa['nisn']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-users me-1"></i>Kelas</span>
                        <span class="info-value"><?= htmlspecialchars($siswa['kelas']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-envelope me-1"></i>Username</span>
                        <span class="info-value"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5 Ujian Terakhir -->
    <?php if (count($ujian_terbaru) > 0): ?>
    <div class="card-custom mt-3">
        <div class="section-header" style="padding: 16px 16px 0 16px;">
            <h5><i class="fas fa-clock me-2"></i>5 Ujian Terakhir</h5>
            <a href="riwayat_ujian.php">Lihat Semua <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        <div class="table-wrapper">
            <table class="table-responsive-custom">
                <thead>
                    <tr>
                        <th>Ujian</th>
                        <th>Mapel</th>
                        <th>Nilai</th>
                        <th>KKM</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ujian_terbaru as $ujian): 
                        $nilai = $ujian['nilai'] ?? 0;
                        $kkm = $ujian['kkm'] ?? 70;
                        $lulus = $nilai >= $kkm;
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars(mb_substr($ujian['judul_ujian'], 0, 25)) ?></strong><br>
                            <span style="font-size: 10px; color: #6c757d;"><?= date('d/m/Y', strtotime($ujian['waktu_selesai'])) ?></span>
                        </td>
                        <td><span class="badge-mapel"><?= htmlspecialchars($ujian['kode_mapel']) ?></span></td>
                        <td class="<?= $lulus ? 'text-success' : 'text-danger' ?>" style="font-weight: 600;">
                            <?= number_format($nilai, 1) ?>
                        </td>
                        <td><span class="badge-kkm"><?= $kkm ?></span></td>
                        <td>
                            <span class="<?= $lulus ? 'badge-lulus' : 'badge-tidak-lulus' ?>">
                                <?= $lulus ? 'LULUS' : 'TIDAK LULUS' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-state mt-3">
        <i class="fas fa-inbox"></i>
        <p>Belum ada riwayat ujian</p>
        <a href="daftar_ujian.php" class="btn-action btn-primary-custom" style="display: inline-block; width: auto; padding: 10px 20px;">
            <i class="fas fa-clipboard-list me-1"></i>Ikuti Ujian Sekarang
        </a>
    </div>
    <?php endif; ?>

    <!-- Tips Belajar -->
    <div class="tips-card mt-3">
        <div class="d-flex gap-3 align-items-center">
            <div class="text-center">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div>
                <h6>Tips Belajar</h6>
                <p>Persiapkan diri dengan baik sebelum ujian. Baca soal dengan teliti dan kelola waktu dengan bijak.</p>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>