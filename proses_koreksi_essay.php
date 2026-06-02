<?php
// proses_koreksi_essay.php - Memproses koreksi essay
require_once 'config.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit();
}

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? 'update';
    $hasil_ujian_id = $_POST['hasil_ujian_id'];
    $soal_id = $_POST['soal_id'];
    $skor_essay = $_POST['skor_essay'];
    $komentar_guru = $_POST['komentar_guru'] ?? '';
    $status_koreksi = $_POST['status_koreksi'] ?? 'belum';
    
    // Validasi
    if (empty($hasil_ujian_id) || empty($soal_id) || empty($skor_essay)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit();
    }
    
    if ($action == 'insert') {
        // Insert data baru ke jawaban_essay
        $sql = "INSERT INTO jawaban_essay 
                (hasil_ujian_id, soal_id, jawaban_text, skor_essay, komentar_guru, status_koreksi, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        // Ambil jawaban_text dari jawaban_siswa
        $stmt = $pdo->prepare("SELECT jawaban_essay FROM jawaban_siswa WHERE hasil_ujian_id = ? AND soal_id = ?");
        $stmt->execute([$hasil_ujian_id, $soal_id]);
        $jawaban = $stmt->fetch(PDO::FETCH_ASSOC);
        $jawaban_text = $jawaban['jawaban_essay'] ?? '';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hasil_ujian_id, $soal_id, $jawaban_text, $skor_essay, $komentar_guru, $status_koreksi]);
        
    } else {
        // Update data yang sudah ada
        $jawaban_essay_id = $_POST['jawaban_essay_id'];
        
        $sql = "UPDATE jawaban_essay 
                SET skor_essay = ?, komentar_guru = ?, status_koreksi = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$skor_essay, $komentar_guru, $status_koreksi, $jawaban_essay_id]);
    }
    
    // Jika status sudah dikoreksi, update total nilai di hasil_ujian
    if ($status_koreksi == 'sudah') {
        updateTotalNilai($hasil_ujian_id);
    }
    
    echo json_encode(['success' => true, 'message' => 'Koreksi berhasil disimpan']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function updateTotalNilai($hasil_ujian_id) {
    global $pdo;
    
    // Hitung total skor dari semua soal (pilihan ganda + essay)
    $sql = "
        SELECT 
            SUM(CASE 
                WHEN s.jenis_soal = 'pilihan_ganda' THEN 
                    CASE WHEN js.jawaban_siswa = s.jawaban_benar THEN s.skor ELSE 0 END
                WHEN s.jenis_soal = 'essay' THEN COALESCE(je.skor_essay, 0)
                WHEN s.jenis_soal = 'benar_salah' THEN 
                    CASE WHEN js.jawaban_siswa = s.jawaban_benar THEN s.skor ELSE 0 END
                ELSE 0
            END) as total_nilai,
            SUM(s.skor) as total_maksimal
        FROM jawaban_siswa js
        JOIN soal s ON js.soal_id = s.id
        LEFT JOIN jawaban_essay je ON je.hasil_ujian_id = js.hasil_ujian_id AND je.soal_id = js.soal_id
        WHERE js.hasil_ujian_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hasil_ujian_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_nilai = $result['total_nilai'] ?? 0;
    $total_maksimal = $result['total_maksimal'] ?? 100;
    
    // Hitung persentase
    $persentase = $total_maksimal > 0 ? ($total_nilai / $total_maksimal) * 100 : 0;
    
    // Update hasil_ujian
    $sql_update = "UPDATE hasil_ujian SET nilai = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql_update);
    $stmt->execute([$persentase, $hasil_ujian_id]);
}
?>