<?php
// download_template_siswa.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Buat file CSV sederhana
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_siswa.csv"');

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['NISN', 'Nama', 'Username', 'Kelas', 'Jenis Kelamin'], ';');

// Data contoh
$examples = [
    ['20240001', 'Andi Wijaya', 'andi2024', 'X IPA 1', 'L'],
    ['20240002', 'Siti Aminah', 'siti2024', 'X IPA 1', 'P'],
    ['20240003', 'Budi Santoso', 'budi2024', 'X IPA 2', 'L'],
    ['20240004', 'Dewi Lestari', 'dewi2024', 'X IPA 2', 'P'],
];

foreach ($examples as $example) {
    fputcsv($output, $example, ';');
}

fclose($output);
exit;
?>