<?php
// config.php - File konfigurasi utama dengan sinkronisasi waktu WIB
ob_start();
session_start();

// ===================================================
// 1. KONFIGURASI TIMEZONE - WIB (Waktu Indonesia Barat)
// ===================================================

// Set timezone PHP ke WIB
date_default_timezone_set('Asia/Jakarta');

// Untuk hosting, gunakan error reporting yang aman
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===================================================
// 2. KONFIGURASI DATABASE
// ===================================================

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ujianonline';

// Base URL untuk hosting
define('BASE_URL', 'http://ujianonlinedemo.my.id');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');
define('MAX_FILE_SIZE_SOAL', 5 * 1024 * 1024); // 5MB untuk gambar soal

// ===================================================
// 3. KONEKSI DATABASE DENGAN TIMEZONE SINKRON WIB
// ===================================================

try {
    // GUNAKAN CHARSET LATIN1 UNTUK MENYESUAIKAN DATABASE
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=latin1", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // SET COLLATION UNTUK MENYESUAIKAN DATABASE
    $pdo->exec("SET NAMES latin1 COLLATE latin1_swedish_ci");
    
    // ===================================================
    // PENTING: SET TIMEZONE DATABASE KE WIB (+7:00)
    // Ini yang akan menyinkronkan PHP dan MySQL
    // ===================================================
    $pdo->exec("SET time_zone = '+07:00'");
    $pdo->exec("SET @@session.time_zone = '+07:00'");
    
} catch(PDOException $e) {
    // Error handling yang aman untuk hosting
    error_log("[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage());
    die("<!DOCTYPE html>
        <html>
        <head>
            <title>System Maintenance</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error-box { 
                    background: #f8d7da; 
                    border: 1px solid #f5c6cb; 
                    padding: 20px; 
                    border-radius: 5px;
                    max-width: 500px;
                    margin: 0 auto;
                }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h2>⚠️ System Sedang Dalam Perbaikan</h2>
                <p>Mohon maaf, sistem ujian online sedang mengalami perbaikan.</p>
                <p>Silakan coba lagi dalam beberapa saat.</p>
                <p><small>Error: Database connection error</small></p>
            </div>
        </body>
        </html>");
}

// ===================================================
// 4. FUNGSI BARU UNTUK MEDIA (GAMBAR & VIDEO)
// ===================================================

/**
 * Upload gambar untuk soal atau opsi
 * @param array $file File $_FILES
 * @param string $type Jenis upload (soal, opsi_a, opsi_b, opsi_c, opsi_d)
 * @return string|null Nama file atau null jika gagal
 */
function uploadGambarSoal($file, $type = 'soal') {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = MAX_FILE_SIZE_SOAL;
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validasi ekstensi
    if (!in_array($file_ext, $allowed_extensions)) {
        flashMessage('danger', 'Format file gambar tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.');
        return null;
    }
    
    // Validasi ukuran
    if ($file_size > $max_size) {
        flashMessage('danger', 'Ukuran file gambar terlalu besar. Maksimal 5MB.');
        return null;
    }
    
    // Generate nama file unik
    $new_file_name = $type . '_' . time() . '_' . uniqid() . '.' . $file_ext;
    $upload_path = UPLOAD_PATH . 'soal_images/' . $new_file_name;
    
    // Buat folder jika belum ada
    if (!file_exists(UPLOAD_PATH . 'soal_images/')) {
        mkdir(UPLOAD_PATH . 'soal_images/', 0777, true);
    }
    
    // Pindahkan file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        return 'assets/uploads/soal_images/' . $new_file_name;
    }
    
    return null;
}

/**
 * Hapus gambar lama jika ada
 * @param string $file_path Path file gambar
 */
function hapusGambarLama($file_path) {
    if ($file_path && file_exists($file_path) && !str_contains($file_path, 'default')) {
        @unlink($file_path);
    }
}

/**
 * Validasi URL YouTube
 * @param string $url URL YouTube
 * @return bool|string Video ID jika valid, false jika tidak
 */
function validasiYouTubeUrl($url) {
    if (empty($url)) return true; // Boleh kosong
    
    // Pattern untuk berbagai format YouTube URL
    $patterns = [
        '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/user\/.*#.*\/[a-zA-Z0-9_-]+\/([a-zA-Z0-9_-]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1]; // Return video ID
        }
    }
    
    return false;
}

/**
 * Cek apakah URL adalah video URL
 * @param string $url URL untuk dicek
 * @return bool True jika URL video
 */
function isVideoUrl($url) {
    if (empty($url)) return false;
    
    $video_patterns = [
        '/youtube\.com/',
        '/youtu\.be/',
        '/vimeo\.com/',
        '/\.mp4$/',
        '/\.webm$/',
        '/\.ogg$/'
    ];
    
    foreach ($video_patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Tampilkan media (gambar atau video)
 * @param string $url URL media
 * @param string $type Jenis media (gambar, video, auto)
 * @param string $class Kelas CSS tambahan
 * @param string $width Lebar (default: 100%)
 * @param string $height Tinggi (default: 300px)
 * @return string HTML media
 */
function tampilkanMedia($url, $type = 'auto', $class = '', $width = '100%', $height = '300') {
    if (empty($url)) return '';
    
    // Auto detect type
    if ($type === 'auto') {
        $type = isVideoUrl($url) ? 'video' : 'image';
    }
    
    if ($type === 'image') {
        return '<div class="media-container mb-3 ' . $class . '">
                    <img src="' . htmlspecialchars($url) . '" 
                         class="img-fluid rounded border" 
                         alt="Media soal"
                         style="max-height: ' . $height . 'px; width: ' . $width . ';">
                </div>';
    } elseif ($type === 'video') {
        $video_id = validasiYouTubeUrl($url);
        if ($video_id) {
            return '<div class="media-container mb-3 ' . $class . '">
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.youtube.com/embed/' . $video_id . '" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen>
                            </iframe>
                        </div>
                    </div>';
        } else {
            // Untuk video non-YouTube (MP4, WebM, etc)
            return '<div class="media-container mb-3 ' . $class . '">
                        <div class="ratio ratio-16x9">
                            <video controls style="width: ' . $width . '; max-height: ' . $height . 'px;">
                                <source src="' . htmlspecialchars($url) . '" type="video/mp4">
                                Browser Anda tidak mendukung tag video.
                            </video>
                        </div>
                    </div>';
        }
    }
    
    return '';
}

/**
 * Cek apakah file adalah gambar yang valid
 * @param array $file File dari $_FILES
 * @return bool True jika valid
 */
function isImageFile($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Cek menggunakan finfo
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        return in_array($mime, $allowed_mimes);
    }
    
    // Fallback: cek extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

/**
 * Simpan soal dengan media support
 * @param array $data Data soal
 * @param array $files File upload
 * @param bool $is_update Apakah update atau insert
 * @return array Hasil penyimpanan
 */
function simpanSoalDenganMedia($data, $files = [], $is_update = false) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Handle upload gambar soal
        $gambar_soal = null;
        if (!empty($files['gambar_soal']['name']) && isImageFile($files['gambar_soal'])) {
            $gambar_soal = uploadGambarSoal($files['gambar_soal'], 'soal');
        }
        
        // Handle upload gambar opsi
        $opsi_gambar = [];
        $opsi_fields = ['opsi_a', 'opsi_b', 'opsi_c', 'opsi_d'];
        foreach ($opsi_fields as $opsi) {
            $field_name = $opsi . '_gambar';
            if (!empty($files[$field_name]['name']) && isImageFile($files[$field_name])) {
                $opsi_gambar[$field_name] = uploadGambarSoal($files[$field_name], $opsi);
            } else {
                $opsi_gambar[$field_name] = null;
            }
        }
        
        // Validasi video URL
        $video_soal = trim($data['video_soal'] ?? '');
        if (!empty($video_soal)) {
            if (!filter_var($video_soal, FILTER_VALIDATE_URL)) {
                throw new Exception('URL video tidak valid');
            }
            if (strpos($video_soal, 'youtube') !== false && !validasiYouTubeUrl($video_soal)) {
                throw new Exception('URL YouTube tidak valid');
            }
        }
        
        if ($is_update && isset($data['id'])) {
            // Update soal yang ada
            $sql = "UPDATE soal SET 
                    mapel_id = :mapel_id,
                    kelas = :kelas,
                    pertanyaan = :pertanyaan,
                    gambar_soal = COALESCE(:gambar_soal, gambar_soal),
                    video_soal = :video_soal,
                    opsi_a = :opsi_a,
                    opsi_a_gambar = COALESCE(:opsi_a_gambar, opsi_a_gambar),
                    opsi_b = :opsi_b,
                    opsi_b_gambar = COALESCE(:opsi_b_gambar, opsi_b_gambar),
                    opsi_c = :opsi_c,
                    opsi_c_gambar = COALESCE(:opsi_c_gambar, opsi_c_gambar),
                    opsi_d = :opsi_d,
                    opsi_d_gambar = COALESCE(:opsi_d_gambar, opsi_d_gambar),
                    jawaban_benar = :jawaban_benar,
                    skor = :skor
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $params = [
                ':id' => $data['id'],
                ':mapel_id' => $data['mapel_id'],
                ':kelas' => $data['kelas'],
                ':pertanyaan' => $data['pertanyaan'],
                ':gambar_soal' => $gambar_soal,
                ':video_soal' => $video_soal,
                ':opsi_a' => $data['opsi_a'],
                ':opsi_a_gambar' => $opsi_gambar['opsi_a_gambar'],
                ':opsi_b' => $data['opsi_b'],
                ':opsi_b_gambar' => $opsi_gambar['opsi_b_gambar'],
                ':opsi_c' => $data['opsi_c'],
                ':opsi_c_gambar' => $opsi_gambar['opsi_c_gambar'],
                ':opsi_d' => $data['opsi_d'],
                ':opsi_d_gambar' => $opsi_gambar['opsi_d_gambar'],
                ':jawaban_benar' => $data['jawaban_benar'],
                ':skor' => $data['skor']
            ];
        } else {
            // Insert soal baru
            $sql = "INSERT INTO soal (
                    mapel_id, kelas, pertanyaan, gambar_soal, video_soal,
                    opsi_a, opsi_a_gambar, opsi_b, opsi_b_gambar,
                    opsi_c, opsi_c_gambar, opsi_d, opsi_d_gambar,
                    jawaban_benar, skor, created_by
                ) VALUES (
                    :mapel_id, :kelas, :pertanyaan, :gambar_soal, :video_soal,
                    :opsi_a, :opsi_a_gambar, :opsi_b, :opsi_b_gambar,
                    :opsi_c, :opsi_c_gambar, :opsi_d, :opsi_d_gambar,
                    :jawaban_benar, :skor, :created_by
                )";
            
            $stmt = $pdo->prepare($sql);
            $params = [
                ':mapel_id' => $data['mapel_id'],
                ':kelas' => $data['kelas'],
                ':pertanyaan' => $data['pertanyaan'],
                ':gambar_soal' => $gambar_soal,
                ':video_soal' => $video_soal,
                ':opsi_a' => $data['opsi_a'],
                ':opsi_a_gambar' => $opsi_gambar['opsi_a_gambar'],
                ':opsi_b' => $data['opsi_b'],
                ':opsi_b_gambar' => $opsi_gambar['opsi_b_gambar'],
                ':opsi_c' => $data['opsi_c'],
                ':opsi_c_gambar' => $opsi_gambar['opsi_c_gambar'],
                ':opsi_d' => $data['opsi_d'],
                ':opsi_d_gambar' => $opsi_gambar['opsi_d_gambar'],
                ':jawaban_benar' => $data['jawaban_benar'],
                ':skor' => $data['skor'],
                ':created_by' => $_SESSION['user_id'] ?? 0
            ];
        }
        
        $stmt->execute($params);
        $soal_id = $is_update ? $data['id'] : $pdo->lastInsertId();
        
        $pdo->commit();
        
        return [
            'success' => true,
            'soal_id' => $soal_id,
            'message' => 'Soal berhasil disimpan'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logWaktu("Error simpan soal dengan media", [
            'data' => $data,
            'error' => $e->getMessage()
        ]);
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Ambil detail soal dengan media
 * @param int $soal_id ID soal
 * @return array|null Detail soal atau null jika tidak ditemukan
 */
function getDetailSoalMedia($soal_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT 
                s.*,
                mp.nama_mapel,
                mp.kode_mapel,
                g.nama as nama_guru
            FROM soal s
            LEFT JOIN mata_pelajaran mp ON s.mapel_id = mp.id
            LEFT JOIN guru g ON s.created_by = g.id
            WHERE s.id = ?");
        $stmt->execute([$soal_id]);
        $soal = $stmt->fetch();
        
        if ($soal) {
            $soal['has_gambar_soal'] = !empty($soal['gambar_soal']);
            $soal['has_video_soal'] = !empty($soal['video_soal']);
            $soal['has_opsi_gambar'] = !empty($soal['opsi_a_gambar']) || !empty($soal['opsi_b_gambar']) || 
                                      !empty($soal['opsi_c_gambar']) || !empty($soal['opsi_d_gambar']);
            $soal['has_media'] = $soal['has_gambar_soal'] || $soal['has_video_soal'] || $soal['has_opsi_gambar'];
        }
        
        return $soal;
        
    } catch (Exception $e) {
        logWaktu("Error get detail soal media", ['soal_id' => $soal_id, 'error' => $e->getMessage()]);
        return null;
    }
}

/**
 * Hapus soal beserta file medianya
 * @param int $soal_id ID soal
 * @return bool True jika berhasil
 */
function hapusSoalDenganMedia($soal_id) {
    global $pdo;
    
    try {
        // Ambil data soal untuk menghapus file
        $stmt = $pdo->prepare("SELECT 
                gambar_soal, 
                opsi_a_gambar, opsi_b_gambar, opsi_c_gambar, opsi_d_gambar 
            FROM soal WHERE id = ?");
        $stmt->execute([$soal_id]);
        $soal = $stmt->fetch();
        
        if ($soal) {
            // Hapus file gambar
            $files_to_delete = [
                $soal['gambar_soal'],
                $soal['opsi_a_gambar'],
                $soal['opsi_b_gambar'],
                $soal['opsi_c_gambar'],
                $soal['opsi_d_gambar']
            ];
            
            foreach ($files_to_delete as $file) {
                hapusGambarLama($file);
            }
        }
        
        // Hapus dari database
        $stmt = $pdo->prepare("DELETE FROM soal WHERE id = ?");
        return $stmt->execute([$soal_id]);
        
    } catch (Exception $e) {
        logWaktu("Error hapus soal dengan media", ['soal_id' => $soal_id, 'error' => $e->getMessage()]);
        return false;
    }
}

// ===================================================
// 5. FUNGSI HELPER WAKTU YANG KONSISTEN (SEMUA WIB)
// ===================================================

/**
 * Mendapatkan waktu sekarang dalam format database yang konsisten (WIB)
 * @return string Waktu sekarang dalam format Y-m-d H:i:s (WIB)
 */
function waktuSekarang() {
    return date('Y-m-d H:i:s');
}

/**
 * Mendapatkan timestamp sekarang (WIB)
 * @return int Timestamp sekarang (WIB)
 */
function timestampSekarang() {
    return time();
}

/**
 * Format waktu untuk tampilan (WIB)
 * @param string $waktu Waktu dalam format database (WIB)
 * @return string Waktu yang diformat (d/m/Y H:i:s) WIB
 */
function formatWaktu($waktu) {
    if (empty($waktu)) return '-';
    return date('d/m/Y H:i:s', strtotime($waktu));
}

/**
 * Format waktu singkat untuk tampilan (WIB)
 * @param string $waktu Waktu dalam format database (WIB)
 * @return string Waktu yang diformat singkat (d/m/Y H:i) WIB
 */
function formatWaktuSingkat($waktu) {
    if (empty($waktu)) return '-';
    return date('d/m/Y H:i', strtotime($waktu));
}

/**
 * Format waktu untuk input form (WIB)
 * @param string $waktu Waktu dalam format database (WIB)
 * @return string Waktu yang diformat untuk input datetime-local
 */
function formatWaktuUntukInput($waktu) {
    if (empty($waktu)) return '';
    return date('Y-m-d\TH:i', strtotime($waktu));
}

/**
 * Cek apakah waktu sekarang dalam rentang ujian (WIB)
 * @param string $waktu_mulai Waktu mulai ujian (WIB)
 * @param string $waktu_selesai Waktu selesai ujian (WIB)
 * @param int $toleransi_menit Toleransi dalam menit (default: 15)
 * @return array Informasi lengkap status waktu
 */
function cekStatusWaktuUjian($waktu_mulai, $waktu_selesai, $toleransi_menit = 15) {
    $sekarang = timestampSekarang();
    $mulai_ts = strtotime($waktu_mulai);
    $selesai_ts = strtotime($waktu_selesai);
    $selesai_dengan_toleransi = $selesai_ts + ($toleransi_menit * 60);
    
    // Hitung selisih waktu
    $sisa_dari_selesai = $selesai_ts - $sekarang;
    $sisa_dengan_toleransi = $selesai_dengan_toleransi - $sekarang;
    
    // Status utama
    $status = 'unknown';
    if ($sekarang < $mulai_ts) {
        $status = 'belum_mulai';
    } elseif ($sekarang >= $mulai_ts && $sekarang <= $selesai_dengan_toleransi) {
        $status = 'berlangsung';
    } else {
        $status = 'selesai';
    }
    
    return [
        'status' => $status,
        'sekarang' => $sekarang,
        'mulai' => $mulai_ts,
        'selesai' => $selesai_ts,
        'selesai_toleransi' => $selesai_dengan_toleransi,
        'belum_mulai' => $sekarang < $mulai_ts,
        'berlangsung' => $sekarang >= $mulai_ts && $sekarang <= $selesai_dengan_toleransi,
        'selesai_strict' => $sekarang > $selesai_ts,
        'selesai_dengan_toleransi' => $sekarang > $selesai_dengan_toleransi,
        'sisa_detik' => max(0, $sisa_dengan_toleransi),
        'sisa_menit' => floor(max(0, $sisa_dengan_toleransi) / 60),
        'lewat_detik' => max(0, $sekarang - $selesai_ts),
        'lewat_menit' => floor(max(0, $sekarang - $selesai_ts) / 60),
        'mulai_formatted' => formatWaktuSingkat($waktu_mulai),
        'selesai_formatted' => formatWaktuSingkat($waktu_selesai),
        'sekarang_formatted' => date('d/m/Y H:i:s')
    ];
}

/**
 * Cek waktu pengerjaan pribadi siswa (WIB)
 * @param string $waktu_mulai_pengerjaan Waktu mulai pengerjaan siswa (WIB)
 * @param int $durasi_menit Durasi ujian dalam menit
 * @return array Informasi waktu pengerjaan
 */
function cekWaktuPengerjaan($waktu_mulai_pengerjaan, $durasi_menit) {
    $sekarang = timestampSekarang();
    $mulai_ts = strtotime($waktu_mulai_pengerjaan);
    $durasi_detik = $durasi_menit * 60;
    $selesai_pengerjaan = $mulai_ts + $durasi_detik;
    $sisa_pengerjaan = max(0, $selesai_pengerjaan - $sekarang);
    
    return [
        'mulai' => $mulai_ts,
        'selesai' => $selesai_pengerjaan,
        'sisa_detik' => $sisa_pengerjaan,
        'sisa_menit' => floor($sisa_pengerjaan / 60),
        'sudah_selesai' => $sisa_pengerjaan <= 0,
        'mulai_formatted' => formatWaktuSingkat($waktu_mulai_pengerjaan),
        'selesai_formatted' => date('d/m/Y H:i', $selesai_pengerjaan)
    ];
}

/**
 * Debug informasi waktu sistem (WIB)
 * @param string $label Label untuk debug
 * @return string HTML debug info
 */
function debugInfoWaktu($label = 'Debug Waktu') {
    global $pdo;
    
    $output = "<div style='background:#f8f9fa;border:1px solid #ddd;padding:15px;margin:10px 0;font-family:monospace;font-size:12px;'>";
    $output .= "<strong style='color:#dc3545;'>$label</strong><br>";
    $output .= "<hr style='margin:5px 0;'>";
    
    // Info PHP
    $output .= "<strong>PHP:</strong><br>";
    $output .= "&nbsp;&nbsp;Timezone: " . date_default_timezone_get() . "<br>";
    $output .= "&nbsp;&nbsp;Waktu: " . date('Y-m-d H:i:s') . "<br>";
    $output .= "&nbsp;&nbsp;Timestamp: " . time() . "<br>";
    
    try {
        // Info Database
        $stmt = $pdo->query("SELECT 
            NOW() as db_now,
            DATE_FORMAT(NOW(), '%Y-%m-d %H:%i:%s') as db_now_formatted,
            UNIX_TIMESTAMP(NOW()) as db_timestamp,
            @@system_time_zone as system_tz,
            @@time_zone as db_tz,
            @@session.time_zone as session_tz");
        
        if ($stmt) {
            $row = $stmt->fetch();
            
            $output .= "<br><strong>Database:</strong><br>";
            $output .= "&nbsp;&nbsp;Timezone System: " . $row['system_tz'] . "<br>";
            $output .= "&nbsp;&nbsp;Timezone Global: " . $row['db_tz'] . "<br>";
            $output .= "&nbsp;&nbsp;Timezone Session: " . $row['session_tz'] . "<br>";
            $output .= "&nbsp;&nbsp;Waktu Database: " . $row['db_now'] . "<br>";
            $output .= "&nbsp;&nbsp;Timestamp Database: " . $row['db_timestamp'] . "<br>";
            
            // Hitung perbedaan
            $php_time = time();
            $db_time = $row['db_timestamp'];
            $diff = abs($php_time - $db_time);
            
            $output .= "<br><strong>Sinkronisasi:</strong><br>";
            $output .= "&nbsp;&nbsp;Perbedaan: " . $diff . " detik<br>";
            
            if ($diff == 0) {
                $output .= "&nbsp;&nbsp;<span style='color:green;'>✅ Waktu PHP dan Database SINKRON (WIB)</span><br>";
            } elseif ($diff <= 2) {
                $output .= "&nbsp;&nbsp;<span style='color:orange;'>⚠ Perbedaan kecil (" . $diff . " detik)</span><br>";
            } else {
                $output .= "&nbsp;&nbsp;<span style='color:red;'>❌ Perbedaan signifikan (" . $diff . " detik)</span><br>";
            }
            
            // Cek waktu di tabel ujian
            $stmt2 = $pdo->query("SELECT 
                waktu_mulai, 
                waktu_selesai,
                DATE_FORMAT(waktu_mulai, '%Y-%m-d %H:%i:%s') as mulai_formatted,
                DATE_FORMAT(waktu_selesai, '%Y-%m-d %H:%i:%s') as selesai_formatted
                FROM ujian 
                ORDER BY id DESC LIMIT 1");
            
            if ($stmt2 && $ujian = $stmt2->fetch()) {
                $output .= "<br><strong>Contoh Ujian Terakhir:</strong><br>";
                $output .= "&nbsp;&nbsp;Mulai: " . $ujian['waktu_mulai'] . " (DB)<br>";
                $output .= "&nbsp;&nbsp;Selesai: " . $ujian['waktu_selesai'] . " (DB)<br>";
                $output .= "&nbsp;&nbsp;Formatted Mulai: " . $ujian['mulai_formatted'] . "<br>";
                $output .= "&nbsp;&nbsp;Formatted Selesai: " . $ujian['selesai_formatted'] . "<br>";
            }
        }
        
    } catch (Exception $e) {
        $output .= "<br><span style='color:red;'>Error cek database: " . $e->getMessage() . "</span>";
    }
    
    $output .= "</div>";
    
    return $output;
}

/**
 * Log waktu untuk debugging (WIB)
 * @param string $pesan Pesan log
 * @param mixed $data Data tambahan
 */
function logWaktu($pesan, $data = null) {
    $log = "[" . date('Y-m-d H:i:s') . "] " . $pesan;
    if ($data !== null) {
        $log .= " | Data: " . json_encode($data);
    }
    error_log($log);
}

/**
 * Validasi dan konversi input waktu dari form ke format database (WIB)
 * @param string $input_waktu Input dari form (datetime-local)
 * @return string Waktu dalam format database Y-m-d H:i:s (WIB)
 */
function sanitizeWaktuInput($input_waktu) {
    if (empty($input_waktu)) {
        return null;
    }
    
    // Input dari datetime-local: "2025-12-06T19:30"
    $datetime = DateTime::createFromFormat('Y-m-d\TH:i', $input_waktu, new DateTimeZone('Asia/Jakarta'));
    
    if ($datetime === false) {
        // Coba format lain jika gagal
        $datetime = new DateTime($input_waktu, new DateTimeZone('Asia/Jakarta'));
    }
    
    return $datetime->format('Y-m-d H:i:s');
}

// ===================================================
// 6. FUNGSI UTILITAS LAINNYA
// ===================================================

/**
 * Mendapatkan pengaturan sistem
 * @param string $key Kunci pengaturan
 * @return string|null Nilai pengaturan
 */
function getSetting($key) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT value FROM pengaturan WHERE nama = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['value'] : null;
    } catch (PDOException $e) {
        logWaktu("Get setting error", ['key' => $key, 'error' => $e->getMessage()]);
        return null;
    }
}

/**
 * Update pengaturan sistem
 * @param string $key Kunci pengaturan
 * @param string $value Nilai pengaturan
 * @return bool Berhasil atau tidak
 */
function updateSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO pengaturan (nama, value) VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()");
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        logWaktu("Update setting error", ['key' => $key, 'value' => $value, 'error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Redirect dengan header
 * @param string $url URL tujuan
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Set flash message
 * @param string $type Tipe alert (success, danger, warning, info)
 * @param string $message Pesan
 */
function flashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
        'time' => waktuSekarang()
    ];
}

/**
 * Tampilkan flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        $time = $_SESSION['flash']['time'] ?? '';
        
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>";
        echo "<div class='d-flex justify-content-between align-items-start'>";
        echo "<div>";
        
        // Icon berdasarkan tipe
        $icons = [
            'success' => 'fas fa-check-circle',
            'danger' => 'fas fa-exclamation-circle',
            'warning' => 'fas fa-exclamation-triangle',
            'info' => 'fas fa-info-circle'
        ];
        
        $icon = $icons[$type] ?? 'fas fa-info-circle';
        echo "<i class='$icon me-2'></i>";
        echo htmlspecialchars($message);
        echo "</div>";
        
        if ($time) {
            echo "<small class='text-muted ms-3'>" . formatWaktuSingkat($time) . "</small>";
        }
        
        echo "</div>";
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
        echo "</div>";
        
        unset($_SESSION['flash']);
    }
}

/**
 * Validasi role user
 * @param string $role Role yang diizinkan
 * @return bool True jika sesuai
 */
function validateRole($role) {
    if (!isset($_SESSION['role'])) return false;
    return $_SESSION['role'] === $role;
}

/**
 * Cek apakah user sudah login
 * @return bool True jika sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Generate token CSRF
 * @return string Token CSRF
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi token CSRF
 * @param string $token Token yang akan divalidasi
 * @return bool True jika valid
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input
 * @param mixed $data Data yang akan disanitasi
 * @return mixed Data yang sudah disanitasi
 */
function sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value);
        }
        return $data;
    }
    
    if (is_string($data)) {
        // Hapus karakter berbahaya
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Format file size
 * @param int $bytes Ukuran dalam bytes
 * @return string Ukuran yang diformat
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// ===================================================
// 7. CEK DAN PERBAIKI DATA WAKTU YANG SUDAH ADA
// ===================================================

/**
 * Fungsi untuk memperbaiki data waktu yang sudah ada di database
 * Hanya dijalankan sekali jika diperlukan
 */
function perbaikiDataWaktuUjian() {
    global $pdo;
    
    try {
        // Backup data sebelum perbaikan
        $pdo->exec("CREATE TABLE IF NOT EXISTS ujian_backup_wib LIKE ujian");
        $pdo->exec("TRUNCATE TABLE ujian_backup_wib");
        $pdo->exec("INSERT INTO ujian_backup_wib SELECT * FROM ujian");
        
        logWaktu("Backup data ujian dibuat", ['table' => 'ujian_backup_wib']);
        
        // Periksa data yang ada
        $stmt = $pdo->query("SELECT id, waktu_mulai, waktu_selesai FROM ujian");
        $ujian_list = $stmt->fetchAll();
        
        $total_diperbaiki = 0;
        foreach ($ujian_list as $ujian) {
            $id = $ujian['id'];
            $mulai = $ujian['waktu_mulai'];
            $selesai = $ujian['waktu_selesai'];
            
            // Jika waktu menunjukkan UTC (7 jam lebih awal), konversi ke WIB
            $mulai_timestamp = strtotime($mulai);
            $selesai_timestamp = strtotime($selesai);
            
            // Cek jika waktu terlihat seperti UTC (biasanya 7 jam lebih awal dari WIB)
            $jam_mulai = date('H', $mulai_timestamp);
            $jam_sekarang = date('H');
            
            if ($jam_mulai < 7) { // Jika waktu mulai sebelum jam 7 pagi, mungkin UTC
                // Konversi ke WIB (tambah 7 jam)
                $mulai_wib = date('Y-m-d H:i:s', strtotime($mulai . ' +7 hours'));
                $selesai_wib = date('Y-m-d H:i:s', strtotime($selesai . ' +7 hours'));
                
                $update_stmt = $pdo->prepare("UPDATE ujian SET waktu_mulai = ?, waktu_selesai = ? WHERE id = ?");
                $update_stmt->execute([$mulai_wib, $selesai_wib, $id]);
                $total_diperbaiki++;
                
                logWaktu("Ujian ID $id diperbaiki", [
                    'mulai_lama' => $mulai,
                    'selesai_lama' => $selesai,
                    'mulai_baru' => $mulai_wib,
                    'selesai_baru' => $selesai_wib
                ]);
            }
        }
        
        return [
            'success' => true,
            'total_diperbaiki' => $total_diperbaiki,
            'message' => "Perbaikan data waktu selesai. $total_diperbaiki data diperbaiki."
        ];
        
    } catch (Exception $e) {
        logWaktu("Error perbaiki data waktu", ['error' => $e->getMessage()]);
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ===================================================
// 8. FUNGSI HITUNG NILAI UJIAN - DIPERBAIKI!
// ===================================================

/**
 * Hitung nilai ujian otomatis berdasarkan TOTAL SOAL UJIAN dengan skor per soal
 * PERBAIKAN: Sekarang menghitung dengan benar berdasarkan jumlah soal yang benar
 * @param int $hasil_ujian_id ID hasil ujian
 * @return array Hasil perhitungan nilai
 */
function hitungNilaiUjian($hasil_ujian_id) {
    global $pdo;
    
    try {
        // 1. Ambil data hasil ujian DAN TOTAL SOAL UJIAN
        $stmt = $pdo->prepare("SELECT 
                hu.*, 
                u.durasi, u.mapel_id, u.judul_ujian, u.id as ujian_id,
                s.nama as nama_siswa,
                (SELECT COUNT(*) FROM soal_ujian su WHERE su.ujian_id = u.id) as total_soal_ujian
            FROM hasil_ujian hu 
            JOIN ujian u ON hu.ujian_id = u.id 
            JOIN siswa s ON hu.siswa_id = s.id
            WHERE hu.id = ?");
        $stmt->execute([$hasil_ujian_id]);
        $hasil_ujian = $stmt->fetch();
        
        if (!$hasil_ujian) {
            return ['success' => false, 'error' => 'Data hasil ujian tidak ditemukan'];
        }
        
        $ujian_id = $hasil_ujian['ujian_id'];
        $total_soal_ujian = (int)$hasil_ujian['total_soal_ujian'];
        
        if ($total_soal_ujian == 0) {
            return ['success' => false, 'error' => 'Tidak ada soal dalam ujian ini'];
        }
        
        // 2. Ambil semua soal dari ujian ini untuk menghitung skor
        $sql_soal_ujian = "SELECT 
                s.id, s.skor, s.jawaban_benar
            FROM soal_ujian su
            JOIN soal s ON su.soal_id = s.id
            WHERE su.ujian_id = ?
            ORDER BY su.urutan";
        
        $stmt_soal = $pdo->prepare($sql_soal_ujian);
        $stmt_soal->execute([$ujian_id]);
        $soal_ujian = $stmt_soal->fetchAll();
        
        // 3. Ambil jawaban siswa untuk ujian ini
        $sql_jawaban = "SELECT 
                js.soal_id, js.jawaban_siswa, 
                s.jawaban_benar, s.skor
            FROM jawaban_siswa js
            JOIN soal s ON js.soal_id = s.id
            WHERE js.hasil_ujian_id = ?";
        
        $stmt_jawaban = $pdo->prepare($sql_jawaban);
        $stmt_jawaban->execute([$hasil_ujian_id]);
        $jawaban_siswa = $stmt_jawaban->fetchAll();
        
        // 4. Hitung berdasarkan TOTAL SOAL UJIAN dengan benar
        $jawaban_benar = 0;
        $total_skor = 0;
        $skor_maksimal = 0;
        
        // Mapping jawaban siswa untuk pencarian cepat
        $jawaban_map = [];
        foreach ($jawaban_siswa as $j) {
            $jawaban_map[$j['soal_id']] = $j;
        }
        
        // Hitung berdasarkan semua soal ujian
        foreach ($soal_ujian as $soal) {
            $soal_id = $soal['id'];
            $skor = $soal['skor'] ? (int)$soal['skor'] : 10; // Default skor 10 jika null
            $skor_maksimal += $skor;
            
            // Cek apakah siswa menjawab soal ini
            if (isset($jawaban_map[$soal_id])) {
                $jawaban = $jawaban_map[$soal_id];
                if ($jawaban['jawaban_siswa'] == $soal['jawaban_benar']) {
                    $jawaban_benar++;
                    $total_skor += $skor;
                }
                // Jika jawaban salah, tidak ditambahkan skor (0)
            }
            // Jika tidak dijawab, tidak ditambahkan skor (0)
        }
        
        // 5. PERBAIKAN: Hitung persentase dengan BENAR
        // Jika skor maksimal > 0, gunakan perhitungan berdasarkan skor
        if ($skor_maksimal > 0) {
            $nilai_persentase = ($total_skor / $skor_maksimal) * 100;
        } else {
            // Jika tidak ada skor, hitung berdasarkan jumlah soal
            $nilai_persentase = ($jawaban_benar / $total_soal_ujian) * 100;
        }
        
        // Bulatkan ke 2 desimal
        $nilai_persentase = round($nilai_persentase, 2);
        
        // 6. Cek apakah nilai terlalu tinggi (bug check)
        // Jika nilai > 100%, set ke 100% maksimum
        if ($nilai_persentase > 100) {
            logWaktu("Nilai terlalu tinggi - diperbaiki", [
                'hasil_ujian_id' => $hasil_ujian_id,
                'nilai_asli' => $nilai_persentase,
                'nilai_diperbaiki' => 100,
                'jawaban_benar' => $jawaban_benar,
                'total_soal' => $total_soal_ujian,
                'total_skor' => $total_skor,
                'skor_maksimal' => $skor_maksimal
            ]);
            $nilai_persentase = 100;
        }
        
        // 7. Ambil passing grade dari pengaturan
        $passing_grade = getSetting('passing_grade') ? (float)getSetting('passing_grade') : 60;
        
        // 8. Tentukan status kelulusan
        $lulus = $nilai_persentase >= $passing_grade;
        $status_nilai = $lulus ? 'Lulus' : 'Tidak Lulus';
        
        // 9. Update nilai di database
        $update_stmt = $pdo->prepare("UPDATE hasil_ujian 
                                     SET nilai = ? 
                                     WHERE id = ?");
        $update_stmt->execute([$nilai_persentase, $hasil_ujian_id]);
        
        logWaktu("Nilai dihitung - PERBAIKAN", [
            'hasil_ujian_id' => $hasil_ujian_id,
            'siswa' => $hasil_ujian['nama_siswa'],
            'ujian_id' => $ujian_id,
            'nilai' => $nilai_persentase,
            'total_soal_ujian' => $total_soal_ujian,
            'jawaban_benar' => $jawaban_benar,
            'jawaban_salah' => $total_soal_ujian - $jawaban_benar,
            'tidak_dijawab' => $total_soal_ujian - count($jawaban_siswa),
            'total_skor' => $total_skor,
            'skor_maksimal' => $skor_maksimal,
            'perhitungan' => "($total_skor / $skor_maksimal) × 100 = $nilai_persentase%"
        ]);
        
        return [
            'success' => true,
            'hasil_ujian_id' => $hasil_ujian_id,
            'siswa_id' => $hasil_ujian['siswa_id'],
            'nama_siswa' => $hasil_ujian['nama_siswa'],
            'ujian_id' => $ujian_id,
            'judul_ujian' => $hasil_ujian['judul_ujian'],
            'total_soal_ujian' => $total_soal_ujian,
            'total_jawaban_diberikan' => count($jawaban_siswa),
            'jawaban_benar' => $jawaban_benar,
            'jawaban_salah' => $total_soal_ujian - $jawaban_benar,
            'tidak_dijawab' => $total_soal_ujian - count($jawaban_siswa),
            'total_skor' => $total_skor,
            'skor_maksimal' => $skor_maksimal,
            'nilai' => $nilai_persentase,
            'nilai_format' => number_format($nilai_persentase, 2),
            'passing_grade' => $passing_grade,
            'lulus' => $lulus,
            'status_nilai' => $status_nilai,
            'waktu_selesai' => $hasil_ujian['waktu_selesai'],
            'durasi' => $hasil_ujian['durasi']
        ];
        
    } catch (PDOException $e) {
        logWaktu("Error hitung nilai", [
            'hasil_ujian_id' => $hasil_ujian_id,
            'error' => $e->getMessage()
        ]);
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Hitung semua nilai ujian yang belum dihitung
 * @return array Hasil perhitungan untuk semua ujian
 */
function hitungSemuaNilaiBelumDihitung() {
    global $pdo;
    
    try {
        // Cari hasil ujian yang sudah selesai tapi nilainya NULL
        $sql = "SELECT hu.id, hu.siswa_id, hu.ujian_id, 
                s.nama as nama_siswa, 
                u.judul_ujian,
                (SELECT COUNT(*) FROM soal_ujian su WHERE su.ujian_id = u.id) as total_soal_ujian
                FROM hasil_ujian hu
                JOIN siswa s ON hu.siswa_id = s.id
                JOIN ujian u ON hu.ujian_id = u.id
                WHERE hu.status = 'selesai' 
                AND (hu.nilai IS NULL OR hu.nilai = '')
                HAVING total_soal_ujian > 0";
        
        $stmt = $pdo->query($sql);
        $hasil_ujian_belum_dihitung = $stmt->fetchAll();
        
        $results = [];
        $total_dihitung = 0;
        $total_error = 0;
        
        foreach ($hasil_ujian_belum_dihitung as $hasil) {
            $hitung = hitungNilaiUjian($hasil['id']);
            
            if ($hitung['success']) {
                $total_dihitung++;
                $results[] = $hitung;
            } else {
                $total_error++;
                logWaktu("Gagal hitung nilai", [
                    'hasil_ujian_id' => $hasil['id'],
                    'error' => $hitung['error'] ?? 'Unknown error'
                ]);
            }
        }
        
        return [
            'success' => true,
            'total_ditemukan' => count($hasil_ujian_belum_dihitung),
            'total_dihitung' => $total_dihitung,
            'total_error' => $total_error,
            'results' => $results
        ];
        
    } catch (PDOException $e) {
        logWaktu("Error hitung semua nilai", ['error' => $e->getMessage()]);
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Dapatkan detail hasil ujian lengkap dengan nilai
 * PERBAIKAN: Hitung ulang jika nilai tidak sesuai
 * @param int $hasil_ujian_id ID hasil ujian
 * @return array Detail hasil ujian
 */
function getDetailHasilUjian($hasil_ujian_id) {
    global $pdo;
    
    try {
        // Ambil data hasil ujian
        $stmt = $pdo->prepare("SELECT * FROM hasil_ujian WHERE id = ?");
        $stmt->execute([$hasil_ujian_id]);
        $hasil_ujian = $stmt->fetch();
        
        if (!$hasil_ujian) {
            return ['success' => false, 'error' => 'Data hasil ujian tidak ditemukan'];
        }
        
        // Ambil data lengkap dengan TOTAL SOAL UJIAN
        $sql = "SELECT 
                hu.*,
                u.judul_ujian, u.durasi, u.waktu_mulai as ujian_mulai, u.waktu_selesai as ujian_selesai, u.id as ujian_id,
                s.nama as nama_siswa, s.nisn, s.kelas,
                mp.nama_mapel, mp.kode_mapel,
                (SELECT COUNT(*) FROM soal_ujian su WHERE su.ujian_id = u.id) as total_soal_ujian,
                (SELECT COUNT(*) FROM jawaban_siswa js WHERE js.hasil_ujian_id = hu.id) as total_jawaban_diberikan
            FROM hasil_ujian hu
            JOIN ujian u ON hu.ujian_id = u.id
            JOIN siswa s ON hu.siswa_id = s.id
            JOIN mata_pelajaran mp ON u.mapel_id = mp.id
            WHERE hu.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hasil_ujian_id]);
        $detail = $stmt->fetch();
        
        if (!$detail) {
            return ['success' => false, 'error' => 'Detail hasil ujian tidak ditemukan'];
        }
        
        $ujian_id = $detail['ujian_id'];
        
        // Ambil semua soal ujian untuk perhitungan
        $sql_soal_ujian = "SELECT 
                su.soal_id, su.urutan,
                s.pertanyaan, s.opsi_a, s.opsi_b, s.opsi_c, s.opsi_d, s.jawaban_benar, s.skor
            FROM soal_ujian su
            JOIN soal s ON su.soal_id = s.id
            WHERE su.ujian_id = ?
            ORDER BY su.urutan";
        
        $stmt_soal = $pdo->prepare($sql_soal_ujian);
        $stmt_soal->execute([$ujian_id]);
        $soal_ujian = $stmt_soal->fetchAll();
        
        // Ambil jawaban siswa
        $sql_jawaban = "SELECT 
                js.soal_id, js.jawaban_siswa
            FROM jawaban_siswa js
            WHERE js.hasil_ujian_id = ?";
        
        $stmt_jawaban = $pdo->prepare($sql_jawaban);
        $stmt_jawaban->execute([$hasil_ujian_id]);
        $jawaban_siswa_raw = $stmt_jawaban->fetchAll();
        
        // Mapping jawaban siswa
        $jawaban_map = [];
        foreach ($jawaban_siswa_raw as $j) {
            $jawaban_map[$j['soal_id']] = $j['jawaban_siswa'];
        }
        
        // Hitung berdasarkan TOTAL soal ujian
        $jawaban_benar = 0;
        $total_skor = 0;
        $skor_maksimal = 0;
        $jawaban_detail = [];
        
        foreach ($soal_ujian as $soal) {
            $soal_id = $soal['soal_id'];
            $skor = $soal['skor'] ? (int)$soal['skor'] : 10;
            $skor_maksimal += $skor;
            
            $jawaban_siswa = isset($jawaban_map[$soal_id]) ? $jawaban_map[$soal_id] : null;
            $benar = ($jawaban_siswa == $soal['jawaban_benar']);
            
            if ($benar) {
                $jawaban_benar++;
                $total_skor += $skor;
            }
            
            $jawaban_detail[] = [
                'soal_id' => $soal_id,
                'urutan' => $soal['urutan'],
                'pertanyaan' => $soal['pertanyaan'],
                'opsi_a' => $soal['opsi_a'],
                'opsi_b' => $soal['opsi_b'],
                'opsi_c' => $soal['opsi_c'],
                'opsi_d' => $soal['opsi_d'],
                'jawaban_siswa' => $jawaban_siswa,
                'jawaban_benar' => $soal['jawaban_benar'],
                'skor' => $skor,
                'benar' => $benar,
                'skor_didapat' => $benar ? $skor : 0
            ];
        }
        
        $detail['jawaban_detail'] = $jawaban_detail;
        $detail['total_soal_ujian'] = count($soal_ujian);
        $detail['jawaban_benar'] = $jawaban_benar;
        $detail['jawaban_salah'] = $detail['total_soal_ujian'] - $jawaban_benar;
        $detail['tidak_dijawab'] = $detail['total_soal_ujian'] - $detail['total_jawaban_diberikan'];
        $detail['total_skor'] = $total_skor;
        $detail['skor_maksimal'] = $skor_maksimal;
        $detail['passing_grade'] = getSetting('passing_grade') ? (float)getSetting('passing_grade') : 60;
        
        // PERBAIKAN: Hitung ulang nilai jika tidak sesuai
        $nilai_calculated = 0;
        if ($skor_maksimal > 0) {
            $nilai_calculated = ($total_skor / $skor_maksimal) * 100;
        } else if ($detail['total_soal_ujian'] > 0) {
            $nilai_calculated = ($jawaban_benar / $detail['total_soal_ujian']) * 100;
        }
        
        $nilai_calculated = round($nilai_calculated, 2);
        
        // Jika nilai di database berbeda > 1% dengan perhitungan, perbaiki
        $nilai_database = $detail['nilai'] ? (float)$detail['nilai'] : 0;
        $selisih = abs($nilai_database - $nilai_calculated);
        
        if ($selisih > 1) { // Jika selisih > 1%
            logWaktu("Nilai database tidak sesuai - diperbaiki", [
                'hasil_ujian_id' => $hasil_ujian_id,
                'nilai_database' => $nilai_database,
                'nilai_dihitung' => $nilai_calculated,
                'selisih' => $selisih
            ]);
            
            // Update nilai yang benar
            $update_stmt = $pdo->prepare("UPDATE hasil_ujian SET nilai = ? WHERE id = ?");
            $update_stmt->execute([$nilai_calculated, $hasil_ujian_id]);
            $detail['nilai'] = $nilai_calculated;
        } else if ($detail['nilai'] === null || $detail['nilai'] === '') {
            // Jika nilai kosong, isi dengan perhitungan
            $update_stmt = $pdo->prepare("UPDATE hasil_ujian SET nilai = ? WHERE id = ?");
            $update_stmt->execute([$nilai_calculated, $hasil_ujian_id]);
            $detail['nilai'] = $nilai_calculated;
        }
        
        $detail['lulus'] = $detail['nilai'] >= $detail['passing_grade'];
        $detail['status_nilai'] = $detail['lulus'] ? 'Lulus' : 'Tidak Lulus';
        
        // Format nilai
        $detail['nilai_format'] = number_format((float)$detail['nilai'], 2);
        $detail['nilai_persen'] = round((float)$detail['nilai'], 2);
        
        // Hitung persentase jawaban benar
        $detail['persentase_benar'] = $detail['total_soal_ujian'] > 0 
            ? round(($jawaban_benar / $detail['total_soal_ujian']) * 100, 2) 
            : 0;
        
        return [
            'success' => true,
            'data' => $detail
        ];
        
    } catch (PDOException $e) {
        logWaktu("Error get detail hasil ujian", [
            'hasil_ujian_id' => $hasil_ujian_id,
            'error' => $e->getMessage()
        ]);
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

// ===================================================
// 9. FUNGSI TAMBAHAN UNTUK PERBAIKAN NILAI
// ===================================================

/**
 * Perbaiki semua nilai yang tidak sesuai
 * @return array Hasil perbaikan
 */
function perbaikiSemuaNilai() {
    global $pdo;
    
    try {
        // Ambil semua hasil ujian yang sudah selesai
        $sql = "SELECT hu.id, hu.nilai, hu.ujian_id, s.nama as nama_siswa, u.judul_ujian
                FROM hasil_ujian hu
                JOIN siswa s ON hu.siswa_id = s.id
                JOIN ujian u ON hu.ujian_id = u.id
                WHERE hu.status = 'selesai' 
                AND hu.nilai IS NOT NULL";
        
        $stmt = $pdo->query($sql);
        $semua_hasil = $stmt->fetchAll();
        
        $total_diperiksa = 0;
        $total_diperbaiki = 0;
        $results = [];
        
        foreach ($semua_hasil as $hasil) {
            $total_diperiksa++;
            
            // Hitung nilai yang seharusnya
            $hitung = hitungNilaiUjian($hasil['id']);
            
            if ($hitung['success']) {
                $nilai_database = (float)$hasil['nilai'];
                $nilai_seharusnya = (float)$hitung['nilai'];
                $selisih = abs($nilai_database - $nilai_seharusnya);
                
                if ($selisih > 0.01) { // Jika selisih > 0.01%
                    $total_diperbaiki++;
                    $results[] = [
                        'hasil_ujian_id' => $hasil['id'],
                        'nama_siswa' => $hasil['nama_siswa'],
                        'judul_ujian' => $hasil['judul_ujian'],
                        'nilai_lama' => $nilai_database,
                        'nilai_baru' => $nilai_seharusnya,
                        'selisih' => $selisih
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'total_diperiksa' => $total_diperiksa,
            'total_diperbaiki' => $total_diperbaiki,
            'results' => $results,
            'message' => "Periksa $total_diperiksa nilai, diperbaiki $total_diperbaiki nilai"
        ];
        
    } catch (PDOException $e) {
        logWaktu("Error perbaiki semua nilai", ['error' => $e->getMessage()]);
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

// ===================================================
// 10. FUNGSI RESTART APLIKASI
// ===================================================

/**
 * Restart aplikasi - kosongkan semua data kecuali admin
 * @return bool Berhasil atau tidak
 */
function restartAplikasi() {
    global $pdo;
    
    try {
        // Mulai transaksi
        $pdo->beginTransaction();
        
        // Hapus data dengan urutan yang benar (perhatikan foreign key constraints)
        
        // 1. Hapus sesi ujian
        $pdo->exec("DELETE FROM sesi_ujian");
        
        // 2. Hapus jawaban siswa
        $pdo->exec("DELETE FROM jawaban_siswa");
        
        // 3. Hapus hasil ujian
        $pdo->exec("DELETE FROM hasil_ujian");
        
        // 4. Hapus soal ujian (pivot table)
        $pdo->exec("DELETE FROM soal_ujian");
        
        // 5. Hapus ujian
        $pdo->exec("DELETE FROM ujian");
        
        // 6. Hapus soal
        $pdo->exec("DELETE FROM soal");
        
        // 7. Hapus siswa
        // Hapus user siswa terlebih dahulu
        $pdo->exec("DELETE FROM users WHERE role = 'siswa'");
        
        // 8. Hapus data siswa
        $pdo->exec("DELETE FROM siswa");
        
        // 9. Reset guru (kecuali admin)
        // Update guru selain admin
        $pdo->exec("UPDATE guru SET 
            nip = NULL, 
            email = NULL, 
            no_telp = NULL, 
            alamat = NULL, 
            foto = NULL 
            WHERE id != 1");
        
        // 10. Reset user guru (kecuali admin)
        $pdo->exec("UPDATE users SET 
            password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
            nama_lengkap = 'Guru' 
            WHERE id != 1 AND role = 'guru'");
        
        // 11. Hapus mata pelajaran
        $pdo->exec("DELETE FROM mata_pelajaran");
        
        // 12. Reset pengaturan (kecuali yang penting)
        $pdo->exec("DELETE FROM pengaturan WHERE nama NOT IN ('random_soal', 'allow_restart', 'site_name', 'site_description', 'timezone')");
        
        // Insert default mata pelajaran (opsional)
        // $pdo->exec("INSERT INTO mata_pelajaran (kode_mapel, nama_mapel) VALUES ('UMUM', 'Pendidikan Umum')");
        
        // Commit transaksi
        $pdo->commit();
        
        logWaktu("Aplikasi direstart", [
            'success' => true,
            'timestamp' => waktuSekarang()
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        // Rollback jika ada error
        $pdo->rollBack();
        
        logWaktu("Error restart aplikasi", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return false;
    }
}

// ===================================================
// 11. FUNGSI PROFIL SEKOLAH
// ===================================================

/**
 * Ambil data profil sekolah - VERSI DIPERBAIKI
 * @return array Data profil sekolah
 */
function getProfilSekolah() {
    global $pdo;
    
    try {
        // PERBAIKAN: Ambil record TERBARU berdasarkan ID DESC (bukan LIMIT 1 biasa)
        $stmt = $pdo->prepare("SELECT * FROM profil_sekolah ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $profil = $stmt->fetch();
        
        if (!$profil) {
            // Return default values jika tidak ada data
            return [
                'id' => 0,
                'nama_sekolah' => 'Nama Sekolah',
                'npsn' => '',
                'alamat' => '',
                'telepon' => '',
                'email' => '',
                'website' => '',
                'kepala_sekolah' => '',
                'nip_kepala' => '',
                'logo' => 'assets/images/logo.png',
                'visi' => '',
                'misi' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Debug: Log informasi profil yang diambil
        error_log("Profil sekolah diambil - ID: " . $profil['id'] . ", Logo: " . $profil['logo']);
        
        return $profil;
        
    } catch (PDOException $e) {
        logWaktu("Error get profil sekolah", ['error' => $e->getMessage()]);
        return [];
    }
}
/**
 * Simpan/update profil sekolah
 * @param array $data Data profil sekolah
 * @return bool Berhasil atau tidak
 */
function saveProfilSekolah($data) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO profil_sekolah (
            nama_sekolah, npsn, alamat, telepon, email, 
            website, kepala_sekolah, nip_kepala, logo, 
            visi, misi, updated_at
        ) VALUES (
            :nama_sekolah, :npsn, :alamat, :telepon, :email,
            :website, :kepala_sekolah, :nip_kepala, :logo,
            :visi, :misi, NOW()
        ) ON DUPLICATE KEY UPDATE
            nama_sekolah = VALUES(nama_sekolah),
            npsn = VALUES(npsn),
            alamat = VALUES(alamat),
            telepon = VALUES(telepon),
            email = VALUES(email),
            website = VALUES(website),
            kepala_sekolah = VALUES(kepala_sekolah),
            nip_kepala = VALUES(nip_kepala),
            logo = VALUES(logo),
            visi = VALUES(visi),
            misi = VALUES(misi),
            updated_at = VALUES(updated_at)";
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
            ':nama_sekolah' => $data['nama_sekolah'] ?? '',
            ':npsn' => $data['npsn'] ?? '',
            ':alamat' => $data['alamat'] ?? '',
            ':telepon' => $data['telepon'] ?? '',
            ':email' => $data['email'] ?? '',
            ':website' => $data['website'] ?? '',
            ':kepala_sekolah' => $data['kepala_sekolah'] ?? '',
            ':nip_kepala' => $data['nip_kepala'] ?? '',
            ':logo' => $data['logo'] ?? '',
            ':visi' => $data['visi'] ?? '',
            ':misi' => $data['misi'] ?? ''
        ];
        
        return $stmt->execute($params);
        
    } catch (PDOException $e) {
        logWaktu("Error save profil sekolah", ['error' => $e->getMessage(), 'data' => $data]);
        return false;
    }
}

/**
 * Upload logo sekolah - PERBAIKAN VERSI
 * @param array $file File $_FILES
 * @param string $old_logo Logo lama (jika ada)
 * @return string Nama file baru atau string kosong jika gagal
 */
function uploadLogo($file, $old_logo = '') {
    // Debug info
    error_log("=== UPLOAD LOGO PROCESS STARTED ===");
    error_log("Old logo: " . $old_logo);
    error_log("File name: " . ($file['name'] ?? 'No file'));
    error_log("File error: " . ($file['error'] ?? 'No error code'));
    
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload failed: Error code " . ($file['error'] ?? 'unknown'));
        return $old_logo;
    }
    
    // Konstanta yang sudah didefinisikan di atas
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validasi ekstensi
    if (!in_array($file_ext, $allowed_extensions)) {
        error_log("Invalid extension: " . $file_ext);
        $_SESSION['upload_error'] = 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.';
        return $old_logo;
    }
    
    // Validasi ukuran
    if ($file_size > $max_size) {
        error_log("File too large: " . $file_size . " bytes");
        $_SESSION['upload_error'] = 'Ukuran file terlalu besar. Maksimal 2MB.';
        return $old_logo;
    }
    
    // Validasi tipe file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);
    
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_mime_types)) {
        error_log("Invalid MIME type: " . $mime_type);
        $_SESSION['upload_error'] = 'Tipe file tidak valid.';
        return $old_logo;
    }
    
    // Generate nama file unik
    $timestamp = time();
    $unique_id = uniqid();
    $new_file_name = 'logo_' . $timestamp . '_' . $unique_id . '.' . $file_ext;
    
    // Path untuk upload
    $upload_dir = __DIR__ . '/assets/uploads/logos/';
    $upload_path = $upload_dir . $new_file_name;
    
    // Buat folder jika belum ada
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create directory: " . $upload_dir);
            $_SESSION['upload_error'] = 'Gagal membuat direktori upload.';
            return $old_logo;
        }
        // Buat file index.html untuk keamanan
        file_put_contents($upload_dir . 'index.html', '<html><body><h1>Directory listing disabled</h1></body></html>');
    }
    
    // Cek apakah direktori bisa ditulisi
    if (!is_writable($upload_dir)) {
        error_log("Directory not writable: " . $upload_dir);
        $_SESSION['upload_error'] = 'Direktori upload tidak bisa ditulisi.';
        return $old_logo;
    }
    
    // Pindahkan file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        error_log("File uploaded successfully: " . $upload_path);
        
        // Hapus logo lama jika ada dan bukan logo default
        if ($old_logo && $old_logo !== 'assets/images/logo.png') {
            $old_logo_path = __DIR__ . '/' . $old_logo;
            if (file_exists($old_logo_path) && is_file($old_logo_path)) {
                if (unlink($old_logo_path)) {
                    error_log("Old logo deleted: " . $old_logo_path);
                } else {
                    error_log("Failed to delete old logo: " . $old_logo_path);
                }
            }
        }
        
        // Return path relatif untuk database
        $relative_path = 'assets/uploads/logos/' . $new_file_name;
        error_log("Returning relative path: " . $relative_path);
        return $relative_path;
    } else {
        error_log("Failed to move uploaded file from " . $file_tmp . " to " . $upload_path);
        $_SESSION['upload_error'] = 'Gagal mengupload file.';
        return $old_logo;
    }
}

// ===================================================
// 12. AUTO CHECK TIMEZONE (Optional untuk debugging)
// ===================================================

// Uncomment untuk debugging timezone
// echo debugInfoWaktu("System Time Check (WIB)");
// logWaktu("System started (WIB)", [
//     'php_timezone' => date_default_timezone_get(),
//     'php_time' => date('Y-m-d H:i:s'),
//     'server' => $_SERVER['SERVER_NAME'] ?? 'localhost'
// ]);

// ===================================================
// 13. SETTING DEFAULT JIKA BELUM ADA
// ===================================================

// Cek dan buat setting default jika belum ada
try {
    $default_settings = [
        'random_soal' => '1',
        'allow_restart' => '1',
        'max_file_size' => '5242880', // 5MB
        'allowed_extensions' => 'jpg,jpeg,png,pdf',
        'site_name' => 'Sistem Ujian Online',
        'site_description' => 'Platform Ujian Online Sekolah',
        'timezone' => 'Asia/Jakarta',
        'passing_grade' => '60',
        'auto_calculate' => '1'
    ];
    
    foreach ($default_settings as $key => $value) {
        $current = getSetting($key);
        if ($current === null) {
            updateSetting($key, $value);
            logWaktu("Setting default created", ['key' => $key, 'value' => $value]);
        }
    }
} catch (Exception $e) {
    logWaktu("Error setting default settings", ['error' => $e->getMessage()]);
}

// ===================================================
// 14. CEK WAKTU SINKRONISASI (Hanya untuk development)
// ===================================================

// Untuk development, tambahkan ini di halaman admin
function getTimeSyncStatus() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT NOW() as db_time, UNIX_TIMESTAMP(NOW()) as db_timestamp");
        $row = $stmt->fetch();
        
        $php_time = date('Y-m-d H:i:s');
        $php_timestamp = time();
        $db_time = $row['db_time'];
        $db_timestamp = $row['db_timestamp'];
        
        $diff_seconds = abs($php_timestamp - $db_timestamp);
        
        return [
            'php_time' => $php_time,
            'php_timestamp' => $php_timestamp,
            'db_time' => $db_time,
            'db_timestamp' => $db_timestamp,
            'diff_seconds' => $diff_seconds,
            'in_sync' => $diff_seconds <= 1,
            'php_timezone' => date_default_timezone_get(),
            'status' => $diff_seconds <= 1 ? 'Sinkron (WIB)' : 'Tidak Sinkron'
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// ===================================================
// 15. CREATE TABLES IF NOT EXISTS
// ===================================================

// Cek dan buat tabel profil_sekolah jika belum ada
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS profil_sekolah (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_sekolah VARCHAR(200) NOT NULL,
        npsn VARCHAR(20),
        alamat TEXT,
        telepon VARCHAR(20),
        email VARCHAR(100),
        website VARCHAR(100),
        kepala_sekolah VARCHAR(100),
        nip_kepala VARCHAR(20),
        logo VARCHAR(255) DEFAULT 'assets/images/logo.png',
        visi TEXT,
        misi TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");
    
    logWaktu("Tabel profil_sekolah sudah ada/dibuat");
} catch (Exception $e) {
    logWaktu("Error create table profil_sekolah", ['error' => $e->getMessage()]);
}

// ===================================================
// 16. UPDATE TABEL SOAL UNTUK MEDIA
// ===================================================

// Cek dan tambah kolom untuk media di tabel soal
try {
    // Cek apakah kolom gambar_soal sudah ada
    $stmt = $pdo->query("SHOW COLUMNS FROM soal LIKE 'gambar_soal'");
    if ($stmt->rowCount() == 0) {
        // Tambah kolom untuk media
        $pdo->exec("ALTER TABLE soal 
                    ADD COLUMN gambar_soal VARCHAR(255) DEFAULT NULL AFTER pertanyaan,
                    ADD COLUMN video_soal TEXT DEFAULT NULL AFTER gambar_soal,
                    ADD COLUMN opsi_a_gambar VARCHAR(255) DEFAULT NULL AFTER opsi_a,
                    ADD COLUMN opsi_b_gambar VARCHAR(255) DEFAULT NULL AFTER opsi_b,
                    ADD COLUMN opsi_c_gambar VARCHAR(255) DEFAULT NULL AFTER opsi_c,
                    ADD COLUMN opsi_d_gambar VARCHAR(255) DEFAULT NULL AFTER opsi_d");
        logWaktu("Kolom media ditambahkan ke tabel soal");
    }
} catch (Exception $e) {
    logWaktu("Error update table soal for media", ['error' => $e->getMessage()]);
}

// ===================================================
// 17. INITIAL CHECK (Hanya sekali saat development)
// ===================================================

// Hanya jalankan sekali untuk memperbaiki data yang ada
// if (isset($_GET['fix_time']) && $_GET['fix_time'] == 'yes') {
//     $result = perbaikiDataWaktuUjian();
//     echo "<pre>" . print_r($result, true) . "</pre>";
// }

// Hanya jalankan sekali untuk menghitung semua nilai yang belum dihitung
// if (isset($_GET['calculate_all']) && $_GET['calculate_all'] == 'yes') {
//     $result = hitungSemuaNilaiBelumDihitung();
//     echo "<pre>" . print_r($result, true) . "</pre>";
// }

// Hanya jalankan sekali untuk memperbaiki semua nilai yang tidak sesuai
// if (isset($_GET['fix_all_scores']) && $_GET['fix_all_scores'] == 'yes') {
//     $result = perbaikiSemuaNilai();
//     echo "<pre>" . print_r($result, true) . "</pre>";
// }
// ===================================================
// 18. FUNGSI UNTUK MEMBERSIHKAN DATA DUPLIKAT PROFIL SEKOLAH
// ===================================================

/**
 * Membersihkan data duplikat profil sekolah - hanya ambil yang terbaru
 * @return array Hasil pembersihan
 */
function cleanDuplicateProfilSekolah() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Ambil ID terbaru
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM profil_sekolah");
        $result = $stmt->fetch();
        $latest_id = $result['max_id'] ?? 0;
        
        if ($latest_id > 0) {
            // 2. Backup data duplikat ke tabel backup (opsional)
            $pdo->exec("CREATE TABLE IF NOT EXISTS profil_sekolah_backup LIKE profil_sekolah");
            $pdo->exec("INSERT INTO profil_sekolah_backup SELECT * FROM profil_sekolah");
            
            // 3. Hapus semua kecuali yang terbaru
            $delete_stmt = $pdo->prepare("DELETE FROM profil_sekolah WHERE id < ?");
            $delete_stmt->execute([$latest_id]);
            
            $rows_deleted = $delete_stmt->rowCount();
            
            $pdo->commit();
            
            logWaktu("Data duplikat profil sekolah dibersihkan", [
                'latest_id' => $latest_id,
                'rows_deleted' => $rows_deleted,
                'status' => 'success'
            ]);
            
            return [
                'success' => true,
                'latest_id' => $latest_id,
                'rows_deleted' => $rows_deleted,
                'message' => "Data duplikat berhasil dibersihkan. $rows_deleted record dihapus."
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Tidak ada data profil sekolah'
        ];
        
    } catch (PDOException $e) {
        // Rollback jika ada error
        $pdo->rollBack();
        
        logWaktu("Error clean duplicate profil sekolah", ['error' => $e->getMessage()]);
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Periksa dan bersihkan data duplikat profil sekolah (otomatis)
 * Hanya jalankan jika diperlukan
 */
function autoCleanDuplicateProfilSekolah() {
    global $pdo;
    
    try {
        // Hitung jumlah record
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM profil_sekolah");
        $result = $stmt->fetch();
        $total_records = $result['total'] ?? 0;
        
        // Jika ada lebih dari 1 record, mungkin ada duplikat
        if ($total_records > 1) {
            $clean_result = cleanDuplicateProfilSekolah();
            
            if ($clean_result['success']) {
                logWaktu("Auto clean duplicate profil sekolah", $clean_result);
                return $clean_result;
            }
        }
        
        return [
            'success' => true,
            'total_records' => $total_records,
            'message' => 'Tidak perlu pembersihan duplikat'
        ];
        
    } catch (Exception $e) {
        logWaktu("Error auto clean duplicate profil sekolah", ['error' => $e->getMessage()]);
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ===================================================
// 19. AUTO CHECK DAN PERBAIKAN PROFIL SEKOLAH
// ===================================================

// Jalankan auto clean saat akses pertama kali (opsional)
// if (!isset($_SESSION['profil_cleaned']) && $_SERVER['REQUEST_URI'] == '/pengaturan.php') {
//     $clean_result = autoCleanDuplicateProfilSekolah();
//     $_SESSION['profil_cleaned'] = true;
//     error_log("Auto clean profil sekolah: " . json_encode($clean_result));
// }
?>