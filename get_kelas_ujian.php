<?php
// get_kelas_ujian.php
require_once 'config.php';

if (!isset($_GET['ujian_id'])) {
    echo json_encode(['error' => 'Parameter tidak valid']);
    exit();
}

$ujian_id = intval($_GET['ujian_id']);

try {
    $query = "SELECT kelas_target FROM ujian WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ujian_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode(['kelas' => $result['kelas_target']]);
    } else {
        echo json_encode(['kelas' => '']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>