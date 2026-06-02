<?php
// absensi_ujian_guru.php - Halaman absensi ujian untuk guru (DENGAN MULTI KELAS SUPPORT & FILTER PER KELAS)

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

$title = "Absensi Ujian - Guru";

// Include header guru
if (file_exists('templates/header_guru.php')) {
    require_once 'templates/header_guru.php';
} else {
    // Fallback header sederhana
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
            .sidebar { background: linear-gradient(180deg, #4e73df 0%, #224abe 100%); }
            .stat-card { transition: transform 0.2s; }
            .stat-card:hover { transform: translateY(-5px); }
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
        } else {
            die("Data guru tidak ditemukan!");
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
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

// Query untuk mendapatkan daftar ujian yang perlu diambil absensinya (DENGAN MULTI KELAS)
$query = "
    SELECT u.*, m.nama_mapel, g.nama as nama_guru_pembuat
    FROM ujian u
    LEFT JOIN mata_pelajaran m ON u.mapel_id = m.id
    LEFT JOIN guru g ON u.created_by = g.id
    WHERE u.status = 'published'
    AND (u.waktu_mulai <= NOW() OR u.waktu_selesai >= NOW())
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
$filter_tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$filter_kelas_ujian = isset($_GET['kelas_ujian']) ? $_GET['kelas_ujian'] : ''; // Filter untuk kelas dalam ujian

if ($filter_kelas || $filter_tanggal || $filter_kelas_ujian) {
    $filtered_list = [];
    foreach ($ujian_list as $ujian) {
        $match = true;
        
        if ($filter_kelas_ujian) {
            $kelas_target_array = explode(',', $ujian['kelas_target']);
            if (!in_array($filter_kelas_ujian, $kelas_target_array)) {
                $match = false;
            }
        }
        
        if ($filter_tanggal && $match) {
            if (date('Y-m-d', strtotime($ujian['waktu_mulai'])) != $filter_tanggal) {
                $match = false;
            }
        }
        
        if ($match) {
            $filtered_list[] = $ujian;
        }
    }
    $ujian_list = $filtered_list;
}

// Get unique classes for filter (dari semua ujian yang ada - multi kelas support)
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

// Handle form submission for attendance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_attendance'])) {
    $ujian_id = $_POST['ujian_id'];
    $siswa_id = $_POST['siswa_id'];
    $status_hadir = $_POST['status_hadir'];
    $keterangan = $_POST['keterangan'];
    
    try {
        // Cek apakah absensi sudah ada
        $check_stmt = $pdo->prepare("SELECT id FROM absensi_ujian WHERE ujian_id = ? AND siswa_id = ?");
        $check_stmt->execute([$ujian_id, $siswa_id]);
        
        if ($check_stmt->rowCount() > 0) {
            // Update existing attendance
            $update_stmt = $pdo->prepare("
                UPDATE absensi_ujian 
                SET status_hadir = ?, 
                    keterangan = ?,
                    waktu_hadir = CASE WHEN ? = 'hadir' THEN COALESCE(waktu_hadir, NOW()) ELSE waktu_hadir END,
                    updated_at = NOW()
                WHERE ujian_id = ? AND siswa_id = ?
            ");
            $update_stmt->execute([$status_hadir, $keterangan, $status_hadir, $ujian_id, $siswa_id]);
        } else {
            // Insert new attendance
            $insert_stmt = $pdo->prepare("
                INSERT INTO absensi_ujian 
                (ujian_id, siswa_id, status_hadir, keterangan, waktu_hadir, created_at, updated_at)
                VALUES (?, ?, ?, ?, CASE WHEN ? = 'hadir' THEN NOW() ELSE NULL END, NOW(), NOW())
            ");
            $insert_stmt->execute([$ujian_id, $siswa_id, $status_hadir, $keterangan, $status_hadir]);
        }
        
        flashMessage('success', 'Absensi berhasil disimpan!');
        header("Location: absensi_ujian_guru.php?ujian_id=" . $ujian_id . "&kelas_ujian=" . ($filter_kelas_ujian ?: ''));
        exit();
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Error: ' . $e->getMessage());
        header("Location: absensi_ujian_guru.php?ujian_id=" . $ujian_id . "&kelas_ujian=" . ($filter_kelas_ujian ?: ''));
        exit();
    }
}

// Get students for a specific exam (DENGAN MULTI KELAS & FILTER PER KELAS)
$students = [];
$exam = null;
$selected_kelas = null;

if (isset($_GET['ujian_id'])) {
    $ujian_id = $_GET['ujian_id'];
    $selected_kelas = isset($_GET['kelas_ujian']) ? $_GET['kelas_ujian'] : null;
    
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
            
            // Jika filter kelas dipilih, gunakan hanya kelas tersebut
            if ($selected_kelas && in_array($selected_kelas, $kelas_target_array)) {
                $kelas_filter = [$selected_kelas];
            } else {
                $kelas_filter = $kelas_target_array;
            }
            
            // Buat placeholder untuk query IN
            $placeholders = str_repeat('?,', count($kelas_filter) - 1) . '?';
            
            // Get students in the selected classes
            $stmt = $pdo->prepare("
                SELECT s.*, 
                       a.status_hadir, 
                       a.keterangan, 
                       a.waktu_hadir,
                       a.waktu_pulang,
                       a.id as absensi_id
                FROM siswa s
                LEFT JOIN absensi_ujian a ON s.id = a.siswa_id AND a.ujian_id = ?
                WHERE s.kelas IN ($placeholders)
                ORDER BY s.kelas, s.nama ASC
            ");
            
            $params = array_merge([$ujian_id], $kelas_filter);
            $stmt->execute($params);
            $students = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching exam details: " . $e->getMessage());
    }
}
?>

<div class="container-fluid">
    <!-- Flash Messages -->
    <?php displayFlashMessage(); ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-clipboard-check text-primary me-2"></i>Absensi Ujian
        </h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
            <i class="fas fa-filter me-1"></i> Filter
        </button>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filter Ujian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="GET" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="kelas_ujian" class="form-label">Kelas Ujian</label>
                            <select class="form-select" id="kelas_ujian" name="kelas_ujian">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                    <option value="<?php echo htmlspecialchars($kelas); ?>" 
                                        <?php echo ($filter_kelas_ujian == $kelas) ? 'selected' : ''; ?>>
                                        Kelas <?php echo htmlspecialchars($kelas); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Filter ujian berdasarkan kelas target</small>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal" class="form-label">Tanggal Ujian</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" 
                                   value="<?php echo htmlspecialchars($filter_tanggal); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="absensi_ujian_guru.php" class="btn btn-secondary">Reset</a>
                        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($exam)): ?>
        <!-- Exam Details -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Detail Ujian
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Mata Pelajaran</th>
                                <td><?php echo htmlspecialchars($exam['kode_mapel'] . ' - ' . $exam['nama_mapel']); ?></td>
                            </tr>
                            <tr>
                                <th>Judul Ujian</th>
                                <td><?php echo htmlspecialchars($exam['judul_ujian']); ?></td>
                            </tr>
                            <tr>
                                <th>Kelas Target</th>
                                <td><?php echo formatKelasDisplay($exam['kelas_target']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Waktu Mulai</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($exam['waktu_mulai'])); ?></td>
                            </tr>
                            <tr>
                                <th>Waktu Selesai</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($exam['waktu_selesai'])); ?></td>
                            </tr>
                            <tr>
                                <th>Durasi</th>
                                <td><?php echo floor($exam['durasi'] / 60) . ' menit'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Filter Kelas Dalam Ujian -->
                <?php 
                $kelas_target_array = explode(',', $exam['kelas_target']);
                if (count($kelas_target_array) > 1): 
                ?>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Filter Kelas:</strong>
                            <?php foreach ($kelas_target_array as $k): ?>
                                <a href="absensi_ujian_guru.php?ujian_id=<?php echo $exam['id']; ?>&kelas_ujian=<?php echo trim($k); ?>" 
                                   class="btn btn-sm <?php echo ($selected_kelas == trim($k)) ? 'btn-primary' : 'btn-outline-primary'; ?> me-1">
                                    Kelas <?php echo htmlspecialchars(trim($k)); ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if ($selected_kelas): ?>
                                <a href="absensi_ujian_guru.php?ujian_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-times me-1"></i> Hapus Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users me-2"></i>Daftar Peserta Ujian
                    <span class="badge bg-light text-dark ms-2"><?php echo count($students); ?> Siswa</span>
                    <?php if ($selected_kelas): ?>
                        <span class="badge bg-warning text-dark ms-2">Filter: Kelas <?php echo htmlspecialchars($selected_kelas); ?></span>
                    <?php endif; ?>
                </h5>
                <div>
                    <a href="absensi_ujian_guru.php" class="btn btn-light btn-sm me-1">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                    <button class="btn btn-light btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php if ($selected_kelas): ?>
                            Tidak ada siswa di kelas <?php echo htmlspecialchars($selected_kelas); ?> untuk ujian ini.
                        <?php else: ?>
                            Tidak ada siswa di kelas target ujian ini.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>NISN</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Status</th>
                                    <th>Waktu Hadir</th>
                                    <th>Keterangan</th>
                                    <th width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $index => $student): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($student['nisn']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['nama']); ?></strong></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($student['kelas']); ?></span></td>
                                        <td>
                                            <?php 
                                            $status = $student['status_hadir'] ?? 'tidak_hadir';
                                            $badge_class = [
                                                'hadir' => 'bg-success',
                                                'tidak_hadir' => 'bg-danger',
                                                'izin' => 'bg-warning',
                                                'sakit' => 'bg-info'
                                            ][$status];
                                            $status_text = [
                                                'hadir' => 'Hadir',
                                                'tidak_hadir' => 'Tidak Hadir',
                                                'izin' => 'Izin',
                                                'sakit' => 'Sakit'
                                            ][$status];
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($student['waktu_hadir']): ?>
                                                <?php echo date('H:i', strtotime($student['waktu_hadir'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($student['keterangan'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#attendanceModal<?php echo $student['id']; ?>">
                                                <i class="fas fa-edit"></i> Ubah
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Attendance Modal for each student -->
                                    <div class="modal fade" id="attendanceModal<?php echo $student['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Absensi Siswa</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="ujian_id" value="<?php echo $ujian_id; ?>">
                                                        <input type="hidden" name="siswa_id" value="<?php echo $student['id']; ?>">
                                                        <?php if ($selected_kelas): ?>
                                                        <input type="hidden" name="kelas_ujian" value="<?php echo $selected_kelas; ?>">
                                                        <?php endif; ?>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Siswa</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($student['nama'] . ' (' . $student['nisn'] . ') - Kelas ' . $student['kelas']); ?>" 
                                                                   readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Kehadiran</label>
                                                            <select class="form-select" name="status_hadir" required>
                                                                <option value="hadir" <?php echo ($student['status_hadir'] == 'hadir') ? 'selected' : ''; ?>>Hadir</option>
                                                                <option value="tidak_hadir" <?php echo ($student['status_hadir'] == 'tidak_hadir' || !$student['status_hadir']) ? 'selected' : ''; ?>>Tidak Hadir</option>
                                                                <option value="izin" <?php echo ($student['status_hadir'] == 'izin') ? 'selected' : ''; ?>>Izin</option>
                                                                <option value="sakit" <?php echo ($student['status_hadir'] == 'sakit') ? 'selected' : ''; ?>>Sakit</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Keterangan (Opsional)</label>
                                                            <textarea class="form-control" name="keterangan" rows="3" 
                                                                      placeholder="Misal: Sakit panas, Izin keluarga, dll."><?php echo htmlspecialchars($student['keterangan'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="submit_attendance" class="btn btn-primary">Simpan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Attendance Summary -->
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="card stat-card border-success">
                                <div class="card-body text-center">
                                    <h6 class="text-success">Hadir</h6>
                                    <h3>
                                        <?php 
                                        $hadir_count = 0;
                                        foreach ($students as $student) {
                                            if (($student['status_hadir'] ?? 'tidak_hadir') == 'hadir') {
                                                $hadir_count++;
                                            }
                                        }
                                        echo $hadir_count;
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card border-danger">
                                <div class="card-body text-center">
                                    <h6 class="text-danger">Tidak Hadir</h6>
                                    <h3>
                                        <?php 
                                        $tidak_hadir_count = 0;
                                        foreach ($students as $student) {
                                            $status = $student['status_hadir'] ?? 'tidak_hadir';
                                            if ($status == 'tidak_hadir') {
                                                $tidak_hadir_count++;
                                            }
                                        }
                                        echo $tidak_hadir_count;
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card border-warning">
                                <div class="card-body text-center">
                                    <h6 class="text-warning">Izin</h6>
                                    <h3>
                                        <?php 
                                        $izin_count = 0;
                                        foreach ($students as $student) {
                                            if (($student['status_hadir'] ?? '') == 'izin') {
                                                $izin_count++;
                                            }
                                        }
                                        echo $izin_count;
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card border-info">
                                <div class="card-body text-center">
                                    <h6 class="text-info">Sakit</h6>
                                    <h3>
                                        <?php 
                                        $sakit_count = 0;
                                        foreach ($students as $student) {
                                            if (($student['status_hadir'] ?? '') == 'sakit') {
                                                $sakit_count++;
                                            }
                                        }
                                        echo $sakit_count;
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Exam List -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list-alt me-2"></i>Daftar Ujian yang Tersedia
                    <?php if ($filter_kelas_ujian): ?>
                        <span class="badge bg-warning text-dark ms-2">Filter: Kelas <?php echo htmlspecialchars($filter_kelas_ujian); ?></span>
                    <?php endif; ?>
                    <?php if ($filter_tanggal): ?>
                        <span class="badge bg-info text-dark ms-2">Tanggal: <?php echo htmlspecialchars($filter_tanggal); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($ujian_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Judul Ujian</th>
                                    <th>Kelas Target</th>
                                    <th>Waktu</th>
                                    <th>Status</th>
                                    <th width="10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                foreach ($ujian_list as $row): 
                                    $now = time();
                                    $start_time = strtotime($row['waktu_mulai']);
                                    $end_time = strtotime($row['waktu_selesai']);
                                    
                                    if ($now < $start_time) {
                                        $status = "Belum Dimulai";
                                        $status_class = "bg-warning";
                                    } elseif ($now >= $start_time && $now <= $end_time) {
                                        $status = "Sedang Berlangsung";
                                        $status_class = "bg-success";
                                    } else {
                                        $status = "Selesai";
                                        $status_class = "bg-secondary";
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_mapel']); ?></td>
                                        <td><?php echo htmlspecialchars($row['judul_ujian']); ?></td>
                                        <td><?php echo formatKelasDisplay($row['kelas_target']); ?></td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y H:i', $start_time); ?><br>
                                                <span class="text-muted">s/d</span><br>
                                                <?php echo date('d/m/Y H:i', $end_time); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="absensi_ujian_guru.php?ujian_id=<?php echo $row['id']; ?><?php echo $filter_kelas_ujian ? '&kelas_ujian=' . $filter_kelas_ujian : ''; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-clipboard-check"></i> Absensi
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if ($filter_kelas_ujian || $filter_tanggal): ?>
                            Tidak ada ujian yang sesuai dengan filter yang dipilih.
                        <?php else: ?>
                            Tidak ada ujian yang tersedia untuk diambil absensinya.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
</style>

<?php 
// Include footer
if (file_exists('templates/footer.php')) {
    require_once 'templates/footer.php';
} else {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar untuk mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar')?.classList.toggle('active');
        });
    </script>
    </body>
    </html>
    <?php
}
?>