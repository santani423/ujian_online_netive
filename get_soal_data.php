<?php
// get_soal_data.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM soal WHERE id = ?");
$stmt->execute([$id]);
$soal = $stmt->fetch(PDO::FETCH_ASSOC);

if ($soal) {
    echo json_encode(['success' => true, 'soal' => $soal]);
} else {
    echo json_encode(['success' => false, 'message' => 'Soal not found']);
}
?>