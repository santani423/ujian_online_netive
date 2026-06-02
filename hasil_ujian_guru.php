<?php
// hasil_ujian_guru.php - Halaman lihat hasil ujian untuk guru (AUTO SYNC - SAMA DENGAN hasil_ujian.php)

// Mulai output buffering di awal
ob_start();

require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$guru_id = $_SESSION['guru_id'] ?? 0;
$nama_lengkap = $_SESSION['nama_lengkap'];
$is_guru_mapel = $_SESSION['is_guru_mapel'] ?? false;
$mapel_diampu = $_SESSION['mapel_diampu'] ?? [];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? [];

// ===================================================
// FUNGSI UTILITY - SAMA PERSIS DENGAN hasil_ujian.php
// ===================================================

function deteksiJenisSoal($soal) {
    if (!empty($soal['jenis_soal']) && $soal['jenis_soal'] != 'pilihan_ganda') {
        return $soal['jenis_soal'];
    }
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

function hitungNilaiPGKompleks($jawaban_siswa, $jawaban_kompleks, $skor_per_jawaban) {
    if (empty($jawaban_siswa) || empty($jawaban_kompleks)) return 0;
    
    $jawabanArray = explode(',', $jawaban_siswa);
    $jawabanBenar = json_decode($jawaban_kompleks, true);
    if (!is_array($jawabanBenar)) $jawabanBenar = [];
    
    $jumlahBenar = 0;
    foreach ($jawabanArray as $jwb) {
        if (in_array(trim($jwb), $jawabanBenar)) $jumlahBenar++;
    }
    return $jumlahBenar * $skor_per_jawaban;
}

function hitungGrade($nilai) {
    if ($nilai >= 90) return 'A';
    if ($nilai >= 80) return 'B';
    if ($nilai >= 70) return 'C';
    if ($nilai >= 60) return 'D';
    return 'E';
}

function formatNilai($nilai) {
    if ($nilai == 0 || $nilai == null) return '0.00';
    return number_format($nilai, 2);
}

function cekKelulusan($nilai, $kkm) {
    if ($nilai == 0 || $nilai == null) {
        return ['status' => false, 'status_text' => 'Belum', 'status_class' => 'secondary'];
    }
    if ($nilai >= $kkm) {
        return ['status' => true, 'status_text' => 'LULUS', 'status_class' => 'success'];
    } else {
        return ['status' => false, 'status_text' => 'TIDAK LULUS', 'status_class' => 'danger'];
    }
}

function getKKM($mapel_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT kkm FROM mata_pelajaran WHERE id = ?");
        $stmt->execute([$mapel_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['kkm'])) return (int)$result['kkm'];
    } catch (PDOException $e) {
        error_log("Error getKKM: " . $e->getMessage());
    }
    return 70;
}

function hitungNilaiAkhirLengkap($hasil_ujian_id, $ujian_id = null) {
    global $pdo;
    
    try {
        $stmt_soal = $pdo->prepare("
            SELECT su.*, s.* 
            FROM soal_ujian su 
            JOIN soal s ON su.soal_id = s.id 
            WHERE su.ujian_id = ? 
            ORDER BY su.urutan
        ");
        $stmt_soal->execute([$ujian_id]);
        $soal_list = $stmt_soal->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($soal_list)) return 0;
        
        $stmt_jawaban = $pdo->prepare("SELECT soal_id, jawaban_siswa, jawaban_essay FROM jawaban_siswa WHERE hasil_ujian_id = ?");
        $stmt_jawaban->execute([$hasil_ujian_id]);
        $jawaban_map = [];
        while ($jwb = $stmt_jawaban->fetch(PDO::FETCH_ASSOC)) {
            $jawaban_map[$jwb['soal_id']] = $jwb;
        }
        
        $stmt_manual = $pdo->prepare("SELECT soal_id, skor_essay FROM jawaban_essay WHERE hasil_ujian_id = ?");
        $stmt_manual->execute([$hasil_ujian_id]);
        $manual_map = [];
        while ($manual = $stmt_manual->fetch(PDO::FETCH_ASSOC)) {
            $manual_map[$manual['soal_id']] = $manual['skor_essay'];
        }
        
        $nilai_otomatis = 0;
        $total_skor = 0;
        
        foreach ($soal_list as $soal) {
            $jenis = deteksiJenisSoal($soal);
            $skor_soal = $soal['skor'];
            $total_skor += $skor_soal;
            
            if ($jenis == 'pilihan_ganda') {
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                if ($jawaban == $soal['jawaban_benar']) $nilai_otomatis += $skor_soal;
            } 
            elseif ($jenis == 'pilihan_ganda_kompleks') {
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                $nilai_otomatis += hitungNilaiPGKompleks($jawaban, $soal['jawaban_kompleks'], $soal['skor_per_jawaban']);
            }
            elseif ($jenis == 'benar_salah') {
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                if ($jawaban == $soal['jawaban_benar']) $nilai_otomatis += $skor_soal;
            }
        }
        
        $nilai_manual = 0;
        foreach ($manual_map as $skor) {
            $nilai_manual += $skor;
        }
        
        $total_nilai = $nilai_otomatis + $nilai_manual;
        $persentase = $total_skor > 0 ? ($total_nilai / $total_skor) * 100 : 0;
        
        return round($persentase, 2);
        
    } catch (PDOException $e) {
        error_log("Error hitungNilaiAkhirLengkap: " . $e->getMessage());
        return 0;
    }
}

function sinkronkanNilaiUjian($ujian_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM hasil_ujian WHERE ujian_id = ? AND status IN ('selesai', 'waktu_habis')");
        $stmt->execute([$ujian_id]);
        $hasil_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated_count = 0;
        foreach ($hasil_list as $hasil) {
            $nilai_baru = hitungNilaiAkhirLengkap($hasil['id'], $ujian_id);
            $stmt_update = $pdo->prepare("UPDATE hasil_ujian SET nilai = ? WHERE id = ?");
            $stmt_update->execute([$nilai_baru, $hasil['id']]);
            $updated_count++;
        }
        return $updated_count;
        
    } catch (PDOException $e) {
        error_log("Error sinkronkanNilaiUjian: " . $e->getMessage());
        return 0;
    }
}

function updateStatusWaktuHabis($ujian_id = null) {
    global $pdo;
    
    try {
        $sql = "
            UPDATE hasil_ujian hu
            JOIN ujian u ON hu.ujian_id = u.id
            SET hu.status = 'waktu_habis'
            WHERE hu.status = 'sedang_ujian' AND u.waktu_selesai < NOW()
        ";
        if ($ujian_id) {
            $sql .= " AND u.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ujian_id]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Error updateStatusWaktuHabis: " . $e->getMessage());
        return 0;
    }
}

function formatKelasDisplay($kelas_str) {
    if (empty($kelas_str)) return '-';
    $kelas_array = explode(',', $kelas_str);
    $badges = [];
    foreach ($kelas_array as $k) {
        $badges[] = '<span class="badge bg-info me-1">' . htmlspecialchars(trim($k)) . '</span>';
    }
    return implode(' ', $badges);
}

// ===================================================
// AUTO SYNC - JALANKAN SETIAP KALI HALAMAN DIAKSES
// ===================================================

// Ambil parameter filter
$filter_kelas = $_GET['kelas'] ?? '';
$filter_mapel = $_GET['mapel'] ?? '';
$filter_ujian = $_GET['ujian_id'] ?? '';
$filter_tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$filter_tanggal_selesai = $_GET['tanggal_selesai'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Ambil data untuk filter
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll();
$mapel_list = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// Jika guru mapel, filter berdasarkan mapel yang diampu
if ($is_guru_mapel) {
    $mapel_ids = array_column($mapel_diampu, 'id');
    $kelas_list_filtered = array_column($kelas_diampu, 'kelas');
    
    if (count($mapel_ids) > 0) {
        $mapel_placeholders = str_repeat('?,', count($mapel_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM mata_pelajaran WHERE id IN ($mapel_placeholders) ORDER BY nama_mapel");
        $stmt->execute($mapel_ids);
        $mapel_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (count($kelas_list_filtered) > 0) {
        $kelas_list = array_map(function($kelas) { return ['kelas' => $kelas]; }, $kelas_list_filtered);
    }
}

// Ambil daftar ujian untuk filter
$where_conditions = [];
$params = [];

if ($is_guru_mapel && isset($mapel_ids) && count($mapel_ids) > 0) {
    $where_conditions[] = "u.mapel_id IN (" . str_repeat('?,', count($mapel_ids) - 1) . "?)";
    $params = array_merge($params, $mapel_ids);
}

if ($filter_mapel) {
    $where_conditions[] = "u.mapel_id = ?";
    $params[] = $filter_mapel;
}

if ($filter_tanggal_mulai) {
    $where_conditions[] = "DATE(u.waktu_mulai) >= ?";
    $params[] = $filter_tanggal_mulai;
}

if ($filter_tanggal_selesai) {
    $where_conditions[] = "DATE(u.waktu_selesai) <= ?";
    $params[] = $filter_tanggal_selesai;
}

$where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$ujian_list = $pdo->prepare("
    SELECT u.*, mp.nama_mapel, mp.kode_mapel, mp.kkm
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

// Jika ada ujian_id yang dipilih, lakukan AUTO SYNC
$auto_sync_updated = 0;
if ($filter_ujian) {
    updateStatusWaktuHabis($filter_ujian);
    $auto_sync_updated = sinkronkanNilaiUjian($filter_ujian);
}

// Jika ada ujian_id yang dipilih, ambil data hasil ujian
$ujian_data = null;
$hasil_ujian_list = [];
$statistik_ujian = [];

if ($filter_ujian) {
    $stmt = $pdo->prepare("
        SELECT u.*, mp.nama_mapel, mp.kode_mapel, mp.kkm
        FROM ujian u 
        JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$filter_ujian]);
    $ujian_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $kkm_mapel = $ujian_data['kkm'] ?? 70;
    
    // Query hasil ujian dengan FIND_IN_SET untuk multi kelas
    $sql = "
        SELECT 
            s.id as siswa_id,
            s.nisn,
            s.nama as nama_siswa,
            s.kelas as kelas_siswa,
            s.jenis_kelamin,
            hu.id as hasil_ujian_id,
            hu.nilai,
            hu.waktu_mulai,
            hu.waktu_selesai,
            hu.status,
            (SELECT COUNT(*) FROM soal_ujian WHERE ujian_id = ?) as total_soal,
            (SELECT COUNT(*) FROM jawaban_siswa WHERE hasil_ujian_id = hu.id) as jumlah_dijawab
        FROM siswa s
        LEFT JOIN hasil_ujian hu ON hu.siswa_id = s.id AND hu.ujian_id = ?
        CROSS JOIN ujian u ON u.id = ?
        WHERE u.id = ?
        AND (FIND_IN_SET(s.kelas, u.kelas_target) > 0 OR u.kelas_target = '' OR u.kelas_target IS NULL)
    ";
    
    $params_sql = [$filter_ujian, $filter_ujian, $filter_ujian, $filter_ujian];
    
    if ($filter_kelas) {
        $sql .= " AND s.kelas = ?";
        $params_sql[] = $filter_kelas;
    } elseif ($is_guru_mapel && isset($kelas_list_filtered) && count($kelas_list_filtered) > 0) {
        $sql .= " AND s.kelas IN (" . str_repeat('?,', count($kelas_list_filtered) - 1) . "?)";
        $params_sql = array_merge($params_sql, $kelas_list_filtered);
    }
    
    if (!empty($filter_status)) {
        if ($filter_status == 'sedang') $sql .= " AND hu.status = 'sedang_ujian'";
        elseif ($filter_status == 'selesai') $sql .= " AND hu.status = 'selesai'";
        elseif ($filter_status == 'belum') $sql .= " AND hu.id IS NULL";
    }
    
    $sql .= " ORDER BY (CASE WHEN hu.id IS NULL THEN 1 ELSE 0 END), hu.nilai DESC, s.nama";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_sql);
    $hasil_ujian_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hitung statistik
    $siswa_dengan_hasil = array_filter($hasil_ujian_list, function($h) { return $h['hasil_ujian_id'] !== null; });
    $total_siswa = count($siswa_dengan_hasil);
    $nilai_tertinggi = 0;
    $nilai_terendah = 100;
    $total_nilai = 0;
    $lulus = 0;
    $tidak_lulus = 0;
    
    foreach ($siswa_dengan_hasil as $hasil) {
        $nilai = $hasil['nilai'] ?? 0;
        if ($nilai > $nilai_tertinggi) $nilai_tertinggi = $nilai;
        if ($nilai < $nilai_terendah && $nilai > 0) $nilai_terendah = $nilai;
        $total_nilai += $nilai;
        if ($nilai >= $kkm_mapel) $lulus++; else $tidak_lulus++;
    }
    
    $rata_rata = $total_siswa > 0 ? $total_nilai / $total_siswa : 0;
    $nilai_terendah = ($nilai_terendah == 100) ? 0 : $nilai_terendah;
    
    $statistik_ujian = [
        'total_siswa' => $total_siswa,
        'nilai_tertinggi' => $nilai_tertinggi,
        'nilai_terendah' => $nilai_terendah,
        'rata_rata' => $rata_rata,
        'lulus' => $lulus,
        'tidak_lulus' => $tidak_lulus,
        'persentase_lulus' => $total_siswa > 0 ? ($lulus / $total_siswa) * 100 : 0,
        'kkm' => $kkm_mapel
    ];
}

$title = "Hasil Ujian - Guru";
include 'templates/header_guru.php';
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-chart-bar me-2"></i>Hasil Ujian</h2>
            <p class="text-muted mb-0">Hasil ujian dengan koreksi otomatis & manual (essay) - KKM per mata pelajaran</p>
            <?php if ($auto_sync_updated > 0): ?>
            <p class="text-success small mt-1"><i class="fas fa-sync-alt me-1"></i>Sinkronisasi otomatis: <?= $auto_sync_updated ?> nilai diperbarui</p>
            <?php endif; ?>
        </div>
        <div class="text-muted">
            Selamat datang, <span class="fw-bold"><?= htmlspecialchars($nama_lengkap) ?></span>
            <?php if ($is_guru_mapel): ?>
                <span class="badge bg-primary ms-2">Guru Mata Pelajaran</span>
            <?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Informasi Role (jika guru mapel) -->
    <?php if ($is_guru_mapel && !empty($mapel_diampu)): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Informasi Mengajar</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Mata Pelajaran:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($mapel_diampu as $mapel): ?>
                                    <span class="badge bg-primary p-2">
                                        <i class="fas fa-book me-1"></i>
                                        <?= htmlspecialchars($mapel['nama_mapel']) ?>
                                        <small class="ms-1">(KKM: <?= $mapel['kkm'] ?? 70 ?>)</small>
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
                                        Kelas <?= htmlspecialchars($kelas['kelas']) ?>
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

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Hasil Ujian</h5>
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
                            <select name="mapel" class="form-select" id="selectMapel">
                                <option value="">Semua Mapel</option>
                                <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?= $mapel['id'] ?>" <?= $filter_mapel == $mapel['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mapel['nama_mapel']) ?> (KKM: <?= $mapel['kkm'] ?? 70 ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">Ujian</label>
                            <select name="ujian_id" class="form-select" id="selectUjian">
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
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="belum" <?= $filter_status == 'belum' ? 'selected' : '' ?>>Belum Mengerjakan</option>
                                <option value="sedang" <?= $filter_status == 'sedang' ? 'selected' : '' ?>>Sedang Ujian</option>
                                <option value="selesai" <?= $filter_status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" class="form-control" value="<?= $filter_tanggal_mulai ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tanggal Selesai</label>
                            <input type="date" name="tanggal_selesai" class="form-control" value="<?= $filter_tanggal_selesai ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Terapkan Filter
                        </button>
                        <a href="hasil_ujian_guru.php" class="btn btn-secondary">
                            <i class="fas fa-refresh me-2"></i>Reset Filter
                        </a>
                        <?php if ($filter_ujian): ?>
                        <a href="export_hasil.php?ujian_id=<?= $filter_ujian ?>&kelas=<?= $filter_kelas ?>" class="btn btn-success ms-2">
                            <i class="fas fa-download me-2"></i>Export Excel
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($ujian_data): ?>
    <!-- Statistik Ujian -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h4 class="mb-0"><?= $statistik_ujian['total_siswa'] ?></h4><p class="mb-0 small">Total Peserta</p></div>
                    <i class="fas fa-users fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-success h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h4 class="mb-0"><?= number_format($statistik_ujian['rata_rata'], 1) ?></h4><p class="mb-0 small">Rata-rata Nilai</p></div>
                    <i class="fas fa-chart-line fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-info h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h4 class="mb-0"><?= number_format($statistik_ujian['nilai_tertinggi'], 1) ?></h4><p class="mb-0 small">Nilai Tertinggi</p></div>
                    <i class="fas fa-trophy fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-warning h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h4 class="mb-0"><?= number_format($statistik_ujian['persentase_lulus'], 1) ?>%</h4><p class="mb-0 small">Tingkat Kelulusan</p></div>
                    <i class="fas fa-graduation-cap fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Ujian -->
    <div class="card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Ujian Terpilih</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless table-sm">
                        <tr><td width="35%" class="text-muted">Judul Ujian</td><td class="fw-bold"><?= htmlspecialchars($ujian_data['judul_ujian']) ?></td></tr>
                        <tr><td class="text-muted">Mata Pelajaran</td><td><?= htmlspecialchars($ujian_data['nama_mapel']) ?> (<?= htmlspecialchars($ujian_data['kode_mapel']) ?>)</td></tr>
                        <tr><td class="text-muted">Waktu Ujian</td><td><?= date('d/m/Y H:i', strtotime($ujian_data['waktu_mulai'])) ?> - <?= date('H:i', strtotime($ujian_data['waktu_selesai'])) ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless table-sm">
                        <tr><td width="35%" class="text-muted">Kelas Target</td><td><?= formatKelasDisplay($ujian_data['kelas_target']) ?></td></tr>
                        <tr><td class="text-muted">Durasi</td><td><?= $ujian_data['durasi'] ?> menit</td></tr>
                        <tr><td class="text-muted">KKM</td><td><span class="badge bg-info"><?= $ujian_data['kkm'] ?? 70 ?></span></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Hasil Ujian -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Daftar Hasil Ujian Peserta</h5>
        </div>
        <div class="card-body p-0">
            <?php if (count($hasil_ujian_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th width="50" class="text-center">No</th>
                                <th>NISN</th>
                                <th>Nama Siswa</th>
                                <th width="80" class="text-center">Kelas</th>
                                <th width="100" class="text-center">Status</th>
                                <th width="100" class="text-center">Nilai</th>
                                <th width="80" class="text-center">KKM</th>
                                <th width="100" class="text-center">Status Kelulusan</th>
                                <th width="120" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($hasil_ujian_list as $hasil): 
                                $nilai = $hasil['nilai'] ?? 0;
                                $is_lulus = $nilai >= $kkm_mapel;
                                $nilai_class = $is_lulus ? 'success' : 'danger';
                                $status_class = $hasil['hasil_ujian_id'] ? ($hasil['status'] == 'selesai' ? 'success' : 'warning') : 'secondary';
                                $status_text = $hasil['hasil_ujian_id'] ? ($hasil['status'] == 'selesai' ? 'Selesai' : 'Sedang Ujian') : 'Belum Mengerjakan';
                                $persen_jawaban = $hasil['total_soal'] > 0 ? round(($hasil['jumlah_dijawab'] / $hasil['total_soal']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td class="text-center align-middle"><?= $no++ ?></td>
                                <td class="align-middle"><?= htmlspecialchars($hasil['nisn']) ?></td>
                                <td class="align-middle"><strong><?= htmlspecialchars($hasil['nama_siswa']) ?></strong></td>
                                <td class="text-center align-middle"><?= htmlspecialchars($hasil['kelas_siswa']) ?></td>
                                <td class="text-center align-middle">
                                    <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                    <?php if ($hasil['hasil_ujian_id']): ?>
                                    <br><small class="text-muted">Jawaban: <?= $hasil['jumlah_dijawab'] ?>/<?= $hasil['total_soal'] ?> (<?= $persen_jawaban ?>%)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if ($hasil['hasil_ujian_id']): ?>
                                    <span class="badge bg-<?= $nilai_class ?> fs-6 p-2" style="min-width: 70px;"><?= number_format($nilai, 1) ?>%</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary p-2">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle"><span class="badge bg-info"><?= $kkm_mapel ?></span></td>
                                <td class="text-center align-middle">
                                    <?php if ($hasil['hasil_ujian_id']): ?>
                                    <span class="badge bg-<?= $is_lulus ? 'success' : 'danger' ?> px-3 py-2">
                                        <?= $is_lulus ? 'LULUS' : 'TIDAK LULUS' ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if ($hasil['hasil_ujian_id']): ?>
                                    <a href="resume_siswa_pdf.php?hasil_ujian_id=<?= $hasil['hasil_ujian_id'] ?>&ujian_id=<?= $filter_ujian ?>" 
                                       class="btn btn-sm btn-danger" 
                                       target="_blank"
                                       title="Resume Soal dan Jawaban">
                                        <i class="fas fa-file-pdf me-1"></i>Resume
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada data hasil ujian</h5>
                    <p class="text-muted">Silakan pilih filter yang berbeda atau tunggu siswa mengerjakan ujian.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">Pilih Ujian Terlebih Dahulu</h5>
            <p class="text-muted">Silakan pilih ujian dari filter di atas untuk melihat hasil.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.stat-item { transition: transform 0.2s; border: 1px solid #dee2e6; }
.stat-item:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.gap-2 { gap: 0.5rem; }
.table-bordered th, .table-bordered td { border: 1px solid #dee2e6; vertical-align: middle; }
@media print {
    .no-print { display: none !important; }
    .btn { display: none !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectMapel = document.getElementById('selectMapel');
    const selectUjian = document.getElementById('selectUjian');
    
    if (selectMapel) {
        selectMapel.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }
    
    if (selectUjian) {
        selectUjian.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>