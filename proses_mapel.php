<?php
// proses_mapel.php - FULL CODE
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$action = $_GET['action'] ?? '';

if ($action == 'tambah') {
    $kode_mapel = strtoupper(trim($_POST['kode_mapel']));
    $nama_mapel = trim($_POST['nama_mapel']);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $kkm = intval($_POST['kkm'] ?? 70);
    
    if (empty($kode_mapel) || empty($nama_mapel)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Kode Mapel dan Nama Mapel harus diisi!'];
        header("Location: kelola_mapel.php");
        exit();
    }
    
    if ($kkm < 0 || $kkm > 100) $kkm = 70;
    
    $stmt = $pdo->prepare("SELECT id FROM mata_pelajaran WHERE kode_mapel = ?");
    $stmt->execute([$kode_mapel]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => "Kode Mapel '$kode_mapel' sudah ada!"];
        header("Location: kelola_mapel.php");
        exit();
    }
    
    $stmt = $pdo->prepare("INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, deskripsi, kkm) VALUES (?, ?, ?, ?)");
    $stmt->execute([$kode_mapel, $nama_mapel, $deskripsi, $kkm]);
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Mata pelajaran '$nama_mapel' berhasil ditambahkan!"];
    header("Location: kelola_mapel.php");
    
} elseif ($action == 'edit') {
    $id = intval($_POST['id']);
    $kode_mapel = strtoupper(trim($_POST['kode_mapel']));
    $nama_mapel = trim($_POST['nama_mapel']);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $kkm = intval($_POST['kkm'] ?? 70);
    
    if ($kkm < 0 || $kkm > 100) $kkm = 70;
    
    $stmt = $pdo->prepare("SELECT id FROM mata_pelajaran WHERE kode_mapel = ? AND id != ?");
    $stmt->execute([$kode_mapel, $id]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => "Kode Mapel '$kode_mapel' sudah digunakan mapel lain!"];
        header("Location: kelola_mapel.php");
        exit();
    }
    
    $stmt = $pdo->prepare("UPDATE mata_pelajaran SET kode_mapel = ?, nama_mapel = ?, deskripsi = ?, kkm = ? WHERE id = ?");
    $stmt->execute([$kode_mapel, $nama_mapel, $deskripsi, $kkm, $id]);
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Mata pelajaran '$nama_mapel' berhasil diupdate!"];
    header("Location: kelola_mapel.php");
    
} elseif ($action == 'hapus') {
    $id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE mapel_id = ?");
    $stmt->execute([$id]);
    $jumlah_soal = $stmt->fetchColumn();
    
    if ($jumlah_soal > 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => "Tidak dapat menghapus! Masih terdapat $jumlah_soal soal yang menggunakan mapel ini."];
        header("Location: kelola_mapel.php");
        exit();
    }
    
    $stmt = $pdo->prepare("DELETE FROM mata_pelajaran WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Mata pelajaran berhasil dihapus!"];
    header("Location: kelola_mapel.php");
    
} elseif ($action == 'import') {
    if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] != 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Gagal upload file!'];
        header("Location: kelola_mapel.php");
        exit();
    }
    
    $file = $_FILES['file_excel']['tmp_name'];
    $handle = fopen($file, "r");
    
    if (!$handle) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Gagal membaca file!'];
        header("Location: kelola_mapel.php");
        exit();
    }
    
    // Baca baris pertama (header)
    $header_line = fgets($handle);
    
    // Deteksi delimiter otomatis
    $delimiter = ';'; // default
    if (strpos($header_line, ';') !== false) {
        $delimiter = ';';
    } elseif (strpos($header_line, ',') !== false) {
        $delimiter = ',';
    }
    
    // Reset pointer ke awal
    rewind($handle);
    
    // Baca header dengan delimiter yang terdeteksi
    $header = fgetcsv($handle, 0, $delimiter);
    
    if (!$header) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'File CSV kosong atau format salah!'];
        header("Location: kelola_mapel.php");
        exit();
    }
    
    // Bersihkan header (hapus BOM jika ada)
    $header[0] = str_replace("\xEF\xBB\xBF", '', $header[0]);
    $header = array_map('trim', $header);
    $header = array_map('strtoupper', $header);
    
    // Cari index kolom
    $idx_kode = array_search('KODE_MAPEL', $header);
    $idx_nama = array_search('NAMA_MAPEL', $header);
    $idx_deskripsi = array_search('DESKRIPSI', $header);
    $idx_kkm = array_search('KKM', $header);
    
    $success = 0;
    $failed = 0;
    $row_num = 1;
    
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row_num++;
        
        // Lewati baris kosong
        if (count($data) == 0 || (count($data) == 1 && trim($data[0]) == '')) {
            continue;
        }
        
        // Ambil nilai
        $kode_mapel = '';
        $nama_mapel = '';
        $deskripsi = '';
        $kkm = 70;
        
        if ($idx_kode !== false && isset($data[$idx_kode])) {
            $kode_mapel = strtoupper(trim($data[$idx_kode]));
        }
        
        if ($idx_nama !== false && isset($data[$idx_nama])) {
            $nama_mapel = trim($data[$idx_nama]);
        }
        
        if ($idx_deskripsi !== false && isset($data[$idx_deskripsi])) {
            $deskripsi = trim($data[$idx_deskripsi]);
        }
        
        if ($idx_kkm !== false && isset($data[$idx_kkm]) && is_numeric(trim($data[$idx_kkm]))) {
            $kkm = intval(trim($data[$idx_kkm]));
            if ($kkm < 0 || $kkm > 100) $kkm = 70;
        }
        
        // Validasi
        if (empty($kode_mapel) || empty($nama_mapel)) {
            $failed++;
            continue;
        }
        
        // Cek duplikat
        $stmt = $pdo->prepare("SELECT id FROM mata_pelajaran WHERE kode_mapel = ?");
        $stmt->execute([$kode_mapel]);
        if ($stmt->fetch()) {
            $failed++;
            continue;
        }
        
        // Insert
        $stmt = $pdo->prepare("INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, deskripsi, kkm) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$kode_mapel, $nama_mapel, $deskripsi, $kkm])) {
            $success++;
        } else {
            $failed++;
        }
    }
    
    fclose($handle);
    
    $message = "Berhasil import $success mata pelajaran.";
    if ($failed > 0) {
        $message .= " Gagal: $failed baris.";
    }
    
    $_SESSION['flash'] = ['type' => $success > 0 ? 'success' : 'danger', 'message' => $message];
    header("Location: kelola_mapel.php");
    
} else {
    header("Location: kelola_mapel.php");
}
?>