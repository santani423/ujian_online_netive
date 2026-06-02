<?php
// lanjutkan_ujian.php - Untuk melanjutkan ujian yang belum selesai
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    flashMessage('danger', 'ID ujian tidak ditemukan!');
    header("Location: riwayat_ujian.php");
    exit();
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

// AMBIL DATA - TANPA KONVERSI WAKTU
$sql = "SELECT hu.*, u.judul_ujian, u.durasi, u.mapel_id, u.kelas_target, 
               u.waktu_mulai as waktu_mulai_ujian, u.waktu_selesai as waktu_selesai_ujian,
               mp.nama_mapel,
               TIMESTAMPDIFF(SECOND, hu.waktu_mulai, NOW()) as waktu_berjalan_detik,
               su.sisa_waktu
        FROM hasil_ujian hu
        JOIN ujian u ON hu.ujian_id = u.id
        JOIN mata_pelajaran mp ON u.mapel_id = mp.id
        LEFT JOIN sesi_ujian su ON su.hasil_ujian_id = hu.id
        WHERE hu.id = ? AND hu.siswa_id = ? AND hu.status = 'sedang_ujian'";

$stmt = $pdo->prepare($sql);
$stmt->execute([$hasil_ujian_id, $siswa['id']]);
$hasil_ujian = $stmt->fetch();

// Validasi
if (!$hasil_ujian) {
    // Cek apakah ujian sudah selesai
    $stmt_check = $pdo->prepare("SELECT * FROM hasil_ujian WHERE id = ? AND siswa_id = ?");
    $stmt_check->execute([$hasil_ujian_id, $siswa['id']]);
    $check = $stmt_check->fetch();
    
    if ($check && $check['status'] == 'selesai') {
        flashMessage('info', 'Ujian sudah diselesaikan!');
        header("Location: detail_hasil.php?id=" . $hasil_ujian_id);
        exit();
    }
    
    flashMessage('danger', 'Ujian tidak ditemukan atau tidak dapat dilanjutkan!');
    header("Location: riwayat_ujian.php");
    exit();
}

// WAKTU SEKARANG - GUNAKAN TIMESTAMP LANGSUNG
$sekarang = time();
$waktu_mulai_ujian = strtotime($hasil_ujian['waktu_mulai_ujian']);
$waktu_selesai_ujian = strtotime($hasil_ujian['waktu_selesai_ujian']);

// LOG UNTUK DEBUG
error_log("=== DEBUG lanjutkan_ujian.php ===");
error_log("Sekarang (timestamp): " . $sekarang . " = " . date('Y-m-d H:i:s', $sekarang));
error_log("Waktu mulai ujian: " . $waktu_mulai_ujian . " = " . date('Y-m-d H:i:s', $waktu_mulai_ujian));
error_log("Waktu selesai ujian: " . $waktu_selesai_ujian . " = " . date('Y-m-d H:i:s', $waktu_selesai_ujian));

// HITUNG WAKTU PENGERJAAN PRIBADI
$waktu_mulai_pengerjaan = strtotime($hasil_ujian['waktu_mulai']);
$durasi_total = $hasil_ujian['durasi'] * 60; // dalam detik
$waktu_selesai_pengerjaan = $waktu_mulai_pengerjaan + $durasi_total;

// Sisa waktu pengerjaan pribadi
$sisa_waktu_pengerjaan = $waktu_selesai_pengerjaan - $sekarang;

// LOG LAGI
error_log("Waktu mulai pengerjaan: " . date('Y-m-d H:i:s', $waktu_mulai_pengerjaan));
error_log("Durasi total: " . $durasi_total . " detik = " . $hasil_ujian['durasi'] . " menit");
error_log("Waktu selesai pengerjaan: " . date('Y-m-d H:i:s', $waktu_selesai_pengerjaan));
error_log("Sisa waktu pengerjaan: " . $sisa_waktu_pengerjaan . " detik");

// KONDISI 1: WAKTU PENGERJAAN PRIBADI SUDAH HABIS
if ($sisa_waktu_pengerjaan <= 0) {
    error_log("KONDISI 1: Waktu pengerjaan habis");
    $stmt_update = $pdo->prepare("UPDATE hasil_ujian SET status = 'selesai', waktu_selesai = NOW() WHERE id = ?");
    $stmt_update->execute([$hasil_ujian_id]);
    
    flashMessage('warning', 'Waktu pengerjaan Anda telah habis!');
    header("Location: detail_hasil.php?id=" . $hasil_ujian_id);
    exit();
}

// KONDISI 2: WAKTU UJIAN RESMI SUDAH LEWAT (dengan toleransi)
if ($sekarang > $waktu_selesai_ujian) {
    $toleransi = 15 * 60; // 15 menit toleransi
    $lewat_detik = $sekarang - $waktu_selesai_ujian;
    
    if ($lewat_detik > $toleransi) {
        error_log("KONDISI 2: Waktu ujian lewat > 15 menit");
        $stmt_update = $pdo->prepare("UPDATE hasil_ujian SET status = 'selesai', waktu_selesai = NOW() WHERE id = ?");
        $stmt_update->execute([$hasil_ujian_id]);
        
        flashMessage('warning', 'Waktu ujian telah berakhir lebih dari 15 menit!');
        header("Location: detail_hasil.php?id=" . $hasil_ujian_id);
        exit();
    } else {
        error_log("KONDISI 2: Masih dalam toleransi 15 menit (lewat: " . $lewat_detik . " detik)");
        // Lanjutkan saja
    }
}

// KONDISI 3: UJIAN BELUM DIMULAI
if ($sekarang < $waktu_mulai_ujian) {
    error_log("KONDISI 3: Ujian belum dimulai");
    flashMessage('warning', 'Ujian belum dimulai! Dimulai pada: ' . date('d/m/Y H:i', $waktu_mulai_ujian));
    header("Location: daftar_ujian.php");
    exit();
}

// HITUNG SISA WAKTU FINAL
$sisa_waktu_final = $sisa_waktu_pengerjaan; // Utamakan waktu pengerjaan pribadi

// Jika waktu ujian resmi akan berakhir lebih cepat
if ($waktu_selesai_ujian + 900 < $waktu_selesai_pengerjaan) { // 900 detik = 15 menit
    $sisa_waktu_final = ($waktu_selesai_ujian + 900) - $sekarang;
}

error_log("Sisa waktu final: " . $sisa_waktu_final . " detik = " . floor($sisa_waktu_final/60) . " menit");

// Update sesi ujian
if ($hasil_ujian['sisa_waktu'] === null) {
    $stmt_sesi = $pdo->prepare("INSERT INTO sesi_ujian (hasil_ujian_id, sisa_waktu, last_activity) VALUES (?, ?, NOW())");
    $stmt_sesi->execute([$hasil_ujian_id, $sisa_waktu_final]);
} else {
    $sisa_waktu_terkecil = min($hasil_ujian['sisa_waktu'], $sisa_waktu_final);
    $stmt_sesi = $pdo->prepare("UPDATE sesi_ujian SET sisa_waktu = ?, last_activity = NOW() WHERE hasil_ujian_id = ?");
    $stmt_sesi->execute([$sisa_waktu_terkecil, $hasil_ujian_id]);
}

// SUCCESS - Redirect ke halaman ujian
error_log("SUKSES: Redirect ke kerjakan_ujian.php");
header("Location: kerjakan_ujian.php?id=$hasil_ujian_id");
exit();
?>