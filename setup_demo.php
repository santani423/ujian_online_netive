<?php
// setup_demo.php
require_once 'config.php';

// Tambah user guru
$password_guru = password_hash('123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'guru', 'Guru Demo')");
$stmt->execute(['guru', $password_guru]);
$guru_id = $pdo->lastInsertId();

// Tambah data guru
$stmt = $pdo->prepare("INSERT INTO guru (user_id, nip, nama, email) VALUES (?, '123456', 'Guru Demo', 'guru@demo.com')");
$stmt->execute([$guru_id]);

// Tambah user siswa
$password_siswa = password_hash('123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'siswa', 'Siswa Demo')");
$stmt->execute(['siswa', $password_siswa]);
$siswa_id = $pdo->lastInsertId();

// Tambah data siswa
$stmt = $pdo->prepare("INSERT INTO siswa (user_id, nisn, nama, kelas) VALUES (?, '2023001', 'Siswa Demo', 'X IPA 1')");
$stmt->execute([$siswa_id]);

// Tambah mata pelajaran
$mapel = [
    ['MTK', 'Matematika', 'Mata pelajaran matematika'],
    ['BIO', 'Biologi', 'Mata pelajaran biologi'],
    ['FIS', 'Fisika', 'Mata pelajaran fisika'],
    ['KIM', 'Kimia', 'Mata pelajaran kimia']
];

foreach ($mapel as $m) {
    $stmt = $pdo->prepare("INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, deskripsi) VALUES (?, ?, ?)");
    $stmt->execute($m);
}

echo "Data demo berhasil ditambahkan!";
?>