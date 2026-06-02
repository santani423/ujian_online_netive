



































<?php
// ajax_get_soal_ujian.php - Get soal ujian untuk siswa tertentu
require_once 'config.php';

// Cek session tanpa memulai ulang
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit();
}

header('Content-Type: application/json');

$ujian_id = $_GET['ujian_id'] ?? 0;
$hasil_ujian_id = $_GET['hasil_ujian_id'] ?? 0;

if (!$ujian_id || !$hasil_ujian_id) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit();
}

try {
    // Ambil soal untuk ujian ini beserta jawaban siswa
    $sql = "
        SELECT 
            su.*,
            s.*,
            js.jawaban_siswa,
            js.jawaban_essay,
            je.skor_essay,
            je.komentar_guru,
            je.status_koreksi as status_koreksi_essay,
            -- Deteksi akhir untuk pastikan jenis soal benar
            CASE 
                WHEN s.jenis_soal IN ('essay', 'menjodohkan', 'benar_salah') THEN s.jenis_soal
                WHEN TRIM(s.opsi_a) = 'Benar' AND TRIM(s.opsi_b) = 'Salah' AND 
                     (s.opsi_c = '-' OR s.opsi_c = '' OR s.opsi_c IS NULL) AND 
                     (s.opsi_d = '-' OR s.opsi_d = '' OR s.opsi_d IS NULL) THEN 'benar_salah'
                WHEN (s.opsi_c = '-' OR s.opsi_c = '' OR s.opsi_c IS NULL) AND 
                     (s.opsi_d = '-' OR s.opsi_d = '' OR s.opsi_d IS NULL) AND
                     s.opsi_a LIKE '1.%' AND s.opsi_b LIKE '1.%' THEN 'menjodohkan'
                WHEN (s.opsi_c = '-' OR s.opsi_c = '' OR s.opsi_c IS NULL) AND 
                     (s.opsi_d = '-' OR s.opsi_d = '' OR s.opsi_d IS NULL) AND
                     TRIM(s.opsi_a) != 'Benar' AND TRIM(s.opsi_b) != 'Salah' THEN 'essay'
                ELSE 'pilihan_ganda'
            END as jenis_soal_final
        FROM soal_ujian su
        JOIN soal s ON su.soal_id = s.id
        LEFT JOIN jawaban_siswa js ON js.soal_id = s.id AND js.hasil_ujian_id = :hasil_ujian_id
        LEFT JOIN jawaban_essay je ON je.soal_id = s.id AND je.hasil_ujian_id = :hasil_ujian_id2
        WHERE su.ujian_id = :ujian_id
        ORDER BY su.urutan
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ujian_id' => $ujian_id,
        ':hasil_ujian_id' => $hasil_ujian_id,
        ':hasil_ujian_id2' => $hasil_ujian_id
    ]);
    
    $soal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug output untuk cek soal essay
    error_log("Total soal ditemukan: " . count($soal_list));
    foreach ($soal_list as $index => $soal) {
        error_log("Soal #" . ($index+1) . ": ID=" . $soal['id'] . 
                 ", Jenis=" . $soal['jenis_soal'] . 
                 ", Final=" . $soal['jenis_soal_final'] .
                 ", Pertanyaan: " . substr($soal['pertanyaan'], 0, 50));
    }
    
    echo json_encode([
        'success' => true,
        'soal' => $soal_list,
        'debug' => count($soal_list) . ' soal ditemukan'
    ]);
    
} catch (PDOException $e) {
    error_log("Error ajax_get_soal_ujian: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>