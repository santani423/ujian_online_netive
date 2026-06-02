<?php
// proses_siswa.php - CRUD Manual
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$action = $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($action == 'tambah') {
            tambahSiswa();
        } elseif ($action == 'edit') {
            editSiswa();
        }
    } elseif ($action == 'hapus') {
        hapusSiswa();
    }
} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
}

header("Location: kelola_siswa.php");
exit();

function tambahSiswa() {
    global $pdo;
    
    $nisn = $_POST['nisn'] ?? '';
    $nama = $_POST['nama'] ?? '';
    $username = $_POST['username'] ?? '';
    $kelas = $_POST['kelas'] ?? '';
    $jk = $_POST['jenis_kelamin'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validasi
    if (empty($nisn) || empty($nama) || empty($username) || empty($kelas) || empty($jk) || empty($password)) {
        throw new Exception('Semua field harus diisi!');
    }
    
    if (!is_numeric($nisn)) {
        throw new Exception('NISN harus berupa angka!');
    }
    
    // Cek duplikat NISN
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nisn = ?");
    $stmt->execute([$nisn]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("NISN $nisn sudah ada!");
    }
    
    // Cek duplikat username
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Username $username sudah digunakan!");
    }
    
    $pdo->beginTransaction();
    
    try {
        // Insert user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'siswa', ?)");
        $stmt->execute([$username, $hashed_password, $nama]);
        $user_id = $pdo->lastInsertId();
        
        // Insert siswa
        $stmt = $pdo->prepare("INSERT INTO siswa (user_id, nisn, nama, kelas, jenis_kelamin) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $nisn, $nama, $kelas, $jk]);
        
        $pdo->commit();
        
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Siswa $nama berhasil ditambahkan!"];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Gagal menambah siswa: " . $e->getMessage());
    }
}

function editSiswa() {
    global $pdo;
    
    $id = $_POST['id'] ?? '';
    $nisn = $_POST['nisn'] ?? '';
    $nama = $_POST['nama'] ?? '';
    $kelas = $_POST['kelas'] ?? '';
    $jk = $_POST['jenis_kelamin'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($id)) {
        throw new Exception('ID siswa tidak valid!');
    }
    
    // Get user_id
    $stmt = $pdo->prepare("SELECT user_id FROM siswa WHERE id = ?");
    $stmt->execute([$id]);
    $siswa = $stmt->fetch();
    
    if (!$siswa) {
        throw new Exception('Siswa tidak ditemukan!');
    }
    
    $user_id = $siswa['user_id'];
    
    $pdo->beginTransaction();
    
    try {
        // Update siswa
        $stmt = $pdo->prepare("UPDATE siswa SET nisn = ?, nama = ?, kelas = ?, jenis_kelamin = ? WHERE id = ?");
        $stmt->execute([$nisn, $nama, $kelas, $jk, $id]);
        
        // Update user
        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ? WHERE id = ?");
        $stmt->execute([$nama, $user_id]);
        
        // Update password jika diisi
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
        }
        
        $pdo->commit();
        
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Data siswa $nama berhasil diupdate!"];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Gagal mengupdate siswa: " . $e->getMessage());
    }
}

function hapusSiswa() {
    global $pdo;
    
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        throw new Exception('ID siswa tidak valid!');
    }
    
    // Get user_id dan nama
    $stmt = $pdo->prepare("SELECT s.nama, s.user_id FROM siswa s WHERE s.id = ?");
    $stmt->execute([$id]);
    $siswa = $stmt->fetch();
    
    if (!$siswa) {
        throw new Exception('Siswa tidak ditemukan!');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Hapus siswa (akan trigger cascade delete di user karena foreign key)
        $stmt = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
        $stmt->execute([$id]);
        
        // Hapus user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$siswa['user_id']]);
        
        $pdo->commit();
        
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Siswa {$siswa['nama']} berhasil dihapus!"];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Gagal menghapus siswa: " . $e->getMessage());
    }
}
?>