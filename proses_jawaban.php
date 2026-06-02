<?php
// proses_jawaban.php - Untuk menyimpan jawaban siswa
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hasil_ujian_id = $_POST['hasil_ujian_id'] ?? 0;
    $jawaban_data = $_POST['jawaban'] ?? [];
    
    try {
        // Verifikasi kepemilikan hasil ujian
        $stmt_verify = $pdo->prepare("SELECT hu.* FROM hasil_ujian hu 
                                    JOIN siswa s ON hu.siswa_id = s.id 
                                    WHERE hu.id = ? AND s.user_id = ? AND hu.status = 'sedang_ujian'");
        $stmt_verify->execute([$hasil_ujian_id, $_SESSION['user_id']]);
        $hasil_ujian = $stmt_verify->fetch();
        
        if (!$hasil_ujian) {
            throw new Exception('Hasil ujian tidak valid');
        }
        
        // Update sesi ujian (last activity)
        $stmt_sesi = $pdo->prepare("UPDATE sesi_ujian SET last_activity = NOW() WHERE hasil_ujian_id = ?");
        $stmt_sesi->execute([$hasil_ujian_id]);
        
        // Simpan setiap jawaban
        foreach ($jawaban_data as $soal_id => $jawaban) {
            // Cek apakah jawaban sudah ada
            $stmt_cek = $pdo->prepare("SELECT id FROM jawaban_siswa WHERE hasil_ujian_id = ? AND soal_id = ?");
            $stmt_cek->execute([$hasil_ujian_id, $soal_id]);
            $existing = $stmt_cek->fetch();
            
            if ($existing) {
                // Update jawaban yang sudah ada
                $stmt_update = $pdo->prepare("UPDATE jawaban_siswa SET jawaban_siswa = ? WHERE id = ?");
                $stmt_update->execute([$jawaban, $existing['id']]);
            } else {
                // Insert jawaban baru
                $stmt_insert = $pdo->prepare("INSERT INTO jawaban_siswa (hasil_ujian_id, soal_id, jawaban_siswa) VALUES (?, ?, ?)");
                $stmt_insert->execute([$hasil_ujian_id, $soal_id, $jawaban]);
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Jawaban berhasil disimpan']);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>