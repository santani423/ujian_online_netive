<?php
// kerjakan_ujian.php - Lanjutkan ujian yang belum selesai (LAYOUT ORIGINAL + WAKTU SINKRON + MULTI KELAS)
require_once 'config.php';

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header('Location: login.php');
    exit();
}

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    flashMessage('danger', 'ID ujian tidak valid!');
    header('Location: daftar_ujian.php');
    exit();
}

$hasil_ujian_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// AMBIL DATA SISWA
$stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
$stmt_siswa->execute([$user_id]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    flashMessage('danger', 'Data siswa tidak ditemukan!');
    header('Location: logout.php');
    exit();
}

// AMBIL DATA HASIL UJIAN
$stmt = $pdo->prepare("
    SELECT hu.*, u.*, mp.nama_mapel, mp.kode_mapel,
           s.nama as nama_siswa, s.kelas,
           (SELECT COUNT(*) FROM soal_ujian su WHERE su.ujian_id = u.id) as jumlah_soal
    FROM hasil_ujian hu
    JOIN ujian u ON hu.ujian_id = u.id
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id
    JOIN siswa s ON hu.siswa_id = s.id
    WHERE hu.id = ? AND hu.siswa_id = ?
    LIMIT 1
");

$stmt->execute([$hasil_ujian_id, $siswa['id']]);
$data_ujian = $stmt->fetch();

if (!$data_ujian) {
    flashMessage('danger', 'Ujian tidak ditemukan!');
    header('Location: daftar_ujian.php');
    exit();
}

// CEK STATUS UJIAN
$status_ujian = $data_ujian['status'] ?? '';
if ($status_ujian == 'selesai') {
    flashMessage('info', 'Ujian ini sudah selesai dikerjakan.');
    header('Location: detail_hasil_siswa.php?id=' . $hasil_ujian_id);
    exit();
}

// CEK KECURANGAN
$stmt_kecurangan = $pdo->prepare("
    SELECT COUNT(*) as jumlah_pelanggaran, 
           MAX(waktu_pelanggaran) as terakhir_pelanggaran 
    FROM log_kecurangan 
    WHERE hasil_ujian_id = ? 
    AND jenis_pelanggaran IN ('keluar_tab', 'keluar_focus', 'fullscreen_exit')
    AND waktu_pelanggaran > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt_kecurangan->execute([$hasil_ujian_id]);
$data_kecurangan = $stmt_kecurangan->fetch();
$jumlah_pelanggaran = $data_kecurangan['jumlah_pelanggaran'] ?? 0;
$terakhir_pelanggaran = $data_kecurangan['terakhir_pelanggaran'] ?? null;

// CEK PENALTI
if ($terakhir_pelanggaran) {
    $waktu_pelanggaran_ts = strtotime($terakhir_pelanggaran);
    $waktu_sekarang_ts = time();
    $selisih_detik = $waktu_sekarang_ts - $waktu_pelanggaran_ts;
    
    if ($selisih_detik < 300) {
        $sisa_penalti = 300 - $selisih_detik;
        $menit_penalti = ceil($sisa_penalti / 60);
        flashMessage('danger', "Anda terdeteksi melakukan pelanggaran! Ujian ditunda selama $menit_penalti menit.");
        $_SESSION['ujian_ditunda'] = true;
        $_SESSION['sisa_penalti'] = $sisa_penalti;
    } else {
        unset($_SESSION['ujian_ditunda']);
        unset($_SESSION['sisa_penalti']);
    }
}

// UPDATE STATUS JIKA MASIH NULL
if (empty($status_ujian) || $status_ujian == '0') {
    $update_status = $pdo->prepare("UPDATE hasil_ujian SET status = 'sedang_ujian' WHERE id = ?");
    $update_status->execute([$hasil_ujian_id]);
    $data_ujian['status'] = 'sedang_ujian';
}

// CEK WAKTU UJIAN - MENGGUNAKAN waktu_selesai DARI DATABASE (SINKRON)
$waktu_sekarang = time();
$waktu_selesai_ujian_db = strtotime($data_ujian['waktu_selesai']);

// HITUNG SISA WAKTU - BERDASARKAN waktu_selesai DARI DATABASE
$sisa_waktu = $waktu_selesai_ujian_db - $waktu_sekarang;

// Jika sisa waktu negatif, ujian sudah habis
if ($sisa_waktu <= 0) {
    $update_stmt = $pdo->prepare("UPDATE hasil_ujian SET status = 'selesai', waktu_selesai = ? WHERE id = ?");
    $update_stmt->execute([date('Y-m-d H:i:s', $waktu_sekarang), $hasil_ujian_id]);
    flashMessage('warning', 'Waktu ujian telah habis! Ujian diakhiri.');
    header('Location: daftar_ujian.php');
    exit();
}

// Jika sisa waktu lebih dari durasi (kasus aneh), batasi dengan durasi
$durasi_detik = $data_ujian['durasi'] * 60;
if ($sisa_waktu > $durasi_detik) {
    $sisa_waktu = $durasi_detik;
}

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
    
    $opsi_a = trim($soal['opsi_a'] ?? '');
    $opsi_b = trim($soal['opsi_b'] ?? '');
    $lines_a = explode("\n", $opsi_a);
    $lines_b = explode("\n", $opsi_b);
    
    $has_numbered_a = false;
    $has_numbered_b = false;
    foreach ($lines_a as $line) {
        if (preg_match('/^(\d+|[a-z])[\.\)\s]/', trim($line))) { $has_numbered_a = true; break; }
    }
    foreach ($lines_b as $line) {
        if (preg_match('/^(\d+|[a-z])[\.\)\s]/', trim($line))) { $has_numbered_b = true; break; }
    }
    
    $is_opsi_c_empty = empty($soal['opsi_c']) || trim($soal['opsi_c'] ?? '') == '-' || trim($soal['opsi_c'] ?? '') == '';
    $is_opsi_d_empty = empty($soal['opsi_d']) || trim($soal['opsi_d'] ?? '') == '-' || trim($soal['opsi_d'] ?? '') == '';
    $is_opsi_e_empty = empty($soal['opsi_e']) || trim($soal['opsi_e'] ?? '') == '-' || trim($soal['opsi_e'] ?? '') == '';
    
    if ($has_numbered_a && $has_numbered_b && $is_opsi_c_empty && $is_opsi_d_empty && $is_opsi_e_empty) {
        return 'menjodohkan';
    }
    
    if ($is_opsi_c_empty && $is_opsi_d_empty && $is_opsi_e_empty) {
        if (trim($soal['opsi_a'] ?? '') != 'Benar' && trim($soal['opsi_b'] ?? '') != 'Salah') {
            if (!$has_numbered_a && !$has_numbered_b) {
                return 'essay';
            }
        }
    }
    
    return 'pilihan_ganda';
}

// Parse soal menjodohkan
function parseSoalMenjodohkan($soal) {
    $result = ['kiri' => [], 'kanan' => []];
    
    if (!empty($soal['opsi_a'])) {
        $lines = explode("\n", $soal['opsi_a']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (preg_match('/^(\d+|[a-z])[\.\)\s]+(.+)$/', $line, $matches)) {
                $result['kiri'][] = ['id' => trim($matches[1]), 'teks' => trim($matches[2])];
            } else {
                $result['kiri'][] = ['id' => count($result['kiri']) + 1, 'teks' => $line];
            }
        }
    }
    
    if (!empty($soal['opsi_b'])) {
        $lines = explode("\n", $soal['opsi_b']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (preg_match('/^(\d+|[a-z])[\.\)\s]+(.+)$/', $line, $matches)) {
                $result['kanan'][] = ['id' => trim($matches[1]), 'teks' => trim($matches[2])];
            } else {
                $result['kanan'][] = ['id' => count($result['kanan']) + 1, 'teks' => $line];
            }
        }
    }
    
    return $result;
}

// AMBIL SOAL
$random_setting = getSetting('random_soal');
$ujian_id = $data_ujian['ujian_id'];

if ($random_setting == 1) {
    $seed = hexdec(substr(md5($siswa['id'] . $ujian_id), 0, 8));
    $stmt_soal = $pdo->prepare("
        SELECT su.*, s.* 
        FROM soal_ujian su
        JOIN soal s ON su.soal_id = s.id
        WHERE su.ujian_id = ? 
        ORDER BY RAND(?)
    ");
    $stmt_soal->execute([$ujian_id, $seed]);
} else {
    $stmt_soal = $pdo->prepare("
        SELECT su.*, s.* 
        FROM soal_ujian su
        JOIN soal s ON su.soal_id = s.id
        WHERE su.ujian_id = ? 
        ORDER BY su.urutan
    ");
    $stmt_soal->execute([$ujian_id]);
}
$soal_list = $stmt_soal->fetchAll();

if (empty($soal_list)) {
    flashMessage('danger', 'Soal ujian tidak ditemukan!');
    header('Location: daftar_ujian.php');
    exit();
}

// AMBIL JAWABAN YANG SUDAH DIISI
$stmt_jawaban = $pdo->prepare("SELECT soal_id, jawaban_siswa, jawaban_essay FROM jawaban_siswa WHERE hasil_ujian_id = ?");
$stmt_jawaban->execute([$hasil_ujian_id]);
$jawaban_siswa = [];
$jawaban_essay = [];
while ($jawaban = $stmt_jawaban->fetch()) {
    $jawaban_siswa[$jawaban['soal_id']] = $jawaban['jawaban_siswa'];
    $jawaban_essay[$jawaban['soal_id']] = $jawaban['jawaban_essay'];
}

// PROSES SIMPAN JAWABAN OTOMATIS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['auto_save']) && $data['auto_save'] == 1) {
        if (isset($data['jawaban']) && is_array($data['jawaban'])) {
            foreach ($data['jawaban'] as $soal_id => $jawaban) {
                $soal_id = intval($soal_id);
                $jenis_soal = '';
                foreach ($soal_list as $soal) {
                    if ($soal['soal_id'] == $soal_id) {
                        $jenis_soal = deteksiJenisSoal($soal);
                        break;
                    }
                }
                
                $stmt_cek = $pdo->prepare("SELECT id FROM jawaban_siswa WHERE hasil_ujian_id = ? AND soal_id = ?");
                $stmt_cek->execute([$hasil_ujian_id, $soal_id]);
                
                if ($stmt_cek->fetch()) {
                    if ($jenis_soal == 'essay' || $jenis_soal == 'menjodohkan') {
                        $stmt_update = $pdo->prepare("UPDATE jawaban_siswa SET jawaban_essay = ? WHERE hasil_ujian_id = ? AND soal_id = ?");
                        $stmt_update->execute([$jawaban, $hasil_ujian_id, $soal_id]);
                    } else {
                        $stmt_update = $pdo->prepare("UPDATE jawaban_siswa SET jawaban_siswa = ? WHERE hasil_ujian_id = ? AND soal_id = ?");
                        $stmt_update->execute([$jawaban, $hasil_ujian_id, $soal_id]);
                    }
                } else {
                    if ($jenis_soal == 'essay' || $jenis_soal == 'menjodohkan') {
                        $stmt_insert = $pdo->prepare("INSERT INTO jawaban_siswa (hasil_ujian_id, soal_id, jawaban_essay) VALUES (?, ?, ?)");
                        $stmt_insert->execute([$hasil_ujian_id, $soal_id, $jawaban]);
                    } else {
                        $stmt_insert = $pdo->prepare("INSERT INTO jawaban_siswa (hasil_ujian_id, soal_id, jawaban_siswa) VALUES (?, ?, ?)");
                        $stmt_insert->execute([$hasil_ujian_id, $soal_id, $jawaban]);
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Auto-saved']);
            exit();
        }
    }
    
    if (isset($data['log_kecurangan'])) {
        $stmt_log = $pdo->prepare("INSERT INTO log_kecurangan (hasil_ujian_id, siswa_id, jenis_pelanggaran, keterangan) VALUES (?, ?, ?, ?)");
        $stmt_log->execute([$hasil_ujian_id, $siswa['id'], $data['log_kecurangan'], $data['keterangan'] ?? '']);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if (isset($_POST['selesai_ujian'])) {
        $update_stmt = $pdo->prepare("UPDATE hasil_ujian SET status = 'selesai', waktu_selesai = ? WHERE id = ?");
        $update_stmt->execute([date('Y-m-d H:i:s', $waktu_sekarang), $hasil_ujian_id]);
        hitungNilaiUjian($hasil_ujian_id);
        header('Location: detail_hasil_siswa.php?id=' . $hasil_ujian_id);
        exit();
    }
}

$title = "Kerjakan Ujian - " . $data_ujian['judul_ujian'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= $title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .ujian-container { min-height: 100vh; padding: 20px; }
        .soal-card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .timer-container { position: sticky; top: 20px; z-index: 1000; }
        .timer-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 15px; text-align: center; }
        .timer-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); animation: pulse 1s infinite; }
        .nav-soal { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 20px; max-height: 200px; overflow-y: auto; }
        .btn-soal { width: 40px; height: 40px; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .btn-soal.terjawab { background-color: #28a745; color: white; }
        .btn-soal.sedang-dikerjakan { background-color: #007bff; color: white; }
        .btn-soal.ragu-ragu { background-color: #ffc107; color: #212529; }
        .opsi-jawaban { cursor: pointer; transition: all 0.3s; padding: 10px 15px; border-radius: 5px; margin-bottom: 10px; border: 1px solid #dee2e6; }
        .opsi-jawaban:hover { background-color: #f8f9fa; border-color: #007bff; }
        .opsi-jawaban.terpilih { background-color: #007bff; color: white; border-color: #0056b3; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
        .fullscreen-warning { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); color: white; display: flex; align-items: center; justify-content: center; z-index: 9999; flex-direction: column; text-align: center; }
        .penalty-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); color: white; display: flex; align-items: center; justify-content: center; z-index: 10000; flex-direction: column; text-align: center; }
        .info-acak { background-color: #e7f3ff; border-left: 4px solid #007bff; padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; }
        .soal-text { line-height: 1.6; word-wrap: break-word; overflow-wrap: break-word; }
        .save-indicator { position: fixed; bottom: 20px; right: 20px; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; display: none; z-index: 1000; }
        
        .jenis-soal-badge { font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; margin-left: 10px; vertical-align: middle; }
        .jenis-pilihan-ganda { background-color: #007bff; color: white; }
        .jenis-pg-kompleks { background-color: #6f42c1; color: white; }
        .jenis-essay { background-color: #28a745; color: white; }
        .jenis-menjodohkan { background-color: #ffc107; color: black; }
        .jenis-benar-salah { background-color: #dc3545; color: white; }
        
        .essay-textarea { min-height: 150px; font-size: 14px; line-height: 1.5; width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ced4da; resize: vertical; }
        
        .menjodohkan-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        @media (max-width: 768px) { .menjodohkan-container { grid-template-columns: 1fr; } }
        .menjodohkan-list { border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background-color: #f8f9fa; }
        .menjodohkan-item { padding: 10px; margin-bottom: 10px; border: 1px solid #ced4da; border-radius: 5px; background-color: white; cursor: move; transition: all 0.3s; user-select: none; }
        .menjodohkan-item:active { cursor: grabbing; }
        .menjodohkan-item:hover { background-color: #e9ecef; border-color: #6c757d; }
        .menjodohkan-target { min-height: 50px; border: 2px dashed #6c757d; border-radius: 5px; padding: 10px; margin-bottom: 10px; transition: all 0.3s; background-color: white; }
        .menjodohkan-target.active { border-color: #007bff; background-color: rgba(0,123,255,0.1); }
        .menjodohkan-target.has-item { border-color: #28a745; background-color: rgba(40,167,69,0.1); }
        .matched-item { background-color: #d4edda; border-color: #c3e6cb; padding: 8px; margin-bottom: 5px; border-radius: 4px; }
        
        .benar-salah-container { display: flex; gap: 20px; flex-wrap: wrap; }
        .benar-salah-option { flex: 1; min-width: 100px; text-align: center; padding: 15px; border: 2px solid #dee2e6; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .benar-salah-option.selected { border-color: #28a745; background-color: rgba(40,167,69,0.1); }
        .benar-salah-option.selected.benar { border-color: #28a745; background-color: #d4edda; }
        .benar-salah-option.selected.salah { border-color: #dc3545; background-color: #f8d7da; }
        .benar-salah-icon { font-size: 2rem; margin-bottom: 10px; }
        .benar-salah-text { font-size: 1.1rem; font-weight: bold; }
        
        .soal-gambar { max-width: 100%; height: auto; border-radius: 5px; margin: 10px 0; cursor: pointer; }
        .warning-counter { position: fixed; top: 10px; right: 10px; background: #dc3545; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; z-index: 1001; }
        
        .bg-purple { background-color: #6f42c1 !important; }
        
        @media (max-width: 768px) {
            .ujian-container { padding: 10px; }
            .timer-card { padding: 10px; }
            .timer-card h4 { font-size: 1.2rem; }
            .btn-soal { width: 35px; height: 35px; font-size: 0.8rem; }
            .opsi-jawaban { padding: 8px 12px; font-size: 0.9rem; }
            .essay-textarea { font-size: 16px; }
            .soal-text { font-size: 0.95rem; }
            textarea, input, select { font-size: 16px !important; }
            .benar-salah-option { padding: 12px; min-width: 120px; }
            .benar-salah-icon { font-size: 1.5rem; }
            .benar-salah-text { font-size: 1rem; }
        }
        
        button, .btn, .opsi-jawaban, .benar-salah-option { touch-action: manipulation; }
        
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 20000;
            cursor: pointer;
            justify-content: center;
            align-items: center;
        }
        .image-modal img { max-width: 90%; max-height: 90%; object-fit: contain; }
        .image-modal .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <span class="close-modal">&times;</span>
        <img id="modalImage" src="" alt="Preview Gambar">
    </div>

    <div id="penaltyOverlay" class="penalty-overlay d-none">
        <div class="container">
            <i class="fas fa-ban fa-5x mb-4 text-danger"></i>
            <h1 class="display-4 text-danger">UJIAN DITUNDA!</h1>
            <div class="alert alert-danger" style="max-width: 600px; margin: 0 auto;">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>Pelanggaran Terdeteksi</h4>
                <p class="mb-0">Ujian ditunda selama:</p>
            </div>
            <div id="penaltyTimer" class="display-1 my-4 text-warning">05:00</div>
        </div>
    </div>

    <div id="fullscreenWarning" class="fullscreen-warning d-none">
        <div class="container">
            <i class="fas fa-exclamation-triangle fa-4x mb-3 text-warning"></i>
            <h2>Peringatan!</h2>
            <button class="btn btn-warning btn-lg mt-3" onclick="hideFullscreenWarning()">Mengerti, Lanjutkan Ujian</button>
        </div>
    </div>

    <div id="warningCounter" class="warning-counter d-none">
        <i class="fas fa-exclamation-circle"></i>
        <span id="warningCount">0</span> Peringatan
    </div>

    <div id="saveIndicator" class="save-indicator">
        <i class="fas fa-check-circle me-2"></i>
        <span id="saveText">Jawaban tersimpan</span>
    </div>

    <div class="container-fluid ujian-container">
        <div class="row">
            <div class="col-lg-3">
                <div class="timer-container">
                    <div class="timer-card" id="timerCard">
                        <h4 id="timerDisplay">00:00:00</h4>
                        <small>Sisa Waktu</small>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-body">
                            <h6 class="card-title">Informasi Ujian</h6>
                            <p class="card-text small mb-1"><strong>Mata Pelajaran:</strong><br><?= htmlspecialchars($data_ujian['nama_mapel']) ?></p>
                            <p class="card-text small mb-1"><strong>Judul Ujian:</strong><br><?= htmlspecialchars($data_ujian['judul_ujian']) ?></p>
                            <p class="card-text small mb-1"><strong>Jumlah Soal:</strong><br><?= count($soal_list) ?> Soal</p>
                            <p class="card-text small"><strong>Durasi:</strong><br><?= $data_ujian['durasi'] ?> Menit</p>
                            
                            <?php if ($random_setting == 1): ?>
                            <div class="info-acak small mt-2"><i class="fas fa-random me-1"></i><strong>Urutan soal diacak</strong></div>
                            <?php endif; ?>
                            
                            <div class="alert alert-warning small mt-2 p-2">
                                <i class="fas fa-shield-alt me-1"></i>
                                <strong>Sistem Anti-Kecurangan Aktif</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header"><h6 class="mb-0">Navigasi Soal</h6></div>
                        <div class="card-body">
                            <div class="nav-soal" id="navSoalContainer">
                                <?php foreach ($soal_list as $index => $soal): 
                                    $soal_id = $soal['soal_id'];
                                    $jenis_soal = deteksiJenisSoal($soal);
                                    $badge_class = '';
                                    switch($jenis_soal) {
                                        case 'essay': $badge_class = 'bg-success'; break;
                                        case 'menjodohkan': $badge_class = 'bg-warning'; break;
                                        case 'benar_salah': $badge_class = 'bg-danger'; break;
                                        case 'pilihan_ganda_kompleks': $badge_class = 'bg-purple'; break;
                                        default: $badge_class = 'bg-primary';
                                    }
                                    $terjawab = isset($jawaban_siswa[$soal_id]) || isset($jawaban_essay[$soal_id]);
                                ?>
                                <button type="button" class="btn btn-outline-secondary btn-soal position-relative <?= $terjawab ? 'terjawab' : '' ?> <?= $index == 0 ? 'sedang-dikerjakan' : '' ?>" onclick="showSoal(<?= $index ?>)" id="navSoal<?= $index ?>" title="Soal <?= $index + 1 ?>">
                                    <?= $index + 1 ?>
                                    <?php if ($jenis_soal != 'pilihan_ganda'): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge <?= $badge_class ?> p-1" style="font-size: 0.55rem;"><?= substr(strtoupper($jenis_soal), 0, 1) ?></span>
                                    <?php endif; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-body">
                            <button type="button" class="btn btn-success w-100 mb-2" onclick="simpanSemuaJawaban()"><i class="fas fa-save me-2"></i>Simpan Semua Jawaban</button>
                            <button type="button" class="btn btn-warning w-100 mb-2" onclick="tandaiRaguRagu()"><i class="fas fa-question me-2"></i>Tandai Ragu-ragu</button>
                            <form method="POST" id="formSelesai">
                                <button type="submit" name="selesai_ujian" class="btn btn-danger w-100" onclick="return confirm('Yakin ingin menyelesaikan ujian?')"><i class="fas fa-flag me-2"></i>Selesaikan Ujian</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <div class="card soal-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Kerjakan Ujian (Dilanjutkan)</h5>
                        <span class="badge bg-light text-dark" id="soalCounter">Soal 1 dari <?= count($soal_list) ?></span>
                    </div>
                    <div class="card-body" id="soalCardBody">
                        <form id="formJawaban">
                            <input type="hidden" name="hasil_ujian_id" value="<?= $hasil_ujian_id ?>">
                            
                            <?php foreach ($soal_list as $index => $soal): 
                                $soal_id = $soal['soal_id'];
                                $jenis_soal = deteksiJenisSoal($soal);
                                $jawaban_terpilih = $jawaban_siswa[$soal_id] ?? '';
                                $jawaban_essay_text = $jawaban_essay[$soal_id] ?? '';
                                
                                $jawaban_kompleks_terpilih = !empty($jawaban_terpilih) ? explode(',', $jawaban_terpilih) : [];
                                
                                $badge_class = '';
                                $badge_text = '';
                                switch($jenis_soal) {
                                    case 'essay': $badge_class = 'bg-success'; $badge_text = 'ESSAY'; break;
                                    case 'menjodohkan': $badge_class = 'bg-warning'; $badge_text = 'JODOH'; break;
                                    case 'benar_salah': $badge_class = 'bg-danger'; $badge_text = 'BENAR/SALAH'; break;
                                    case 'pilihan_ganda_kompleks': $badge_class = 'bg-purple'; $badge_text = 'PG KOMPLEKS'; break;
                                    default: $badge_class = 'bg-primary'; $badge_text = 'PILIHAN GANDA';
                                }
                            ?>
                            <div class="soal-content <?= $index == 0 ? '' : 'd-none' ?>" id="soal<?= $index ?>">
                                <input type="hidden" name="soal_id[]" value="<?= $soal_id ?>">
                                <input type="hidden" name="jenis_soal[]" value="<?= $jenis_soal ?>">
                                
                                <div class="soal-text mb-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                                        <h6>Soal <?= $index + 1 ?></h6>
                                        <span class="badge <?= $badge_class ?>"><?= $badge_text ?></span>
                                    </div>
                                    <div class="border-start border-3 border-primary ps-3 mt-2">
                                        <?= nl2br(htmlspecialchars($soal['pertanyaan'])) ?>
                                        
                                        <?php if (!empty($soal['gambar_soal'])): ?>
                                        <div class="mt-3">
                                            <span class="text-muted small"><i class="fas fa-image me-1"></i>Gambar soal:</span>
                                            <img src="<?= htmlspecialchars($soal['gambar_soal']) ?>" alt="Gambar soal" class="soal-gambar img-fluid d-block" onclick="showImagePreview('<?= htmlspecialchars($soal['gambar_soal']) ?>')">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($soal['video_soal'])): ?>
                                        <div class="mt-3">
                                            <span class="text-muted small"><i class="fas fa-video me-1"></i>Video soal:</span>
                                            <div onclick="showVideoPreview('<?= htmlspecialchars($soal['video_soal']) ?>')" style="cursor: pointer;">
                                                <i class="fas fa-play-circle fa-2x text-primary"></i> Tonton Video
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- PILIHAN GANDA KOMPLEKS -->
                                <?php if ($jenis_soal == 'pilihan_ganda_kompleks'): ?>
                                <div class="opsi-jawaban-list">
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Pilihan Ganda Kompleks:</strong> Pilih lebih dari satu jawaban yang benar.
                                        Skor: <?= $soal['skor_per_jawaban'] ?? 5 ?> poin per jawaban benar.
                                    </div>
                                    <?php 
                                    $opsi_list = ['a', 'b', 'c', 'd', 'e'];
                                    foreach ($opsi_list as $opsi): 
                                        $opsi_text = $soal['opsi_' . $opsi] ?? '';
                                        if (empty($opsi_text) || $opsi_text == '-') continue;
                                    ?>
                                    <div class="opsi-jawaban <?= in_array($opsi, $jawaban_kompleks_terpilih) ? 'terpilih' : '' ?>" 
                                         onclick="pilihJawabanPGKompleks(<?= $index ?>, '<?= $opsi ?>', <?= $soal_id ?>)">
                                        <input type="checkbox" class="form-check-input" 
                                               name="jawaban[<?= $soal_id ?>][]" 
                                               id="soal<?= $soal_id ?>_<?= $opsi ?>" value="<?= $opsi ?>" 
                                               <?= in_array($opsi, $jawaban_kompleks_terpilih) ? 'checked' : '' ?>
                                               style="display: none;">
                                        <label class="form-check-label w-100 mb-0" for="soal<?= $soal_id ?>_<?= $opsi ?>">
                                            <strong><?= strtoupper($opsi) ?>.</strong> 
                                            <?= nl2br(htmlspecialchars($opsi_text)) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- PILIHAN GANDA BIASA -->
                                <?php elseif ($jenis_soal == 'pilihan_ganda'): ?>
                                <div class="opsi-jawaban-list">
                                    <?php 
                                    $opsi_list = ['a', 'b', 'c', 'd', 'e'];
                                    foreach ($opsi_list as $opsi): 
                                        $opsi_text = $soal['opsi_' . $opsi] ?? '';
                                        if (empty($opsi_text) || $opsi_text == '-') continue;
                                    ?>
                                    <div class="opsi-jawaban <?= ($jawaban_terpilih == $opsi) ? 'terpilih' : '' ?>" 
                                         onclick="pilihJawabanPilihanGanda(<?= $index ?>, '<?= $opsi ?>', <?= $soal_id ?>)">
                                        <input class="form-check-input" type="radio" 
                                               name="jawaban[<?= $soal_id ?>]" 
                                               id="soal<?= $soal_id ?>_<?= $opsi ?>" value="<?= $opsi ?>" 
                                               <?= ($jawaban_terpilih == $opsi) ? 'checked' : '' ?>
                                               style="display: none;">
                                        <label class="form-check-label w-100 mb-0" for="soal<?= $soal_id ?>_<?= $opsi ?>">
                                            <strong><?= strtoupper($opsi) ?>.</strong> 
                                            <?= nl2br(htmlspecialchars($opsi_text)) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- ESSAY -->
                                <?php elseif ($jenis_soal == 'essay'): ?>
                                <div class="form-group">
                                    <label for="essay<?= $soal_id ?>" class="form-label"><strong>Jawaban Essay:</strong></label>
                                    <textarea id="essay<?= $soal_id ?>" name="jawaban[<?= $soal_id ?>]" class="form-control essay-textarea" rows="6"
                                              oninput="updateEssay(<?= $index ?>, <?= $soal_id ?>, this.value)"><?= htmlspecialchars($jawaban_essay_text) ?></textarea>
                                    <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle"></i> Jawaban essay akan dikoreksi manual oleh guru.</small>
                                </div>
                                
                                <!-- MENJODOHKAN -->
                                <?php elseif ($jenis_soal == 'menjodohkan'): 
                                    $parsed_soal = parseSoalMenjodohkan($soal);
                                    $items_kiri = $parsed_soal['kiri'];
                                    $items_kanan = $parsed_soal['kanan'];
                                    $jawaban_pasangan = [];
                                    if (!empty($jawaban_essay_text)) {
                                        $pairs = explode(',', $jawaban_essay_text);
                                        foreach ($pairs as $pair) {
                                            $pair = trim($pair);
                                            if (strpos($pair, '-') !== false) {
                                                list($kiri, $kanan) = explode('-', $pair, 2);
                                                $jawaban_pasangan[trim($kiri)] = trim($kanan);
                                            }
                                        }
                                    }
                                    shuffle($items_kanan);
                                ?>
                                <div class="jodoh-info alert alert-info"><i class="fas fa-info-circle me-2"></i>Drag item dari kolom kiri ke kolom kanan untuk menjodohkan.</div>
                                <div class="menjodohkan-container">
                                    <div class="menjodohkan-list">
                                        <h6 class="text-center mb-3">Pernyataan</h6>
                                        <div id="items-kiri-<?= $soal_id ?>" class="items-container">
                                            <?php foreach ($items_kiri as $item): 
                                                $item_id = $item['id'];
                                                $is_matched = isset($jawaban_pasangan[$item_id]);
                                            ?>
                                            <div class="menjodohkan-item" data-soal-id="<?= $soal_id ?>" data-item-id="<?= htmlspecialchars($item_id) ?>"
                                                 draggable="true" ondragstart="dragStartMenjodohkan(event)" id="item-<?= $soal_id ?>-<?= htmlspecialchars($item_id) ?>"
                                                 <?= $is_matched ? 'style="display: none;"' : '' ?>>
                                                <strong><?= htmlspecialchars($item_id) ?>.</strong> <?= htmlspecialchars($item['teks']) ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="menjodohkan-list">
                                        <h6 class="text-center mb-3">Pasangan</h6>
                                        <div id="items-kanan-<?= $soal_id ?>" class="items-container">
                                            <?php foreach ($items_kanan as $item): 
                                                $item_id = $item['id'];
                                                $target_id = "target-" . $soal_id . "-" . $item_id;
                                                $matched_kiri = '';
                                                $is_target_used = false;
                                                foreach ($jawaban_pasangan as $kiri => $kanan) {
                                                    if ($kanan == $item_id) { $matched_kiri = $kiri; $is_target_used = true; break; }
                                                }
                                            ?>
                                            <div class="menjodohkan-target" id="<?= $target_id ?>" data-soal-id="<?= $soal_id ?>" data-item-id="<?= htmlspecialchars($item_id) ?>"
                                                 ondragover="dragOverMenjodohkan(event)" ondrop="dropMenjodohkan(event)" ondragenter="dragEnterMenjodohkan(event)" ondragleave="dragLeaveMenjodohkan(event)"
                                                 <?= $is_target_used ? 'data-has-item="true"' : '' ?>>
                                                <div class="drop-zone" <?= $is_target_used ? 'style="display: none;"' : '' ?>><i class="fas fa-hand-point-up"></i><br>Drop di sini</div>
                                                <?php if ($is_target_used): ?>
                                                <div class="matched-item" id="matched-<?= $soal_id ?>-<?= $item_id ?>">
                                                    <strong><?= htmlspecialchars($item_id) ?>.</strong> <?= htmlspecialchars($item['teks']) ?>
                                                    <div class="mt-2"><span class="badge bg-success">Dijodohkan dengan: <?= $matched_kiri ?></span>
                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeMatch('<?= $soal_id ?>', '<?= $item_id ?>', '<?= $matched_kiri ?>')"><i class="fas fa-times"></i></button></div>
                                                </div>
                                                <?php else: ?>
                                                <div class="item-content"><strong><?= htmlspecialchars($item_id) ?>.</strong> <?= htmlspecialchars($item['teks']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <input type="hidden" name="jawaban[<?= $soal_id ?>]" id="jawabanMenjodohkan<?= $soal_id ?>" value="<?= htmlspecialchars($jawaban_essay_text) ?>">
                                </div>
                                
                                <!-- BENAR/SALAH -->
                                <?php elseif ($jenis_soal == 'benar_salah'): ?>
                                <div class="benar-salah-container">
                                    <div class="benar-salah-option <?= ($jawaban_terpilih == 'a') ? 'selected benar' : '' ?>" 
                                         onclick="pilihJawabanPilihanGanda(<?= $index ?>, 'a', <?= $soal_id ?>)">
                                        <input type="radio" name="jawaban[<?= $soal_id ?>]" id="soal<?= $soal_id ?>_a" value="a" <?= ($jawaban_terpilih == 'a') ? 'checked' : '' ?> style="display: none;">
                                        <div class="benar-salah-icon"><i class="fas fa-check-circle text-success"></i></div>
                                        <div class="benar-salah-text"><strong>BENAR</strong></div>
                                    </div>
                                    <div class="benar-salah-option <?= ($jawaban_terpilih == 'b') ? 'selected salah' : '' ?>" 
                                         onclick="pilihJawabanPilihanGanda(<?= $index ?>, 'b', <?= $soal_id ?>)">
                                        <input type="radio" name="jawaban[<?= $soal_id ?>]" id="soal<?= $soal_id ?>_b" value="b" <?= ($jawaban_terpilih == 'b') ? 'checked' : '' ?> style="display: none;">
                                        <div class="benar-salah-icon"><i class="fas fa-times-circle text-danger"></i></div>
                                        <div class="benar-salah-text"><strong>SALAH</strong></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary" onclick="prevSoal()" <?= $index == 0 ? 'disabled' : '' ?>><i class="fas fa-arrow-left me-2"></i>Soal Sebelumnya</button>
                                    <button type="button" class="btn btn-primary" onclick="nextSoal()" <?= $index == count($soal_list) - 1 ? 'disabled' : '' ?>>Soal Berikutnya <i class="fas fa-arrow-right ms-2"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </form>
                    </div>
                    <div class="card-footer bg-light">
                        <small class="text-muted"><i class="fas fa-sync-alt me-1"></i> Auto-save aktif setiap 30 detik</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentSoal = 0;
        const totalSoal = <?= count($soal_list) ?>;
        let sisaWaktu = <?= $sisa_waktu ?>;
        let timerInterval;
        let autoSaveInterval;
        let draggedItemMenjodohkan = null;
        let warningCount = <?= $jumlah_pelanggaran ?>;
        let isPenaltyActive = <?= isset($_SESSION['ujian_ditunda']) ? 'true' : 'false' ?>;
        let penaltyTimeLeft = <?= isset($_SESSION['sisa_penalti']) ? $_SESSION['sisa_penalti'] : 300 ?>;
        let penaltyInterval;
        let blurStartTime = null;
        let isTypingEssay = false;
        
        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(function() {
                if (isPenaltyActive) return;
                sisaWaktu--;
                if (sisaWaktu <= 0) { clearInterval(timerInterval); selesaikanUjianOtomatis(); return; }
                const hours = Math.floor(sisaWaktu / 3600);
                const minutes = Math.floor((sisaWaktu % 3600) / 60);
                const seconds = sisaWaktu % 60;
                document.getElementById('timerDisplay').textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                if (sisaWaktu <= 300) document.getElementById('timerCard').classList.add('timer-warning');
            }, 1000);
        }
        
        function showSoal(index) {
            document.querySelectorAll('.soal-content').forEach(soal => soal.classList.add('d-none'));
            document.querySelectorAll('.btn-soal').forEach(btn => btn.classList.remove('sedang-dikerjakan'));
            document.getElementById('soal' + index).classList.remove('d-none');
            document.getElementById('navSoal' + index).classList.add('sedang-dikerjakan');
            document.getElementById('soalCounter').textContent = `Soal ${index + 1} dari ${totalSoal}`;
            currentSoal = index;
            
            if (window.innerWidth <= 768) {
                const soalCard = document.querySelector('.soal-card');
                if (soalCard) {
                    soalCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }
        
        function nextSoal() { if (currentSoal < totalSoal - 1) showSoal(currentSoal + 1); }
        function prevSoal() { if (currentSoal > 0) showSoal(currentSoal - 1); }
        
        function pilihJawabanPilihanGanda(soalIndex, jawaban, soalId) {
            if (isPenaltyActive) return;
            const radio = document.getElementById(`soal${soalId}_${jawaban}`);
            if (radio) {
                radio.checked = true;
                document.querySelectorAll(`#soal${soalIndex} .opsi-jawaban`).forEach(opsi => opsi.classList.remove('terpilih'));
                document.querySelectorAll(`#soal${soalIndex} .benar-salah-option`).forEach(opsi => opsi.classList.remove('selected', 'benar', 'salah'));
                
                const selectedDiv = document.querySelector(`#soal${soalIndex} .benar-salah-option:nth-child(${jawaban === 'a' ? 1 : 2})`);
                if (selectedDiv) {
                    selectedDiv.classList.add('selected');
                    if (jawaban === 'a') selectedDiv.classList.add('benar');
                    else selectedDiv.classList.add('salah');
                }
                
                const pgDiv = document.querySelector(`#soal${soalIndex} .opsi-jawaban:nth-child(${['a','b','c','d','e'].indexOf(jawaban) + 1})`);
                if (pgDiv) pgDiv.classList.add('terpilih');
                
                document.getElementById(`navSoal${soalIndex}`).classList.add('terjawab');
                setTimeout(autoSaveJawaban, 100);
            }
        }
        
        // PG Kompleks - checkbox multiple jawaban
        function pilihJawabanPGKompleks(soalIndex, jawaban, soalId) {
            if (isPenaltyActive) return;
            const checkbox = document.getElementById(`soal${soalId}_${jawaban}`);
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                const parentDiv = checkbox.closest('.opsi-jawaban');
                if (checkbox.checked) parentDiv.classList.add('terpilih');
                else parentDiv.classList.remove('terpilih');
                
                const semuaCheckbox = document.querySelectorAll(`#soal${soalIndex} .opsi-jawaban input[type="checkbox"]`);
                let adaJawaban = false;
                semuaCheckbox.forEach(cb => { if (cb.checked) adaJawaban = true; });
                if (adaJawaban) document.getElementById(`navSoal${soalIndex}`).classList.add('terjawab');
                else document.getElementById(`navSoal${soalIndex}`).classList.remove('terjawab');
                setTimeout(autoSaveJawaban, 100);
            }
        }
        
        function updateEssay(soalIndex, soalId, value) {
            if (isPenaltyActive) return;
            const navBtn = document.getElementById(`navSoal${soalIndex}`);
            if (navBtn) value.trim().length > 0 ? navBtn.classList.add('terjawab') : navBtn.classList.remove('terjawab');
            clearTimeout(window.essayTimer);
            window.essayTimer = setTimeout(() => autoSaveJawaban(), 2000);
        }
        
        // MENJODOHKAN FUNCTIONS
        function dragStartMenjodohkan(event) { 
            if (isPenaltyActive) {
                event.preventDefault();
                return false;
            }
            draggedItemMenjodohkan = event.target; 
            event.dataTransfer.setData('text/plain', event.target.id); 
            event.target.classList.add('dragging'); 
        }
        function dragOverMenjodohkan(event) { event.preventDefault(); }
        function dragEnterMenjodohkan(event) { 
            event.preventDefault(); 
            const target = event.target.closest('.menjodohkan-target'); 
            if (target && !target.hasAttribute('data-has-item')) target.classList.add('active'); 
        }
        function dragLeaveMenjodohkan(event) { 
            const target = event.target.closest('.menjodohkan-target'); 
            if (target) target.classList.remove('active'); 
        }
        
        function dropMenjodohkan(event) {
            if (isPenaltyActive) return;
            event.preventDefault(); 
            event.stopPropagation();
            const target = event.target.closest('.menjodohkan-target');
            if (!target || target.hasAttribute('data-has-item')) return;
            target.classList.remove('active');
            if (draggedItemMenjodohkan) {
                const soalId = draggedItemMenjodohkan.getAttribute('data-soal-id');
                const itemKiriId = draggedItemMenjodohkan.getAttribute('data-item-id');
                const itemKananId = target.getAttribute('data-item-id');
                const inputJawaban = document.getElementById(`jawabanMenjodohkan${soalId}`);
                let jawabanPairs = {};
                if (inputJawaban.value) {
                    inputJawaban.value.split(',').forEach(pair => {
                        const [kiri, kanan] = pair.split('-');
                        if (kiri && kanan) jawabanPairs[kiri.trim()] = kanan.trim();
                    });
                }
                jawabanPairs[itemKiriId] = itemKananId;
                const newJawaban = Object.entries(jawabanPairs).map(([kiri, kanan]) => `${kiri}-${kanan}`).join(',');
                inputJawaban.value = newJawaban;
                draggedItemMenjodohkan.style.display = 'none';
                target.setAttribute('data-has-item', 'true');
                const dropZone = target.querySelector('.drop-zone');
                if (dropZone) dropZone.style.display = 'none';
                const itemContent = target.querySelector('.item-content');
                if (itemContent) {
                    const itemText = itemContent.innerHTML;
                    itemContent.outerHTML = `<div class="matched-item" id="matched-${soalId}-${itemKananId}">
                        <strong>${itemKananId}.</strong> ${itemText.replace(`<strong>${itemKananId}.</strong> `, '')}
                        <div class="mt-2"><span class="badge bg-success">Dijodohkan dengan: ${itemKiriId}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeMatch('${soalId}', '${itemKananId}', '${itemKiriId}')"><i class="fas fa-times"></i></button></div>
                    </div>`;
                }
                draggedItemMenjodohkan.classList.remove('dragging');
                draggedItemMenjodohkan = null;
                setTimeout(autoSaveJawaban, 100);
            }
        }
        
        function removeMatch(soalId, itemKananId, itemKiriId) {
            if (isPenaltyActive) return;
            const inputJawaban = document.getElementById(`jawabanMenjodohkan${soalId}`);
            let jawabanPairs = {};
            if (inputJawaban.value) {
                inputJawaban.value.split(',').forEach(pair => {
                    const [kiri, kanan] = pair.split('-');
                    if (kiri && kanan) jawabanPairs[kiri.trim()] = kanan.trim();
                });
            }
            delete jawabanPairs[itemKiriId];
            const newJawaban = Object.entries(jawabanPairs).map(([kiri, kanan]) => `${kiri}-${kanan}`).join(',');
            inputJawaban.value = newJawaban;
            const itemKiri = document.getElementById(`item-${soalId}-${itemKiriId}`);
            if (itemKiri) itemKiri.style.display = 'block';
            const target = document.getElementById(`target-${soalId}-${itemKananId}`);
            if (target) {
                target.removeAttribute('data-has-item');
                const dropZone = target.querySelector('.drop-zone');
                if (dropZone) dropZone.style.display = 'block';
                const matchedItem = target.querySelector('.matched-item');
                if (matchedItem) {
                    const itemContent = target.querySelector('.item-content');
                    if (itemContent) {
                        target.innerHTML = `<div class="drop-zone"><i class="fas fa-hand-point-up"></i><br>Drop di sini</div><div class="item-content"><strong>${itemKananId}.</strong> ${itemContent.innerHTML.replace(`<strong>${itemKananId}.</strong> `, '')}</div>`;
                    } else {
                        target.innerHTML = `<div class="drop-zone"><i class="fas fa-hand-point-up"></i><br>Drop di sini</div><div class="item-content"><strong>${itemKananId}.</strong> [Teks pasangan]</div>`;
                    }
                }
            }
            if (newJawaban === '') document.getElementById(`navSoal${currentSoal}`).classList.remove('terjawab');
            setTimeout(autoSaveJawaban, 100);
        }
        
        // AUTO SAVE
        function autoSaveJawaban() {
            if (isPenaltyActive) return;
            const jawabanData = {};
            document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                const match = radio.name.match(/\[(\d+)\]/);
                if (match) jawabanData[match[1]] = radio.value;
            });
            document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                const match = checkbox.name.match(/\[(\d+)\]/);
                if (match) {
                    const soalId = match[1];
                    if (jawabanData[soalId]) jawabanData[soalId] += ',' + checkbox.value;
                    else jawabanData[soalId] = checkbox.value;
                }
            });
            document.querySelectorAll('textarea[name^="jawaban["], input[type="hidden"][name^="jawaban["]').forEach(input => {
                const match = input.name.match(/\[(\d+)\]/);
                if (match && input.value.trim()) jawabanData[match[1]] = input.value;
            });
            fetch('kerjakan_ujian.php?id=<?= $hasil_ujian_id ?>', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ auto_save: 1, jawaban: jawabanData })
            }).then(r => r.json()).then(d => { if (d.success) showSaveIndicator('Auto-saved'); });
        }
        
        function simpanSemuaJawaban() { if (!isPenaltyActive) { autoSaveJawaban(); showSaveIndicator('Semua jawaban disimpan'); } }
        function tandaiRaguRagu() { if (!isPenaltyActive) document.getElementById(`navSoal${currentSoal}`).classList.toggle('btn-warning'); }
        function showSaveIndicator(message) { const el = document.getElementById('saveIndicator'); document.getElementById('saveText').textContent = message; el.style.display = 'block'; setTimeout(() => el.style.display = 'none', 2000); }
        function selesaikanUjianOtomatis() { fetch('kerjakan_ujian.php?id=<?= $hasil_ujian_id ?>', { method: 'POST', body: 'selesai_ujian=1' }).then(() => window.location.href = 'detail_hasil_siswa.php?id=<?= $hasil_ujian_id ?>&timeout=1'); }
        function hideFullscreenWarning() { document.getElementById('fullscreenWarning').classList.add('d-none'); }
        
        function showImagePreview(src) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = 'flex';
            modalImg.src = src;
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        function showVideoPreview(src) {
            window.open(src, '_blank');
        }
        
        function startAutoSave() { 
            if (autoSaveInterval) clearInterval(autoSaveInterval);
            autoSaveInterval = setInterval(autoSaveJawaban, 30000); 
        }
        
        // Track essay typing state
        document.addEventListener('focusin', function(e) {
            if (e.target && e.target.tagName === 'TEXTAREA') {
                isTypingEssay = true;
            }
        });
        
        document.addEventListener('focusout', function(e) {
            if (e.target && e.target.tagName === 'TEXTAREA') {
                setTimeout(() => {
                    const activeElement = document.activeElement;
                    if (!activeElement || activeElement.tagName !== 'TEXTAREA') {
                        isTypingEssay = false;
                    }
                }, 100);
            }
        });
        
        function handleVisibilityChange() {
            if (isTypingEssay) return;
            
            if (document.hidden) {
                blurStartTime = Date.now();
            } else {
                if (blurStartTime) {
                    const blurDuration = Date.now() - blurStartTime;
                    if (blurDuration > 1000 && blurDuration < 10000) {
                        logKecurangan('keluar_tab', `Meninggalkan halaman ujian selama ${Math.round(blurDuration/1000)} detik`);
                        addWarning();
                    }
                    blurStartTime = null;
                }
            }
        }
        
        window.addEventListener('beforeunload', function(e) {
            if (!isPenaltyActive && sisaWaktu > 0) {
                e.preventDefault();
                e.returnValue = 'Anda sedang mengerjakan ujian. Yakin ingin meninggalkan halaman?';
                return e.returnValue;
            }
        });
        
        document.addEventListener('keydown', function(e) {
            const activeElement = document.activeElement;
            const isTyping = activeElement && (activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'INPUT');
            
            if (isTyping) return;
            
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'R')) {
                e.preventDefault();
                logKecurangan('attempt_refresh', 'Mencoba me-refresh halaman');
                addWarning();
                return false;
            }
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) || (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
                logKecurangan('dev_tools', 'Mencoba membuka developer tools');
                addWarning();
                return false;
            }
            if (e.key === 'ArrowRight' && !e.ctrlKey && !e.altKey) { nextSoal(); e.preventDefault(); }
            else if (e.key === 'ArrowLeft' && !e.ctrlKey && !e.altKey) { prevSoal(); e.preventDefault(); }
            else if (e.key >= '1' && e.key <= '9') { 
                const t = parseInt(e.key)-1; 
                if (t < totalSoal) showSoal(t); 
                e.preventDefault(); 
            }
        });
        
        function addWarning() { warningCount++; updateWarningDisplay(); if (warningCount >= 2) activatePenalty(); }
        function updateWarningDisplay() {
            const el = document.getElementById('warningCounter');
            const span = document.getElementById('warningCount');
            if (warningCount > 0) { el.classList.remove('d-none'); span.textContent = warningCount; }
            else el.classList.add('d-none');
        }
        
        function activatePenalty() {
            isPenaltyActive = true; penaltyTimeLeft = 300;
            document.getElementById('penaltyOverlay').classList.remove('d-none');
            clearInterval(timerInterval); clearInterval(autoSaveInterval);
            penaltyInterval = setInterval(function() {
                penaltyTimeLeft--;
                if (penaltyTimeLeft <= 0) { clearInterval(penaltyInterval); endPenalty(); return; }
                const m = Math.floor(penaltyTimeLeft/60), s = penaltyTimeLeft%60;
                document.getElementById('penaltyTimer').textContent = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
            }, 1000);
            logKecurangan('penalty_activated', 'Penalti 5 menit diaktifkan');
        }
        
        function endPenalty() { isPenaltyActive = false; warningCount = 0; updateWarningDisplay(); document.getElementById('penaltyOverlay').classList.add('d-none'); startTimer(); startAutoSave(); }
        
        function logKecurangan(jenis, keterangan) {
            fetch('kerjakan_ujian.php?id=<?= $hasil_ujian_id ?>', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_kecurangan: jenis, keterangan: keterangan, hasil_ujian_id: <?= $hasil_ujian_id ?> })
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            if (isPenaltyActive) activatePenalty();
            else { startTimer(); startAutoSave(); hideFullscreenWarning(); }
            document.addEventListener('visibilitychange', handleVisibilityChange);
            document.addEventListener('contextmenu', e => { e.preventDefault(); logKecurangan('right_click', 'Klik kanan'); addWarning(); return false; });
            updateWarningDisplay();
            setTimeout(autoSaveJawaban, 1000);
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
</body>
</html>