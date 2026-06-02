<?php
// proses_import_siswa.php - VERSI SIMPLE & PASTI WORK
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

set_time_limit(300);

try {
    // Validasi request
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        throw new Exception('Metode request tidak valid.');
    }

    if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] != UPLOAD_ERR_OK) {
        throw new Exception('Silakan pilih file Excel/CSV yang valid.');
    }

    $file = $_FILES['file_excel'];
    $skip_duplicate = isset($_POST['skip_duplicate']);

    // Validasi file
    $allowed_extensions = ['xlsx', 'xls', 'csv'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Format file harus .xlsx, .xls, atau .csv');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Ukuran file maksimal 5MB');
    }

    // Baca file
    $rows = [];
    
    if ($file_extension == 'csv') {
        // Baca CSV
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
                if (count($data) == 1) {
                    // Coba dengan koma
                    $data = fgetcsv($handle, 1000, ',');
                }
                $rows[] = array_map('trim', $data);
            }
            fclose($handle);
        }
    } else {
        // Baca Excel dengan PhpSpreadsheet
        $autoload_paths = [
            __DIR__ . '/vendor/autoload.php',
            'vendor/autoload.php',
            '../vendor/autoload.php'
        ];
        
        $phpspreadsheet_loaded = false;
        foreach ($autoload_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $phpspreadsheet_loaded = true;
                break;
            }
        }
        
        if ($phpspreadsheet_loaded && class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                $rows = $spreadsheet->getActiveSheet()->toArray();
            } catch (Exception $e) {
                throw new Exception('Gagal membaca file Excel: ' . $e->getMessage());
            }
        } else {
            throw new Exception('Library PhpSpreadsheet tidak tersedia. Silakan install via composer atau gunakan file CSV.');
        }
    }

    // Validasi data
    if (count($rows) < 2) {
        throw new Exception('File harus memiliki data (minimal 1 baris data setelah header)');
    }

    // Proses header
    $headers = array_map('strtolower', $rows[0]);
    
    // Cari index kolom dengan cara sederhana
    $col_nisn = array_search('nisn', $headers);
    $col_nama = array_search('nama', $headers);
    $col_username = array_search('username', $headers);
    $col_kelas = array_search('kelas', $headers);
    $col_jk = array_search('jenis kelamin', $headers);

    // Fallback jika header tidak persis
    if ($col_nisn === false) {
        foreach ($headers as $index => $header) {
            if (strpos($header, 'nisn') !== false || strpos($header, 'induk') !== false) {
                $col_nisn = $index;
                break;
            }
        }
    }

    if ($col_nama === false) {
        foreach ($headers as $index => $header) {
            if (strpos($header, 'nama') !== false) {
                $col_nama = $index;
                break;
            }
        }
    }

    // Validasi header required
    if ($col_nisn === false || $col_nama === false) {
        throw new Exception('Header tidak valid. Pastikan ada kolom NISN dan Nama. Header yang ditemukan: ' . implode(', ', $headers));
    }

    // Proses data
    $success_count = 0;
    $error_count = 0;
    $duplicate_count = 0;
    $errors = [];

    $pdo->beginTransaction();

    try {
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $line = $i + 1;

            // Skip baris kosong
            if (empty(array_filter($row))) {
                continue;
            }

            // Ambil data
            $nisn = isset($row[$col_nisn]) ? trim($row[$col_nisn]) : '';
            $nama = isset($row[$col_nama]) ? trim($row[$col_nama]) : '';
            $username = isset($row[$col_username]) ? trim($row[$col_username]) : '';
            $kelas = isset($row[$col_kelas]) ? trim($row[$col_kelas]) : '';
            $jk_raw = isset($row[$col_jk]) ? trim($row[$col_jk]) : '';

            // Generate username jika kosong
            if (empty($username)) {
                $username = generateUsername($nama);
            }

            // Validasi data required
            if (empty($nisn) || empty($nama) || empty($kelas) || empty($jk_raw)) {
                $error_count++;
                $errors[] = "Baris $line: Data tidak lengkap";
                continue;
            }

            // Validasi NISN
            if (!is_numeric($nisn)) {
                $error_count++;
                $errors[] = "Baris $line: NISN harus angka ('$nisn')";
                continue;
            }

            // Normalisasi jenis kelamin
            $jk = normalizeGender($jk_raw);
            if (!$jk) {
                $error_count++;
                $errors[] = "Baris $line: Jenis kelamin harus L/P ('$jk_raw')";
                continue;
            }

            // Cek duplikat
            if (checkDuplicate($nisn, $username)) {
                if ($skip_duplicate) {
                    $duplicate_count++;
                    continue;
                } else {
                    $error_count++;
                    $errors[] = "Baris $line: NISN '$nisn' atau Username '$username' sudah ada";
                    continue;
                }
            }

            // Insert data
            if (insertStudent($nisn, $nama, $username, $kelas, $jk)) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Baris $line: Gagal menyimpan data";
            }
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Gagal memproses data: ' . $e->getMessage());
    }

    // Siapkan pesan hasil
    $message = "Import selesai: $success_count data berhasil diimport";
    $type = 'success';

    if ($duplicate_count > 0) {
        $message .= ", $duplicate_count data duplikat dilewati";
    }

    if ($error_count > 0) {
        $message .= ", $error_count error";
        $type = 'warning';
        
        // Simpan error detail
        $_SESSION['import_errors'] = array_slice($errors, 0, 10);
    }

    if ($success_count == 0 && $error_count > 0) {
        $type = 'danger';
        $message = "Tidak ada data yang berhasil diimport. Silakan periksa file Anda.";
    }

    $_SESSION['flash'] = ['type' => $type, 'message' => $message];

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
}

header("Location: kelola_siswa.php");
exit();

// Fungsi helper
function generateUsername($nama) {
    $base = preg_replace('/[^a-z]/', '', strtolower($nama));
    return $base ?: 'siswa' . rand(1000, 9999);
}

function normalizeGender($jk) {
    $jk = strtoupper(trim($jk));
    
    if (in_array($jk, ['L', 'LAKI-LAKI', 'LAKI', 'LKI', 'PRIA', 'MALE'])) {
        return 'L';
    }
    if (in_array($jk, ['P', 'PEREMPUAN', 'PEREM', 'WANITA', 'FEMALE'])) {
        return 'P';
    }
    
    return false;
}

function checkDuplicate($nisn, $username) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nisn = ?");
    $stmt->execute([$nisn]);
    if ($stmt->fetchColumn() > 0) return true;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetchColumn() > 0;
}

function insertStudent($nisn, $nama, $username, $kelas, $jk) {
    global $pdo;
    
    try {
        // Insert user
        $password = password_hash('siswa123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'siswa', ?)");
        $stmt->execute([$username, $password, $nama]);
        $user_id = $pdo->lastInsertId();
        
        // Insert siswa
        $stmt = $pdo->prepare("INSERT INTO siswa (user_id, nisn, nama, kelas, jenis_kelamin) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $nisn, $nama, $kelas, $jk]);
        
        return true;
        
    } catch (Exception $e) {
        // Rollback user jika gagal
        if (isset($user_id)) {
            try {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            } catch (Exception $e) {}
        }
        return false;
    }
}
?>