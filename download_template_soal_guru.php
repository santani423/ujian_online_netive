<?php
// download_template_soal_guru.php - Template dengan support Multi Kelas untuk guru
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_soal_guru.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header
fputcsv($output, ['jenis_soal', 'mapel_id', 'kelas', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'jawaban_benar', 'skor'], ';', '"');

// Data contoh dengan multi kelas
$contoh = [
    ['pilihan_ganda', '1', '7A', 'Apa ibukota Indonesia?', 'Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang', 'a', '10'],
    ['pilihan_ganda', '1', '7A,7B,7C', 'Siapa presiden pertama Indonesia? (Multi Kelas: 7A,7B,7C)', 'Soeharto', 'Soekarno', 'Habibie', 'Gus Dur', 'Megawati', 'b', '10'],
    ['pilihan_ganda', '2', '8A,8B', '2 + 2 = ? (Multi Kelas: 8A,8B)', '3', '4', '5', '6', '7', 'b', '10'],
    ['pilihan_ganda', '2', '9A', 'Lambang negara Indonesia?', 'Garuda', 'Banteng', 'Elang', 'Merpati', 'Rajawali', 'a', '10'],
    ['pilihan_ganda', '1', '7A,8A,9A', 'x² + 2x + 1 = 0, x = ? (Multi Kelas: 7A,8A,9A)', '-1', '1', '2', '-2', '0', 'a', '15'],
    ['pilihan_ganda_kompleks', '1', '7A,7B', 'Manakah yang termasuk hewan mamalia? (PG Kompleks - Multi Kelas)', 'Kucing', 'Ayam', 'Kambing', 'Ikan', 'Sapi', 'a,c,e', '15'],
    ['essay', '1', '8A,8B,8C', 'Jelaskan pengertian Pancasila? (Essay - Multi Kelas)', '-', '-', '-', '-', '-', '-', '20']
];

foreach ($contoh as $row) {
    fputcsv($output, $row, ';', '"');
}

// Baris kosong
fputcsv($output, [], ';', '"');

// Petunjuk
$notes = [

];

foreach ($notes as $note) {
    fputcsv($output, $note, ';', '"');
}

fclose($output);
exit();
?>