<?php
// ajax_get_kecurangan.php - Ambil data kecurangan siswa (HANYA UNTUK UJIAN INI)
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$hasil_ujian_id = $_GET['hasil_ujian_id'] ?? 0;
$ujian_id = $_GET['ujian_id'] ?? 0;

if (!$hasil_ujian_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    // Pastikan hasil_ujian_id ini milik ujian yang dipilih
    $stmt_cek = $pdo->prepare("
        SELECT hu.id, hu.ujian_id 
        FROM hasil_ujian hu 
        WHERE hu.id = ? AND hu.ujian_id = ?
    ");
    $stmt_cek->execute([$hasil_ujian_id, $ujian_id]);
    
    if (!$stmt_cek->fetch()) {
        echo json_encode([
            'success' => false, 
            'message' => 'Data tidak ditemukan untuk ujian ini'
        ]);
        exit();
    }
    
    // Ambil data kecurangan HANYA untuk ujian ini
    $stmt = $pdo->prepare("
        SELECT * FROM log_kecurangan 
        WHERE hasil_ujian_id = ? 
        ORDER BY waktu_pelanggaran DESC
    ");
    $stmt->execute([$hasil_ujian_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total' => count($logs),
        'ujian_id' => $ujian_id,
        'hasil_ujian_id' => $hasil_ujian_id
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
exit();
?>