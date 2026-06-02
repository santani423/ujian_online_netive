<?php
// koreksi_essay.php - Halaman koreksi manual untuk soal essay
require_once 'config.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Pastikan ini bukan admin
if ($_SESSION['is_admin'] ?? false) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Akses ditolak. Halaman ini untuk guru saja.'
    ];
    header("Location: dashboard.php");
    exit();
}

$guru_id = $_SESSION['user_id'];
$title = "Koreksi Essay";

include 'templates/header_guru.php';

// Ambil parameter filter
$filter_kelas = $_GET['kelas'] ?? '';
$filter_mapel = $_GET['mapel'] ?? '';
$filter_ujian = $_GET['ujian_id'] ?? '';
$filter_status = $_GET['status'] ?? 'semua'; // semua, belum, sudah

// Ambil data untuk filter
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll();
$mapel_list = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// Ambil daftar ujian untuk filter
$ujian_list = $pdo->query("
    SELECT u.*, mp.nama_mapel 
    FROM ujian u 
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
    WHERE u.status = 'published' 
    ORDER BY u.waktu_mulai DESC
")->fetchAll();

// Query untuk mendapatkan daftar jawaban essay yang perlu dikoreksi
// PERBAIKAN: Menggunakan struktur database yang benar
$where_conditions = [];
$params = [];

// Hanya tampilkan soal essay
$where_conditions[] = "s.jenis_soal = 'essay'";

// Filter berdasarkan kelas
if ($filter_kelas) {
    $where_conditions[] = "s.kelas = ?";
    $params[] = $filter_kelas;
}

// Filter berdasarkan mata pelajaran
if ($filter_mapel) {
    $where_conditions[] = "u.mapel_id = ?";
    $params[] = $filter_mapel;
}

// Filter berdasarkan ujian
if ($filter_ujian) {
    $where_conditions[] = "hu.ujian_id = ?";
    $params[] = $filter_ujian;
}

// Filter berdasarkan status koreksi
if ($filter_status == 'belum') {
    $where_conditions[] = "(je.status_koreksi IS NULL OR je.status_koreksi = 'belum')";
} elseif ($filter_status == 'sudah') {
    $where_conditions[] = "je.status_koreksi = 'sudah'";
}

$where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// PERBAIKAN QUERY: Menggunakan struktur database yang benar dari file SQL
$sql = "
    SELECT 
        je.id as jawaban_essay_id,
        je.jawaban_text,
        je.skor_essay,
        je.komentar_guru,
        je.status_koreksi,
        je.created_at as waktu_jawab,
        je.updated_at as waktu_koreksi,
        
        s.id as soal_id,
        s.pertanyaan,
        s.gambar_soal,
        s.video_soal,
        s.opsi_a as petunjuk_jawaban,
        s.skor as maksimal_skor,
        s.kelas,
        
        sw.nisn,
        sw.nama as nama_siswa,
        sw.kelas as kelas_siswa,
        
        u.id as ujian_id,
        u.judul_ujian,
        u.durasi,
        
        mp.id as mapel_id,
        mp.nama_mapel,
        mp.kode_mapel,
        
        hu.id as hasil_ujian_id,
        hu.nilai as nilai_sebelumnya,
        hu.waktu_mulai,
        hu.waktu_selesai
    FROM jawaban_siswa js
    LEFT JOIN jawaban_essay je ON je.hasil_ujian_id = js.hasil_ujian_id AND je.soal_id = js.soal_id
    JOIN soal s ON js.soal_id = s.id
    JOIN hasil_ujian hu ON js.hasil_ujian_id = hu.id
    JOIN ujian u ON hu.ujian_id = u.id
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id
    JOIN siswa sw ON hu.siswa_id = sw.id
    $where_sql
    ORDER BY je.status_koreksi ASC, hu.waktu_selesai DESC, sw.nama
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jawaban_essay = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Hitung statistik
$total_essay = count($jawaban_essay);
$belum_dikoreksi = 0;
$sudah_dikoreksi = 0;

foreach ($jawaban_essay as $essay) {
    if ($essay['status_koreksi'] == 'sudah') {
        $sudah_dikoreksi++;
    } else {
        $belum_dikoreksi++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - Sistem Ujian Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .skor-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .komentar-input:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }
        
        .btn-koreksi {
            transition: all 0.3s;
        }
        
        .btn-koreksi:hover {
            transform: scale(1.05);
        }
        
        .bg-light {
            background-color: #f8f9fa !important;
        }
        
        .stat-card {
            border-radius: 10px;
        }
        
        /* Scrollbar custom untuk textarea jawaban */
        .jawaban-siswa {
            min-height: 150px; 
            max-height: 300px; 
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        .jawaban-siswa::-webkit-scrollbar {
            width: 8px;
        }
        
        .jawaban-siswa::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .jawaban-siswa::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .jawaban-siswa::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .toast-container {
            z-index: 9999;
        }
    </style>
</head>
<body>
    <?php include 'templates/navbar_guru.php'; ?>
    
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-check-double me-2"></i>Koreksi Soal Essay</h2>
                <p class="text-muted mb-0">Koreksi manual untuk jawaban soal essay</p>
            </div>
            <div>
                <a href="hasil_ujian.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Hasil Ujian
                </a>
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
        ?>

        <!-- Statistik -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card border-0 bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= number_format($total_essay) ?></h4>
                                <p class="mb-0">Total Essay</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-file-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-0 bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= number_format($belum_dikoreksi) ?></h4>
                                <p class="mb-0">Belum Dikoreksi</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-0 bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= number_format($sudah_dikoreksi) ?></h4>
                                <p class="mb-0">Sudah Dikoreksi</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-0 bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <?php 
                                $persentase = $total_essay > 0 ? round(($sudah_dikoreksi / $total_essay) * 100, 1) : 0;
                                ?>
                                <h4 class="mb-0"><?= $persentase ?>%</h4>
                                <p class="mb-0">Progress Koreksi</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Essay</h5>
            </div>
            <div class="card-body">
                <form method="GET" id="filterForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Kelas</label>
                                <select name="kelas" class="form-select">
                                    <option value="">Semua Kelas</option>
                                    <?php foreach ($kelas_list as $kelas): ?>
                                    <option value="<?= htmlspecialchars($kelas['kelas']) ?>" <?= $filter_kelas == $kelas['kelas'] ? 'selected' : '' ?>>
                                        Kelas <?= htmlspecialchars($kelas['kelas']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Mata Pelajaran</label>
                                <select name="mapel" class="form-select">
                                    <option value="">Semua Mapel</option>
                                    <?php foreach ($mapel_list as $mapel): ?>
                                    <option value="<?= $mapel['id'] ?>" <?= $filter_mapel == $mapel['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mapel['nama_mapel']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Ujian</label>
                                <select name="ujian_id" class="form-select">
                                    <option value="">Semua Ujian</option>
                                    <?php foreach ($ujian_list as $ujian): ?>
                                    <option value="<?= $ujian['id'] ?>" <?= $filter_ujian == $ujian['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ujian['judul_ujian']) ?> (<?= date('d/m/Y', strtotime($ujian['waktu_mulai'])) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Status Koreksi</label>
                                <select name="status" class="form-select">
                                    <option value="semua" <?= $filter_status == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="belum" <?= $filter_status == 'belum' ? 'selected' : '' ?>>Belum Dikoreksi</option>
                                    <option value="sudah" <?= $filter_status == 'sudah' ? 'selected' : '' ?>>Sudah Dikoreksi</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-2"></i>Terapkan Filter
                            </button>
                            <a href="koreksi_essay.php" class="btn btn-secondary">
                                <i class="fas fa-refresh me-2"></i>Reset Filter
                            </a>
                            <?php if ($belum_dikoreksi > 0): ?>
                            <button type="button" class="btn btn-success" onclick="koreksiCepatSemua()">
                                <i class="fas fa-bolt me-2"></i>Koreksi Cepat Semua
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Daftar Jawaban Essay -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Jawaban Essay</h5>
            </div>
            <div class="card-body">
                <?php if (count($jawaban_essay) > 0): ?>
                    <div class="row">
                        <?php foreach ($jawaban_essay as $index => $essay): 
                            $is_dikoreksi = $essay['status_koreksi'] == 'sudah';
                            $badge_class = $is_dikoreksi ? 'bg-success' : 'bg-warning';
                            $badge_text = $is_dikoreksi ? 'Sudah Dikoreksi' : 'Belum Dikoreksi';
                            $skor_display = $is_dikoreksi ? number_format($essay['skor_essay'], 1) : 'Belum';
                            
                            // Format waktu
                            $waktu_jawab = date('d/m/Y H:i', strtotime($essay['waktu_jawab']));
                            $waktu_koreksi = $essay['waktu_koreksi'] ? date('d/m/Y H:i', strtotime($essay['waktu_koreksi'])) : '-';
                            
                            // Hitung persentase skor jika sudah dikoreksi
                            $persentase_skor = $is_dikoreksi && $essay['maksimal_skor'] > 0 ? 
                                round(($essay['skor_essay'] / $essay['maksimal_skor']) * 100, 1) : 0;
                        ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-<?= $is_dikoreksi ? 'success' : 'warning' ?>">
                                <div class="card-header bg-<?= $is_dikoreksi ? 'success' : 'warning' ?> text-white d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Essay #<?= $index + 1 ?></h6>
                                        <small><?= htmlspecialchars($essay['nama_mapel']) ?> - <?= htmlspecialchars($essay['judul_ujian']) ?></small>
                                    </div>
                                    <span class="badge bg-light text-dark"><?= $badge_text ?></span>
                                </div>
                                <div class="card-body">
                                    <!-- Info Siswa -->
                                    <div class="mb-3 p-3 bg-light rounded">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">Siswa:</small>
                                                <strong><?= htmlspecialchars($essay['nama_siswa']) ?></strong>
                                                <div class="small">NISN: <?= htmlspecialchars($essay['nisn']) ?></div>
                                                <div class="small">Kelas: <?= htmlspecialchars($essay['kelas_siswa']) ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">Waktu:</small>
                                                <div class="small">Jawab: <?= $waktu_jawab ?></div>
                                                <div class="small">Koreksi: <?= $waktu_koreksi ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Soal -->
                                    <div class="mb-3">
                                        <h6><i class="fas fa-question-circle me-2"></i>Soal:</h6>
                                        <div class="p-3 bg-light rounded">
                                            <?= nl2br(htmlspecialchars($essay['pertanyaan'])) ?>
                                            
                                            <?php if (!empty($essay['petunjuk_jawaban']) && $essay['petunjuk_jawaban'] != '-'): ?>
                                            <div class="mt-2 p-2 bg-info bg-opacity-10 rounded">
                                                <small class="text-muted d-block mb-1"><strong>Petunjuk:</strong></small>
                                                <?= nl2br(htmlspecialchars($essay['petunjuk_jawaban'])) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Jawaban Siswa -->
                                    <div class="mb-3">
                                        <h6><i class="fas fa-edit me-2"></i>Jawaban Siswa:</h6>
                                        <div class="p-3 bg-light rounded jawaban-siswa">
                                            <?php if (!empty($essay['jawaban_text'])): ?>
                                                <?= nl2br(htmlspecialchars($essay['jawaban_text'])) ?>
                                            <?php else: ?>
                                                <div class="text-center text-muted py-3">
                                                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                                                    <p class="mb-0">Siswa tidak menjawab</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Form Koreksi -->
                                    <div class="mb-3">
                                        <h6><i class="fas fa-check-circle me-2"></i>Koreksi:</h6>
                                        <form action="proses_koreksi_essay.php" method="POST" class="form-koreksi" data-id="<?= $essay['jawaban_essay_id'] ? $essay['jawaban_essay_id'] : 'new_' . $essay['hasil_ujian_id'] . '_' . $essay['soal_id'] ?>">
                                            <?php if ($essay['jawaban_essay_id']): ?>
                                            <input type="hidden" name="jawaban_essay_id" value="<?= $essay['jawaban_essay_id'] ?>">
                                            <?php endif; ?>
                                            <input type="hidden" name="hasil_ujian_id" value="<?= $essay['hasil_ujian_id'] ?>">
                                            <input type="hidden" name="soal_id" value="<?= $essay['soal_id'] ?>">
                                            <input type="hidden" name="action" value="<?= $essay['jawaban_essay_id'] ? 'update' : 'insert' ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Skor (maks: <?= $essay['maksimal_skor'] ?> poin)</label>
                                                    <input type="number" 
                                                           class="form-control skor-input" 
                                                           name="skor_essay" 
                                                           min="0" 
                                                           max="<?= $essay['maksimal_skor'] ?>" 
                                                           step="0.5"
                                                           value="<?= $is_dikoreksi ? $essay['skor_essay'] : '' ?>"
                                                           <?= $is_dikoreksi ? 'readonly' : 'required' ?>>
                                                    <div class="form-text">
                                                        <small id="persentaseDisplay<?= $index ?>">
                                                            <?php if ($is_dikoreksi): ?>
                                                                <?= $persentase_skor ?>% dari maksimal
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Status Koreksi</label>
                                                    <select class="form-select" name="status_koreksi" <?= $is_dikoreksi ? 'disabled' : '' ?>>
                                                        <option value="belum" <?= !$is_dikoreksi ? 'selected' : '' ?>>Belum Dikoreksi</option>
                                                        <option value="sudah" <?= $is_dikoreksi ? 'selected' : '' ?>>Sudah Dikoreksi</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Komentar/Koreksi</label>
                                                <textarea class="form-control komentar-input" 
                                                          name="komentar_guru" 
                                                          rows="3" 
                                                          placeholder="Berikan komentar atau koreksi untuk siswa..."
                                                          <?= $is_dikoreksi ? 'readonly' : '' ?>><?= $is_dikoreksi && !empty($essay['komentar_guru']) ? htmlspecialchars($essay['komentar_guru']) : '' ?></textarea>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <?php if (!$is_dikoreksi): ?>
                                                <button type="submit" class="btn btn-success btn-koreksi">
                                                    <i class="fas fa-save me-2"></i>Simpan Koreksi
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-batal" style="display: none;">
                                                    <i class="fas fa-times me-2"></i>Batal
                                                </button>
                                                <?php else: ?>
                                                <div class="alert alert-success w-100 mb-0">
                                                    <i class="fas fa-check-circle me-2"></i>
                                                    <strong>Sudah dikoreksi:</strong> <?= $essay['skor_essay'] ?> poin
                                                    <?php if (!empty($essay['komentar_guru'])): ?>
                                                    <div class="mt-1">
                                                        <small><strong>Komentar:</strong> <?= htmlspecialchars($essay['komentar_guru']) ?></small>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Set baris baru setiap 2 kolom -->
                        <?php if (($index + 1) % 2 == 0): ?>
                            </div><div class="row">
                        <?php endif; ?>
                        
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_essay > 20): ?>
                    <div class="mt-4 text-center">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-double fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada jawaban essay</h5>
                        <p class="text-muted">
                            <?php if ($filter_kelas || $filter_mapel || $filter_ujian || $filter_status != 'semua'): ?>
                            Tidak ditemukan jawaban essay dengan filter yang dipilih.
                            <?php else: ?>
                            Tidak ada soal essay yang perlu dikoreksi saat ini.
                            <?php endif; ?>
                        </p>
                        <?php if ($filter_kelas || $filter_mapel || $filter_ujian || $filter_status != 'semua'): ?>
                        <a href="koreksi_essay.php" class="btn btn-primary">
                            <i class="fas fa-refresh me-2"></i>Tampilkan Semua
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Koreksi Cepat -->
    <div class="modal fade" id="modalKoreksiCepat" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-bolt me-2"></i>Koreksi Cepat Semua</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian!</strong> Ini akan memberikan nilai rata-rata untuk semua essay yang belum dikoreksi.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Persentase Nilai (%)</label>
                        <input type="range" class="form-range" id="rangePersentase" min="0" max="100" value="70">
                        <div class="d-flex justify-content-between">
                            <small>0%</small>
                            <small id="persentaseValue">70%</small>
                            <small>100%</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Komentar Standar</label>
                        <textarea class="form-control" id="komentarStandar" rows="3" placeholder="Komentar akan diberikan ke semua siswa...">Jawaban sudah cukup baik, terus berlatih!</textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-2"></i>
                            Aksi ini akan mengoreksi <strong id="jumlahEssay"><?= $belum_dikoreksi ?></strong> essay yang belum dikoreksi.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-warning" id="btnProsesKoreksiCepat">
                        <i class="fas fa-bolt me-2"></i>Proses Koreksi Cepat
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Update persentase skor secara real-time
        const skorInputs = document.querySelectorAll('.skor-input');
        skorInputs.forEach((input, index) => {
            const maksimal = parseFloat(input.max);
            const persentaseDisplay = document.getElementById(`persentaseDisplay${index}`);
            
            input.addEventListener('input', function() {
                const nilai = parseFloat(this.value) || 0;
                const persentase = maksimal > 0 ? Math.round((nilai / maksimal) * 100) : 0;
                
                if (persentaseDisplay) {
                    persentaseDisplay.textContent = `${persentase}% dari maksimal`;
                    
                    // Warna berdasarkan persentase
                    if (persentase >= 80) {
                        persentaseDisplay.className = 'text-success';
                    } else if (persentase >= 60) {
                        persentaseDisplay.className = 'text-warning';
                    } else {
                        persentaseDisplay.className = 'text-danger';
                    }
                }
            });
            
            // Trigger initial calculation jika sudah ada nilai
            if (input.value) {
                input.dispatchEvent(new Event('input'));
            }
        });
        
        // Handle form submission
        const forms = document.querySelectorAll('.form-koreksi');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('.btn-koreksi');
                const originalText = submitBtn ? submitBtn.innerHTML : '';
                
                // Validasi skor
                const skorInput = this.querySelector('.skor-input');
                const maksimal = parseFloat(skorInput.max);
                const nilai = parseFloat(skorInput.value) || 0;
                
                if (nilai > maksimal) {
                    alert(`Skor tidak boleh melebihi ${maksimal} poin!`);
                    return;
                }
                
                // Tampilkan loading jika ada tombol submit
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';
                    submitBtn.disabled = true;
                }
                
                // Kirim data via AJAX
                fetch('proses_koreksi_essay.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Tampilkan pesan sukses
                        showToast('success', data.message || 'Koreksi berhasil disimpan!');
                        
                        // Reload halaman setelah 1.5 detik
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast('error', data.message || 'Terjadi kesalahan!');
                        if (submitBtn) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'Terjadi kesalahan jaringan!');
                    if (submitBtn) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                });
            });
        });
        
        // Fungsi untuk menampilkan toast
        function showToast(type, message) {
            // Buat elemen toast
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${icon} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            // Tambahkan ke container toast
            const toastContainer = document.getElementById('toastContainer');
            toastContainer.appendChild(toast);
            
            // Inisialisasi dan tampilkan toast
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Hapus toast setelah ditutup
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        }
        
        // Handle koreksi cepat semua
        window.koreksiCepatSemua = function() {
            const modal = new bootstrap.Modal(document.getElementById('modalKoreksiCepat'));
            modal.show();
        };
        
        // Update nilai range
        const rangePersentase = document.getElementById('rangePersentase');
        const persentaseValue = document.getElementById('persentaseValue');
        
        if (rangePersentase) {
            rangePersentase.addEventListener('input', function() {
                persentaseValue.textContent = this.value + '%';
            });
        }
        
        // Proses koreksi cepat
        const btnProsesKoreksiCepat = document.getElementById('btnProsesKoreksiCepat');
        if (btnProsesKoreksiCepat) {
            btnProsesKoreksiCepat.addEventListener('click', function() {
                const persentase = rangePersentase.value;
                const komentar = document.getElementById('komentarStandar').value;
                const originalText = this.innerHTML;
                
                // Tampilkan loading
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
                this.disabled = true;
                
                // Kirim data via AJAX
                fetch('proses_koreksi_cepat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `persentase=${persentase}&komentar=${encodeURIComponent(komentar)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message || 'Koreksi cepat berhasil!');
                        
                        // Tutup modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalKoreksiCepat'));
                        modal.hide();
                        
                        // Reload halaman setelah 2 detik
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showToast('error', data.message || 'Terjadi kesalahan!');
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'Terjadi kesalahan jaringan!');
                    this.innerHTML = originalText;
                    this.disabled = false;
                });
            });
        }
        
        // Auto-save draft (opsional)
        let autoSaveTimer;
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            const formId = form.dataset.id;
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        saveDraft(formId, form);
                    }, 2000);
                });
            });
        });
        
        function saveDraft(formId, form) {
            const formData = new FormData(form);
            formData.append('action', 'save_draft');
            formData.append('form_id', formId);
            
            // Kirim draft via AJAX
            fetch('save_draft_koreksi.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Draft tersimpan otomatis');
                }
            });
        }
        
        // Load saved drafts on page load
        window.addEventListener('load', function() {
            forms.forEach(form => {
                const formId = form.dataset.id;
                if (formId && formId.startsWith('new_')) {
                    // Coba load draft hanya untuk form baru
                    loadDraft(formId, form);
                }
            });
        });
        
        function loadDraft(formId, form) {
            fetch(`get_draft_koreksi.php?id=${formId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.draft) {
                        const draft = data.draft;
                        
                        // Isi form dengan data draft
                        const skorInput = form.querySelector('.skor-input');
                        const komentarInput = form.querySelector('.komentar-input');
                        const statusSelect = form.querySelector('[name="status_koreksi"]');
                        
                        if (skorInput && draft.skor_essay) skorInput.value = draft.skor_essay;
                        if (komentarInput && draft.komentar_guru) komentarInput.value = draft.komentar_guru;
                        if (statusSelect && draft.status_koreksi) statusSelect.value = draft.status_koreksi;
                        
                        // Trigger input event untuk update persentase
                        if (skorInput && skorInput.value) {
                            skorInput.dispatchEvent(new Event('input'));
                        }
                    }
                })
                .catch(error => console.error('Error loading draft:', error));
        }
    });
    </script>
</body>
</html>