<?php
// proses_berita_acara.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'tambah':
        tambahBeritaAcara();
        break;
    case 'edit':
        editBeritaAcara();
        break;
    case 'hapus':
        hapusBeritaAcara();
        break;
    default:
        flashMessage('danger', 'Aksi tidak valid');
        header("Location: berita_acara.php");
        exit();
}

function tambahBeritaAcara() {
    global $pdo;
    
    try {
        $ujian_id = $_POST['ujian_id'];
        $guru_pengawas = $_POST['guru_pengawas'];
        $tanggal_ujian = $_POST['tanggal_ujian'];
        $waktu_mulai = $_POST['waktu_mulai'];
        $waktu_selesai = $_POST['waktu_selesai'];
        $kelas = $_POST['kelas'];
        $ruangan = $_POST['ruangan'];
        $kejadian_penting = $_POST['kejadian_penting'] ?? '';
        $kendala_teknis = $_POST['kendala_teknis'] ?? '';
        $tindak_lanjut = $_POST['tindak_lanjut'] ?? '';
        
        // Hitung jumlah peserta dari tabel siswa
        $peserta_query = $pdo->prepare("SELECT COUNT(*) as total FROM siswa WHERE kelas = ?");
        $peserta_query->execute([$kelas]);
        $jumlah_peserta = $peserta_query->fetch()['total'];
        
        // Hitung jumlah hadir dari absensi untuk ujian ini
        $hadir_query = $pdo->prepare("SELECT COUNT(DISTINCT a.siswa_id) as hadir 
                                     FROM absensi_ujian a 
                                     JOIN siswa s ON a.siswa_id = s.id 
                                     WHERE a.ujian_id = ? 
                                     AND s.kelas = ? 
                                     AND a.status_hadir = 'hadir'");
        $hadir_query->execute([$ujian_id, $kelas]);
        $jumlah_hadir = $hadir_query->fetch()['hadir'];
        
        $jumlah_tidak_hadir = $jumlah_peserta - $jumlah_hadir;
        
        $query = "INSERT INTO berita_acara 
                  (ujian_id, guru_pengawas, tanggal_ujian, waktu_mulai, waktu_selesai, 
                   jumlah_peserta, jumlah_hadir, jumlah_tidak_hadir, kelas, ruangan,
                   kejadian_penting, kendala_teknis, tindak_lanjut, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $ujian_id, $guru_pengawas, $tanggal_ujian, $waktu_mulai, $waktu_selesai,
            $jumlah_peserta, $jumlah_hadir, $jumlah_tidak_hadir, $kelas, $ruangan,
            $kejadian_penting, $kendala_teknis, $tindak_lanjut
        ]);
        
        flashMessage('success', 'Berita acara berhasil ditambahkan!');
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Error: ' . $e->getMessage());
    }
    
    header("Location: berita_acara.php");
    exit();
}

function editBeritaAcara() {
    global $pdo;
    
    try {
        $id = $_POST['id'];
        $status = $_POST['status'];
        
        $query = "UPDATE berita_acara SET 
                  status = ?,
                  updated_at = NOW()
                  WHERE id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$status, $id]);
        
        flashMessage('success', 'Status berita acara berhasil diperbarui!');
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Error: ' . $e->getMessage());
    }
    
    header("Location: berita_acara.php");
    exit();
}

function hapusBeritaAcara() {
    global $pdo;
    
    try {
        $id = $_GET['id'];
        
        $query = "DELETE FROM berita_acara WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id]);
        
        flashMessage('success', 'Berita acara berhasil dihapus!');
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Error: ' . $e->getMessage());
    }
    
    header("Location: berita_acara.php");
    exit();
}
?>