<?php
// proses_selesai_ujian.php - Untuk menyelesaikan ujian
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $hasil_ujian_id = $input['hasil_ujian_id'] ?? 0;
    
    try {
        // Verifikasi kepemilikan hasil ujian
        $stmt_verify = $pdo->prepare("SELECT hu.* FROM hasil_ujian hu 
                                    JOIN siswa s ON hu.siswa_id = s.id 
                                    WHERE hu.id = ? AND s.user_id = ?");
        $stmt_verify->execute([$hasil_ujian_id, $_SESSION['user_id']]);
        $hasil_ujian = $stmt_verify->fetch();
        
        if (!$hasil_ujian) {
            throw new Exception('Hasil ujian tidak valid');
        }
        
        if ($hasil_ujian['status'] == 'selesai') {
            throw new Exception('Ujian sudah diselesaikan');
        }
        
        // Hitung nilai
        $stmt_jawaban = $pdo->prepare("SELECT js.soal_id, js.jawaban_siswa, s.jawaban_benar 
                                      FROM jawaban_siswa js 
                                      JOIN soal s ON js.soal_id = s.id 
                                      WHERE js.hasil_ujian_id = ?");
        $stmt_jawaban->execute([$hasil_ujian_id]);
        $jawaban_list = $stmt_jawaban->fetchAll();
        
        $total_soal = count($jawaban_list);
        $jawaban_benar = 0;
        
        foreach ($jawaban_list as $jawaban) {
            if ($jawaban['jawaban_siswa'] == $jawaban['jawaban_benar']) {
                $jawaban_benar++;
            }
        }
        
        $nilai = ($total_soal > 0) ? round(($jawaban_benar / $total_soal) * 100, 2) : 0;
        
        // Update hasil ujian
        $stmt_update = $pdo->prepare("UPDATE hasil_ujian SET status = 'selesai', waktu_selesai = NOW(), nilai = ? WHERE id = ?");
        $stmt_update->execute([$nilai, $hasil_ujian_id]);
        
        // Hapus sesi ujian
        $stmt_hapus_sesi = $pdo->prepare("DELETE FROM sesi_ujian WHERE hasil_ujian_id = ?");
        $stmt_hapus_sesi->execute([$hasil_ujian_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Ujian berhasil diselesaikan', 'nilai' => $nilai]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>