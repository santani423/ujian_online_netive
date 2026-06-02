<?php
// berita_acara_guru.php - Halaman berita acara ujian untuk guru (FIXED - Tanpa Ubah Struktur DB)

// Gunakan config.php yang sama dengan admin
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    die("File config.php tidak ditemukan!");
}

// Pastikan user sudah login dan role adalah guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Berita Acara Ujian - Guru";

// Include header guru
if (file_exists('templates/header_guru.php')) {
    require_once 'templates/header_guru.php';
} else {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $title; ?> - Sistem Ujian Online</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { background-color: #f8f9fa; }
            .card { border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .table th { background-color: #f8f9fa; }
            .kelas-section { margin-bottom: 30px; }
            .kelas-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border-radius: 10px 10px 0 0; margin-bottom: 0; }
            .detail-label { font-size: 11px; text-transform: uppercase; color: #6c757d; margin-bottom: 3px; }
            .alert-fixed {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                animation: slideInRight 0.3s ease-out;
            }
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        </style>
    </head>
    <body>
    <?php
}

// Ambil data guru dari session
$guru_id = $_SESSION['guru_id'] ?? null;
$guru_nama = $_SESSION['nama_lengkap'] ?? 'Guru';
$is_guru_mapel = $_SESSION['is_guru_mapel'] ?? false;
$mapel_diampu = $_SESSION['mapel_diampu'] ?? [];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? [];

// Jika guru_id belum ada di session, cari dari database
if (!$guru_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, nama FROM guru WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $guru = $stmt->fetch();
        
        if ($guru) {
            $guru_id = $guru['id'];
            $guru_nama = $guru['nama'];
            $_SESSION['guru_id'] = $guru_id;
            $_SESSION['guru_nama'] = $guru_nama;
        } else {
            die("Data guru tidak ditemukan!");
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Ambil daftar semua guru untuk dropdown
$daftar_guru = [];
try {
    $stmt = $pdo->query("SELECT id, nama FROM guru ORDER BY nama");
    $daftar_guru = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching guru list: " . $e->getMessage());
}

// Fungsi untuk format kelas dari string (bisa comma separated)
function formatKelasDisplay($kelas_str) {
    if (empty($kelas_str)) return '-';
    $kelas_array = explode(',', $kelas_str);
    $badges = [];
    foreach ($kelas_array as $k) {
        $badges[] = '<span class="badge bg-info me-1">' . htmlspecialchars(trim($k)) . '</span>';
    }
    return implode(' ', $badges);
}

// Query untuk mendapatkan daftar ujian yang sudah selesai
$query = "
    SELECT u.*, m.nama_mapel, m.kode_mapel
    FROM ujian u
    LEFT JOIN mata_pelajaran m ON u.mapel_id = m.id
    WHERE u.status = 'published'
    AND u.waktu_mulai <= NOW()
";

// Jika guru mapel, filter berdasarkan mapel yang diampu
$params = [];
if ($is_guru_mapel && !empty($mapel_diampu)) {
    $mapel_ids = array_column($mapel_diampu, 'id');
    $placeholders = str_repeat('?,', count($mapel_ids) - 1) . '?';
    $query .= " AND u.mapel_id IN ($placeholders)";
    $params = array_merge($params, $mapel_ids);
}

$query .= " ORDER BY u.waktu_mulai DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $ujian_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $ujian_list = [];
    error_log("Database error: " . $e->getMessage());
}

// Handle filter
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

if ($filter_kelas) {
    $filtered_list = [];
    foreach ($ujian_list as $ujian) {
        $kelas_target_array = explode(',', $ujian['kelas_target']);
        if (in_array($filter_kelas, $kelas_target_array)) {
            $filtered_list[] = $ujian;
        }
    }
    $ujian_list = $filtered_list;
}

// Get unique classes for filter
$kelas_list = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT kelas_target FROM ujian WHERE kelas_target IS NOT NULL AND kelas_target != '' ORDER BY kelas_target");
    while ($row = $stmt->fetch()) {
        $kelas_array = explode(',', $row['kelas_target']);
        foreach ($kelas_array as $k) {
            $k = trim($k);
            if (!in_array($k, $kelas_list)) {
                $kelas_list[] = $k;
            }
        }
    }
    sort($kelas_list);
} catch (PDOException $e) {
    error_log("Error fetching kelas: " . $e->getMessage());
}

// Handle form submission for berita acara
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_berita_acara'])) {
    $ujian_id = $_POST['ujian_id'];
    $kelas = $_POST['kelas'];
    $guru_pengawas_id = $_POST['guru_pengawas_id'];
    $tanggal_ujian = $_POST['tanggal_ujian'];
    $waktu_mulai = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];
    $jumlah_peserta = $_POST['jumlah_peserta'] ?? 0;
    $jumlah_hadir = $_POST['jumlah_hadir'] ?? 0;
    $jumlah_tidak_hadir = $_POST['jumlah_tidak_hadir'] ?? 0;
    $ruangan = $_POST['ruangan'];
    $kejadian_penting = $_POST['kejadian_penting'];
    $kendala_teknis = $_POST['kendala_teknis'];
    $tindak_lanjut = $_POST['tindak_lanjut'];
    $status = $_POST['status'];
    
    try {
        // Cek apakah berita acara sudah ada untuk ujian dan kelas ini
        $check_stmt = $pdo->prepare("SELECT id FROM berita_acara WHERE ujian_id = ? AND kelas = ?");
        $check_stmt->execute([$ujian_id, $kelas]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Update existing berita acara
            $update_stmt = $pdo->prepare("
                UPDATE berita_acara 
                SET guru_pengawas = ?,
                    tanggal_ujian = ?,
                    waktu_mulai = ?,
                    waktu_selesai = ?,
                    jumlah_peserta = ?,
                    jumlah_hadir = ?,
                    jumlah_tidak_hadir = ?,
                    ruangan = ?,
                    kejadian_penting = ?,
                    kendala_teknis = ?,
                    tindak_lanjut = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE ujian_id = ? AND kelas = ?
            ");
            $update_stmt->execute([
                $guru_pengawas_id, $tanggal_ujian, $waktu_mulai, $waktu_selesai,
                $jumlah_peserta, $jumlah_hadir, $jumlah_tidak_hadir,
                $ruangan, $kejadian_penting, $kendala_teknis, $tindak_lanjut,
                $status, $ujian_id, $kelas
            ]);
            
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Berita acara untuk kelas ' . htmlspecialchars($kelas) . ' berhasil diperbarui!'
            ];
        } else {
            // Insert new berita acara
            $insert_stmt = $pdo->prepare("
                INSERT INTO berita_acara 
                (ujian_id, guru_pengawas, tanggal_ujian, waktu_mulai, waktu_selesai,
                 jumlah_peserta, jumlah_hadir, jumlah_tidak_hadir, kelas, ruangan,
                 kejadian_penting, kendala_teknis, tindak_lanjut, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $insert_stmt->execute([
                $ujian_id, $guru_pengawas_id, $tanggal_ujian, $waktu_mulai, $waktu_selesai,
                $jumlah_peserta, $jumlah_hadir, $jumlah_tidak_hadir,
                $kelas, $ruangan, $kejadian_penting, $kendala_teknis, $tindak_lanjut, $status
            ]);
            
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Berita acara untuk kelas ' . htmlspecialchars($kelas) . ' berhasil dibuat!'
            ];
        }
        
        header("Location: berita_acara_guru.php?ujian_id=" . $ujian_id);
        exit();
        
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $_SESSION['flash'] = [
                'type' => 'warning',
                'message' => 'Berita acara untuk kelas ' . htmlspecialchars($kelas) . ' sudah ada. Silakan refresh halaman.'
            ];
        } elseif ($e->errorInfo[1] == 1452) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Error: Guru pengawas tidak valid. Silakan pilih guru dari daftar.'
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
        header("Location: berita_acara_guru.php?ujian_id=" . $ujian_id);
        exit();
    }
}

// Handle delete berita acara
if (isset($_GET['delete']) && isset($_GET['ujian_id']) && isset($_GET['kelas'])) {
    $ujian_id = $_GET['ujian_id'];
    $kelas = $_GET['kelas'];
    
    try {
        $delete_stmt = $pdo->prepare("DELETE FROM berita_acara WHERE ujian_id = ? AND kelas = ?");
        $delete_stmt->execute([$ujian_id, $kelas]);
        
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Berita acara untuk kelas ' . htmlspecialchars($kelas) . ' berhasil dihapus!'
        ];
    } catch (PDOException $e) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Gagal menghapus: ' . $e->getMessage()
        ];
    }
    
    header("Location: berita_acara_guru.php?ujian_id=" . $ujian_id);
    exit();
}

// Get berita acara and students for a specific exam (PER KELAS)
$berita_acara_by_class = [];
$att_stats_by_class = [];
$exam = null;

if (isset($_GET['ujian_id'])) {
    $ujian_id = $_GET['ujian_id'];
    
    try {
        // Get exam details
        $stmt = $pdo->prepare("
            SELECT u.*, m.nama_mapel, m.kode_mapel 
            FROM ujian u 
            LEFT JOIN mata_pelajaran m ON u.mapel_id = m.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$ujian_id]);
        $exam = $stmt->fetch();
        
        if ($exam) {
            // Pisahkan kelas_target menjadi array
            $kelas_target_array = explode(',', $exam['kelas_target']);
            
            // Untuk setiap kelas, ambil berita acara dan statistik
            foreach ($kelas_target_array as $kelas) {
                $kelas = trim($kelas);
                
                // Get berita acara for this class dengan join ke tabel guru
                $stmt = $pdo->prepare("
                    SELECT ba.*, g.nama as guru_nama
                    FROM berita_acara ba
                    LEFT JOIN guru g ON ba.guru_pengawas = g.id
                    WHERE ba.ujian_id = ? AND ba.kelas = ?
                ");
                $stmt->execute([$ujian_id, $kelas]);
                $berita_acara = $stmt->fetch();
                $berita_acara_by_class[$kelas] = $berita_acara;
                
                // Get attendance statistics for this class
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as jumlah_peserta,
                        SUM(CASE WHEN a.status_hadir = 'hadir' THEN 1 ELSE 0 END) as jumlah_hadir,
                        SUM(CASE WHEN a.status_hadir = 'tidak_hadir' OR a.status_hadir IS NULL THEN 1 ELSE 0 END) as jumlah_tidak_hadir
                    FROM siswa s
                    LEFT JOIN absensi_ujian a ON s.id = a.siswa_id AND a.ujian_id = ?
                    WHERE s.kelas = ?
                ");
                $stmt->execute([$ujian_id, $kelas]);
                $att_stats = $stmt->fetch();
                
                if (!$att_stats) {
                    $att_stats = ['jumlah_peserta' => 0, 'jumlah_hadir' => 0, 'jumlah_tidak_hadir' => 0];
                }
                $att_stats_by_class[$kelas] = $att_stats;
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching exam details: " . $e->getMessage());
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}
?>

<div class="container-fluid">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash'])): ?>
    <div class="alert-fixed">
        <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible fade show shadow" role="alert">
            <i class="fas <?php echo [
                'success' => 'fa-check-circle',
                'danger' => 'fa-exclamation-circle',
                'warning' => 'fa-exclamation-triangle',
                'info' => 'fa-info-circle'
            ][$_SESSION['flash']['type']]; ?> me-2"></i>
            <?php echo htmlspecialchars($_SESSION['flash']['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-file-contract text-primary me-2"></i>Berita Acara Ujian</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
            <i class="fas fa-filter me-1"></i> Filter
        </button>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Filter Ujian</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="GET" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="kelas" class="form-label">Kelas</label>
                            <select class="form-select" id="kelas" name="kelas">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($kelas_list as $kelas_opt): ?>
                                    <option value="<?php echo htmlspecialchars($kelas_opt); ?>" 
                                        <?php echo ($filter_kelas == $kelas_opt) ? 'selected' : ''; ?>>
                                        Kelas <?php echo htmlspecialchars($kelas_opt); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="berita_acara_guru.php" class="btn btn-secondary">Reset</a>
                        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($exam)): ?>
        <!-- Exam Details -->
        <div class="card mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Ujian</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Judul Ujian</div>
                        <div class="fw-bold mb-2"><?php echo htmlspecialchars($exam['judul_ujian']); ?></div>
                        
                        <div class="detail-label">Mata Pelajaran</div>
                        <div class="mb-2"><?php echo htmlspecialchars($exam['nama_mapel']); ?> (<?php echo htmlspecialchars($exam['kode_mapel']); ?>)</div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Waktu Ujian</div>
                        <div class="mb-2"><?php echo date('d/m/Y H:i', strtotime($exam['waktu_mulai'])); ?> - <?php echo date('H:i', strtotime($exam['waktu_selesai'])); ?></div>
                        
                        <div class="detail-label">Kelas Target</div>
                        <div><?php echo formatKelasDisplay($exam['kelas_target']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Berita Acara per Class -->
        <?php foreach ($berita_acara_by_class as $kelas => $berita_acara): 
            $att_stats = $att_stats_by_class[$kelas] ?? ['jumlah_peserta' => 0, 'jumlah_hadir' => 0, 'jumlah_tidak_hadir' => 0];
            $status_text = '';
            $status_class = '';
            if ($berita_acara) {
                $status_text = [
                    'draft' => 'Draft',
                    'selesai' => 'Selesai',
                    'diverifikasi' => 'Terverifikasi'
                ][$berita_acara['status']];
                $status_class = [
                    'draft' => 'bg-warning',
                    'selesai' => 'bg-success',
                    'diverifikasi' => 'bg-info'
                ][$berita_acara['status']];
            }
        ?>
            <div class="kelas-section">
                <div class="kelas-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Kelas <?php echo htmlspecialchars($kelas); ?>
                        <?php if ($berita_acara): ?>
                            <span class="badge <?php echo $status_class; ?> ms-2"><?php echo $status_text; ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary ms-2">Belum Dibuat</span>
                        <?php endif; ?>
                    </h5>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <?php if ($berita_acara): ?>
                            <!-- Display Existing Berita Acara -->
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="35%">Guru Pengawas</th>
                                            <td><?php echo htmlspecialchars($berita_acara['guru_nama'] ?? 'Tidak diketahui'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Ujian</th>
                                            <td><?php echo date('d/m/Y', strtotime($berita_acara['tanggal_ujian'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Waktu Pelaksanaan</th>
                                            <td><?php echo $berita_acara['waktu_mulai'] . ' - ' . $berita_acara['waktu_selesai']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Ruangan</th>
                                            <td><?php echo htmlspecialchars($berita_acara['ruangan'] ?: '-'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="35%">Jumlah Peserta</th>
                                            <td><?php echo $berita_acara['jumlah_peserta']; ?> siswa</td>
                                        </tr>
                                        <tr>
                                            <th>Jumlah Hadir</th>
                                            <td>
                                                <span class="text-success"><?php echo $berita_acara['jumlah_hadir']; ?> siswa</span>
                                                (<?php echo $berita_acara['jumlah_peserta'] > 0 ? round(($berita_acara['jumlah_hadir'] / $berita_acara['jumlah_peserta']) * 100, 1) : 0; ?>%)
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Jumlah Tidak Hadir</th>
                                            <td>
                                                <span class="text-danger"><?php echo $berita_acara['jumlah_tidak_hadir']; ?> siswa</span>
                                                (<?php echo $berita_acara['jumlah_peserta'] > 0 ? round(($berita_acara['jumlah_tidak_hadir'] / $berita_acara['jumlah_peserta']) * 100, 1) : 0; ?>%)
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h6><i class="fas fa-exclamation-circle me-2"></i>Kejadian Penting</h6>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <?php echo nl2br(htmlspecialchars($berita_acara['kejadian_penting'] ?: '- Tidak ada kejadian penting -')); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-tools me-2"></i>Kendala Teknis</h6>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <?php echo nl2br(htmlspecialchars($berita_acara['kendala_teknis'] ?: '- Tidak ada kendala teknis -')); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-tasks me-2"></i>Tindak Lanjut</h6>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <?php echo nl2br(htmlspecialchars($berita_acara['tindak_lanjut'] ?: '- Tidak ada tindak lanjut -')); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-warning" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#beritaAcaraModal<?php echo str_replace(' ', '', $kelas); ?>">
                                    <i class="fas fa-edit me-1"></i> Edit Berita Acara
                                </button>
                                <button type="button" class="btn btn-danger ms-2" 
                                        onclick="confirmDelete('<?php echo $ujian_id; ?>', '<?php echo $kelas; ?>')">
                                    <i class="fas fa-trash me-1"></i> Hapus
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <p class="mb-3">Berita acara untuk kelas <?php echo htmlspecialchars($kelas); ?> belum dibuat.</p>
                                <button type="button" class="btn btn-success" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#beritaAcaraModal<?php echo str_replace(' ', '', $kelas); ?>">
                                    <i class="fas fa-plus me-1"></i> Buat Berita Acara
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Modal Berita Acara per Kelas -->
            <div class="modal fade" id="beritaAcaraModal<?php echo str_replace(' ', '', $kelas); ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header <?php echo $berita_acara ? 'bg-warning' : 'bg-success'; ?> text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-file-alt me-2"></i>
                                <?php echo $berita_acara ? 'Edit Berita Acara' : 'Buat Berita Acara'; ?> - Kelas <?php echo htmlspecialchars($kelas); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="">
                            <div class="modal-body">
                                <input type="hidden" name="ujian_id" value="<?php echo $exam['id']; ?>">
                                <input type="hidden" name="kelas" value="<?php echo htmlspecialchars($kelas); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Guru Pengawas <span class="text-danger">*</span></label>
                                            <select class="form-select" name="guru_pengawas_id" required>
                                                <option value="">-- Pilih Guru Pengawas --</option>
                                                <?php foreach ($daftar_guru as $guru): ?>
                                                    <option value="<?php echo $guru['id']; ?>" 
                                                        <?php echo ($berita_acara && $berita_acara['guru_pengawas'] == $guru['id']) ? 'selected' : ''; ?>
                                                        <?php echo (!$berita_acara && $guru['id'] == $guru_id) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($guru['nama']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text small">Pilih guru yang bertugas sebagai pengawas ujian</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal Ujian <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="tanggal_ujian" 
                                                   value="<?php echo $berita_acara ? $berita_acara['tanggal_ujian'] : date('Y-m-d', strtotime($exam['waktu_mulai'])); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" name="waktu_mulai" 
                                                   value="<?php echo $berita_acara ? $berita_acara['waktu_mulai'] : date('H:i', strtotime($exam['waktu_mulai'])); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" name="waktu_selesai" 
                                                   value="<?php echo $berita_acara ? $berita_acara['waktu_selesai'] : date('H:i', strtotime($exam['waktu_selesai'])); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Status <span class="text-danger">*</span></label>
                                            <select class="form-select" name="status" required>
                                                <option value="draft" <?php echo ($berita_acara && $berita_acara['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                                <option value="selesai" <?php echo ($berita_acara && $berita_acara['status'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                                <option value="diverifikasi" <?php echo ($berita_acara && $berita_acara['status'] == 'diverifikasi') ? 'selected' : ''; ?>>Diverifikasi</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Jumlah Peserta</label>
                                            <input type="number" class="form-control" name="jumlah_peserta" 
                                                   value="<?php echo $berita_acara ? $berita_acara['jumlah_peserta'] : ($att_stats['jumlah_peserta'] ?? 0); ?>" 
                                                   min="0">
                                            <div class="form-text small">Data absensi: <?php echo $att_stats['jumlah_peserta'] ?? 0; ?> siswa</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Jumlah Hadir</label>
                                            <input type="number" class="form-control" name="jumlah_hadir" 
                                                   value="<?php echo $berita_acara ? $berita_acara['jumlah_hadir'] : ($att_stats['jumlah_hadir'] ?? 0); ?>" 
                                                   min="0">
                                            <div class="form-text small">Data absensi: <?php echo $att_stats['jumlah_hadir'] ?? 0; ?> siswa</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Jumlah Tidak Hadir</label>
                                            <input type="number" class="form-control" name="jumlah_tidak_hadir" 
                                                   value="<?php echo $berita_acara ? $berita_acara['jumlah_tidak_hadir'] : ($att_stats['jumlah_tidak_hadir'] ?? 0); ?>" 
                                                   min="0">
                                            <div class="form-text small">Data absensi: <?php echo $att_stats['jumlah_tidak_hadir'] ?? 0; ?> siswa</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ruangan</label>
                                            <input type="text" class="form-control" name="ruangan" 
                                                   value="<?php echo $berita_acara ? $berita_acara['ruangan'] : ''; ?>" 
                                                   placeholder="Misal: Lab. Komputer 1, Ruang 7A, dll.">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Kejadian Penting</label>
                                    <textarea class="form-control" name="kejadian_penting" rows="3" 
                                              placeholder="Catat kejadian penting selama ujian berlangsung..."><?php echo htmlspecialchars($berita_acara['kejadian_penting'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Kendala Teknis</label>
                                    <textarea class="form-control" name="kendala_teknis" rows="3" 
                                              placeholder="Catat kendala teknis yang dialami (jaringan, komputer, dll)..."><?php echo htmlspecialchars($berita_acara['kendala_teknis'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Tindak Lanjut</label>
                                    <textarea class="form-control" name="tindak_lanjut" rows="3" 
                                              placeholder="Rencana tindak lanjut dari hasil ujian..."><?php echo htmlspecialchars($berita_acara['tindak_lanjut'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="submit_berita_acara" class="btn btn-primary">Simpan Berita Acara</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="text-center mt-3">
            <a href="berita_acara_guru.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Ujian
            </a>
        </div>
        
    <?php else: ?>
        <!-- Exam List -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list-alt me-2"></i> Daftar Ujian untuk Berita Acara
                    <?php if ($filter_kelas): ?>
                        <span class="badge bg-warning text-dark ms-2">Filter: Kelas <?php echo htmlspecialchars($filter_kelas); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($ujian_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Judul Ujian</th>
                                    <th>Kelas Target</th>
                                    <th>Waktu Pelaksanaan</th>
                                    <th width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                foreach ($ujian_list as $row): 
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_mapel']); ?></td>
                                        <td><?php echo htmlspecialchars($row['judul_ujian']); ?></td>
                                        <td><?php echo formatKelasDisplay($row['kelas_target']); ?></small></td>
                                        <td><small><?php echo date('d/m/Y H:i', strtotime($row['waktu_mulai'])); ?></small></small></td>
                                        <td>
                                            <a href="berita_acara_guru.php?ujian_id=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-file-alt"></i> 
                                                Kelola Berita Acara
                                            </a>
                                         </small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if ($filter_kelas): ?>
                            Tidak ada ujian yang sesuai dengan filter yang dipilih.
                        <?php else: ?>
                            Tidak ada ujian yang memerlukan berita acara.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function confirmDelete(ujianId, kelas) {
    if (confirm('Apakah Anda yakin ingin menghapus berita acara untuk kelas ' + kelas + '? Data yang dihapus tidak dapat dikembalikan.')) {
        window.location.href = 'berita_acara_guru.php?ujian_id=' + ujianId + '&kelas=' + encodeURIComponent(kelas) + '&delete=1';
    }
}

// Auto close flash message after 5 seconds
setTimeout(function() {
    var alert = document.querySelector('.alert-fixed');
    if (alert) {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s ease';
        setTimeout(function() {
            if (alert) alert.remove();
        }, 500);
    }
}, 5000);
</script>

<style>
.kelas-section {
    margin-bottom: 30px;
}
.kelas-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 10px 10px 0 0;
    margin-bottom: 0;
}
.detail-label {
    font-size: 11px;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 3px;
}
.table-bordered th, .table-bordered td {
    border: 1px solid #dee2e6;
    padding: 10px;
    vertical-align: middle;
}
.alert-fixed {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 350px;
    animation: slideInRight 0.3s ease-out;
}
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<?php 
// Include footer
if (file_exists('templates/footer.php')) {
    require_once 'templates/footer.php';
} else {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>