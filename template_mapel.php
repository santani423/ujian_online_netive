<?php
// template_mapel.php - DELIMITER ; AGAR RAPI DI EXCEL
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Headers untuk download file CSV dengan delimiter ;
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_mapel.csv"');

$output = fopen('php://output', 'w');

// BOM for UTF-8 (agar Excel tidak corrupt)
fputs($output, "\xEF\xBB\xBF");

// Header CSV dengan delimiter ;
$header = ['KODE_MAPEL', 'NAMA_MAPEL', 'DESKRIPSI', 'KKM'];
fputcsv($output, $header, ';');

// Data contoh
$contoh_data = [
    ['MTK', 'Matematika', 'Mata pelajaran matematika dasar', '70'],
    ['BIN', 'Bahasa Indonesia', 'Mata pelajaran bahasa Indonesia', '75'],
    ['BIG', 'Bahasa Inggris', 'Mata pelajaran bahasa Inggris', '70'],
    ['FIS', 'Fisika', 'Mata pelajaran fisika', '70'],
    ['KIM', 'Kimia', 'Mata pelajaran kimia', '70'],
    ['BIO', 'Biologi', 'Mata pelajaran biologi', '70'],
    ['SEJ', 'Sejarah', 'Mata pelajaran sejarah', '75'],
    ['GEO', 'Geografi', 'Mata pelajaran geografi', '70'],
    ['EKO', 'Ekonomi', 'Mata pelajaran ekonomi', '75'],
    ['SOS', 'Sosiologi', 'Mata pelajaran sosiologi', '70'],
    ['PKN', 'PKN', 'Pendidikan Kewarganegaraan', '75'],
    ['SEN', 'Seni Budaya', 'Mata pelajaran seni budaya', '70'],
    ['PJOK', 'PJOK', 'Pendidikan Jasmani Olahraga', '70'],
    ['TIK', 'TIK', 'Teknologi Informasi', '70']
];

foreach ($contoh_data as $row) {
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
?>