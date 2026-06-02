<?php
// proses_import_soal_guru.php - Import soal (Pilihan Ganda, PG Kompleks, Essay, dll) untuk guru
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header('Location: login.php');
    exit();
}

$guru_id = $_SESSION['user_id'];

// Dapatkan guru_id dari tabel guru
$stmt_guru = $pdo->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt_guru->execute([$guru_id]);
$guru_data = $stmt_guru->fetch(PDO::FETCH_ASSOC);
$guru_table_id = $guru_data['id'] ?? 0;

set_time_limit(300);

try {
    // Validasi request
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        throw new Exception('Metode request tidak valid.');
    }

    if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] != UPLOAD_ERR_OK) {
        throw new Exception('Silakan pilih file CSV yang valid.');
    }

    $file = $_FILES['file_excel'];
    $skip_duplicate = isset($_POST['skip_duplicate']);

    // Validasi file
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_extension != 'csv') {
        throw new Exception('Format file harus .csv (Comma Separated Values)');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Ukuran file maksimal 5MB');
    }

    // Baca file CSV dengan delimiter titik koma (;) atau koma (,)
    $rows = [];
    $delimiter = ';'; // default delimiter
    
    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
        $first_line = fgets($handle);
        fclose($handle);
        
        // Deteksi delimiter (prioritas titik koma)
        if (strpos($first_line, ';') !== false) {
            $delimiter = ';';
        } elseif (strpos($first_line, ',') !== false) {
            $delimiter = ',';
        } elseif (strpos($first_line, "\t") !== false) {
            $delimiter = "\t";
        }
        
        // Baca file dengan delimiter yang tepat
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                $cleaned_data = array_map(function($item) {
                    $item = preg_replace('/^\xEF\xBB\xBF/', '', $item);
                    return trim($item);
                }, $data);
                
                if (!empty(array_filter($cleaned_data))) {
                    $rows[] = $cleaned_data;
                }
            }
            fclose($handle);
        }
    }

    if (count($rows) < 2) {
        throw new Exception('File harus memiliki data (minimal 1 baris data setelah header)');
    }

    // Proses header
    $headers = array_map(function($header) {
        return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header)));
    }, $rows[0]);
    
    // Cari index kolom
    $findColumn = function($names) use ($headers) {
        foreach ($names as $name) {
            $index = array_search($name, $headers);
            if ($index !== false) return $index;
        }
        return false;
    };

    $col_jenis_soal = $findColumn(['jenis_soal', 'jenis', 'type']);
    $col_mapel_id = $findColumn(['mapel_id', 'mapel', 'mata pelajaran id', 'id mapel']);
    $col_kelas = $findColumn(['kelas', 'class', 'tingkat']);
    $col_pertanyaan = $findColumn(['pertanyaan', 'soal', 'question', 'teks']);
    $col_opsi_a = $findColumn(['opsi_a', 'pilihan_a', 'a', 'option_a']);
    $col_opsi_b = $findColumn(['opsi_b', 'pilihan_b', 'b', 'option_b']);
    $col_opsi_c = $findColumn(['opsi_c', 'pilihan_c', 'c', 'option_c']);
    $col_opsi_d = $findColumn(['opsi_d', 'pilihan_d', 'd', 'option_d']);
    $col_opsi_e = $findColumn(['opsi_e', 'pilihan_e', 'e', 'option_e']);
    $col_jawaban = $findColumn(['jawaban_benar', 'jawaban', 'kunci', 'answer', 'key']);
    $col_skor = $findColumn(['skor', 'score', 'bobot', 'point']);

    // Validasi header required (minimal mapel, pertanyaan, opsi_a, jawaban)
    $missing_headers = [];
    if ($col_mapel_id === false) $missing_headers[] = 'mapel_id';
    if ($col_pertanyaan === false) $missing_headers[] = 'pertanyaan';
    if ($col_opsi_a === false) $missing_headers[] = 'opsi_a';
    if ($col_jawaban === false) $missing_headers[] = 'jawaban_benar';

    if (!empty($missing_headers)) {
        throw new Exception('Header tidak lengkap. Kolom yang wajib: ' . implode(', ', $missing_headers) . '. Header yang ditemukan: ' . implode(', ', $headers));
    }

    // Ambil daftar mapel yang diampu guru
    $stmt_mapel = $pdo->prepare("SELECT DISTINCT mp.id FROM mata_pelajaran mp JOIN guru_mapel gm ON mp.id = gm.mapel_id WHERE gm.guru_id = ?");
    $stmt_mapel->execute([$guru_table_id]);
    $mapel_diampu = $stmt_mapel->fetchAll(PDO::FETCH_COLUMN);
    
    // Ambil daftar kelas yang diampu guru
    $stmt_kelas = $pdo->prepare("SELECT DISTINCT kelas FROM guru_kelas WHERE guru_id = ?");
    $stmt_kelas->execute([$guru_table_id]);
    $kelas_diampu = $stmt_kelas->fetchAll(PDO::FETCH_COLUMN);

    $success = 0;
    $error = 0;
    $duplicate = 0;
    $errors = [];

    $pdo->beginTransaction();

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $line = $i + 1;

        // Skip baris kosong
        if (empty(array_filter($row))) {
            continue;
        }

        // Ambil data
        $jenis_soal = isset($row[$col_jenis_soal]) ? strtolower(trim($row[$col_jenis_soal])) : 'pilihan_ganda';
        $mapel_id = isset($row[$col_mapel_id]) ? trim($row[$col_mapel_id]) : '';
        $kelas = isset($row[$col_kelas]) ? trim($row[$col_kelas]) : '';
        $pertanyaan = isset($row[$col_pertanyaan]) ? trim($row[$col_pertanyaan]) : '';
        $opsi_a = isset($row[$col_opsi_a]) ? trim($row[$col_opsi_a]) : '';
        $opsi_b = isset($row[$col_opsi_b]) ? trim($row[$col_opsi_b]) : '';
        $opsi_c = isset($row[$col_opsi_c]) ? trim($row[$col_opsi_c]) : '';
        $opsi_d = isset($row[$col_opsi_d]) ? trim($row[$col_opsi_d]) : '';
        $opsi_e = isset($row[$col_opsi_e]) ? trim($row[$col_opsi_e]) : '';
        $jawaban_benar = isset($row[$col_jawaban]) ? strtolower(trim($row[$col_jawaban])) : '';
        $skor = isset($row[$col_skor]) ? intval(trim($row[$col_skor])) : 10;
        
        // Untuk pilihan ganda kompleks, jawaban bisa berupa "a,b,c" atau "a, b, c"
        $jawaban_kompleks = '';
        $skor_per_jawaban = 0;
        
        if ($jenis_soal == 'pilihan_ganda_kompleks' && strpos($jawaban_benar, ',') !== false) {
            $jawaban_array = array_map('trim', explode(',', $jawaban_benar));
            $jawaban_kompleks = json_encode($jawaban_array);
            $skor_per_jawaban = 5; // default skor per jawaban
            $jawaban_benar = 'a'; // placeholder
        }

        // Validasi data required
        if (empty($mapel_id)) {
            $error++;
            $errors[] = "Baris $line: mapel_id tidak boleh kosong";
            continue;
        }

        if (empty($pertanyaan)) {
            $error++;
            $errors[] = "Baris $line: pertanyaan tidak boleh kosong";
            continue;
        }

        if (empty($opsi_a)) {
            $error++;
            $errors[] = "Baris $line: opsi_a tidak boleh kosong";
            continue;
        }

        if (empty($jawaban_benar) && $jenis_soal != 'essay' && $jenis_soal != 'menjodohkan') {
            $error++;
            $errors[] = "Baris $line: jawaban_benar tidak boleh kosong untuk jenis soal $jenis_soal";
            continue;
        }

        // Validasi mapel_id
        if (!is_numeric($mapel_id)) {
            $error++;
            $errors[] = "Baris $line: mapel_id harus angka ('$mapel_id')";
            continue;
        }

        // Validasi guru mengampu mapel
        if (!empty($mapel_diampu) && !in_array($mapel_id, $mapel_diampu)) {
            $error++;
            $errors[] = "Baris $line: Anda tidak mengampu mapel ID $mapel_id";
            continue;
        }

        // Validasi kelas (multi kelas dipisah koma)
        if (empty($kelas)) {
            $error++;
            $errors[] = "Baris $line: kelas tidak boleh kosong";
            continue;
        }
        
        // Cek apakah guru mengampu setidaknya satu kelas yang dipilih
        $kelas_array = explode(',', $kelas);
        $kelas_valid = false;
        if (empty($kelas_diampu)) {
            $kelas_valid = true;
        } else {
            foreach ($kelas_array as $k) {
                $k = trim($k);
                if (in_array($k, $kelas_diampu)) {
                    $kelas_valid = true;
                    break;
                }
            }
        }
        
        if (!$kelas_valid) {
            $error++;
            $errors[] = "Baris $line: Anda tidak mengampu kelas $kelas";
            continue;
        }

        // Validasi jawaban benar untuk pilihan ganda biasa
        if ($jenis_soal == 'pilihan_ganda') {
            $jawaban_benar = strtolower($jawaban_benar);
            if (!in_array($jawaban_benar, ['a', 'b', 'c', 'd', 'e'])) {
                $error++;
                $errors[] = "Baris $line: jawaban_benar harus a, b, c, d, atau e ('$jawaban_benar')";
                continue;
            }
        }

        // Validasi skor
        if ($skor < 1 || $skor > 100) {
            $skor = 10;
        }

        // Cek apakah mapel_id ada di database
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mata_pelajaran WHERE id = ?");
        $stmt->execute([$mapel_id]);
        if ($stmt->fetchColumn() == 0) {
            $error++;
            $errors[] = "Baris $line: mapel_id $mapel_id tidak ditemukan di database";
            continue;
        }

        // Cek duplikat pertanyaan (jika opsi diaktifkan)
        if ($skip_duplicate) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE pertanyaan = ? AND mapel_id = ?");
            $stmt->execute([$pertanyaan, $mapel_id]);
            if ($stmt->fetchColumn() > 0) {
                $duplicate++;
                continue;
            }
        }

        // Insert data ke database
        try {
            $sql = "INSERT INTO soal (mapel_id, kelas, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, jawaban_benar, jawaban_kompleks, skor_per_jawaban, skor, created_by, jenis_soal, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $mapel_id, 
                $kelas, 
                $pertanyaan, 
                $opsi_a, 
                $opsi_b ?: '', 
                $opsi_c ?: '', 
                $opsi_d ?: '', 
                $opsi_e ?: '', 
                $jawaban_benar, 
                $jawaban_kompleks,
                $skor_per_jawaban,
                $skor, 
                $guru_table_id,
                $jenis_soal
            ]);

            if ($result) {
                $success++;
            } else {
                $error++;
                $errors[] = "Baris $line: Gagal menyimpan ke database";
            }
            
        } catch (PDOException $e) {
            $error++;
            $errors[] = "Baris $line: Database error - " . $e->getMessage();
        }
    }

    $pdo->commit();

    // Siapkan pesan hasil
    $message = "Import selesai: $success soal berhasil diimport";
    $message_type = 'success';

    if ($duplicate > 0) {
        $message .= ", $duplicate soal duplikat dilewati";
        $message_type = 'info';
    }

    if ($error > 0) {
        $message .= ", $error gagal";
        $message_type = 'warning';
        
        $_SESSION['import_errors'] = array_slice($errors, 0, 10);
    }

    if ($success == 0) {
        $message_type = 'danger';
        if ($error > 0) {
            $message = "Tidak ada soal yang berhasil diimport. Silakan periksa file dan pastikan format sesuai.";
        } else if ($duplicate > 0) {
            $message = "Semua soal sudah ada di database (duplikat).";
        }
    }

    $_SESSION['flash'] = [
        'type' => $message_type, 
        'message' => $message
    ];

} catch (Exception $e) {
    $_SESSION['flash'] = [
        'type' => 'danger', 
        'message' => 'Error: ' . $e->getMessage()
    ];
}

header("Location: kelola_soal_guru.php");
exit();
?>