<?php
// riwayat_ujian.php - MOBILE FRIENDLY (TANPA ANIMASI)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header("Location: login.php");
    exit();
}

$title = "Riwayat Ujian";
include 'templates/header_siswa.php';

// Ambil data siswa
$stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    flashMessage('danger', 'Data siswa tidak ditemukan!');
    redirect('logout.php');
}

// Hitung statistik siswa dengan KKM
$sql_stat = "SELECT 
             COUNT(hu.id) as total_ujian,
             AVG(hu.nilai) as rata_rata,
             MAX(hu.nilai) as nilai_tertinggi,
             MIN(hu.nilai) as nilai_terendah,
             COUNT(CASE WHEN hu.nilai >= mp.kkm THEN 1 END) as lulus
             FROM hasil_ujian hu
             JOIN ujian u ON hu.ujian_id = u.id
             JOIN mata_pelajaran mp ON u.mapel_id = mp.id
             WHERE hu.siswa_id = ? AND hu.status IN ('selesai', 'waktu_habis')";

$stmt_stat = $pdo->prepare($sql_stat);
$stmt_stat->execute([$siswa['id']]);
$statistik = $stmt_stat->fetch(PDO::FETCH_ASSOC);

$total_ujian = $statistik['total_ujian'] ?? 0;
$rata_rata = number_format($statistik['rata_rata'] ?? 0, 1);
$nilai_tertinggi = number_format($statistik['nilai_tertinggi'] ?? 0, 1);
$nilai_terendah = number_format($statistik['nilai_terendah'] ?? 0, 1);
$lulus = $statistik['lulus'] ?? 0;
$tidak_lulus = $total_ujian - $lulus;
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

/* Container */
.container-fluid-custom {
    padding: 16px;
}

/* Header */
.header-section {
    margin-bottom: 20px;
}

.header-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #2c3e50;
}

.header-subtitle {
    font-size: 13px;
    color: #6c757d;
}

.btn-header {
    display: inline-block;
    background-color: #007bff;
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    text-align: center;
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
    text-align: center;
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

/* Stat Card Colors */
.stat-card.bg-primary .stat-body { background-color: #007bff; color: white; }
.stat-card.bg-info .stat-body { background-color: #17a2b8; color: white; }
.stat-card.bg-success .stat-body { background-color: #28a745; color: white; }
.stat-card.bg-warning .stat-body { background-color: #ffc107; color: #212529; }
.stat-card.bg-danger .stat-body { background-color: #dc3545; color: white; }

/* Table Styles */
.table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.data-table th,
.data-table td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.data-table th {
    background-color: #212529;
    color: white;
    font-weight: 600;
    font-size: 12px;
}

.data-table tr:last-child td {
    border-bottom: none;
}

/* Badge */
.badge-mapel {
    background-color: #6c757d;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
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
    text-align: center;
    min-width: 45px;
}

.badge-nilai {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    min-width: 70px;
    text-align: center;
}

.badge-nilai.success { background-color: #d4edda; color: #155724; }
.badge-nilai.danger { background-color: #f8d7da; color: #721c24; }
.badge-nilai.secondary { background-color: #e9ecef; color: #6c757d; }

.badge-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-status.lulus { background-color: #d4edda; color: #155724; }
.badge-status.tidak-lulus { background-color: #f8d7da; color: #721c24; }
.badge-status.sedang { background-color: #fff3cd; color: #856404; }
.badge-status.waktu-habis { background-color: #f8d7da; color: #721c24; }

/* Button Action */
.btn-action {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
    text-decoration: none;
    text-align: center;
    width: 100%;
}

.btn-info-action {
    background-color: #17a2b8;
    color: white;
}

.btn-warning-action {
    background-color: #ffc107;
    color: #212529;
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

/* Grid System */
.row-grid {
    display: flex;
    flex-wrap: wrap;
    margin: -8px;
}

.col-grid {
    padding: 8px;
}

.col-6 {
    width: 50%;
}

.col-12 {
    width: 100%;
}

/* Spacing */
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }

/* Flex */
.d-flex {
    display: flex;
}

.justify-between {
    justify-content: space-between;
}

.align-center {
    align-items: center;
}

.flex-wrap {
    flex-wrap: wrap;
}

.gap-2 {
    gap: 8px;
}

/* Text */
.text-center { text-align: center; }
.text-muted { color: #6c757d; }
.small { font-size: 11px; }

/* Responsive */
@media (min-width: 768px) {
    .container-fluid-custom {
        padding: 20px;
    }
    
    .header-title {
        font-size: 24px;
    }
    
    .stat-card .stat-number {
        font-size: 32px;
    }
    
    .col-md-2 {
        width: 16.666%;
    }
    
    .col-md-12 {
        width: 100%;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px 15px;
    }
}

@media (max-width: 576px) {
    .stat-card .stat-number {
        font-size: 22px;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px 8px;
        font-size: 12px;
    }
    
    .badge-nilai {
        min-width: 60px;
        padding: 4px 8px;
        font-size: 11px;
    }
    
    .btn-action {
        padding: 5px 8px;
        font-size: 10px;
    }
}
</style>

<div class="container-fluid-custom">
    
    <!-- Header -->
    <div class="d-flex justify-between align-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="header-title"><i class="fas fa-history me-2"></i>Riwayat Ujian</h1>
            <p class="header-subtitle">Riwayat ujian yang telah Anda ikuti</p>
        </div>
        <div>
            <a href="daftar_ujian.php" class="btn-header">
                <i class="fas fa-clipboard-list me-2"></i>Daftar Ujian Aktif
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Statistik Cards -->
    <div class="row-grid mb-4">
        <div class="col-6 col-md-2 col-grid">
            <div class="stat-card bg-primary">
                <div class="stat-body">
                    <div class="stat-number"><?= $total_ujian ?></div>
                    <div class="stat-label">Total Ujian</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 col-grid">
            <div class="stat-card bg-info">
                <div class="stat-body">
                    <div class="stat-number"><?= $rata_rata ?></div>
                    <div class="stat-label">Rata-rata</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 col-grid">
            <div class="stat-card bg-success">
                <div class="stat-body">
                    <div class="stat-number"><?= $nilai_tertinggi ?></div>
                    <div class="stat-label">Nilai Tertinggi</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 col-grid">
            <div class="stat-card bg-warning">
                <div class="stat-body">
                    <div class="stat-number"><?= $nilai_terendah ?></div>
                    <div class="stat-label">Nilai Terendah</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 col-grid">
            <div class="stat-card bg-success">
                <div class="stat-body">
                    <div class="stat-number"><?= $lulus ?></div>
                    <div class="stat-label">Lulus</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 col-grid">
            <div class="stat-card bg-danger">
                <div class="stat-body">
                    <div class="stat-number"><?= $tidak_lulus ?></div>
                    <div class="stat-label">Tidak Lulus</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th>Ujian</th>
                    <th>Mapel</th>
                    <th width="60">KKM</th>
                    <th width="80">Nilai</th>
                    <th width="100">Status</th>
                    <th width="130">Waktu Selesai</th>
                    <th width="80">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Ambil riwayat ujian siswa dengan KKM
                $sql = "SELECT hu.*, u.judul_ujian, mp.nama_mapel, mp.kode_mapel, mp.kkm
                        FROM hasil_ujian hu 
                        JOIN ujian u ON hu.ujian_id = u.id 
                        JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
                        WHERE hu.siswa_id = ? 
                        ORDER BY hu.waktu_selesai DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$siswa['id']]);
                
                $no = 1;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                    $kkm = $row['kkm'] ?? 70;
                    $nilai = $row['nilai'] ?? 0;
                    $nilai_class = 'secondary';
                    $nilai_text = '-';
                    $status_text = '';
                    $status_class = '';
                    
                    if ($row['status'] == 'selesai') {
                        if ($nilai >= $kkm) {
                            $nilai_class = 'success';
                            $status_text = 'LULUS';
                            $status_class = 'lulus';
                        } else {
                            $nilai_class = 'danger';
                            $status_text = 'TIDAK LULUS';
                            $status_class = 'tidak-lulus';
                        }
                        $nilai_text = number_format($nilai, 1);
                    } elseif ($row['status'] == 'sedang_ujian') {
                        $status_text = 'Sedang Ujian';
                        $status_class = 'sedang';
                        $nilai_text = '-';
                    } elseif ($row['status'] == 'waktu_habis') {
                        $status_text = 'Waktu Habis';
                        $status_class = 'waktu-habis';
                        $nilai_text = number_format($nilai, 1);
                        if ($nilai >= $kkm) {
                            $nilai_class = 'success';
                        } else {
                            $nilai_class = 'danger';
                        }
                    }
                    
                    $waktu_selesai = ($row['status'] != 'sedang_ujian' && $row['waktu_selesai']) ? date('d/m/Y H:i', strtotime($row['waktu_selesai'])) : '-';
                    
                    // Potong judul panjang
                    $judul = htmlspecialchars($row['judul_ujian']);
                    if (strlen($judul) > 30) {
                        $judul = substr($judul, 0, 27) . '...';
                    }
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <strong><?= $judul ?></strong>
                    </td>
                    <td>
                        <span class="badge-mapel"><?= htmlspecialchars($row['kode_mapel']) ?></span>
                        <div class="small text-muted mt-1"><?= htmlspecialchars($row['nama_mapel']) ?></div>
                    </td>
                    <td class="text-center">
                        <span class="badge-kkm"><?= $kkm ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge-nilai <?= $nilai_class ?>">
                            <?= $nilai_text ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge-status <?= $status_class ?>">
                            <?= $status_text ?>
                        </span>
                    </td>
                    <td class="text-center small"><?= $waktu_selesai ?></td>
                    <td class="text-center">
                        <?php if ($row['status'] == 'selesai' || $row['status'] == 'waktu_habis'): ?>
                        <a href="detail_hasil_siswa.php?id=<?= $row['id'] ?>" class="btn-action btn-info-action">
                            <i class="fas fa-chart-bar me-1"></i> Detail
                        </a>
                        <?php else: ?>
                        <a href="lanjutkan_ujian.php?id=<?= $row['id'] ?>" class="btn-action btn-warning-action">
                            <i class="fas fa-play me-1"></i> Lanjut
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if ($stmt->rowCount() == 0): ?>
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>Belum ada riwayat ujian</p>
                            <a href="daftar_ujian.php" class="btn-header" style="display: inline-block; width: auto;">
                                <i class="fas fa-clipboard-list me-1"></i>Ikuti Ujian Sekarang
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'templates/footer.php'; ?>