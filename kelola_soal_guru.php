<?php
// kelola_soal_guru.php - Halaman kelola soal khusus untuk guru (dengan multi kelas)
require_once 'config.php';

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

// Pastikan ini bukan admin
if ($_SESSION['is_admin'] ?? false) {
    flashMessage('danger', 'Akses ditolak. Halaman ini untuk guru saja.');
    redirect('dashboard.php');
}

$guru_id = $_SESSION['user_id'];
$is_guru_mapel = $_SESSION['is_guru_mapel'] ?? false;
$mapel_diampu = $_SESSION['mapel_diampu'] ?? [];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? [];

// Dapatkan guru_id dari tabel guru berdasarkan user_id
$stmt = $pdo->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt->execute([$guru_id]);
$guru_data = $stmt->fetch(PDO::FETCH_ASSOC);
$guru_table_id = $guru_data['id'] ?? 0;

// Ambil parameter filter
$filter_mapel = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : '';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$filter_jenis_soal = isset($_GET['jenis_soal']) ? $_GET['jenis_soal'] : '';
$filter_media_type = isset($_GET['media_type']) ? $_GET['media_type'] : '';

// Fungsi untuk mendeteksi jenis soal berdasarkan konten
function deteksiJenisSoal($soal) {
    if (!empty($soal['jenis_soal']) && $soal['jenis_soal'] != 'pilihan_ganda') {
        return $soal['jenis_soal'];
    }
    
    if (!empty($soal['jawaban_kompleks']) && $soal['jawaban_kompleks'] != '[]' && $soal['jawaban_kompleks'] != 'null') {
        return 'pilihan_ganda_kompleks';
    }
    
    $opsi_a_trim = trim($soal['opsi_a'] ?? '');
    $opsi_b_trim = trim($soal['opsi_b'] ?? '');
    if ($opsi_a_trim == 'Benar' && $opsi_b_trim == 'Salah') {
        return 'benar_salah';
    }
    
    $opsi_a_lines = explode("\n", $soal['opsi_a'] ?? '');
    $opsi_b_lines = explode("\n", $soal['opsi_b'] ?? '');
    
    $has_numbered_pattern_a = false;
    $has_numbered_pattern_b = false;
    
    foreach ($opsi_a_lines as $line) {
        if (preg_match('/^\s*\d+\./', trim($line))) {
            $has_numbered_pattern_a = true;
            break;
        }
    }
    
    foreach ($opsi_b_lines as $line) {
        if (preg_match('/^\s*\d+\./', trim($line))) {
            $has_numbered_pattern_b = true;
            break;
        }
    }
    
    $opsi_c_empty = empty($soal['opsi_c']) || trim($soal['opsi_c'] ?? '') == '' || trim($soal['opsi_c'] ?? '') == '-';
    $opsi_d_empty = empty($soal['opsi_d']) || trim($soal['opsi_d'] ?? '') == '' || trim($soal['opsi_d'] ?? '') == '-';
    $opsi_e_empty = empty($soal['opsi_e']) || trim($soal['opsi_e'] ?? '') == '' || trim($soal['opsi_e'] ?? '') == '-';
    
    if ($has_numbered_pattern_a && $has_numbered_pattern_b) {
        if ($opsi_c_empty && $opsi_d_empty && $opsi_e_empty) {
            return 'menjodohkan';
        }
    }
    
    if (!empty($soal['pasangan_jodoh']) && trim($soal['pasangan_jodoh']) != '' && $soal['pasangan_jodoh'] != '-') {
        if (preg_match('/\d+\-\d+/', $soal['pasangan_jodoh'])) {
            return 'menjodohkan';
        }
    }
    
    if ($opsi_c_empty && $opsi_d_empty && $opsi_e_empty) {
        if ($opsi_a_trim != 'Benar' && $opsi_b_trim != 'Salah') {
            if (!($has_numbered_pattern_a && $has_numbered_pattern_b)) {
                return 'essay';
            }
        }
    }
    
    return 'pilihan_ganda';
}

// Fungsi untuk format kelas dari string (bisa comma separated)
function formatKelasDisplay($kelas_str) {
    if (empty($kelas_str)) return '<span class="badge bg-secondary">-</span>';
    $kelas_array = explode(',', $kelas_str);
    $badges = [];
    foreach ($kelas_array as $k) {
        $badges[] = '<span class="badge bg-info me-1">' . htmlspecialchars(trim($k)) . '</span>';
    }
    return implode(' ', $badges);
}

// Ambil daftar kelas berdasarkan yang diampu
if ($is_guru_mapel && count($kelas_diampu) > 0) {
    $kelas_list = $kelas_diampu;
} else {
    $stmt = $pdo->prepare("SELECT DISTINCT kelas FROM soal WHERE created_by = ? ORDER BY kelas");
    $stmt->execute([$guru_table_id]);
    $kelas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// HITUNG STATISTIK
$total_soal = 0;
$total_skor = 0;
$soal_hari_ini = 0;
$soal_dengan_gambar = 0;
$soal_dengan_video = 0;
$soal_pilihan_ganda = 0;
$soal_pilihan_ganda_kompleks = 0;
$soal_essay = 0;
$soal_menjodohkan = 0;
$soal_benar_salah = 0;

if ($is_guru_mapel && count($mapel_diampu) > 0 && count($kelas_diampu) > 0) {
    $mapel_ids = array_column($mapel_diampu, 'id');
    $kelas_values = array_column($kelas_diampu, 'kelas');
    
    $placeholders_mapel = implode(',', array_fill(0, count($mapel_ids), '?'));
    $placeholders_kelas = implode(',', array_fill(0, count($kelas_values), '?'));
    $params = array_merge($mapel_ids, $kelas_values);
    
    // Filter tambahan untuk statistik
    $stat_where = " WHERE mapel_id IN ($placeholders_mapel) AND kelas IN ($placeholders_kelas)";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal $stat_where");
    $stmt->execute($params);
    $total_soal = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(skor), 0) FROM soal $stat_where");
    $stmt->execute($params);
    $total_skor = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal $stat_where AND DATE(created_at) = CURDATE()");
    $stmt->execute($params);
    $soal_hari_ini = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal $stat_where AND gambar_soal IS NOT NULL");
    $stmt->execute($params);
    $soal_dengan_gambar = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal $stat_where AND video_soal IS NOT NULL");
    $stmt->execute($params);
    $soal_dengan_video = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT * FROM soal $stat_where");
    $stmt->execute($params);
    $all_soal_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE created_by = ?");
    $stmt->execute([$guru_table_id]);
    $total_soal = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(skor), 0) FROM soal WHERE created_by = ?");
    $stmt->execute([$guru_table_id]);
    $total_skor = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE created_by = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$guru_table_id]);
    $soal_hari_ini = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE created_by = ? AND gambar_soal IS NOT NULL");
    $stmt->execute([$guru_table_id]);
    $soal_dengan_gambar = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE created_by = ? AND video_soal IS NOT NULL");
    $stmt->execute([$guru_table_id]);
    $soal_dengan_video = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT * FROM soal WHERE created_by = ?");
    $stmt->execute([$guru_table_id]);
    $all_soal_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($all_soal_data as $soal) {
    $jenis = deteksiJenisSoal($soal);
    switch($jenis) {
        case 'pilihan_ganda': $soal_pilihan_ganda++; break;
        case 'pilihan_ganda_kompleks': $soal_pilihan_ganda_kompleks++; break;
        case 'essay': $soal_essay++; break;
        case 'menjodohkan': $soal_menjodohkan++; break;
        case 'benar_salah': $soal_benar_salah++; break;
    }
}

// PERBAIKAN: Daftar mapel untuk dropdown filter (hanya mapel yang diampu guru)
$mapel_options_for_filter = [];
if ($is_guru_mapel) {
    $mapel_options_for_filter = $mapel_diampu;
} else {
    $stmt = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel");
    $mapel_options_for_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$title = "Kelola Soal dengan Media";
include 'templates/header_guru.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Soal - Ujian Online (Guru)</title>
    
    <!-- MathJax untuk render matematika -->
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
    
    <!-- Font untuk Arab dan Matematika -->
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Noto+Naskh+Arabic:wght@400;500;600;700&family=Noto+Sans+Math&family=Lateef&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .arabic-text, .arabic-font {
            font-family: 'Amiri', 'Noto Naskh Arabic', 'Lateef', 'Traditional Arabic', serif;
            font-size: 1.2em;
            direction: rtl;
            text-align: right;
        }
        
        .math-text, .math-font {
            font-family: 'Noto Sans Math', 'Cambria Math', 'STIX Two Math', 'Times New Roman', serif;
            font-size: 1.1em;
        }
        
        .question-content {
            font-family: 'Segoe UI', 'Amiri', 'Noto Sans Math', 'Lateef', sans-serif;
            line-height: 1.6;
        }
        
        .bg-purple { background-color: #6f42c1 !important; }
        .table-responsive { overflow-x: auto; }
        
        .editor-toolbar {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px 5px 0 0;
            padding: 8px 12px;
            margin-bottom: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            border-bottom: none;
        }
        
        .editor-toolbar .btn-group {
            margin-right: 10px;
        }
        
        .editor-toolbar .btn-toolbar-icon {
            background: white;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0 2px;
        }
        
        .editor-toolbar .btn-toolbar-icon:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .editor-toolbar .dropdown-menu {
            min-width: 200px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .editor-toolbar .dropdown-item {
            font-family: monospace;
            font-size: 14px;
            padding: 5px 15px;
            cursor: pointer;
        }
        
        .editor-toolbar .dropdown-item:hover {
            background-color: #007bff;
            color: white;
        }
        
        .editor-toolbar .divider {
            width: 1px;
            background: #dee2e6;
            margin: 0 5px;
        }
        
        textarea.form-control {
            font-family: 'Segoe UI', 'Amiri', 'Noto Sans Math', monospace;
            font-size: 14px;
            border-radius: 0 0 5px 5px;
        }
        
        .math-symbol {
            font-family: 'Noto Sans Math', 'Cambria Math', monospace;
        }
        
        /* Style untuk multiple select */
        select[multiple] {
            min-height: 120px;
            border-radius: 8px;
            padding: 8px;
        }
        select[multiple] option {
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 2px;
        }
        select[multiple] option:checked {
            background-color: #0d6efd linear-gradient(0deg, #0d6efd, #0d6efd);
            color: white;
        }
        .form-text kbd {
            background-color: #f8f9fa;
            border: 1px solid #ccc;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 0.85em;
        }
        
        /* Modal import yang rapi */
        .modal-import-body {
            max-height: 60vh;
            overflow-y: auto;
            padding: 15px;
        }
        .template-table {
            font-size: 12px;
        }
        .template-table td, .template-table th {
            padding: 6px 8px;
        }
        
        @media (max-width: 768px) {
            .table td, .table th { font-size: 12px; }
            .btn-group-sm .btn { padding: 0.2rem 0.4rem; }
            .editor-toolbar { flex-wrap: wrap; }
            select[multiple] { min-height: 100px; }
            .modal-import-body { max-height: 50vh; }
        }
    </style>
</head>
<body>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><i class="fas fa-question-circle me-2"></i>Kelola Soal dengan Media</h2>
            <p class="text-muted mb-0">Kelola bank soal dengan berbagai jenis: Pilihan Ganda (A-E), Pilihan Ganda Kompleks, Essay, Menjodohkan, Benar/Salah</p>
        </div>
        <div>
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importSoalModal">
                <i class="fas fa-file-excel me-2"></i>Import Excel
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#soalModal" onclick="resetSoalForm()">
                <i class="fas fa-plus me-2"></i>Tambah Soal
            </button>
        </div>
    </div>

    <?php 
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                <i class='fas fa-info-circle me-2'></i>$message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        unset($_SESSION['flash']);
    }
    
    if (isset($_SESSION['import_errors'])) {
        echo '<div class="alert alert-warning">';
        echo '<h6><i class="fas fa-exclamation-triangle me-2"></i>Detail Error Import:</h6>';
        echo '<ul class="mb-0">';
        foreach ($_SESSION['import_errors'] as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        unset($_SESSION['import_errors']);
    }
    ?>

    <!-- Statistik Cards -->
    <div class="row mb-4 g-3">
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card border-0 bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($total_soal) ?></h4><p class="mb-0 small">Total Soal</p></div>
                        <div class="bg-white bg-opacity-25 p-3 rounded-circle"><i class="fas fa-question-circle fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card border-0 bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($soal_pilihan_ganda) ?></h4><p class="mb-0 small">Pilihan Ganda</p></div>
                        <div class="bg-white bg-opacity-25 p-3 rounded-circle"><i class="fas fa-list-ul fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card border-0 text-white h-100" style="background-color: #6f42c1;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($soal_pilihan_ganda_kompleks) ?></h4><p class="mb-0 small">PG Kompleks</p></div>
                        <div class="bg-white bg-opacity-25 p-3 rounded-circle"><i class="fas fa-check-double fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card border-0 bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($soal_essay) ?></h4><p class="mb-0 small">Essay</p></div>
                        <div class="bg-white bg-opacity-25 p-3 rounded-circle"><i class="fas fa-edit fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card border-0 bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($soal_menjodohkan) ?></h4><p class="mb-0 small">Menjodohkan</p></div>
                        <div class="bg-white bg-opacity-25 p-3 rounded-circle"><i class="fas fa-random fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card border-0 bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($soal_benar_salah) ?></h4><p class="mb-0 small">Benar/Salah</p></div>
                        <div class="bg-white bg-opacity-25 p-3 rounded-circle"><i class="fas fa-check-circle fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card mb-4">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Info Mengajar</h6></div>
                <div class="card-body">
                    <?php if ($is_guru_mapel): ?>
                        <?php if (count($mapel_diampu) > 0): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Mata Pelajaran:</small>
                            <?php foreach ($mapel_diampu as $mapel): ?>
                                <span class="badge bg-primary mb-1 me-1"><?php echo htmlspecialchars($mapel['kode_mapel'] ?? $mapel['nama_mapel']); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (count($kelas_diampu) > 0): ?>
                        <div>
                            <small class="text-muted d-block mb-1">Kelas:</small>
                            <?php foreach ($kelas_diampu as $kelas): ?>
                                <span class="badge bg-success mb-1 me-1"><?php echo htmlspecialchars($kelas['kelas']); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0"><i class="fas fa-info-circle me-2"></i>Anda dapat membuat soal untuk semua mapel dan kelas</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="dashboard_guru.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-tachometer-alt text-primary me-3"></i>
                            <div><div class="fw-bold">Dashboard</div><small class="text-muted">Kembali ke dashboard</small></div>
                        </a>
                        <a href="hasil_ujian_guru.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-chart-line text-success me-3"></i>
                            <div><div class="fw-bold">Hasil Ujian</div><small class="text-muted">Lihat hasil ujian</small></div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Jenis Soal</h6></div>
                <div class="card-body p-2">
                    <div class="alert alert-primary p-2 mb-2"><small><strong>Pilihan Ganda (A-E):</strong><br>• 5 opsi jawaban (A, B, C, D, E)<br>• 1 jawaban benar<br>• Auto grading</small></div>
                    <div class="alert p-2 mb-2" style="background-color: #6f42c1; color: white;"><small><strong>Pilihan Ganda Kompleks:</strong><br>• 5 opsi jawaban (A, B, C, D, E)<br>• Bisa memiliki >1 jawaban benar<br>• Skor dihitung per jawaban benar</small></div>
                    <div class="alert alert-info p-2 mb-2"><small><strong>Essay:</strong><br>• Jawaban uraian<br>• Koreksi manual</small></div>
                    <div class="alert alert-warning p-2 mb-2"><small><strong>Menjodohkan:</strong><br>• Opsi A: pernyataan kiri<br>• Opsi B: pernyataan kanan</small></div>
                    <div class="alert alert-danger p-2 mb-2"><small><strong>Benar/Salah:</strong><br>• Opsi A: "Benar"<br>• Opsi B: "Salah"</small></div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Filter -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Soal</h6>
                    <div>
                        <a href="download_resume_soal_pdf.php<?= !empty($_GET) ? '?' . http_build_query($_GET) : '' ?>" class="btn btn-danger btn-sm me-2" target="_blank">
                            <i class="fas fa-file-pdf me-1"></i> Download Resume Soal (PDF)
                        </a>
                        <a href="kelola_soal_guru.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-refresh me-1"></i> Reset Filter
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <?php if (!$is_guru_mapel || count($mapel_diampu) > 1): ?>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Mata Pelajaran</label>
                            <select class="form-select" name="mapel_id">
                                <option value="">Semua Mapel</option>
                                <?php foreach ($mapel_options_for_filter as $mapel): ?>
                                    <?php $selected = ($filter_mapel == $mapel['id']) ? 'selected' : ''; ?>
                                    <?php $display_text = isset($mapel['kode_mapel']) ? "{$mapel['kode_mapel']} - {$mapel['nama_mapel']}" : $mapel['nama_mapel']; ?>
                                    <option value="<?= $mapel['id'] ?>" <?= $selected ?>><?= htmlspecialchars($display_text) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php elseif ($is_guru_mapel && count($mapel_diampu) == 1): ?>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Mata Pelajaran</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($mapel_diampu[0]['kode_mapel'] . ' - ' . $mapel_diampu[0]['nama_mapel']) ?>" readonly disabled>
                            <input type="hidden" name="mapel_id" value="<?= $mapel_diampu[0]['id'] ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Kelas</label>
                            <select class="form-select" name="kelas">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= $kelas['kelas'] ?>" <?= ($filter_kelas == $kelas['kelas']) ? 'selected' : '' ?>><?= htmlspecialchars($kelas['kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Jenis Soal</label>
                            <select class="form-select" name="jenis_soal">
                                <option value="">Semua Jenis</option>
                                <option value="pilihan_ganda" <?= ($filter_jenis_soal == 'pilihan_ganda') ? 'selected' : '' ?>>Pilihan Ganda (A-E)</option>
                                <option value="pilihan_ganda_kompleks" <?= ($filter_jenis_soal == 'pilihan_ganda_kompleks') ? 'selected' : '' ?>>Pilihan Ganda Kompleks</option>
                                <option value="essay" <?= ($filter_jenis_soal == 'essay') ? 'selected' : '' ?>>Essay</option>
                                <option value="menjodohkan" <?= ($filter_jenis_soal == 'menjodohkan') ? 'selected' : '' ?>>Menjodohkan</option>
                                <option value="benar_salah" <?= ($filter_jenis_soal == 'benar_salah') ? 'selected' : '' ?>>Benar/Salah</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tipe Media</label>
                            <select class="form-select" name="media_type">
                                <option value="">Semua Tipe</option>
                                <option value="gambar" <?= ($filter_media_type == 'gambar') ? 'selected' : '' ?>>Dengan Gambar</option>
                                <option value="video" <?= ($filter_media_type == 'video') ? 'selected' : '' ?>>Dengan Video</option>
                                <option value="tanpa_media" <?= ($filter_media_type == 'tanpa_media') ? 'selected' : '' ?>>Tanpa Media</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mt-3">
                            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter me-2"></i>Terapkan Filter</button>
                            <a href="kelola_soal_guru.php" class="btn btn-outline-secondary"><i class="fas fa-refresh me-2"></i>Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel Soal -->
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="fas fa-table me-2"></i>Daftar Soal</h6>
                    <?php if ($filter_mapel || $filter_kelas || $filter_jenis_soal || $filter_media_type): ?>
                        <span class="badge bg-warning">Filter Aktif</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="50" class="text-center">No</th>
                                    <th>Pertanyaan & Media</th>
                                    <th width="100" class="text-center">Jenis</th>
                                    <th width="80" class="text-center">Mapel</th>
                                    <th width="120" class="text-center">Kelas</th>
                                    <th width="100" class="text-center">Jawaban/Skor</th>
                                    <th width="100" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $where = "WHERE 1=1";
                                $params = [];
                                
                                // PERBAIKAN: Filter berdasarkan mapel yang diampu guru
                                if ($is_guru_mapel && count($mapel_diampu) > 0) {
                                    $mapel_ids = array_column($mapel_diampu, 'id');
                                    $placeholders_mapel = implode(',', array_fill(0, count($mapel_ids), '?'));
                                    $where .= " AND s.mapel_id IN ($placeholders_mapel)";
                                    $params = array_merge($params, $mapel_ids);
                                } else {
                                    $where .= " AND s.created_by = ?";
                                    $params[] = $guru_table_id;
                                }
                                
                                // Filter mapel tambahan
                                if (!empty($filter_mapel)) {
                                    $where .= " AND s.mapel_id = ?";
                                    $params[] = $filter_mapel;
                                }
                                
                                // Filter kelas - menggunakan FIND_IN_SET untuk multi kelas
                                if (!empty($filter_kelas)) {
                                    $where .= " AND (FIND_IN_SET(?, s.kelas) OR s.kelas = ?)";
                                    $params[] = $filter_kelas;
                                    $params[] = $filter_kelas;
                                }
                                
                                // Filter media
                                if (!empty($filter_media_type)) {
                                    if ($filter_media_type == 'gambar') {
                                        $where .= " AND s.gambar_soal IS NOT NULL AND s.gambar_soal != ''";
                                    } elseif ($filter_media_type == 'video') {
                                        $where .= " AND s.video_soal IS NOT NULL AND s.video_soal != ''";
                                    } elseif ($filter_media_type == 'tanpa_media') {
                                        $where .= " AND (s.gambar_soal IS NULL OR s.gambar_soal = '') AND (s.video_soal IS NULL OR s.video_soal = '')";
                                    }
                                }
                                
                                $sql = "SELECT s.*, mp.nama_mapel, mp.kode_mapel FROM soal s LEFT JOIN mata_pelajaran mp ON s.mapel_id = mp.id $where ORDER BY s.created_at DESC";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute($params);
                                
                                $no = 1;
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                                    $jenis_soal = deteksiJenisSoal($row);
                                    // Filter jenis soal (dilakukan di PHP karena jenis soal bersifat dinamis)
                                    if (!empty($filter_jenis_soal) && $jenis_soal != $filter_jenis_soal) continue;
                                    
                                    $pertanyaan_pendek = strip_tags($row['pertanyaan']);
                                    if (strlen($pertanyaan_pendek) > 60) $pertanyaan_pendek = substr($pertanyaan_pendek, 0, 60) . '...';
                                    
                                    $has_gambar_soal = !empty($row['gambar_soal']) && $row['gambar_soal'] != '';
                                    $has_video_soal = !empty($row['video_soal']) && $row['video_soal'] != '';
                                    $has_media = $has_gambar_soal || $has_video_soal;
                                    
                                    $jenis_badge_class = '';
                                    $jenis_text = '';
                                    switch($jenis_soal) {
                                        case 'pilihan_ganda': $jenis_badge_class = 'bg-primary'; $jenis_text = 'PG (A-E)'; break;
                                        case 'pilihan_ganda_kompleks': $jenis_badge_class = 'bg-purple'; $jenis_text = 'PG Kompleks'; break;
                                        case 'essay': $jenis_badge_class = 'bg-info'; $jenis_text = 'Essay'; break;
                                        case 'menjodohkan': $jenis_badge_class = 'bg-warning'; $jenis_text = 'Jodoh'; break;
                                        case 'benar_salah': $jenis_badge_class = 'bg-danger'; $jenis_text = 'B/S'; break;
                                        default: $jenis_badge_class = 'bg-secondary'; $jenis_text = strtoupper($jenis_soal);
                                    }
                                    
                                    $jawaban_display = '';
                                    if ($jenis_soal == 'pilihan_ganda') {
                                        $jawaban_display = '<span class="badge bg-success">' . strtoupper($row['jawaban_benar'] ?? '?') . '</span>';
                                    } elseif ($jenis_soal == 'pilihan_ganda_kompleks') {
                                        $jawaban_kompleks = json_decode($row['jawaban_kompleks'] ?? '[]', true);
                                        $jawaban_labels = [];
                                        if (is_array($jawaban_kompleks)) {
                                            foreach ($jawaban_kompleks as $jwb) $jawaban_labels[] = '<span class="badge bg-success me-1">' . strtoupper($jwb) . '</span>';
                                        }
                                        $jawaban_display = '<div class="mb-1">' . implode(' ', $jawaban_labels) . '</div><small class="text-muted">' . ($row['skor_per_jawaban'] ?? 0) . ' poin/jawaban</small>';
                                    } elseif ($jenis_soal == 'essay') {
                                        $jawaban_display = '<span class="badge bg-info">Essay</span><br><small class="text-muted">Manual</small>';
                                    } elseif ($jenis_soal == 'menjodohkan') {
                                        $jawaban_display = '<span class="badge bg-warning">Jodoh</span><br><small class="text-muted">Manual</small>';
                                    } elseif ($jenis_soal == 'benar_salah') {
                                        $jawaban_bs = ($row['jawaban_benar'] == 'a') ? 'Benar' : 'Salah';
                                        $jawaban_display = '<span class="badge ' . ($row['jawaban_benar'] == 'a' ? 'bg-success' : 'bg-danger') . '">' . $jawaban_bs . '</span>';
                                    }
                                    
                                    $kelas_display = formatKelasDisplay($row['kelas']);
                                ?>
                                <tr>
                                    <td class="text-center align-middle"><span class="badge bg-secondary"><?= $no++ ?></span></td>
                                    <td class="align-middle">
                                        <div class="fw-bold text-dark mb-1 question-content"><?= htmlspecialchars($pertanyaan_pendek) ?></div>
                                        <?php if ($has_media): ?>
                                            <div class="d-flex flex-wrap gap-1 mb-1">
                                                <?php if ($has_gambar_soal): ?><span class="badge bg-success"><i class="fas fa-image me-1"></i>Gambar</span><?php endif; ?>
                                                <?php if ($has_video_soal): ?><span class="badge bg-warning"><i class="fas fa-video me-1"></i>Video</span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center align-middle"><span class="badge <?= $jenis_badge_class ?>"><?= $jenis_text ?></span></td>
                                    <td class="text-center align-middle"><span class="badge bg-info"><?= htmlspecialchars($row['kode_mapel'] ?? '-') ?></span></td>
                                    <td class="text-center align-middle"><?= $kelas_display ?></small></td>
                                    <td class="text-center align-middle"><?= $jawaban_display ?><div class="small text-muted mt-1"><?= $row['skor'] ?? 0 ?> poin</div></td>
                                    <td class="text-center align-middle">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="detailSoal(<?= $row['id'] ?>, '<?= $jenis_soal ?>')" title="Detail"><i class="fas fa-eye"></i></button>
                                            <button class="btn btn-outline-warning" onclick="editSoal(<?= $row['id'] ?>, '<?= $jenis_soal ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-outline-danger" onclick="hapusSoal(<?= $row['id'] ?>)" title="Hapus"><i class="fas fa-trash"></i></button>
                                        </div>
                                     </small></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if ($no == 1): ?>
                                <tr><td colspan="7" class="text-center py-5"><div class="text-muted"><i class="fas fa-inbox fa-3x mb-3"></i><h5>Tidak ada soal ditemukan</h5></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Import Soal -->
<div class="modal fade" id="importSoalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width: 700px;">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title"><i class="fas fa-file-excel me-2"></i>Import Soal dari Excel/CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_import_soal_guru.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body" style="max-height: 65vh; overflow-y: auto;">
                    <div class="card border-success mb-3">
                        <div class="card-header bg-success text-white py-1">
                            <h6 class="mb-0"><i class="fas fa-download me-2"></i>Download Template</h6>
                        </div>
                        <div class="card-body text-center py-2">
                            <a href="download_template_soal_guru.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-excel me-2"></i>Download Template Excel/CSV
                            </a>
                            <small class="d-block text-muted mt-1">Format CSV dengan delimiter titik koma (;)</small>
                        </div>
                    </div>
                    
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white py-1">
                            <h6 class="mb-0"><i class="fas fa-upload me-2"></i>Upload File</h6>
                        </div>
                        <div class="card-body">
                            <input type="file" class="form-control form-control-sm" name="file_excel" accept=".xlsx,.xls,.csv" required>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="skip_duplicate" id="skipDuplicate" checked>
                                <label class="form-check-label small" for="skipDuplicate">Lewati soal duplikat (berdasarkan pertanyaan)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info p-2 mb-0">
                        <h6 class="mb-1"><i class="fas fa-info-circle me-2"></i>Format File Excel/CSV</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 template-table">
                                <thead>
                                    <tr><th>jenis_soal</th><th>mapel_id</th><th>kelas</th><th>pertanyaan</th><th>opsi_a</th><th>opsi_b</th><th>opsi_c</th><th>opsi_d</th><th>opsi_e</th><th>jawaban_benar</th><th>skor</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="small">pilihan_ganda</td><td class="small">1</td><td class="small">7A</td><td class="small">Pertanyaan?</td><td class="small">Jawaban A</td><td class="small">Jawaban B</td><td class="small">Jawaban C</td><td class="small">Jawaban D</td><td class="small">Jawaban E</td><td class="small">a</td><td class="small">10</td></tr>
                                    <tr><td class="small">pilihan_ganda</td><td class="small">1</td><td class="small">7A,7B,8A</td><td class="small">Multi Kelas</td><td class="small">Jawaban A</td><td class="small">Jawaban B</td><td class="small">Jawaban C</td><td class="small">Jawaban D</td><td class="small">Jawaban E</td><td class="small">b</td><td class="small">10</td></tr>
                                    <tr><td class="small">pilihan_ganda_kompleks</td><td class="small">1</td><td class="small">7A</td><td class="small">PG Kompleks</td><td class="small">Jawaban A</td><td class="small">Jawaban B</td><td class="small">Jawaban C</td><td class="small">Jawaban D</td><td class="small">Jawaban E</td><td class="small">a,c,e</td><td class="small">15</td></tr>
                                    <tr><td class="small">essay</td><td class="small">1</td><td class="small">8A</td><td class="small">Soal Essay</td><td colspan="6" class="text-center">-</td><td class="small">20</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Catatan:</strong> Untuk multi kelas, pisahkan dengan koma (contoh: 7A,7B,8A). Gunakan delimiter CSV: titik koma (;)
                        </small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-upload me-2"></i>Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH/EDIT SOAL (SATU MODAL) - DENGAN MULTI KELAS -->
<div class="modal fade" id="soalModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" id="soalModalHeader">
                <h5 class="modal-title" id="soalModalTitle"><i class="fas fa-plus me-2"></i>Tambah Soal Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_soal_guru.php" method="POST" enctype="multipart/form-data" id="formSoal">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="editId" value="">
                <div class="modal-body">
                    <!-- Pilih Jenis Soal -->
                    <div class="card mb-3">
                        <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-list me-2"></i>Pilih Jenis Soal</h6></div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-2 col-6 mb-2"><input type="radio" class="btn-check" name="jenis_soal" id="jenis_pg" value="pilihan_ganda" autocomplete="off" checked><label class="btn btn-outline-primary w-100" for="jenis_pg"><i class="fas fa-list-ul"></i><br><small>Pilihan Ganda</small></label></div>
                                <div class="col-md-2 col-6 mb-2"><input type="radio" class="btn-check" name="jenis_soal" id="jenis_pg_kompleks" value="pilihan_ganda_kompleks" autocomplete="off"><label class="btn btn-outline-secondary w-100" for="jenis_pg_kompleks" style="border-color:#6f42c1; color:#6f42c1;"><i class="fas fa-check-double"></i><br><small>PG Kompleks</small></label></div>
                                <div class="col-md-2 col-6 mb-2"><input type="radio" class="btn-check" name="jenis_soal" id="jenis_essay" value="essay" autocomplete="off"><label class="btn btn-outline-info w-100" for="jenis_essay"><i class="fas fa-edit"></i><br><small>Essay</small></label></div>
                                <div class="col-md-2 col-6 mb-2"><input type="radio" class="btn-check" name="jenis_soal" id="jenis_jodoh" value="menjodohkan" autocomplete="off"><label class="btn btn-outline-warning w-100" for="jenis_jodoh"><i class="fas fa-random"></i><br><small>Menjodohkan</small></label></div>
                                <div class="col-md-2 col-6 mb-2"><input type="radio" class="btn-check" name="jenis_soal" id="jenis_bs" value="benar_salah" autocomplete="off"><label class="btn btn-outline-danger w-100" for="jenis_bs"><i class="fas fa-check-circle"></i><br><small>Benar/Salah</small></label></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Dasar -->
                    <div class="card mb-3">
                        <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Dasar</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Mata Pelajaran <span class="text-danger">*</span></label>
                                    <select class="form-select" name="mapel_id" id="mapel_id" required>
                                        <option value="">Pilih Mata Pelajaran</option>
                                        <?php
                                        $mapel_options = $is_guru_mapel ? $mapel_diampu : [];
                                        if (!$is_guru_mapel) {
                                            $stmt = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel");
                                            $mapel_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        }
                                        foreach ($mapel_options as $mapel) {
                                            $display_text = isset($mapel['kode_mapel']) ? "{$mapel['kode_mapel']} - {$mapel['nama_mapel']}" : $mapel['nama_mapel'];
                                            echo "<option value='{$mapel['id']}'>" . htmlspecialchars($display_text) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Kelas <span class="text-danger">*</span></label>
                                    <div class="form-group">
                                        <select name="kelas[]" id="kelas" class="form-select" multiple size="4">
                                            <?php foreach ($kelas_list as $kelas): ?>
                                            <option value="<?= $kelas['kelas'] ?>">Kelas <?= htmlspecialchars($kelas['kelas']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text mt-2">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <strong>Petunjuk:</strong> Tekan <kbd>Ctrl</kbd> (Windows) atau <kbd>Cmd</kbd> (Mac) untuk memilih lebih dari satu kelas.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dynamic Form Container -->
                    <div id="dynamicFormContainer"></div>
                    
                    <!-- Skor -->
                    <div class="card">
                        <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-star me-2"></i>Skor & Simpan</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Total Skor Soal <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="skor" id="total_skor" value="10" min="1" max="100" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Dibuat Oleh</label>
                                    <input type="text" class="form-control" value="<?= $_SESSION['nama_lengkap'] ?>" readonly>
                                    <input type="hidden" name="created_by" value="<?= $guru_table_id ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSimpan">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Soal -->
<div class="modal fade" id="detailSoalModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Detail Soal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailSoalContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== FUNGSI UTAMA ====================
function hapusSoal(id) {
    if (confirm('Apakah Anda yakin ingin menghapus soal ini?')) {
        window.location.href = 'proses_soal_guru.php?action=hapus&id=' + id;
    }
}

// Data simbol matematika untuk dropdown
const mathSymbols = {
    'Pangkat & Akar': ['x²', 'x³', 'xⁿ', '√', '∛', '∜', '√x', '∛x'],
    'Kalkulus': ['∫', '∬', '∭', '∮', '∑', '∏', '∂', '∇', '∆'],
    'Operasi': ['+', '-', '±', '∓', '×', '÷', '∗', '·', '⁄', '√', '∛'],
    'Perbandingan': ['=', '≠', '≈', '≡', '≅', '≤', '≥', '<', '>', '≪', '≫'],
    'Huruf Yunani': ['α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω', 'Γ', 'Δ', 'Θ', 'Λ', 'Ξ', 'Π', 'Σ', 'Φ', 'Ψ', 'Ω'],
    'Geometri': ['∠', '⊥', '∥', '∡', '∢', '△', '□', '○', '◊', '∎', '∴', '∵', '∼', '∽', '≀'],
    'Logika': ['→', '⇒', '⇔', '↔', '↦', '∀', '∃', '∄', '¬', '∧', '∨', '⊕', '⊻', '⊤', '⊥'],
    'Himpunan': ['∈', '∉', '∋', '∌', '⊂', '⊃', '⊆', '⊇', '⊄', '⊅', '∪', '∩', '∅', 'ℕ', 'ℤ', 'ℚ', 'ℝ', 'ℂ'],
    'Pecahan': ['½', '¼', '¾', '⅓', '⅔', '⅕', '⅖', '⅗', '⅘', '⅙', '⅚', '⅛', '⅜', '⅝', '⅞'],
    'Lainnya': ['∞', '°', '′', '″', '‰', '‱', '℅', '℉', '℃', 'Ω', '℧', '♯', '♭', '♪']
};

function insertMathSymbol(symbol, textareaId) {
    var textarea = document.getElementById(textareaId);
    if (!textarea) { alert('Klik pada kolom teks terlebih dahulu!'); return; }
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var text = textarea.value;
    textarea.value = text.substring(0, start) + symbol + text.substring(end);
    textarea.focus();
    textarea.selectionStart = start + symbol.length;
    textarea.selectionEnd = start + symbol.length;
}

function wrapText(before, after, textareaId) {
    var textarea = document.getElementById(textareaId);
    if (!textarea) return;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var selected = textarea.value.substring(start, end);
    var text = textarea.value;
    if (selected) {
        textarea.value = text.substring(0, start) + before + selected + after + text.substring(end);
        textarea.selectionStart = start + before.length;
        textarea.selectionEnd = start + before.length + selected.length;
    } else {
        textarea.value = text.substring(0, start) + before + after + text.substring(end);
        textarea.selectionStart = start + before.length;
        textarea.selectionEnd = start + before.length;
    }
    textarea.focus();
}

function createSymbolDropdown(textareaId) {
    const dropdown = document.createElement('div');
    dropdown.className = 'btn-group';
    dropdown.setAttribute('style', 'margin-right: 5px;');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn-toolbar-icon';
    button.setAttribute('data-bs-toggle', 'dropdown');
    button.setAttribute('aria-expanded', 'false');
    button.innerHTML = '<i class="fas fa-superscript"></i> <i class="fas fa-chevron-down" style="font-size: 10px;"></i>';
    button.title = 'Simbol Matematika';
    const dropdownMenu = document.createElement('ul');
    dropdownMenu.className = 'dropdown-menu';
    dropdownMenu.setAttribute('style', 'max-height: 400px; overflow-y: auto;');
    for (const [category, symbols] of Object.entries(mathSymbols)) {
        const categoryHeader = document.createElement('li');
        categoryHeader.innerHTML = `<h6 class="dropdown-header" style="background: #f8f9fa;">${category}</h6>`;
        dropdownMenu.appendChild(categoryHeader);
        symbols.forEach(symbol => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.className = 'dropdown-item';
            link.href = '#';
            link.innerHTML = `<span style="font-family: monospace; font-size: 16px;">${symbol}</span> <span class="text-muted ms-2" style="font-size: 11px;">${symbol}</span>`;
            link.onclick = (function(s, id) { return function(e) { e.preventDefault(); insertMathSymbol(s, id); }; })(symbol, textareaId);
            item.appendChild(link);
            dropdownMenu.appendChild(item);
        });
        const separator = document.createElement('li');
        separator.innerHTML = '<hr class="dropdown-divider">';
        dropdownMenu.appendChild(separator);
    }
    dropdown.appendChild(button);
    dropdown.appendChild(dropdownMenu);
    return dropdown;
}

function createFullEditorToolbar(textareaId) {
    const toolbar = document.createElement('div');
    toolbar.className = 'editor-toolbar';
    const textGroup = document.createElement('div');
    textGroup.className = 'btn-group';
    textGroup.innerHTML = `<button type="button" class="btn-toolbar-icon" onclick="wrapText('**', '**', '${textareaId}')" title="Bold"><b>B</b></button><button type="button" class="btn-toolbar-icon" onclick="wrapText('*', '*', '${textareaId}')" title="Italic"><i>I</i></button><button type="button" class="btn-toolbar-icon" onclick="wrapText('__', '__', '${textareaId}')" title="Underline"><u>U</u></button><button type="button" class="btn-toolbar-icon" onclick="wrapText('~~', '~~', '${textareaId}')" title="Strikethrough"><s>S</s></button>`;
    toolbar.appendChild(textGroup);
    toolbar.appendChild(document.createElement('div')).className = 'divider';
    const headingGroup = document.createElement('div');
    headingGroup.className = 'btn-group';
    headingGroup.innerHTML = `<button type="button" class="btn-toolbar-icon" onclick="wrapText('# ', '', '${textareaId}')" title="Heading 1">H1</button><button type="button" class="btn-toolbar-icon" onclick="wrapText('## ', '', '${textareaId}')" title="Heading 2">H2</button><button type="button" class="btn-toolbar-icon" onclick="wrapText('### ', '', '${textareaId}')" title="Heading 3">H3</button>`;
    toolbar.appendChild(headingGroup);
    toolbar.appendChild(document.createElement('div')).className = 'divider';
    const listGroup = document.createElement('div');
    listGroup.className = 'btn-group';
    listGroup.innerHTML = `<button type="button" class="btn-toolbar-icon" onclick="wrapText('- ', '', '${textareaId}')" title="Bullet List"><i class="fas fa-list-ul"></i></button><button type="button" class="btn-toolbar-icon" onclick="wrapText('1. ', '', '${textareaId}')" title="Numbered List"><i class="fas fa-list-ol"></i></button><button type="button" class="btn-toolbar-icon" onclick="wrapText('- [ ] ', '', '${textareaId}')" title="Checklist"><i class="fas fa-check-square"></i></button>`;
    toolbar.appendChild(listGroup);
    toolbar.appendChild(document.createElement('div')).className = 'divider';
    toolbar.appendChild(createSymbolDropdown(textareaId));
    toolbar.appendChild(document.createElement('div')).className = 'divider';
    const latexGroup = document.createElement('div');
    latexGroup.className = 'btn-group';
    latexGroup.innerHTML = `<button type="button" class="btn-toolbar-icon" onclick="wrapText('$', '$', '${textareaId}')" title="Inline Math (LaTeX)"><i class="fas fa-square-root-alt"></i> $</button><button type="button" class="btn-toolbar-icon" onclick="wrapText('$$\\n', '\\n$$', '${textareaId}')" title="Display Math (LaTeX)"><i class="fas fa-square-root-alt"></i> $$</button>`;
    toolbar.appendChild(latexGroup);
    return toolbar;
}

// ==================== LOAD FORM ====================
function loadDynamicForm(jenis) {
    const container = document.getElementById('dynamicFormContainer');
    if (jenis === 'pilihan_ganda') {
        container.innerHTML = `
            <div class="card mb-3"><div class="card-header bg-light">Pertanyaan & Media</div><div class="card-body">
                <label class="form-label fw-bold">Pertanyaan <span class="text-danger">*</span></label>
                <textarea class="form-control question-content" name="pertanyaan" id="txt_pertanyaan" rows="4" required placeholder="Tulis pertanyaan di sini... (support Arab & Matematika)"></textarea>
                <div class="row mt-3"><div class="col-md-6"><label>Gambar Soal</label><input type="file" class="form-control" name="gambar_soal" accept="image/*" id="gambar_soal"><div id="gambarPreview"></div></div>
                <div class="col-md-6"><label>Video Soal</label><input type="text" class="form-control" name="video_soal" id="video_soal" placeholder="URL YouTube"></div></div>
            </div></div>
            <div class="card mb-3"><div class="card-header bg-light">Opsi Jawaban (A-E)</div><div class="card-body">
                <div class="row"><div class="col-md-6 mb-2"><label>A <span class="text-danger">*</span></label><textarea class="form-control" name="opsi_a" id="txt_opsi_a" rows="2" required></textarea></div>
                <div class="col-md-6 mb-2"><label>B <span class="text-danger">*</span></label><textarea class="form-control" name="opsi_b" id="txt_opsi_b" rows="2" required></textarea></div>
                <div class="col-md-6 mb-2"><label>C <span class="text-danger">*</span></label><textarea class="form-control" name="opsi_c" id="txt_opsi_c" rows="2" required></textarea></div>
                <div class="col-md-6 mb-2"><label>D <span class="text-danger">*</span></label><textarea class="form-control" name="opsi_d" id="txt_opsi_d" rows="2" required></textarea></div>
                <div class="col-md-6 mb-2"><label>E <span class="text-danger">*</span></label><textarea class="form-control" name="opsi_e" id="txt_opsi_e" rows="2" required></textarea></div>
                <div class="col-md-6 mb-2"><label>Jawaban Benar <span class="text-danger">*</span></label>
                    <select class="form-select" name="jawaban_benar" id="jawaban_benar" required><option value="">Pilih</option><option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option><option value="e">E</option></select>
                </div></div>
            </div></div>
            <input type="hidden" name="jawaban_kompleks" value=""><input type="hidden" name="skor_per_jawaban" value="0"><input type="hidden" name="pasangan_jodoh" value="">
        `;
        setTimeout(() => {
            const textareas = ['txt_pertanyaan', 'txt_opsi_a', 'txt_opsi_b', 'txt_opsi_c', 'txt_opsi_d', 'txt_opsi_e'];
            textareas.forEach(id => {
                const textarea = document.getElementById(id);
                if (textarea && textarea.parentNode) {
                    const toolbar = createFullEditorToolbar(id);
                    textarea.parentNode.insertBefore(toolbar, textarea);
                }
            });
        }, 100);
    } 
    else if (jenis === 'pilihan_ganda_kompleks') {
        container.innerHTML = `
            <div class="card mb-3"><div class="card-header bg-light">Pertanyaan & Media</div><div class="card-body">
                <label class="form-label fw-bold">Pertanyaan <span class="text-danger">*</span></label>
                <textarea class="form-control" name="pertanyaan" id="txt_pertanyaan" rows="4" required placeholder="Tulis pertanyaan di sini..."></textarea>
                <div class="row mt-3"><div class="col-md-6"><label>Gambar Soal</label><input type="file" class="form-control" name="gambar_soal" accept="image/*"></div>
                <div class="col-md-6"><label>Video Soal</label><input type="text" class="form-control" name="video_soal" placeholder="URL YouTube"></div></div>
            </div></div>
            <div class="card mb-3"><div class="card-header bg-light">Opsi Jawaban (Centang yang benar)</div><div class="card-body">
                <div class="row"><div class="col-md-6 mb-2"><label>A</label><textarea class="form-control" name="opsi_a" id="txt_opsi_a" rows="2"></textarea></div>
                <div class="col-md-6 mb-2"><label>B</label><textarea class="form-control" name="opsi_b" id="txt_opsi_b" rows="2"></textarea></div>
                <div class="col-md-6 mb-2"><label>C</label><textarea class="form-control" name="opsi_c" id="txt_opsi_c" rows="2"></textarea></div>
                <div class="col-md-6 mb-2"><label>D</label><textarea class="form-control" name="opsi_d" id="txt_opsi_d" rows="2"></textarea></div>
                <div class="col-md-6 mb-2"><label>E</label><textarea class="form-control" name="opsi_e" id="txt_opsi_e" rows="2"></textarea></div>
                <div class="col-md-6 mb-2"><label>Jawaban Benar (centang lebih dari satu)</label>
                    <div class="border rounded p-2"><label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="a" id="cek_a"> A</label>
                    <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="b" id="cek_b"> B</label>
                    <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="c" id="cek_c"> C</label>
                    <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="d" id="cek_d"> D</label>
                    <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="e" id="cek_e"> E</label></div>
                </div></div>
                <div class="row mt-3"><div class="col-md-6"><label>Skor per Jawaban Benar</label><input type="number" class="form-control" name="skor_per_jawaban" id="skor_per_jawaban" value="5" min="1"></div></div>
            </div></div>
            <input type="hidden" name="jawaban_benar" value="a"><input type="hidden" name="jawaban_kompleks" id="jawaban_kompleks_hidden" value=""><input type="hidden" name="pasangan_jodoh" value="">
        `;
        setTimeout(() => {
            const textareas = ['txt_pertanyaan', 'txt_opsi_a', 'txt_opsi_b', 'txt_opsi_c', 'txt_opsi_d', 'txt_opsi_e'];
            textareas.forEach(id => {
                const textarea = document.getElementById(id);
                if (textarea && textarea.parentNode) {
                    const toolbar = createFullEditorToolbar(id);
                    textarea.parentNode.insertBefore(toolbar, textarea);
                }
            });
        }, 100);
        setTimeout(() => {
            const skorInput = document.getElementById('skor_per_jawaban');
            const totalSkor = document.getElementById('total_skor');
            const checkboxes = document.querySelectorAll('input[name="jawaban_kompleks_checkbox[]"]');
            function updateTotal() {
                const checked = document.querySelectorAll('input[name="jawaban_kompleks_checkbox[]"]:checked').length;
                const skor = parseInt(skorInput.value) || 0;
                if (totalSkor) totalSkor.value = skor * checked;
                document.getElementById('jawaban_kompleks_hidden').value = JSON.stringify(Array.from(document.querySelectorAll('input[name="jawaban_kompleks_checkbox[]"]:checked')).map(cb => cb.value));
            }
            if (skorInput) skorInput.addEventListener('input', updateTotal);
            checkboxes.forEach(cb => cb.addEventListener('change', updateTotal));
        }, 200);
    }
    else if (jenis === 'essay') {
        container.innerHTML = `
            <div class="card mb-3"><div class="card-header bg-light">Pertanyaan Essay</div><div class="card-body">
                <label class="form-label fw-bold">Pertanyaan <span class="text-danger">*</span></label>
                <textarea class="form-control" name="pertanyaan" id="txt_pertanyaan" rows="4" required placeholder="Tulis pertanyaan essay di sini..."></textarea>
                <div class="row mt-3"><div class="col-md-6"><label>Gambar Soal</label><input type="file" class="form-control" name="gambar_soal" accept="image/*"></div>
                <div class="col-md-6"><label>Video Soal</label><input type="text" class="form-control" name="video_soal" placeholder="URL YouTube"></div></div>
                <div class="mt-3"><label>Petunjuk Jawaban (Opsional)</label><textarea class="form-control" name="opsi_a" id="txt_opsi_a" rows="3" placeholder="Berikan petunjuk untuk siswa..."></textarea></div>
                <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> Soal essay akan dikoreksi manual oleh guru.</div>
            </div></div>
            <input type="hidden" name="opsi_b" value="-"><input type="hidden" name="opsi_c" value="-"><input type="hidden" name="opsi_d" value="-"><input type="hidden" name="opsi_e" value="-">
            <input type="hidden" name="jawaban_benar" value="a"><input type="hidden" name="jawaban_kompleks" value=""><input type="hidden" name="skor_per_jawaban" value="0"><input type="hidden" name="pasangan_jodoh" value="">
        `;
        setTimeout(() => {
            const textareas = ['txt_pertanyaan', 'txt_opsi_a'];
            textareas.forEach(id => {
                const textarea = document.getElementById(id);
                if (textarea && textarea.parentNode) {
                    const toolbar = createFullEditorToolbar(id);
                    textarea.parentNode.insertBefore(toolbar, textarea);
                }
            });
        }, 100);
    }
    else if (jenis === 'menjodohkan') {
        container.innerHTML = `
            <div class="card mb-3"><div class="card-header bg-light">Pertanyaan Menjodohkan</div><div class="card-body">
                <textarea class="form-control" name="pertanyaan" id="txt_pertanyaan" rows="3" required placeholder="Instruksi..."></textarea>
                <div class="row mt-3"><div class="col-md-6"><label>Gambar Soal</label><input type="file" class="form-control" name="gambar_soal" accept="image/*"></div>
                <div class="col-md-6"><label>Video Soal</label><input type="text" class="form-control" name="video_soal" placeholder="URL YouTube"></div></div>
                <div class="row mt-3"><div class="col-md-6"><label>Pernyataan Kiri (Soal) <span class="text-danger">*</span></label><textarea class="form-control" name="opsi_a" rows="4" required placeholder="1. Teks A&#10;2. Teks B&#10;3. Teks C"></textarea></div>
                <div class="col-md-6"><label>Pernyataan Kanan (Jawaban) <span class="text-danger">*</span></label><textarea class="form-control" name="opsi_b" rows="4" required placeholder="1. Teks 1&#10;2. Teks 2&#10;3. Teks 3"></textarea></div></div>
                <div class="mt-3"><label>Pasangan Jawaban <span class="text-danger">*</span></label><textarea class="form-control" name="pasangan_jodoh" rows="2" required placeholder="1-1,2-2,3-3"></textarea></div>
            </div></div>
            <input type="hidden" name="opsi_c" value="-"><input type="hidden" name="opsi_d" value="-"><input type="hidden" name="opsi_e" value="-">
            <input type="hidden" name="jawaban_benar" value="a"><input type="hidden" name="jawaban_kompleks" value=""><input type="hidden" name="skor_per_jawaban" value="0">
        `;
        setTimeout(() => {
            const textarea = document.getElementById('txt_pertanyaan');
            if (textarea && textarea.parentNode) {
                const toolbar = createFullEditorToolbar('txt_pertanyaan');
                textarea.parentNode.insertBefore(toolbar, textarea);
            }
        }, 100);
    }
    else if (jenis === 'benar_salah') {
        container.innerHTML = `
            <div class="card mb-3"><div class="card-header bg-light">Pernyataan Benar/Salah</div><div class="card-body">
                <textarea class="form-control" name="pertanyaan" id="txt_pertanyaan" rows="4" required placeholder="Tulis pernyataan..."></textarea>
                <div class="row mt-3"><div class="col-md-6"><label>Gambar Soal</label><input type="file" class="form-control" name="gambar_soal" accept="image/*"></div>
                <div class="col-md-6"><label>Video Soal</label><input type="text" class="form-control" name="video_soal" placeholder="URL YouTube"></div></div>
                <div class="row mt-3"><div class="col-md-6"><label>Opsi A</label><input type="text" class="form-control" name="opsi_a" value="Benar" readonly></div>
                <div class="col-md-6"><label>Opsi B</label><input type="text" class="form-control" name="opsi_b" value="Salah" readonly></div></div>
                <div class="row mt-3"><div class="col-md-6"><label>Jawaban Benar <span class="text-danger">*</span></label><select class="form-select" name="jawaban_benar" id="jawaban_benar" required><option value="a">Benar</option><option value="b">Salah</option></select></div></div>
            </div></div>
            <input type="hidden" name="opsi_c" value="-"><input type="hidden" name="opsi_d" value="-"><input type="hidden" name="opsi_e" value="-">
            <input type="hidden" name="jawaban_kompleks" value=""><input type="hidden" name="skor_per_jawaban" value="0"><input type="hidden" name="pasangan_jodoh" value="">
        `;
        setTimeout(() => {
            const textarea = document.getElementById('txt_pertanyaan');
            if (textarea && textarea.parentNode) {
                const toolbar = createFullEditorToolbar('txt_pertanyaan');
                textarea.parentNode.insertBefore(toolbar, textarea);
            }
        }, 100);
    }
}

// ==================== RESET FORM UNTUK TAMBAH ====================
function resetSoalForm() {
    document.getElementById('formAction').value = 'tambah';
    document.getElementById('editId').value = '';
    document.getElementById('soalModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Tambah Soal Baru';
    document.getElementById('soalModalHeader').className = 'modal-header bg-primary text-white';
    document.getElementById('btnSimpan').innerHTML = 'Simpan Soal';
    document.getElementById('btnSimpan').className = 'btn btn-primary';
    document.getElementById('mapel_id').value = '';
    
    // Reset multiple select
    const kelasSelect = document.getElementById('kelas');
    if (kelasSelect) {
        for(let i = 0; i < kelasSelect.options.length; i++) {
            kelasSelect.options[i].selected = false;
        }
    }
    
    document.getElementById('total_skor').value = '10';
    document.getElementById('jenis_pg').checked = true;
    loadDynamicForm('pilihan_ganda');
}

// ==================== EDIT SOAL ====================
function editSoal(id, jenis) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('editId').value = id;
    document.getElementById('soalModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Soal';
    document.getElementById('soalModalHeader').className = 'modal-header bg-warning text-white';
    document.getElementById('btnSimpan').innerHTML = 'Update Soal';
    document.getElementById('btnSimpan').className = 'btn btn-warning';
    
    let radioId = 'jenis_pg';
    if (jenis === 'pilihan_ganda') radioId = 'jenis_pg';
    else if (jenis === 'pilihan_ganda_kompleks') radioId = 'jenis_pg_kompleks';
    else if (jenis === 'essay') radioId = 'jenis_essay';
    else if (jenis === 'menjodohkan') radioId = 'jenis_jodoh';
    else if (jenis === 'benar_salah') radioId = 'jenis_bs';
    document.getElementById(radioId).checked = true;
    loadDynamicForm(jenis);
    
    fetch('get_soal_data_guru.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('mapel_id').value = data.soal.mapel_id;
                
                // Handle multi kelas (comma separated)
                const kelasValue = data.soal.kelas || '';
                const kelasSelect = document.getElementById('kelas');
                if (kelasSelect && kelasValue) {
                    const selectedKelas = kelasValue.split(',');
                    for(let i = 0; i < kelasSelect.options.length; i++) {
                        const optionValue = kelasSelect.options[i].value;
                        if (selectedKelas.includes(optionValue)) {
                            kelasSelect.options[i].selected = true;
                        }
                    }
                }
                
                document.getElementById('total_skor').value = data.soal.skor;
                document.getElementById('txt_pertanyaan').value = data.soal.pertanyaan;
                document.getElementById('video_soal').value = data.soal.video_soal || '';
                
                if (jenis === 'pilihan_ganda') {
                    document.getElementById('txt_opsi_a').value = data.soal.opsi_a;
                    document.getElementById('txt_opsi_b').value = data.soal.opsi_b;
                    document.getElementById('txt_opsi_c').value = data.soal.opsi_c;
                    document.getElementById('txt_opsi_d').value = data.soal.opsi_d;
                    document.getElementById('txt_opsi_e').value = data.soal.opsi_e || '';
                    document.getElementById('jawaban_benar').value = data.soal.jawaban_benar;
                    if (data.soal.gambar_soal) {
                        document.getElementById('gambarPreview').innerHTML = '<div class="mt-2"><img src="' + data.soal.gambar_soal + '" style="max-height:80px" class="img-thumbnail"><div class="form-check mt-1"><input type="checkbox" name="hapus_gambar" value="1"> Hapus gambar</div></div>';
                    }
                }
                else if (jenis === 'pilihan_ganda_kompleks') {
                    document.getElementById('txt_opsi_a').value = data.soal.opsi_a;
                    document.getElementById('txt_opsi_b').value = data.soal.opsi_b;
                    document.getElementById('txt_opsi_c').value = data.soal.opsi_c;
                    document.getElementById('txt_opsi_d').value = data.soal.opsi_d;
                    document.getElementById('txt_opsi_e').value = data.soal.opsi_e || '';
                    document.getElementById('skor_per_jawaban').value = data.soal.skor_per_jawaban;
                    let jawabanKompleks = JSON.parse(data.soal.jawaban_kompleks || '[]');
                    document.querySelectorAll('input[name="jawaban_kompleks_checkbox[]"]').forEach(cb => {
                        cb.checked = jawabanKompleks.includes(cb.value);
                    });
                    setTimeout(() => { let event = new Event('change'); document.getElementById('skor_per_jawaban').dispatchEvent(event); }, 100);
                }
                else if (jenis === 'essay') {
                    document.getElementById('txt_opsi_a').value = data.soal.opsi_a;
                }
                else if (jenis === 'menjodohkan') {
                    document.querySelector('textarea[name="opsi_a"]').value = data.soal.opsi_a;
                    document.querySelector('textarea[name="opsi_b"]').value = data.soal.opsi_b;
                    document.querySelector('textarea[name="pasangan_jodoh"]').value = data.soal.pasangan_jodoh;
                }
                else if (jenis === 'benar_salah') {
                    document.getElementById('jawaban_benar').value = data.soal.jawaban_benar;
                }
            }
        })
        .catch(error => console.error('Error:', error));
    
    new bootstrap.Modal(document.getElementById('soalModal')).show();
}

// ==================== DETAIL SOAL ====================
function detailSoal(id, jenis) {
    document.getElementById('detailSoalContent').innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Memuat data soal...</p></div>`;
    fetch('ajax_detail_soal_guru.php?id=' + id + '&jenis=' + jenis)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailSoalContent').innerHTML = data;
            if (window.MathJax) MathJax.typesetPromise();
        })
        .catch(error => { document.getElementById('detailSoalContent').innerHTML = '<div class="alert alert-danger">Gagal memuat detail soal</div>'; });
    new bootstrap.Modal(document.getElementById('detailSoalModal')).show();
}

// ==================== EVENT LISTENER ====================
document.addEventListener('DOMContentLoaded', function() {
    loadDynamicForm('pilihan_ganda');
    const radios = document.querySelectorAll('input[name="jenis_soal"]');
    radios.forEach(radio => { radio.addEventListener('change', function() { if (this.checked) loadDynamicForm(this.value); }); });
    const formSoal = document.getElementById('formSoal');
    if (formSoal) {
        formSoal.addEventListener('submit', function(e) {
            const jenis = document.querySelector('input[name="jenis_soal"]:checked').value;
            if (jenis === 'pilihan_ganda_kompleks') {
                const jawabanArray = Array.from(document.querySelectorAll('input[name="jawaban_kompleks_checkbox[]"]:checked')).map(cb => cb.value);
                document.getElementById('jawaban_kompleks_hidden').value = JSON.stringify(jawabanArray);
            }
            
            // Konversi multiple select ke comma separated string
            const kelasSelect = document.getElementById('kelas');
            if (kelasSelect) {
                const selectedOptions = Array.from(kelasSelect.selectedOptions).map(opt => opt.value);
                if (selectedOptions.length > 0) {
                    kelasSelect.name = '';
                    const hiddenKelas = document.createElement('input');
                    hiddenKelas.type = 'hidden';
                    hiddenKelas.name = 'kelas';
                    hiddenKelas.value = selectedOptions.join(',');
                    formSoal.appendChild(hiddenKelas);
                }
            }
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>
</body>
</html>