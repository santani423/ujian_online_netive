<?php
// input_nilai.php - Halaman input nilai manual oleh guru (DENGAN PG KOMPLEKS - VERSI PHP)
require_once 'config.php';

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

$guru_id = $_SESSION['guru_id'] ?? 0;
$nama_lengkap = $_SESSION['nama_lengkap'];

// Cek apakah guru ini adalah guru mapel
$is_guru_mapel = $_SESSION['is_guru_mapel'] ?? false;

// Fungsi deteksi jenis soal
function deteksiJenisSoal($soal) {
    if (!empty($soal['jenis_soal']) && $soal['jenis_soal'] != 'pilihan_ganda') {
        return $soal['jenis_soal'];
    }
    
    // Deteksi Pilihan Ganda Kompleks
    if (!empty($soal['jawaban_kompleks']) && $soal['jawaban_kompleks'] != '[]' && $soal['jawaban_kompleks'] != 'null') {
        return 'pilihan_ganda_kompleks';
    }
    
    if (trim($soal['opsi_a'] ?? '') == 'Benar' && trim($soal['opsi_b'] ?? '') == 'Salah') {
        return 'benar_salah';
    }
    
    $opsi_c_empty = empty($soal['opsi_c']) || trim($soal['opsi_c'] ?? '') == '-' || trim($soal['opsi_c'] ?? '') == '';
    $opsi_d_empty = empty($soal['opsi_d']) || trim($soal['opsi_d'] ?? '') == '-' || trim($soal['opsi_d'] ?? '') == '';
    $opsi_e_empty = empty($soal['opsi_e']) || trim($soal['opsi_e'] ?? '') == '-' || trim($soal['opsi_e'] ?? '') == '';
    
    if ($opsi_c_empty && $opsi_d_empty && $opsi_e_empty) {
        return 'essay';
    }
    
    return 'pilihan_ganda';
}

// Fungsi untuk menghitung nilai PG Kompleks
function hitungNilaiPGKompleks($jawaban_siswa, $jawaban_kompleks, $skor_per_jawaban) {
    if (empty($jawaban_siswa) || empty($jawaban_kompleks)) {
        return 0;
    }
    
    $jawabanArray = explode(',', $jawaban_siswa);
    $jawabanBenar = json_decode($jawaban_kompleks, true);
    if (!is_array($jawabanBenar)) {
        $jawabanBenar = [];
    }
    
    $jumlahBenar = 0;
    foreach ($jawabanArray as $jwb) {
        if (in_array($jwb, $jawabanBenar)) {
            $jumlahBenar++;
        }
    }
    
    return $jumlahBenar * $skor_per_jawaban;
}

// Ambil parameter filter
$filter_kelas = $_GET['kelas'] ?? '';
$filter_mapel = $_GET['mapel'] ?? '';
$filter_ujian = $_GET['ujian_id'] ?? '';
$filter_siswa = $_GET['siswa_id'] ?? '';

// Ambil data untuk filter
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll();
$mapel_list = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// Jika guru mapel, filter berdasarkan mapel yang diampu
if ($is_guru_mapel) {
    $mapel_diampu = $_SESSION['mapel_diampu'] ?? [];
    $kelas_diampu = $_SESSION['kelas_diampu'] ?? [];
    
    $mapel_ids = array_column($mapel_diampu, 'id');
    $kelas_list_filtered = array_column($kelas_diampu, 'kelas');
    
    if (count($mapel_ids) > 0) {
        $mapel_placeholders = str_repeat('?,', count($mapel_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM mata_pelajaran WHERE id IN ($mapel_placeholders) ORDER BY nama_mapel");
        $stmt->execute($mapel_ids);
        $mapel_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (count($kelas_list_filtered) > 0) {
        $kelas_list = array_map(function($kelas) {
            return ['kelas' => $kelas];
        }, $kelas_list_filtered);
    }
}

// Ambil daftar ujian untuk filter
$where_conditions = [];
$params = [];

if ($is_guru_mapel && isset($mapel_ids) && count($mapel_ids) > 0) {
    $where_conditions[] = "u.mapel_id IN (" . str_repeat('?,', count($mapel_ids) - 1) . "?)";
    $params = array_merge($params, $mapel_ids);
}

$where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$ujian_list = $pdo->prepare("
    SELECT u.*, mp.nama_mapel 
    FROM ujian u 
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
    {$where_sql}
    AND u.status = 'published' 
    ORDER BY u.waktu_mulai DESC
");

if ($params) {
    $ujian_list->execute($params);
} else {
    $ujian_list->execute();
}
$ujian_list = $ujian_list->fetchAll();

// Ambil daftar siswa untuk filter
$siswa_where = [];
$siswa_params = [];

if ($filter_kelas) {
    $siswa_where[] = "kelas = ?";
    $siswa_params[] = $filter_kelas;
} elseif ($is_guru_mapel && isset($kelas_list_filtered) && count($kelas_list_filtered) > 0) {
    $siswa_where[] = "kelas IN (" . str_repeat('?,', count($kelas_list_filtered) - 1) . "?)";
    $siswa_params = array_merge($siswa_params, $kelas_list_filtered);
}

$siswa_where_sql = $siswa_where ? "WHERE " . implode(" AND ", $siswa_where) : "";
$siswa_query = "SELECT id, nisn, nama, kelas FROM siswa {$siswa_where_sql} ORDER BY nama";

$stmt = $pdo->prepare($siswa_query);
$stmt->execute($siswa_params);
$siswa_list = $stmt->fetchAll();

// Jika ada ujian_id yang dipilih, ambil data ujian dan hasilnya
$ujian_data = null;
$hasil_ujian_list = [];
$soal_ujian_list = [];

if ($filter_ujian) {
    // Ambil data ujian
    $stmt = $pdo->prepare("
        SELECT u.*, mp.nama_mapel, mp.kode_mapel 
        FROM ujian u 
        JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$filter_ujian]);
    $ujian_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Filter kondisi untuk hasil ujian
    $where_conditions = ["hu.ujian_id = ?"];
    $params = [$filter_ujian];
    
    if ($filter_siswa) {
        $where_conditions[] = "hu.siswa_id = ?";
        $params[] = $filter_siswa;
    }
    
    if ($filter_kelas && !$filter_siswa) {
        $where_conditions[] = "s.kelas = ?";
        $params[] = $filter_kelas;
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // AMBIL DATA HASIL UJIAN
    $sql = "
        SELECT 
            hu.*,
            s.nisn,
            s.nama as nama_siswa,
            s.kelas as kelas_siswa
        FROM hasil_ujian hu
        JOIN siswa s ON hu.siswa_id = s.id
        WHERE $where_sql
        ORDER BY s.nama
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $hasil_ujian_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil semua soal untuk ujian ini beserta jawaban siswa
    $sql_soal = "
        SELECT su.*, s.* 
        FROM soal_ujian su 
        JOIN soal s ON su.soal_id = s.id 
        WHERE su.ujian_id = ? 
        ORDER BY su.urutan
    ";
    
    $stmt_soal = $pdo->prepare($sql_soal);
    $stmt_soal->execute([$filter_ujian]);
    $soal_ujian_list = $stmt_soal->fetchAll(PDO::FETCH_ASSOC);
    
    // Hitung ulang semua nilai menggunakan PHP
    foreach ($hasil_ujian_list as &$hasil) {
        // Ambil jawaban siswa untuk hasil ujian ini
        $stmt_jawaban = $pdo->prepare("SELECT soal_id, jawaban_siswa, jawaban_essay FROM jawaban_siswa WHERE hasil_ujian_id = ?");
        $stmt_jawaban->execute([$hasil['id']]);
        $jawaban_map = [];
        while ($jwb = $stmt_jawaban->fetch(PDO::FETCH_ASSOC)) {
            $jawaban_map[$jwb['soal_id']] = $jwb;
        }
        
        // Ambil nilai manual dari jawaban_essay
        $stmt_manual = $pdo->prepare("SELECT soal_id, skor_essay FROM jawaban_essay WHERE hasil_ujian_id = ?");
        $stmt_manual->execute([$hasil['id']]);
        $manual_map = [];
        while ($manual = $stmt_manual->fetch(PDO::FETCH_ASSOC)) {
            $manual_map[$manual['soal_id']] = $manual['skor_essay'];
        }
        
        $nilai_otomatis = 0;
        $total_skor = 0;
        
        foreach ($soal_ujian_list as $soal) {
            $jenis = deteksiJenisSoal($soal);
            $skor_soal = $soal['skor'];
            $total_skor += $skor_soal;
            
            if ($jenis == 'pilihan_ganda') {
                // Pilihan ganda biasa
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                if ($jawaban == $soal['jawaban_benar']) {
                    $nilai_otomatis += $skor_soal;
                }
            } 
            elseif ($jenis == 'pilihan_ganda_kompleks') {
                // Pilihan ganda kompleks
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                $nilai_otomatis += hitungNilaiPGKompleks($jawaban, $soal['jawaban_kompleks'], $soal['skor_per_jawaban']);
            }
            elseif ($jenis == 'benar_salah') {
                // Benar salah
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                if ($jawaban == $soal['jawaban_benar']) {
                    $nilai_otomatis += $skor_soal;
                }
            }
        }
        
        // Hitung nilai manual
        $nilai_manual = 0;
        foreach ($manual_map as $skor) {
            $nilai_manual += $skor;
        }
        
        $total_nilai = $nilai_otomatis + $nilai_manual;
        $persentase = $total_skor > 0 ? ($total_nilai / $total_skor) * 100 : 0;
        
        // Update database jika berbeda
        $nilai_sekarang = floatval($hasil['nilai'] ?? 0);
        if (abs($nilai_sekarang - $persentase) > 0.01) {
            $stmt_update = $pdo->prepare("UPDATE hasil_ujian SET nilai = ? WHERE id = ?");
            $stmt_update->execute([$persentase, $hasil['id']]);
            $hasil['nilai'] = $persentase;
        }
        
        $hasil['_nilai_otomatis'] = $nilai_otomatis;
        $hasil['_nilai_manual'] = $nilai_manual;
        $hasil['_total_nilai'] = $total_nilai;
        $hasil['_total_skor'] = $total_skor;
    }
    unset($hasil);
}

// Proses input nilai jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_nilai'])) {
    $hasil_ujian_id = $_POST['hasil_ujian_id'];
    $soal_id = $_POST['soal_id'];
    $skor_manual = floatval($_POST['skor_manual'] ?? 0);
    $komentar = $_POST['komentar'] ?? '';
    
    // Dapatkan jenis soal dan skor maksimal
    $stmt = $pdo->prepare("SELECT jenis_soal, skor FROM soal WHERE id = ?");
    $stmt->execute([$soal_id]);
    $soal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $jenis_soal = $soal_data['jenis_soal'] ?? 'pilihan_ganda';
    $max_skor = $soal_data['skor'] ?? 10;
    
    // Validasi skor manual
    if ($skor_manual < 0) $skor_manual = 0;
    if ($skor_manual > $max_skor) $skor_manual = $max_skor;
    
    try {
        // Cek apakah sudah ada record di jawaban_essay
        $stmt = $pdo->prepare("SELECT id FROM jawaban_essay WHERE hasil_ujian_id = ? AND soal_id = ?");
        $stmt->execute([$hasil_ujian_id, $soal_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ambil jawaban siswa terlebih dahulu
        $stmt = $pdo->prepare("SELECT jawaban_essay FROM jawaban_siswa WHERE hasil_ujian_id = ? AND soal_id = ?");
        $stmt->execute([$hasil_ujian_id, $soal_id]);
        $jawaban = $stmt->fetch(PDO::FETCH_ASSOC);
        $jawaban_text = $jawaban['jawaban_essay'] ?? '';
        
        if ($existing) {
            $sql = "UPDATE jawaban_essay SET skor_essay = ?, komentar_guru = ?, status_koreksi = 'sudah', updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$skor_manual, $komentar, $existing['id']]);
        } else {
            $sql = "INSERT INTO jawaban_essay (hasil_ujian_id, soal_id, jawaban_text, skor_essay, komentar_guru, status_koreksi, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, 'sudah', NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hasil_ujian_id, $soal_id, $jawaban_text, $skor_manual, $komentar]);
        }
        
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Nilai berhasil diinput!'
        ];
        
        $redirect_url = "input_nilai.php?ujian_id=$filter_ujian";
        if ($filter_siswa) $redirect_url .= "&siswa_id=$filter_siswa";
        if ($filter_kelas) $redirect_url .= "&kelas=$filter_kelas";
        
        header("Location: $redirect_url");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// Hitung statistik jika ada ujian yang dipilih
$total_siswa = count($hasil_ujian_list);
$siswa_sudah_nilai = 0;
$siswa_belum_nilai = 0;
$soal_manual_count = 0;

foreach ($hasil_ujian_list as $hasil) {
    // Cek apakah ada soal essay/menjodohkan yang belum dikoreksi
    $sql_check = "
        SELECT COUNT(*) as count_belum 
        FROM soal_ujian su
        JOIN soal s ON su.soal_id = s.id
        LEFT JOIN jawaban_essay je ON je.soal_id = s.id AND je.hasil_ujian_id = ?
        WHERE su.ujian_id = ? 
        AND s.jenis_soal IN ('essay', 'menjodohkan')
        AND (je.status_koreksi IS NULL OR je.status_koreksi = 'belum')
    ";
    
    $stmt = $pdo->prepare($sql_check);
    $stmt->execute([$hasil['id'], $filter_ujian]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count_belum'] == 0) {
        $siswa_sudah_nilai++;
    } else {
        $siswa_belum_nilai++;
    }
}

// Hitung jumlah soal manual (essay + menjodohkan)
foreach ($soal_ujian_list as $soal) {
    $jenis = deteksiJenisSoal($soal);
    if (in_array($jenis, ['essay', 'menjodohkan'])) {
        $soal_manual_count++;
    }
}

$title = "Input Nilai Manual";
include 'templates/header_guru.php';
?>

<!-- KONTEN UTAMA INPUT NILAI - SAMA SEPERTI SEBELUMNYA -->
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Input Nilai Manual</h2>
        <a href="dashboard_guru.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
        </a>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Filter Section -->
    <div class="card mb-4">
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
                                <option value="">Pilih Mapel</option>
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
                                <option value="">Pilih Ujian</option>
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
                            <label class="form-label">Siswa</label>
                            <select name="siswa_id" class="form-select">
                                <option value="">Semua Siswa</option>
                                <?php foreach ($siswa_list as $siswa): ?>
                                <option value="<?= $siswa['id'] ?>" <?= $filter_siswa == $siswa['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($siswa['nama']) ?> (<?= htmlspecialchars($siswa['nisn']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Terapkan Filter
                        </button>
                        <a href="input_nilai.php" class="btn btn-secondary">
                            <i class="fas fa-refresh me-2"></i>Reset Filter
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($ujian_data): ?>
    <!-- Informasi Ujian -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Informasi Ujian</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr><th width="40%">Judul Ujian</th><td>: <?= htmlspecialchars($ujian_data['judul_ujian']) ?></td></tr>
                        <tr><th>Mata Pelajaran</th><td>: <?= htmlspecialchars($ujian_data['nama_mapel']) ?> (<?= htmlspecialchars($ujian_data['kode_mapel']) ?>)</td></tr>
                        <tr><th>Waktu Ujian</th><td>: <?= date('d/m/Y H:i', strtotime($ujian_data['waktu_mulai'])) ?> - <?= date('H:i', strtotime($ujian_data['waktu_selesai'])) ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr><th width="40%">Kelas Target</th><td>: <?= htmlspecialchars($ujian_data['kelas_target']) ?></td></tr>
                        <tr><th>Total Soal</th><td>: <?= count($soal_ujian_list) ?> soal</td></tr>
                        <tr><th>Total Skor Maksimal</th><td>: <?php 
                                $total_skor = 0;
                                foreach ($soal_ujian_list as $soal) {
                                    $total_skor += $soal['skor'];
                                }
                                echo $total_skor . ' poin';
                                ?></td></tr>
                    </table>
                </div>
            </div>
            <?php if ($soal_manual_count > 0): ?>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>PERHATIAN:</strong> Ujian ini memiliki <strong><?= $soal_manual_count ?> soal</strong> yang memerlukan koreksi manual.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistik -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($total_siswa) ?></h4><p class="mb-0">Total Peserta</p></div>
                        <i class="fas fa-users fa-3x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($siswa_sudah_nilai) ?></h4><p class="mb-0">Sudah Dinilai</p></div>
                        <i class="fas fa-check-circle fa-3x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= number_format($siswa_belum_nilai) ?></h4><p class="mb-0">Belum Dinilai</p></div>
                        <i class="fas fa-clock fa-3x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= $soal_manual_count ?></h4><p class="mb-0">Soal Manual</p></div>
                        <i class="fas fa-edit fa-3x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Hasil Ujian -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Daftar Hasil Ujian Peserta</h5>
        </div>
        <div class="card-body">
            <?php if (count($hasil_ujian_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr><th>#</th><th>NISN</th><th>Nama Siswa</th><th>Kelas</th><th>Status</th><th>Nilai</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hasil_ujian_list as $index => $hasil): 
                                $nilai = $hasil['nilai'] ?? 0;
                                if ($nilai >= 80) $nilai_class = 'bg-success';
                                elseif ($nilai >= 60) $nilai_class = 'bg-warning';
                                else $nilai_class = 'bg-danger';
                                $status_class = $hasil['status'] == 'selesai' ? 'bg-success' : 'bg-warning';
                                $status_text = $hasil['status'] == 'selesai' ? 'Selesai' : 'Sedang Ujian';
                            ?>
                            <tr>
                                <td><?= $index + 1 ?> </td>
                                <td><?= htmlspecialchars($hasil['nisn']) ?></td>
                                <td><strong><?= htmlspecialchars($hasil['nama_siswa']) ?></strong></td>
                                <td><?= htmlspecialchars($hasil['kelas_siswa']) ?></td>
                                <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                                <td><span class="badge <?= $nilai_class ?>"><?= number_format($nilai, 1) ?>%</span></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalInputNilai"
                                            data-siswa-id="<?= $hasil['id'] ?>"
                                            data-siswa-nama="<?= htmlspecialchars($hasil['nama_siswa']) ?>"
                                            onclick="loadSoalUntukSiswa(<?= $hasil['id'] ?>, '<?= htmlspecialchars(addslashes($hasil['nama_siswa'])) ?>')">
                                        <i class="fas fa-edit me-1"></i>Input Nilai
                                    </button>
                                 </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada peserta ujian</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-clipboard-list fa-4x text-primary mb-3"></i>
            <h5 class="text-primary">Pilih Ujian Terlebih Dahulu</h5>
            <p class="text-muted">Silakan pilih ujian dari filter di atas untuk mulai menginput nilai.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Input Nilai -->
<div class="modal fade" id="modalInputNilai" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Input Nilai Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Memuat data soal...</p>
                </div>
                <div id="modalContent" style="display: none;">
                    <h6 id="siswaName" class="mb-2"></h6>
                    <div id="soalList"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function loadSoalUntukSiswa(hasilUjianId, siswaNama) {
    const ujianId = <?= $filter_ujian ?: 'null' ?>;
    if (!ujianId) { alert('Ujian ID tidak ditemukan'); return; }
    
    document.getElementById('modalLoading').style.display = 'block';
    document.getElementById('modalContent').style.display = 'none';
    document.getElementById('siswaName').innerHTML = `<strong>Siswa:</strong> ${siswaNama}`;
    
    fetch(`ajax_get_soal_ujian.php?ujian_id=${ujianId}&hasil_ujian_id=${hasilUjianId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderSoalList(data.soal, hasilUjianId);
                document.getElementById('modalLoading').style.display = 'none';
                document.getElementById('modalContent').style.display = 'block';
            } else {
                alert('Gagal memuat data soal: ' + data.message);
            }
        })
        .catch(error => { console.error('Error:', error); alert('Terjadi kesalahan saat memuat data'); });
}

function renderSoalList(soalList, hasilUjianId) {
    const container = document.getElementById('soalList');
    let html = '';
    
    soalList.forEach((soal, index) => {
        const jenisSoal = soal.jenis_soal_final || soal.jenis_soal;
        const isEssay = jenisSoal === 'essay';
        const isMenjodohkan = jenisSoal === 'menjodohkan';
        const isPGKompleks = jenisSoal === 'pilihan_ganda_kompleks';
        const isKoreksiManual = isEssay || isMenjodohkan;
        
        let badgeColor = 'secondary', badgeText = jenisSoal.toUpperCase();
        if (isEssay) { badgeColor = 'info'; badgeText = 'ESSAY'; }
        else if (isMenjodohkan) { badgeColor = 'warning'; badgeText = 'MENJODOHKAN'; }
        else if (isPGKompleks) { badgeColor = 'purple'; badgeText = 'PG KOMPLEKS'; }
        
        let jawabanSiswa = soal.jawaban_siswa || soal.jawaban_essay || '';
        
        // Format jawaban untuk PG Kompleks
        if (isPGKompleks && jawabanSiswa) {
            const jawabanArray = jawabanSiswa.split(',');
            const jawabanBenar = soal.jawaban_kompleks ? JSON.parse(soal.jawaban_kompleks) : [];
            let htmlJawaban = '<div class="mt-2">';
            jawabanArray.forEach(jwb => {
                const isBenar = jawabanBenar.includes(jwb);
                htmlJawaban += `<span class="badge ${isBenar ? 'bg-success' : 'bg-danger'} me-1 mb-1">${jwb.toUpperCase()}</span>`;
            });
            htmlJawaban += '</div>';
            jawabanSiswa = htmlJawaban;
        } else if (isMenjodohkan && jawabanSiswa) {
            const pairs = jawabanSiswa.split(',');
            let htmlJawaban = '<div class="mt-2">';
            pairs.forEach(pair => {
                const [kiri, kanan] = pair.split('-');
                htmlJawaban += `<span class="badge bg-secondary me-1 mb-1">${kiri} → ${kanan}</span>`;
            });
            htmlJawaban += '</div>';
            jawabanSiswa = htmlJawaban;
        }
        
        html += `
            <div class="card mb-3 border-${isKoreksiManual ? 'warning' : 'secondary'}">
                <div class="card-header bg-${isKoreksiManual ? 'warning' : 'light'}">
                    <h6 class="mb-0">Soal #${index + 1} <span class="badge bg-${badgeColor} float-end">${badgeText}</span></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Pertanyaan:</strong>
                        <div class="p-3 bg-light rounded mt-2">${soal.pertanyaan || ''}</div>
                    </div>
                    <div class="mb-3">
                        <strong>Jawaban Siswa:</strong>
                        <div class="p-3 bg-light rounded mt-2" style="min-height: 100px;">${jawabanSiswa || '<em class="text-muted">Tidak ada jawaban</em>'}</div>
                    </div>
                    ${isKoreksiManual ? `
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Soal ini memerlukan koreksi manual oleh guru</div>
                    <form class="form-input-nilai" data-soal-id="${soal.id}" data-hasil-ujian-id="${hasilUjianId}">
                        <input type="hidden" name="hasil_ujian_id" value="${hasilUjianId}">
                        <input type="hidden"name="soal_id" value="${soal.id}">
                        <input type="hidden" name="submit_nilai" value="1">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nilai (maks: ${soal.skor} poin)</label>
                                    <input type="number" class="form-control nilai-input" name="skor_manual" 
                                           min="0" max="${soal.skor}" step="0.5" value="${soal.skor_essay || ''}"
                                           ${soal.status_koreksi_essay === 'sudah' ? 'readonly' : ''}
                                           placeholder="Masukkan nilai (0-${soal.skor})">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Komentar (Opsional)</label>
                                    <textarea class="form-control komentar-input" name="komentar" rows="2"
                                              ${soal.status_koreksi_essay === 'sudah' ? 'readonly' : ''}
                                              placeholder="Berikan komentar untuk siswa...">${soal.komentar_guru || ''}</textarea>
                                </div>
                            </div>
                        </div>
                        ${soal.status_koreksi_essay !== 'sudah' ? `
                        <div class="text-end"><button type="button" class="btn btn-success" onclick="simpanNilaiManual(${soal.id}, ${hasilUjianId})"><i class="fas fa-save me-1"></i>Simpan Nilai</button></div>
                        ` : `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Nilai sudah disimpan: ${soal.skor_essay} poin</div>`}
                    </form>
                    ` : `
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>Soal ini dinilai otomatis oleh sistem.
                    </div>
                    `}
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function simpanNilaiManual(soalId, hasilUjianId) {
    const form = document.querySelector(`form[data-soal-id="${soalId}"][data-hasil-ujian-id="${hasilUjianId}"]`);
    if (!form) return;
    
    const skorManual = form.querySelector('.nilai-input').value;
    if (!skorManual) { alert('Harap masukkan nilai!'); return; }
    
    const btnSimpan = form.querySelector('.btn-success');
    const originalText = btnSimpan.innerHTML;
    btnSimpan.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...';
    btnSimpan.disabled = true;
    
    const formData = new FormData(form);
    fetch('input_nilai.php', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(data => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalInputNilai'));
            modal.hide();
            setTimeout(() => window.location.reload(), 500);
        })
        .catch(error => { console.error('Error:', error); alert('Terjadi kesalahan saat menyimpan'); btnSimpan.innerHTML = originalText; btnSimpan.disabled = false; });
}

document.getElementById('modalInputNilai')?.addEventListener('hidden.bs.modal', function () {
    document.getElementById('soalList').innerHTML = '';
    document.getElementById('modalLoading').style.display = 'block';
    document.getElementById('modalContent').style.display = 'none';
});
</script>

<style>.bg-purple { background-color: #6f42c1 !important; }</style>

<?php include 'templates/footer.php'; ?>