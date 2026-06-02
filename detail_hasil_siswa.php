<?php
// detail_hasil_siswa.php - FULL MOBILE FRIENDLY VERSION (PG A-E & PG KOMPLEKS A-E)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header("Location: login.php");
    exit();
}

// Cek parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    flashMessage('danger', 'ID hasil ujian tidak valid!');
    redirect('daftar_ujian.php');
}

$hasil_ujian_id = (int)$_GET['id'];

// Ambil data siswa
$stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    flashMessage('danger', 'Data siswa tidak ditemukan!');
    redirect('logout.php');
}

// Verifikasi bahwa hasil ujian ini milik siswa yang login
$stmt_verifikasi = $pdo->prepare("SELECT hu.id FROM hasil_ujian hu WHERE hu.id = ? AND hu.siswa_id = ?");
$stmt_verifikasi->execute([$hasil_ujian_id, $siswa['id']]);
$verifikasi = $stmt_verifikasi->fetch();

if (!$verifikasi) {
    flashMessage('danger', 'Akses ditolak! Hasil ujian ini bukan milik Anda.');
    redirect('daftar_ujian.php');
}

// ===================================================
// FUNGSI UNTUK PG KOMPLEKS
// ===================================================

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
        if (in_array(trim($jwb), $jawabanBenar)) {
            $jumlahBenar++;
        }
    }
    
    return $jumlahBenar * $skor_per_jawaban;
}

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

// Fungsi untuk sinkronisasi
function getDetailHasilUjianSinkron($hasil_ujian_id) {
    global $pdo;
    
    try {
        $sql = "
            SELECT 
                hu.*,
                s.nisn,
                s.nama as nama_siswa,
                s.kelas,
                u.judul_ujian,
                u.waktu_mulai as ujian_mulai,
                u.waktu_selesai as ujian_selesai,
                u.durasi,
                mp.nama_mapel,
                mp.kode_mapel,
                mp.kkm
            FROM hasil_ujian hu
            JOIN siswa s ON hu.siswa_id = s.id
            JOIN ujian u ON hu.ujian_id = u.id
            JOIN mata_pelajaran mp ON u.mapel_id = mp.id
            WHERE hu.id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hasil_ujian_id]);
        $hasil = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$hasil) {
            return ['success' => false, 'error' => 'Data hasil ujian tidak ditemukan!'];
        }
        
        $kkm = isset($hasil['kkm']) ? intval($hasil['kkm']) : 70;
        $ujian_id = $hasil['ujian_id'];
        
        // Ambil semua soal ujian - pastikan mengambil semua kolom termasuk opsi_e
        $sql_soal = "
            SELECT su.*, s.id as soal_id, s.pertanyaan, s.jenis_soal, s.skor, 
                   s.jawaban_benar, s.jawaban_kompleks, s.skor_per_jawaban,
                   s.opsi_a, s.opsi_b, s.opsi_c, s.opsi_d, s.opsi_e
            FROM soal_ujian su 
            JOIN soal s ON su.soal_id = s.id 
            WHERE su.ujian_id = ? 
            ORDER BY su.urutan
        ";
        $stmt_soal = $pdo->prepare($sql_soal);
        $stmt_soal->execute([$ujian_id]);
        $soal_list = $stmt_soal->fetchAll(PDO::FETCH_ASSOC);
        
        // Ambil jawaban siswa
        $sql_jawaban = "SELECT soal_id, jawaban_siswa, jawaban_essay FROM jawaban_siswa WHERE hasil_ujian_id = ?";
        $stmt_jawaban = $pdo->prepare($sql_jawaban);
        $stmt_jawaban->execute([$hasil_ujian_id]);
        $jawaban_map = [];
        while ($jwb = $stmt_jawaban->fetch(PDO::FETCH_ASSOC)) {
            $jawaban_map[$jwb['soal_id']] = $jwb;
        }
        
        // Ambil nilai manual
        $sql_manual = "SELECT soal_id, skor_essay, komentar_guru, status_koreksi FROM jawaban_essay WHERE hasil_ujian_id = ?";
        $stmt_manual = $pdo->prepare($sql_manual);
        $stmt_manual->execute([$hasil_ujian_id]);
        $manual_map = [];
        while ($manual = $stmt_manual->fetch(PDO::FETCH_ASSOC)) {
            $manual_map[$manual['soal_id']] = $manual;
        }
        
        // Hitung nilai dan siapkan detail
        $nilai_otomatis = 0;
        $total_skor = 0;
        $jawaban_detail = [];
        $soal_manual_count = 0;
        $soal_manual_dikoreksi = 0;
        
        foreach ($soal_list as $soal) {
            $jenis = deteksiJenisSoal($soal);
            $skor_soal = $soal['skor'];
            $total_skor += $skor_soal;
            
            $jawaban = isset($jawaban_map[$soal['soal_id']]) ? $jawaban_map[$soal['soal_id']] : null;
            $manual = isset($manual_map[$soal['soal_id']]) ? $manual_map[$soal['soal_id']] : null;
            
            $is_manual = in_array($jenis, ['essay', 'menjodohkan']);
            $skor_didapat = 0;
            $benar = false;
            $status_koreksi = 'belum';
            
            if ($is_manual) {
                $soal_manual_count++;
                if ($manual && $manual['status_koreksi'] == 'sudah') {
                    $soal_manual_dikoreksi++;
                    $skor_didapat = floatval($manual['skor_essay'] ?? 0);
                    $nilai_otomatis += $skor_didapat;
                    $status_koreksi = 'sudah';
                }
                $benar = $skor_didapat > 0;
            } 
            elseif ($jenis == 'pilihan_ganda_kompleks') {
                $jawaban_siswa = $jawaban ? $jawaban['jawaban_siswa'] : '';
                $skor_didapat = hitungNilaiPGKompleks($jawaban_siswa, $soal['jawaban_kompleks'], $soal['skor_per_jawaban']);
                $nilai_otomatis += $skor_didapat;
                $benar = $skor_didapat > 0;
            }
            elseif ($jenis == 'pilihan_ganda') {
                $jawaban_siswa = $jawaban ? $jawaban['jawaban_siswa'] : '';
                $benar = ($jawaban_siswa == $soal['jawaban_benar']);
                if ($benar) {
                    $skor_didapat = $skor_soal;
                    $nilai_otomatis += $skor_didapat;
                }
            }
            elseif ($jenis == 'benar_salah') {
                $jawaban_siswa = $jawaban ? $jawaban['jawaban_siswa'] : '';
                $benar = ($jawaban_siswa == $soal['jawaban_benar']);
                if ($benar) {
                    $skor_didapat = $skor_soal;
                    $nilai_otomatis += $skor_didapat;
                }
            }
            
            $jawaban_detail[] = [
                'urutan' => $soal['urutan'],
                'soal_id' => $soal['soal_id'],
                'pertanyaan' => $soal['pertanyaan'],
                'jenis_soal' => $jenis,
                'skor' => $skor_soal,
                'jawaban_benar' => $soal['jawaban_benar'],
                'jawaban_kompleks' => $soal['jawaban_kompleks'],
                'skor_per_jawaban' => $soal['skor_per_jawaban'],
                'opsi_a' => $soal['opsi_a'],
                'opsi_b' => $soal['opsi_b'],
                'opsi_c' => $soal['opsi_c'],
                'opsi_d' => $soal['opsi_d'],
                'opsi_e' => $soal['opsi_e'],
                'jawaban_siswa' => $jawaban ? $jawaban['jawaban_siswa'] : null,
                'jawaban_essay' => $jawaban ? $jawaban['jawaban_essay'] : null,
                'skor_essay' => $manual ? $manual['skor_essay'] : null,
                'komentar_guru' => $manual ? $manual['komentar_guru'] : null,
                'status_koreksi' => $status_koreksi,
                'benar' => $benar,
                'skor_didapat' => $skor_didapat
            ];
        }
        
        // Hitung nilai akhir
        $nilai_persen = $total_skor > 0 ? ($nilai_otomatis / $total_skor) * 100 : 0;
        
        // Update nilai jika perlu
        $nilai_sekarang = floatval($hasil['nilai'] ?? 0);
        if (abs($nilai_sekarang - $nilai_persen) > 0.01) {
            $stmt_update = $pdo->prepare("UPDATE hasil_ujian SET nilai = ? WHERE id = ?");
            $stmt_update->execute([$nilai_persen, $hasil_ujian_id]);
            $hasil['nilai'] = $nilai_persen;
        }
        
        $hasil['nilai_format'] = number_format($hasil['nilai'] ?? 0, 2);
        $hasil['kkm'] = $kkm;
        $hasil['lulus'] = ($hasil['nilai'] ?? 0) >= $kkm;
        $hasil['status_nilai'] = $hasil['lulus'] ? 'LULUS' : 'TIDAK LULUS';
        $hasil['total_soal_ujian'] = count($soal_list);
        $hasil['total_dijawab'] = count(array_filter($jawaban_detail, function($j) { return !empty($j['jawaban_siswa']) || !empty($j['jawaban_essay']); }));
        $hasil['soal_manual'] = $soal_manual_count;
        $hasil['soal_manual_dikoreksi'] = $soal_manual_dikoreksi;
        $hasil['soal_manual_belum'] = $soal_manual_count - $soal_manual_dikoreksi;
        $hasil['nilai_otomatis'] = $nilai_otomatis;
        $hasil['total_skor'] = $nilai_otomatis;
        $hasil['skor_maksimal'] = $total_skor;
        $hasil['jawaban_detail'] = $jawaban_detail;
        
        return ['success' => true, 'data' => $hasil];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

$result = getDetailHasilUjianSinkron($hasil_ujian_id);

if (!$result['success']) {
    flashMessage('danger', $result['error']);
    redirect('daftar_ujian.php');
}

$detail = $result['data'];
$title = "Hasil Ujian: " . htmlspecialchars($detail['judul_ujian']);
include 'templates/header_siswa.php';
?>

<!-- MOBILE FRIENDLY CSS -->
<style>
/* Base mobile styles */
body { font-size: 14px; }
.card { border-radius: 12px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.card-header { border-radius: 12px 12px 0 0 !important; padding: 12px 16px; }
.card-header h5, .card-header h6 { font-size: 1rem; margin: 0; }
.card-body { padding: 16px; }
.stat-number { font-size: 1.8rem; font-weight: bold; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.info-item { background: #f8f9fa; padding: 10px; border-radius: 8px; }
.info-item strong { font-size: 0.75rem; color: #6c757d; display: block; margin-bottom: 4px; }
.info-item span { font-size: 0.9rem; font-weight: 500; }
.progress-custom { height: 8px; border-radius: 4px; background-color: #e9ecef; }
.progress-custom .progress-bar { border-radius: 4px; }
.badge { padding: 6px 10px; font-size: 0.7rem; font-weight: 500; }
.badge-lg { font-size: 0.9rem; padding: 8px 12px; }
.score-display { text-align: center; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; color: white; margin-bottom: 12px; }
.score-display .score-value { font-size: 2.5rem; font-weight: bold; line-height: 1; }
.score-display .score-label { font-size: 0.7rem; opacity: 0.9; }
.question-card { background: white; border: 1px solid #e0e0e0; border-radius: 12px; margin-bottom: 12px; overflow: hidden; }
.question-header { padding: 12px; background: #f8f9fa; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.question-number { font-weight: bold; background: #6c757d; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; }
.question-type { font-size: 0.7rem; padding: 4px 8px; border-radius: 20px; }
.question-text { padding: 12px; font-size: 0.9rem; border-bottom: 1px solid #f0f0f0; }
.question-options { padding: 12px; background: #fafafa; }
.option-item { padding: 8px; margin-bottom: 6px; background: white; border-radius: 8px; display: flex; align-items: flex-start; gap: 8px; font-size: 0.85rem; }
.option-letter { font-weight: bold; min-width: 28px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #e9ecef; }
.option-letter.correct { background: #28a745; color: white; }
.option-letter.wrong { background: #dc3545; color: white; }
.option-letter.selected { background: #ffc107; color: #333; }
.option-text { flex: 1; }
.question-answer { padding: 10px 12px; background: #f0f0f0; border-radius: 8px; margin-top: 8px; font-size: 0.85rem; }
.question-status { padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e0e0e0; font-size: 0.85rem; }
.status-correct { background: #d4edda; border-left: 4px solid #28a745; }
.status-wrong { background: #f8d7da; border-left: 4px solid #dc3545; }
.status-pending { background: #fff3cd; border-left: 4px solid #ffc107; }
.status-partial { background: #cce5ff; border-left: 4px solid #17a2b8; }
.btn-mobile { padding: 10px 16px; font-size: 0.9rem; border-radius: 8px; width: 100%; }
.btn-group-mobile { display: flex; gap: 8px; margin-top: 16px; }
.btn-group-mobile .btn { flex: 1; }
.bg-purple { background-color: #6f42c1 !important; }

@media (max-width: 576px) {
    .info-grid { grid-template-columns: 1fr; gap: 8px; }
    .score-display .score-value { font-size: 2rem; }
    .stat-number { font-size: 1.5rem; }
    .card-body { padding: 12px; }
    .question-header { flex-direction: column; align-items: flex-start; }
}

@media print {
    .no-print, .btn, .target-buttons, .symbol-panel { display: none !important; }
    .card { break-inside: avoid; border: 1px solid #ddd; }
    .question-card { break-inside: avoid; }
}
</style>

<div class="container-fluid p-3 p-md-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-chart-line me-2"></i>Hasil Ujian</h2>
            <p class="text-muted small mb-0">Detail hasil ujian yang telah dikerjakan</p>
        </div>
        <div class="w-100 w-sm-auto">
            <a href="daftar_ujian.php" class="btn btn-secondary btn-mobile w-100 w-sm-auto">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Info Siswa & Ujian -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Informasi Siswa</h6>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item"><strong>Nama Siswa</strong><span><?= htmlspecialchars($detail['nama_siswa']) ?></span></div>
                        <div class="info-item"><strong>NISN</strong><span><?= htmlspecialchars($detail['nisn']) ?></span></div>
                        <div class="info-item"><strong>Kelas</strong><span><?= htmlspecialchars($detail['kelas']) ?></span></div>
                        <div class="info-item"><strong>Tanggal Ujian</strong><span><?= formatWaktu($detail['waktu_mulai']) ?></span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Informasi Ujian</h6>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item"><strong>Mata Pelajaran</strong><span><?= htmlspecialchars($detail['nama_mapel']) ?> (<?= htmlspecialchars($detail['kode_mapel']) ?>)</span></div>
                        <div class="info-item"><strong>Judul Ujian</strong><span><?= htmlspecialchars($detail['judul_ujian']) ?></span></div>
                        <div class="info-item"><strong>Durasi</strong><span><?= $detail['durasi'] ?> menit</span></div>
                        <div class="info-item"><strong>Waktu Ujian</strong><span><?= formatWaktu($detail['ujian_mulai']) ?> s/d <?= formatWaktu($detail['ujian_selesai']) ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Koreksi Manual -->
    <?php if ($detail['soal_manual'] > 0): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Status Koreksi Soal Manual</h6>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6"><div class="p-3 bg-light rounded"><div class="display-6 text-warning fw-bold"><?= $detail['soal_manual_belum'] ?></div><small class="text-muted">Belum Dikoreksi</small></div></div>
                <div class="col-6"><div class="p-3 bg-light rounded"><div class="display-6 text-success fw-bold"><?= $detail['soal_manual_dikoreksi'] ?></div><small class="text-muted">Sudah Dikoreksi</small></div></div>
            </div>
            <div class="alert alert-info mt-3 small">
                <i class="fas fa-info-circle me-2"></i>Soal Essay dan Menjodohkan memerlukan koreksi manual oleh guru.
                <?php if ($detail['soal_manual_belum'] > 0): ?>
                <br>Saat ini masih ada <strong><?= $detail['soal_manual_belum'] ?></strong> soal yang belum dikoreksi.
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ringkasan Nilai -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center border-<?= $detail['lulus'] ? 'success' : 'danger' ?>">
                <div class="card-body p-3">
                    <div class="display-6 fw-bold text-<?= $detail['lulus'] ? 'success' : 'danger' ?>"><?= $detail['nilai_format'] ?></div>
                    <small class="text-muted">Nilai Akhir</small>
                    <div><span class="badge bg-<?= $detail['lulus'] ? 'success' : 'danger' ?> mt-1"><?= $detail['status_nilai'] ?></span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-info">
                <div class="card-body p-3">
                    <div class="display-6 fw-bold text-info"><?= $detail['kkm'] ?></div>
                    <small class="text-muted">KKM</small>
                    <div class="mt-1"><small class="text-<?= $detail['lulus'] ? 'success' : 'danger' ?>"><?= $detail['lulus'] ? '✅ Tercapai' : '❌ Belum Tercapai' ?></small></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body p-3">
                    <div class="display-6 fw-bold text-success"><?= $detail['total_skor'] ?></div>
                    <small class="text-muted">Skor Didapat</small>
                    <div><small>dari <?= $detail['skor_maksimal'] ?></small></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body p-3">
                    <div class="display-6 fw-bold text-warning"><?= $detail['total_dijawab'] ?></div>
                    <small class="text-muted">Soal Dijawab</small>
                    <div><small>dari <?= $detail['total_soal_ujian'] ?></small></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2 small"><span>Progress Nilai</span><span><?= $detail['nilai_format'] ?> / 100</span></div>
            <div class="progress-custom"><div class="progress-bar bg-<?= $detail['lulus'] ? 'success' : 'danger' ?>" style="width: <?= min(100, $detail['nilai_format']) ?>%"></div></div>
            <div class="text-center mt-3"><span class="badge bg-info">KKM: <?= $detail['kkm'] ?>%</span><span class="badge bg-<?= $detail['lulus'] ? 'success' : 'danger' ?> ms-2"><?= $detail['status_nilai'] ?></span></div>
            <div class="alert alert-info mt-3 small"><i class="fas fa-calculator me-2"></i><strong>Perhitungan Nilai:</strong><br>(<?= $detail['total_skor'] ?> / <?= $detail['skor_maksimal'] ?>) × 100 = <strong><?= $detail['nilai_format'] ?>%</strong></div>
        </div>
    </div>

    <!-- Detail Jawaban -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h6 class="mb-0"><i class="fas fa-list-check me-2"></i>Detail Jawaban per Soal</h6>
        </div>
        <div class="card-body p-3">
            <?php if (empty($detail['jawaban_detail'])): ?>
                <div class="text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><p class="text-muted">Belum ada jawaban</p></div>
            <?php else: ?>
                <?php 
                $nomor = 1;
                $total_skor_table = 0;
                $skor_maksimal_table = 0;
                
                foreach ($detail['jawaban_detail'] as $jawaban): 
                    $jenis_soal = $jawaban['jenis_soal'] ?? 'pilihan_ganda';
                    $is_essay = $jenis_soal == 'essay';
                    $is_menjodohkan = $jenis_soal == 'menjodohkan';
                    $is_benar_salah = $jenis_soal == 'benar_salah';
                    $is_pilihan_ganda = $jenis_soal == 'pilihan_ganda';
                    $is_pg_kompleks = $jenis_soal == 'pilihan_ganda_kompleks';
                    $is_manual = $is_essay || $is_menjodohkan;
                    
                    $skor_soal = $jawaban['skor'] ? (int)$jawaban['skor'] : 10;
                    $skor_didapat = $jawaban['skor_didapat'] ?? 0;
                    $benar = $jawaban['benar'] ?? false;
                    
                    $total_skor_table += $skor_didapat;
                    $skor_maksimal_table += $skor_soal;
                    
                    if ($is_manual) {
                        if ($jawaban['status_koreksi'] == 'sudah') {
                            $status_class = $skor_didapat > 0 ? 'status-correct' : 'status-wrong';
                            $status_text = $skor_didapat > 0 ? '✅ Dinilai' : '❌ Nilai 0';
                        } else {
                            $status_class = 'status-pending';
                            $status_text = '⏳ Menunggu Koreksi';
                        }
                    } elseif ($is_pg_kompleks) {
                        if ($skor_didapat > 0 && $skor_didapat == $skor_soal) {
                            $status_class = 'status-correct';
                            $status_text = '✅ Benar (Full)';
                        } elseif ($skor_didapat > 0 && $skor_didapat < $skor_soal) {
                            $status_class = 'status-partial';
                            $status_text = '⚠️ Sebagian Benar';
                        } else {
                            $status_class = 'status-wrong';
                            $status_text = '❌ Salah';
                        }
                    } else {
                        $status_class = $benar ? 'status-correct' : 'status-wrong';
                        $status_text = $benar ? '✅ Benar' : '❌ Salah';
                    }
                    
                    $jenis_badge = ['color' => 'secondary', 'text' => strtoupper($jenis_soal)];
                    if ($is_essay) { $jenis_badge = ['color' => 'info', 'text' => 'ESSAY']; }
                    elseif ($is_menjodohkan) { $jenis_badge = ['color' => 'warning', 'text' => 'MENJODOHKAN']; }
                    elseif ($is_benar_salah) { $jenis_badge = ['color' => 'danger', 'text' => 'BENAR/SALAH']; }
                    elseif ($is_pg_kompleks) { $jenis_badge = ['color' => 'purple', 'text' => 'PG KOMPLEKS']; }
                    elseif ($is_pilihan_ganda) { $jenis_badge = ['color' => 'primary', 'text' => 'PILIHAN GANDA']; }
                ?>
                <div class="question-card <?= $status_class ?>">
                    <div class="question-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="question-number">Soal <?= $nomor ?></span>
                            <span class="badge bg-<?= $jenis_badge['color'] ?> question-type"><?= $jenis_badge['text'] ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning">Skor: <?= $skor_soal ?></span>
                            <span class="badge bg-<?= $skor_didapat > 0 ? 'success' : 'secondary' ?>">+<?= $skor_didapat ?></span>
                        </div>
                    </div>
                    
                    <div class="question-text"><?= nl2br(htmlspecialchars($jawaban['pertanyaan'])) ?></div>
                    
                    <?php if ($is_pilihan_ganda): 
                        // SEMUA OPSI A SAMPAI E
                        $opsi_list = ['a', 'b', 'c', 'd', 'e'];
                    ?>
                    <div class="question-options">
                        <?php foreach ($opsi_list as $opt): 
                            $opt_text = $jawaban['opsi_' . $opt] ?? '';
                            if (empty($opt_text)) continue;
                            $is_jawaban_siswa = ($jawaban['jawaban_siswa'] == $opt);
                            $is_jawaban_benar = ($jawaban['jawaban_benar'] == $opt);
                        ?>
                        <div class="option-item">
                            <span class="option-letter <?= $is_jawaban_benar ? 'correct' : ($is_jawaban_siswa ? 'selected' : '') ?>"><?= strtoupper($opt) ?></span>
                            <span class="option-text"><?= htmlspecialchars($opt_text) ?></span>
                            <?php if ($is_jawaban_siswa): ?><span class="badge bg-secondary">Jawaban Anda</span><?php endif; ?>
                            <?php if ($is_jawaban_benar): ?><span class="badge bg-success">Kunci</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_pg_kompleks): 
                        $jawaban_siswa = $jawaban['jawaban_siswa'] ?? '';
                        $jawaban_kompleks = $jawaban['jawaban_kompleks'] ?? '';
                        $jawaban_benar_array = json_decode($jawaban_kompleks, true);
                        if (!is_array($jawaban_benar_array)) $jawaban_benar_array = [];
                        $jawaban_siswa_array = $jawaban_siswa ? explode(',', $jawaban_siswa) : [];
                        // SEMUA OPSI A SAMPAI E
                        $opsi_list = ['a', 'b', 'c', 'd', 'e'];
                    ?>
                    <div class="question-options">
                        <div class="alert alert-info small mb-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Jawaban benar: <?= implode(', ', array_map('strtoupper', $jawaban_benar_array)) ?> (masing-masing <?= $jawaban['skor_per_jawaban'] ?> poin)
                        </div>
                        <?php foreach ($opsi_list as $opt): 
                            $opt_text = $jawaban['opsi_' . $opt] ?? '';
                            if (empty($opt_text)) continue;
                            $is_jawaban_siswa = in_array($opt, $jawaban_siswa_array);
                            $is_jawaban_benar = in_array($opt, $jawaban_benar_array);
                        ?>
                        <div class="option-item">
                            <span class="option-letter <?= $is_jawaban_benar ? 'correct' : ($is_jawaban_siswa ? 'selected' : '') ?>">
                                <?= strtoupper($opt) ?>
                            </span>
                            <span class="option-text"><?= htmlspecialchars($opt_text) ?></span>
                            <?php if ($is_jawaban_siswa): ?><span class="badge bg-secondary">Jawaban Anda</span><?php endif; ?>
                            <?php if ($is_jawaban_benar): ?><span class="badge bg-success">Kunci</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_benar_salah): ?>
                    <div class="question-options">
                        <div class="option-item">
                            <span class="option-letter <?= $jawaban['jawaban_benar'] == 'a' ? 'correct' : ($jawaban['jawaban_siswa'] == 'a' ? 'selected' : '') ?>">A</span>
                            <span class="option-text">BENAR</span>
                            <?php if ($jawaban['jawaban_siswa'] == 'a'): ?><span class="badge bg-secondary">Jawaban Anda</span><?php endif; ?>
                            <?php if ($jawaban['jawaban_benar'] == 'a'): ?><span class="badge bg-success">Kunci</span><?php endif; ?>
                        </div>
                        <div class="option-item">
                            <span class="option-letter <?= $jawaban['jawaban_benar'] == 'b' ? 'correct' : ($jawaban['jawaban_siswa'] == 'b' ? 'selected' : '') ?>">B</span>
                            <span class="option-text">SALAH</span>
                            <?php if ($jawaban['jawaban_siswa'] == 'b'): ?><span class="badge bg-secondary">Jawaban Anda</span><?php endif; ?>
                            <?php if ($jawaban['jawaban_benar'] == 'b'): ?><span class="badge bg-success">Kunci</span><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_essay && !empty($jawaban['jawaban_essay'])): ?>
                    <div class="question-answer">
                        <strong>Jawaban Anda:</strong><br><?= nl2br(htmlspecialchars(substr($jawaban['jawaban_essay'], 0, 200))) ?>
                        <?php if (strlen($jawaban['jawaban_essay']) > 200): ?>...<?php endif; ?>
                        <?php if (!empty($jawaban['komentar_guru'])): ?>
                        <hr class="my-2"><strong>Komentar Guru:</strong><br><small><?= nl2br(htmlspecialchars($jawaban['komentar_guru'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($is_essay): ?>
                    <div class="question-answer text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Tidak dijawab</div>
                    <?php endif; ?>
                    
                    <?php if ($is_menjodohkan && !empty($jawaban['jawaban_siswa'])): ?>
                    <div class="question-answer"><strong>Jawaban Anda:</strong> <?= htmlspecialchars($jawaban['jawaban_siswa']) ?></div>
                    <?php endif; ?>
                    
                    <div class="question-status"><span><?= $status_text ?></span><span class="fw-bold">Skor: <?= $skor_didapat ?> / <?= $skor_soal ?></span></div>
                </div>
                <?php 
                $nomor++;
                endforeach; 
                ?>
                
                <!-- Total Skor Card -->
                <div class="card mt-3 bg-dark text-white">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center"><span>Total Skor</span><span class="fw-bold fs-5"><?= $total_skor_table ?> / <?= $skor_maksimal_table ?></span></div>
                        <div class="progress-custom mt-2"><div class="progress-bar bg-info" style="width: <?= ($total_skor_table / max(1, $skor_maksimal_table)) * 100 ?>%"></div></div>
                        <div class="text-center mt-2 small">Nilai: <?= $skor_maksimal_table > 0 ? number_format(($total_skor_table / $skor_maksimal_table) * 100, 2) : '0.00' ?>% | KKM: <?= $detail['kkm'] ?>%</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-white">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                <small class="text-muted text-center text-sm-start"><i class="fas fa-calculator me-1"></i>Skor: <?= $detail['total_skor'] ?> / <?= $detail['skor_maksimal'] ?> | Nilai: <?= $detail['nilai_format'] ?>% | KKM: <?= $detail['kkm'] ?>% | <span class="text-<?= $detail['lulus'] ? 'success' : 'danger' ?>"><?= $detail['status_nilai'] ?></span></small>
                <a href="daftar_ujian.php" class="btn btn-primary btn-mobile w-100 w-sm-auto"><i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Ujian</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) })
});
</script>

<?php include 'templates/footer.php'; ?>