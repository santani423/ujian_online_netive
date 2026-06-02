<?php
// download_template_soal.php - Template dengan support Multi Kelas
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Buat file CSV sederhana
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_soal.csv"');

$output = fopen('php://output', 'w');

// Tambahkan BOM untuk UTF-8 (agar karakter khusus terbaca)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header kolom dengan opsi A-E
$headers = ['jenis_soal', 'mapel_id', 'kelas', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'jawaban_benar', 'skor'];
fputcsv($output, $headers, ';', '"');

// Data contoh - PILIHAN GANDA (A-E) dengan multi kelas
$examples = [
    [
        'pilihan_ganda',
        '1',
        '7A',
        'Berapakah hasil dari 2 + 2?',
        '3',
        '4',
        '5',
        '6',
        '7',
        'b',
        '10'
    ],
    [
        'pilihan_ganda',
        '1',
        '7A,7B,7C',
        'Apa ibukota Indonesia? (Multi Kelas: 7A,7B,7C)',
        'Jakarta',
        'Surabaya',
        'Bandung',
        'Medan',
        'Semarang',
        'a',
        '10'
    ],
    [
        'pilihan_ganda',
        '2',
        '8A,8B',
        'Organel sel yang berfungsi sebagai tempat respirasi adalah? (Multi Kelas: 8A,8B)',
        'Mitokondria',
        'Ribosom',
        'Lisosom',
        'Badan Golgi',
        'Nukleus',
        'a',
        '15'
    ],
    [
        'pilihan_ganda',
        '1',
        '9A',
        'Siapa presiden pertama Indonesia?',
        'Soeharto',
        'Soekarno',
        'Habibie',
        'Gus Dur',
        'Megawati',
        'b',
        '10'
    ],
    [
        'pilihan_ganda',
        '1',
        '7A,8A,9A',
        'Apa lambang negara Indonesia? (Multi Kelas: 7A,8A,9A)',
        'Garuda',
        'Banteng',
        'Elang',
        'Merpati',
        'Rajawali',
        'a',
        '10'
    ],
    [
        'pilihan_ganda',
        '3',
        '10',
        'Rumus kimia air adalah?',
        'CO2',
        'O2',
        'H2O',
        'NaCl',
        'CH4',
        'c',
        '10'
    ],
    [
        'pilihan_ganda',
        '1',
        '11',
        'Bilangan prima berikut adalah?',
        '4',
        '6',
        '8',
        '9',
        '11',
        'e',
        '15'
    ],
    [
        'pilihan_ganda_kompleks',
        '1',
        '7A,7B',
        'Manakah yang termasuk hewan mamalia? (PG Kompleks, jawaban: a,c,e)',
        'Kucing',
        'Ayam',
        'Kambing',
        'Ikan',
        'Sapi',
        'a,c,e',
        '15'
    ],
    [
        'essay',
        '1',
        '8A,8B,8C',
        'Jelaskan pengertian Pancasila? (Essay - Multi Kelas)',
        '-',
        '-',
        '-',
        '-',
        '-',
        '-',
        '20'
    ]
];

foreach ($examples as $example) {
    fputcsv($output, $example, ';', '"');
}

// Tambah baris kosong
fputcsv($output, [], ';', '"');

// Tambah keterangan
$notes = [
 
];

foreach ($notes as $note) {
    fputcsv($output, $note, ';', '"');
}

fclose($output);
exit;
?>