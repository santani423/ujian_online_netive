<?php
// daftar_ujian.php - Untuk siswa melihat ujian yang tersedia (DENGAN MULTI KELAS SUPPORT)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header("Location: login.php");
    exit();
}

$title = "Daftar Ujian";
include 'templates/header_siswa.php';

// Ambil data siswa
$stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    flashMessage('danger', 'Data siswa tidak ditemukan!');
    redirect('logout.php');
}

$kelas_siswa = $siswa['kelas'];

// ===================================================
// AMBIL TIMEZONE DARI PENGATURAN
// ===================================================
$timezone_setting = getSetting('timezone') ?? 'Asia/Jakarta';
try {
    date_default_timezone_set($timezone_setting);
} catch (Exception $e) {
    date_default_timezone_set('Asia/Jakarta');
    $timezone_setting = 'Asia/Jakarta';
}

// Waktu sekarang dalam timezone yang dipilih
$sekarang = new DateTime('now', new DateTimeZone($timezone_setting));
$sekarang_str = $sekarang->format('Y-m-d H:i:s');
$sekarang_ts = $sekarang->getTimestamp();

// Mapping nama timezone ke display name
$timezone_display = [
    'Asia/Jakarta' => 'WIB (UTC+7)',
    'Asia/Makassar' => 'WITA (UTC+8)',
    'Asia/Jayapura' => 'WIT (UTC+9)',
    'Asia/Singapore' => 'SGT (UTC+8)',
    'Asia/Kuala_Lumpur' => 'MYT (UTC+8)',
    'UTC' => 'UTC'
];
$timezone_display_name = $timezone_display[$timezone_setting] ?? $timezone_setting;
?>

<!-- Mobile Friendly CSS - Tanpa Animasi -->
<style>
/* Card Styles */
.card-ujian {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    overflow: hidden;
    background: white;
    margin-bottom: 16px;
    height: 100%;
}

.card-header-custom {
    padding: 12px 16px;
    background-color: #007bff;
    color: white;
}

.card-header-custom h5 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.card-body-custom {
    padding: 16px;
}

.card-footer-custom {
    padding: 12px 16px;
    background-color: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

/* Badge */
.badge-primary { background-color: #007bff; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
.badge-warning { background-color: #ffc107; color: #212529; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
.badge-success { background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
.badge-info { background-color: #17a2b8; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
.badge-secondary { background-color: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; }

/* Alert */
.alert-info {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
}

.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
}

/* Button */
.btn {
    display: inline-block;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    border: none;
    cursor: pointer;
}

.btn-success { background-color: #28a745; color: white; }
.btn-info { background-color: #17a2b8; color: white; }
.btn-primary { background-color: #007bff; color: white; }
.btn-outline-primary { background-color: transparent; border: 1px solid #007bff; color: #007bff; }

.btn-block { display: block; width: 100%; }

/* Table */
.table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.data-table th,
.data-table td {
    padding: 10px 8px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.data-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

/* Grid */
.row-grid {
    display: flex;
    flex-wrap: wrap;
    margin: -8px;
}

.col-grid {
    padding: 8px;
}

.col-12 { width: 100%; }
.col-md-6 { width: 100%; }
.col-lg-4 { width: 100%; }

/* Text */
.text-center { text-align: center; }
.text-muted { color: #6c757d; }
.text-danger { color: #dc3545; }
.text-warning { color: #ffc107; }
.text-success { color: #28a745; }

.small { font-size: 12px; }
.fw-bold { font-weight: 600; }

/* Spacing */
.mb-1 { margin-bottom: 4px; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }

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

/* Responsive */
@media (min-width: 768px) {
    .col-md-6 { width: 50%; }
}

@media (min-width: 992px) {
    .col-lg-4 { width: 33.333%; }
}

@media (max-width: 576px) {
    .card-header-custom h5 { font-size: 14px; }
    .card-body-custom { padding: 12px; }
    .btn { padding: 6px 12px; font-size: 13px; }
    .data-table th, .data-table td { padding: 8px 6px; font-size: 11px; }
}
</style>

<div class="container-fluid p-3 p-md-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-clipboard-list me-2"></i>Daftar Ujian</h2>
            <p class="text-muted small mb-0">Ujian yang tersedia untuk dikerjakan</p>
        </div>
        <div>
            <span class="badge-primary" style="padding: 6px 12px;">
                <i class="fas fa-user me-1"></i><?= htmlspecialchars($siswa['nama']) ?>
                | <i class="fas fa-users me-1"></i><?= htmlspecialchars($siswa['kelas']) ?>
            </span>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Info Waktu Sistem -->
    <div class="alert-info mb-3">
        <i class="fas fa-clock me-2"></i>
        <strong>Waktu Sistem:</strong> 
        <?= date('d/m/Y H:i:s') ?> (<?= $timezone_display_name ?>)
        <br><small class="text-muted">Waktu ujian disesuaikan dengan zona waktu Anda</small>
    </div>

    <?php
    // ===================================================
    // AMBIL DATA UJIAN - DENGAN MULTI KELAS SUPPORT
    // ===================================================
    // PERBAIKAN: Menggunakan FIND_IN_SET untuk multi kelas
    $sql = "SELECT u.*, mp.nama_mapel, mp.kode_mapel,
            (SELECT COUNT(*) FROM soal_ujian su WHERE su.ujian_id = u.id) as jumlah_soal,
            hu.id as hasil_ujian_id, 
            hu.status as status_pengerjaan,
            hu.waktu_selesai as waktu_selesai_pengerjaan
            FROM ujian u 
            JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
            LEFT JOIN hasil_ujian hu ON hu.ujian_id = u.id AND hu.siswa_id = ?
            WHERE u.status = 'published'
            AND (FIND_IN_SET(?, u.kelas_target) > 0 OR u.kelas_target = '' OR u.kelas_target IS NULL)
            ORDER BY u.waktu_mulai ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$siswa['id'], $kelas_siswa]);
    $all_ujian = $stmt->fetchAll();
    
    // Filter dan konversi waktu ke timezone yang dipilih
    $ujian_aktif = [];
    $ujian_mendatang = [];
    $ujian_selesai = [];
    
    foreach ($all_ujian as $ujian) {
        // Konversi waktu database ke DateTime dengan timezone yang dipilih
        try {
            $mulai_dt = new DateTime($ujian['waktu_mulai']);
            $selesai_dt = new DateTime($ujian['waktu_selesai']);
            
            // Set timezone ke yang dipilih
            $mulai_dt->setTimezone(new DateTimeZone($timezone_setting));
            $selesai_dt->setTimezone(new DateTimeZone($timezone_setting));
            
            $mulai_ts = $mulai_dt->getTimestamp();
            $selesai_ts = $selesai_dt->getTimestamp();
            
            $ujian['waktu_mulai_tz'] = $mulai_dt->format('Y-m-d H:i:s');
            $ujian['waktu_selesai_tz'] = $selesai_dt->format('Y-m-d H:i:s');
            $ujian['waktu_mulai_ts'] = $mulai_ts;
            $ujian['waktu_selesai_ts'] = $selesai_ts;
            
        } catch (Exception $e) {
            // Fallback ke string langsung
            $ujian['waktu_mulai_tz'] = $ujian['waktu_mulai'];
            $ujian['waktu_selesai_tz'] = $ujian['waktu_selesai'];
            $mulai_ts = strtotime($ujian['waktu_mulai']);
            $selesai_ts = strtotime($ujian['waktu_selesai']);
            $ujian['waktu_mulai_ts'] = $mulai_ts;
            $ujian['waktu_selesai_ts'] = $selesai_ts;
        }
        
        // Tentukan status berdasarkan perbandingan dengan waktu sekarang
        if ($sekarang_ts >= $mulai_ts && $sekarang_ts <= $selesai_ts) {
            $ujian['status_ujian'] = 'AKTIF';
            $ujian_aktif[] = $ujian;
        } elseif ($sekarang_ts < $mulai_ts) {
            $ujian['status_ujian'] = 'MENUNGGU';
            $ujian_mendatang[] = $ujian;
        } else {
            $ujian['status_ujian'] = 'SELESAI';
            $ujian_selesai[] = $ujian;
        }
    }
    ?>

    <!-- Ujian Aktif -->
    <?php if (count($ujian_aktif) > 0): ?>
        <div class="alert-success mb-3">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Ditemukan <?= count($ujian_aktif) ?> ujian yang sedang berlangsung</strong>
        </div>
        
        <div class="row-grid">
            <?php foreach ($ujian_aktif as $ujian):
                $mulai_ts = $ujian['waktu_mulai_ts'];
                $selesai_ts = $ujian['waktu_selesai_ts'];
                
                // Hitung sisa waktu
                $sisa_waktu = $selesai_ts - $sekarang_ts;
                $jam_sisa = floor($sisa_waktu / 3600);
                $menit_sisa = floor(($sisa_waktu % 3600) / 60);
                $detik_sisa = $sisa_waktu % 60;
                
                // Tentukan tombol berdasarkan status pengerjaan
                $has_started = !empty($ujian['hasil_ujian_id']);
                $is_finished = ($ujian['status_pengerjaan'] == 'selesai');
                
                if ($is_finished) {
                    $btn_class = 'btn-info';
                    $btn_text = 'Lihat Hasil';
                    $btn_icon = 'fa-eye';
                    $btn_link = 'detail_hasil_siswa.php?id=' . $ujian['hasil_ujian_id'];
                    $status_badge = '<span class="badge-success">Selesai</span>';
                } elseif ($has_started) {
                    $btn_class = 'btn-success';
                    $btn_text = 'Lanjutkan Ujian';
                    $btn_icon = 'fa-play';
                    $btn_link = 'kerjakan_ujian.php?id=' . $ujian['hasil_ujian_id'];
                    $status_badge = '<span class="badge-warning">Dalam Pengerjaan</span>';
                } else {
                    $btn_class = 'btn-success';
                    $btn_text = 'Mulai Ujian';
                    $btn_icon = 'fa-play';
                    $btn_link = 'mulai_ujian.php?id=' . $ujian['id'];
                    $status_badge = '<span class="badge-primary">Belum Dikerjakan</span>';
                }
                
                // Format kelas target display
                $kelas_target_display = '';
                if (!empty($ujian['kelas_target'])) {
                    $kelas_array = explode(',', $ujian['kelas_target']);
                    $kelas_badges = [];
                    foreach ($kelas_array as $k) {
                        $kelas_badges[] = '<span class="badge-info me-1">' . htmlspecialchars(trim($k)) . '</span>';
                    }
                    $kelas_target_display = '<div class="mt-2">' . implode(' ', $kelas_badges) . '</div>';
                }
            ?>
            <div class="col-12 col-md-6 col-lg-4 col-grid">
                <div class="card-ujian">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-book me-2"></i><?= htmlspecialchars($ujian['nama_mapel']) ?></h5>
                    </div>
                    <div class="card-body-custom">
                        <h6 class="fw-bold mb-2"><?= htmlspecialchars($ujian['judul_ujian']) ?></h6>
                        
                        <div class="alert-success py-1 px-2 mb-2" style="background-color: #d4edda; border-radius: 4px;">
                            <small>
                                <i class="fas fa-clock me-1"></i>
                                <strong>SEDANG BERLANGSUNG</strong> | 
                                Sisa: <?= $jam_sisa ?>j <?= $menit_sisa ?>m <?= $detik_sisa ?>d
                            </small>
                        </div>
                        
                        <div class="mb-2">
                            <?= $status_badge ?>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= $ujian['jumlah_soal'] ?> Soal | <?= $ujian['durasi'] ?> Menit
                            </small>
                        </div>
                        
                        <div class="mb-2">
                            <small><strong><i class="fas fa-calendar-alt me-1"></i>Waktu Mulai:</strong></small><br>
                            <small><?= date('d/m/Y H:i:s', $mulai_ts) ?></small>
                        </div>
                        
                        <div class="mb-2">
                            <small><strong><i class="fas fa-flag-checkered me-1"></i>Waktu Selesai:</strong></small><br>
                            <small><?= date('d/m/Y H:i:s', $selesai_ts) ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <small><strong><i class="fas fa-hourglass-half me-1"></i>Sisa Waktu:</strong></small><br>
                            <small class="<?= $jam_sisa < 1 ? 'text-danger' : ($jam_sisa < 24 ? 'text-warning' : 'text-success') ?> fw-bold">
                                <?= $jam_sisa ?> jam <?= $menit_sisa ?> menit <?= $detik_sisa ?> detik
                            </small>
                        </div>
                        
                        <?= $kelas_target_display ?>
                    </div>
                    <div class="card-footer-custom">
                        <a href="<?= $btn_link ?>" class="btn <?= $btn_class ?> btn-block" style="text-align: center;">
                            <i class="fas <?= $btn_icon ?> me-2"></i><?= $btn_text ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Ujian Mendatang & Selesai -->
    <?php if (count($ujian_aktif) == 0 && (count($ujian_mendatang) > 0 || count($ujian_selesai) > 0)): ?>
        <div class="card-ujian">
            <div class="card-body-custom">
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h5 class="text-muted">Tidak ada ujian yang sedang berlangsung</h5>
                    <p class="text-muted small">Waktu server: <?= $sekarang_str ?> (<?= $timezone_display_name ?>)</p>
                </div>
                
                <?php if (count($ujian_mendatang) > 0 || count($ujian_selesai) > 0): ?>
                <div class="mt-4">
                    <h6 class="fw-bold mb-3">Daftar Ujian Lainnya</h6>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Mapel</th>
                                    <th>Judul Ujian</th>
                                    <th>Kelas Target</th>
                                    <th>Waktu Mulai</th>
                                    <th>Waktu Selesai</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ujian_mendatang as $uj):
                                    $mulai_ts = $uj['waktu_mulai_ts'];
                                    $selesai_ts = $uj['waktu_selesai_ts'];
                                    $kelas_target_display = '';
                                    if (!empty($uj['kelas_target'])) {
                                        $kelas_array = explode(',', $uj['kelas_target']);
                                        $kelas_badges = [];
                                        foreach ($kelas_array as $k) {
                                            $kelas_badges[] = '<span class="badge-info">' . htmlspecialchars(trim($k)) . '</span>';
                                        }
                                        $kelas_target_display = implode(' ', $kelas_badges);
                                    } else {
                                        $kelas_target_display = '<span class="badge-secondary">Semua Kelas</span>';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($uj['kode_mapel']) ?></small></td>
                                    <td><?= htmlspecialchars($uj['judul_ujian']) ?></td>
                                    <td><?= $kelas_target_display ?></td>
                                    <td><small><?= date('d/m/Y H:i', $mulai_ts) ?></small></td>
                                    <td><small><?= date('d/m/Y H:i', $selesai_ts) ?></small></td>
                                    <td><span class="badge-warning">Belum Mulai</span></td>
                                    <td><span class="badge-secondary">-</span></td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php foreach ($ujian_selesai as $uj):
                                    $mulai_ts = $uj['waktu_mulai_ts'];
                                    $selesai_ts = $uj['waktu_selesai_ts'];
                                    $is_finished = ($uj['status_pengerjaan'] == 'selesai');
                                    $has_started = !empty($uj['hasil_ujian_id']);
                                    
                                    $kelas_target_display = '';
                                    if (!empty($uj['kelas_target'])) {
                                        $kelas_array = explode(',', $uj['kelas_target']);
                                        $kelas_badges = [];
                                        foreach ($kelas_array as $k) {
                                            $kelas_badges[] = '<span class="badge-info">' . htmlspecialchars(trim($k)) . '</span>';
                                        }
                                        $kelas_target_display = implode(' ', $kelas_badges);
                                    } else {
                                        $kelas_target_display = '<span class="badge-secondary">Semua Kelas</span>';
                                    }
                                    
                                    if ($is_finished) {
                                        $aksi = '<a href="detail_hasil_siswa.php?id=' . $uj['hasil_ujian_id'] . '" class="btn btn-info" style="padding: 4px 8px; font-size: 11px;">Lihat Hasil</a>';
                                    } elseif ($has_started) {
                                        $aksi = '<a href="kerjakan_ujian.php?id=' . $uj['hasil_ujian_id'] . '" class="btn btn-warning" style="padding: 4px 8px; font-size: 11px;">Lanjutkan</a>';
                                    } else {
                                        $aksi = '<span class="badge-secondary">-</span>';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($uj['kode_mapel']) ?></small></td>
                                    <td><?= htmlspecialchars($uj['judul_ujian']) ?></td>
                                    <td><?= $kelas_target_display ?></td>
                                    <td><small><?= date('d/m/Y H:i', $mulai_ts) ?></small></td>
                                    <td><small><?= date('d/m/Y H:i', $selesai_ts) ?></small></td>
                                    <td><span class="badge-secondary">Selesai</span></td>
                                    <td><?= $aksi ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <button onclick="location.reload()" class="btn btn-primary">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Halaman
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Jika tidak ada ujian sama sekali -->
    <?php if (count($all_ujian) == 0): ?>
    <div class="card-ujian">
        <div class="card-body-custom">
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h5 class="text-muted">Belum ada ujian tersedia</h5>
                <p class="text-muted small">Silakan cek kembali nanti atau hubungi guru Anda.</p>
                <button onclick="location.reload()" class="btn btn-primary mt-2">
                    <i class="fas fa-sync-alt me-2"></i>Refresh Halaman
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto refresh setiap 60 detik (1 menit)
setInterval(function() {
    location.reload();
}, 60000);
</script>

<?php include 'templates/footer.php'; ?>