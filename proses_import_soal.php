<?php
// proses_import_soal.php - Versi dengan support Multi Kelas (comma separated)
require_once 'config.php';

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
        // Baca CSV dengan berbagai delimiter
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            $first_line = fgets($handle);
            fclose($handle);
            
            // Deteksi delimiter
            $delimiter = ';';
            if (strpos($first_line, ',') !== false) {
                $delimiter = ',';
            } elseif (strpos($first_line, "\t") !== false) {
                $delimiter = "\t";
            }
            
            // Baca file dengan delimiter yang tepat
            if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    // Bersihkan data dari BOM dan whitespace
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
    } else {
        // Untuk Excel, gunakan library tambahan jika ada, fallback ke CSV
        throw new Exception('Untuk sementara, gunakan format CSV. Pastikan file CSV menggunakan delimiter koma (,) atau titik koma (;)');
    }

    // Validasi data
    if (count($rows) < 2) {
        throw new Exception('File harus memiliki data (minimal 1 baris data setelah header)');
    }

    // Proses header - case insensitive
    $headers = array_map(function($header) {
        return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header)));
    }, $rows[0]);
    
    // Cari index kolom dengan berbagai kemungkinan nama
    $findColumnIndex = function($possible_names) use ($headers) {
        foreach ($possible_names as $name) {
            $index = array_search($name, $headers);
            if ($index !== false) return $index;
        }
        return false;
    };

    $col_jenis_soal = $findColumnIndex(['jenis_soal', 'jenis', 'type']);
    $col_mapel_id = $findColumnIndex(['mapel_id', 'mapel', 'mata pelajaran id', 'id mapel']);
    $col_kelas = $findColumnIndex(['kelas', 'class', 'tingkat']);
    $col_pertanyaan = $findColumnIndex(['pertanyaan', 'soal', 'question', 'teks']);
    $col_opsi_a = $findColumnIndex(['opsi_a', 'pilihan_a', 'a', 'option_a']);
    $col_opsi_b = $findColumnIndex(['opsi_b', 'pilihan_b', 'b', 'option_b']);
    $col_opsi_c = $findColumnIndex(['opsi_c', 'pilihan_c', 'c', 'option_c']);
    $col_opsi_d = $findColumnIndex(['opsi_d', 'pilihan_d', 'd', 'option_d']);
    $col_opsi_e = $findColumnIndex(['opsi_e', 'pilihan_e', 'e', 'option_e']);
    $col_jawaban = $findColumnIndex(['jawaban_benar', 'jawaban', 'kunci', 'answer', 'key']);
    $col_skor = $findColumnIndex(['skor', 'score', 'bobot', 'point']);

    // Validasi header required (minimal mapel, pertanyaan, opsi_a, jawaban)
    $missing_headers = [];
    if ($col_mapel_id === false) $missing_headers[] = 'mapel_id';
    if ($col_pertanyaan === false) $missing_headers[] = 'pertanyaan';
    if ($col_opsi_a === false) $missing_headers[] = 'opsi_a';
    if ($col_jawaban === false) $missing_headers[] = 'jawaban_benar';

    if (!empty($missing_headers)) {
        throw new Exception('Header tidak lengkap. Kolom yang wajib: ' . implode(', ', $missing_headers) . '. Header yang ditemukan: ' . implode(', ', $headers));
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

            // Ambil data dengan default values
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
                $error_count++;
                $errors[] = "Baris $line: mapel_id tidak boleh kosong";
                continue;
            }

            if (empty($pertanyaan)) {
                $error_count++;
                $errors[] = "Baris $line: pertanyaan tidak boleh kosong";
                continue;
            }

            if (empty($opsi_a)) {
                $error_count++;
                $errors[] = "Baris $line: opsi_a tidak boleh kosong";
                continue;
            }

            if (empty($jawaban_benar) && $jenis_soal != 'essay' && $jenis_soal != 'menjodohkan') {
                $error_count++;
                $errors[] = "Baris $line: jawaban_benar tidak boleh kosong untuk jenis soal $jenis_soal";
                continue;
            }

            // Validasi mapel_id
            if (!is_numeric($mapel_id)) {
                $error_count++;
                $errors[] = "Baris $line: mapel_id harus angka ('$mapel_id')";
                continue;
            }

            // Validasi jawaban benar untuk pilihan ganda biasa
            if ($jenis_soal == 'pilihan_ganda') {
                $jawaban_benar = strtolower($jawaban_benar);
                if (!in_array($jawaban_benar, ['a', 'b', 'c', 'd', 'e'])) {
                    $error_count++;
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
                $error_count++;
                $errors[] = "Baris $line: mapel_id $mapel_id tidak ditemukan di database";
                continue;
            }

            // Format kelas (bisa multi kelas dipisah koma)
            // Validasi format kelas (opsional, bisa dikosongkan)
            if (empty($kelas)) {
                $kelas = '';
            }

            // Cek duplikat pertanyaan (jika opsi diaktifkan)
            if ($skip_duplicate) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE pertanyaan = ? AND mapel_id = ?");
                $stmt->execute([$pertanyaan, $mapel_id]);
                if ($stmt->fetchColumn() > 0) {
                    $duplicate_count++;
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
                    $_SESSION['user_id'],
                    $jenis_soal
                ]);

                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = "Baris $line: Gagal menyimpan ke database";
                }
                
            } catch (PDOException $e) {
                $error_count++;
                $errors[] = "Baris $line: Database error - " . $e->getMessage();
            }
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Gagal memproses data: ' . $e->getMessage());
    }

    // Siapkan pesan hasil
    $message = "Import selesai: $success_count soal berhasil diimport";
    $message_type = 'success';

    if ($duplicate_count > 0) {
        $message .= ", $duplicate_count soal duplikat dilewati";
        $message_type = 'info';
    }

    if ($error_count > 0) {
        $message .= ", $error_count gagal";
        $message_type = 'warning';
        
        $_SESSION['import_errors'] = array_slice($errors, 0, 10);
    }

    if ($success_count == 0) {
        $message_type = 'danger';
        if ($error_count > 0) {
            $message = "Tidak ada soal yang berhasil diimport. Silakan periksa file dan pastikan format sesuai.";
        } else if ($duplicate_count > 0) {
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

header("Location: kelola_soal.php");
exit();
?>