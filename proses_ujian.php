<?php
// proses_ujian.php
ob_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Tangani aksi yang dikirim
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Validasi aksi
if (empty($action) || empty($id)) {
    flashMessage('danger', 'Parameter tidak valid!');
    header("Location: kelola_ujian.php");
    exit();
}

try {
    switch ($action) {
        case 'hapus':
            hapusUjian($pdo, $id, $_SESSION['user_id']);
            break;
            
        case 'publish':
            publishUjian($pdo, $id, $_SESSION['user_id']);
            break;
            
        case 'unpublish':
            unpublishUjian($pdo, $id, $_SESSION['user_id']);
            break;
            
        default:
            flashMessage('danger', 'Aksi tidak valid!');
            header("Location: kelola_ujian.php");
            exit();
    }
} catch (Exception $e) {
    flashMessage('danger', 'Terjadi kesalahan: ' . $e->getMessage());
    header("Location: kelola_ujian.php");
    exit();
}

/**
 * Hapus ujian
 */
function hapusUjian($pdo, $id, $user_id) {
    // Validasi kepemilikan ujian
    $stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND created_by = ?");
    $stmt->execute([$id, $user_id]);
    $ujian = $stmt->fetch();
    
    if (!$ujian) {
        flashMessage('danger', 'Ujian tidak ditemukan atau Anda tidak memiliki akses!');
        redirect('kelola_ujian.php');
    }
    
    // Cek apakah sudah ada siswa yang mengikuti ujian
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM hasil_ujian WHERE ujian_id = ?");
    $stmt->execute([$id]);
    $hasil = $stmt->fetch();
    
    if ($hasil['total'] > 0) {
        flashMessage('warning', 'Tidak dapat menghapus ujian karena sudah ada siswa yang mengikutinya!');
        redirect('kelola_ujian.php');
    }
    
    // Hapus ujian
    $stmt = $pdo->prepare("DELETE FROM ujian WHERE id = ?");
    $stmt->execute([$id]);
    
    flashMessage('success', 'Ujian berhasil dihapus!');
    redirect('kelola_ujian.php');
}

/**
 * Publish ujian
 */
function publishUjian($pdo, $id, $user_id) {
    // Validasi kepemilikan ujian
    $stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND created_by = ?");
    $stmt->execute([$id, $user_id]);
    $ujian = $stmt->fetch();
    
    if (!$ujian) {
        flashMessage('danger', 'Ujian tidak ditemukan atau Anda tidak memiliki akses!');
        redirect('kelola_ujian.php');
    }
    
    // Cek apakah ujian sudah memiliki soal
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_soal FROM soal_ujian WHERE ujian_id = ?");
    $stmt->execute([$id]);
    $soal = $stmt->fetch();
    
    if ($soal['total_soal'] == 0) {
        flashMessage('warning', 'Tidak dapat mempublish ujian tanpa soal!');
        redirect('kelola_ujian.php');
    }
    
    // Cek apakah waktu sudah lewat
    $waktu_selesai = strtotime($ujian['waktu_selesai']);
    if (time() > $waktu_selesai) {
        flashMessage('warning', 'Tidak dapat mempublish ujian yang sudah lewat waktunya!');
        redirect('kelola_ujian.php');
    }
    
    // Update status menjadi published
    $stmt = $pdo->prepare("UPDATE ujian SET status = 'published' WHERE id = ?");
    $stmt->execute([$id]);
    
    flashMessage('success', 'Ujian berhasil dipublish! Sekarang dapat diakses oleh siswa.');
    redirect('kelola_ujian.php');
}

/**
 * Unpublish ujian
 */
function unpublishUjian($pdo, $id, $user_id) {
    // Validasi kepemilikan ujian
    $stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND created_by = ?");
    $stmt->execute([$id, $user_id]);
    $ujian = $stmt->fetch();
    
    if (!$ujian) {
        flashMessage('danger', 'Ujian tidak ditemukan atau Anda tidak memiliki akses!');
        redirect('kelola_ujian.php');
    }
    
    // Update status menjadi draft
    $stmt = $pdo->prepare("UPDATE ujian SET status = 'draft' WHERE id = ?");
    $stmt->execute([$id]);
    
    flashMessage('success', 'Ujian berhasil diunpublish!');
    redirect('kelola_ujian.php');
}