<?php
// proses_absensi.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action == 'save_all') {
    try {
        $ujian_id = $_POST['ujian_id'];
        $tanggal = $_POST['tanggal'];
        $absensi_data = json_decode($_POST['absensi_data'], true);
        
        $pdo->beginTransaction();
        
        foreach ($absensi_data as $data) {
            $siswa_id = $data['siswa_id'];
            $status = $data['status'];
            $keterangan = $data['keterangan'];
            $waktu_hadir = $data['waktu_hadir'] ?: null;
            
            // Cek apakah absensi sudah ada
            $stmt = $pdo->prepare("SELECT id FROM absensi_ujian WHERE ujian_id = ? AND siswa_id = ? AND DATE(created_at) = ?");
            $stmt->execute([$ujian_id, $siswa_id, $tanggal]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update
                $stmt = $pdo->prepare("UPDATE absensi_ujian SET 
                    status_hadir = ?, 
                    keterangan = ?, 
                    waktu_hadir = ?,
                    updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([$status, $keterangan, $waktu_hadir, $existing['id']]);
            } else {
                // Insert baru
                $stmt = $pdo->prepare("INSERT INTO absensi_ujian 
                    (ujian_id, siswa_id, status_hadir, keterangan, waktu_hadir, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$ujian_id, $siswa_id, $status, $keterangan, $waktu_hadir]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Absensi berhasil disimpan!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} elseif ($action == 'tambah') {
    // ... kode untuk tambah absensi individu
} elseif ($action == 'edit') {
    // ... kode untuk edit absensi
} elseif ($action == 'hapus') {
    // ... kode untuk hapus absensi
}

exit();