<?php
session_start();
require_once 'config/database.php';

if ($_SESSION['role'] != 'guru') {
    header('Location: dashboard.php');
    exit();
}

$id = $_GET['id'];

$query = "DELETE FROM absensi_ujian WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$id]);

header('Location: absensi_ujian.php?success=Absensi berhasil dihapus');
exit();