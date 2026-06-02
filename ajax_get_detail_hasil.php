<?php
// ajax_get_detail_hasil.php - Ambil detail hasil ujian siswa
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');

// Log untuk debugging
function logDebug($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log .= " - " . json_encode($data);
    }
    error_log($log);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$ujian_id = $_GET['ujian_id'] ?? $_POST['ujian_id'] ?? 0;
$hasil_ujian_id = $_GET['hasil_ujian_id'] ?? $_POST['hasil_ujian_id'] ?? 0;

logDebug("Request received", ['ujian_id' => $ujian_id, 'hasil_ujian_id' => $hasil_ujian_id]);

if (!$hasil_ujian_id) {
    echo json_encode(['success' => false, 'message' => 'ID hasil ujian tidak valid']);
    exit();
}

try {
    // PERBAIKAN: Cek apakah hasil_ujian_id ada (tanpa filter ujian_id dulu)
    $stmt_cek = $pdo->prepare("
        SELECT hu.id, hu.ujian_id, hu.siswa_id, hu.nilai, hu.status,
               s.nama as nama_siswa, s.nisn, s.kelas
        FROM hasil_ujian hu 
        LEFT JOIN siswa s ON hu.siswa_id = s.id
        WHERE hu.id = ?
    ");
    $stmt_cek->execute([$hasil_ujian_id]);
    $cek_hasil = $stmt_cek->fetch(PDO::FETCH_ASSOC);
    
    if (!$cek_hasil) {
        logDebug("Hasil ujian not found", ['hasil_ujian_id' => $hasil_ujian_id]);
        echo json_encode(['success' => false, 'message' => 'Data hasil ujian tidak ditemukan']);
        exit();
    }
    
    logDebug("Hasil ujian found", $cek_hasil);
    
    // Gunakan ujian_id dari database, bukan dari parameter
    $ujian_id_dari_db = $cek_hasil['ujian_id'];
    
    // Ambil data hasil ujian lengkap
    $stmt = $pdo->prepare("
        SELECT 
            hu.*,
            s.nisn,
            s.nama as nama_siswa,
            s.kelas,
            s.jenis_kelamin,
            u.judul_ujian,
            u.durasi,
            u.waktu_mulai as ujian_mulai,
            u.waktu_selesai as ujian_selesai,
            u.kelas_target,
            mp.nama_mapel,
            mp.kode_mapel,
            (SELECT COUNT(*) FROM jawaban_siswa WHERE hasil_ujian_id = hu.id) as total_soal_dijawab,
            (SELECT COALESCE(SUM(skor), 0) FROM soal WHERE id IN (SELECT soal_id FROM soal_ujian WHERE ujian_id = hu.ujian_id)) as total_skor_maksimal,
            (SELECT COALESCE(SUM(CASE 
                WHEN s2.jenis_soal IN ('pilihan_ganda', 'benar_salah') AND js2.jawaban_siswa = s2.jawaban_benar THEN s2.skor 
                ELSE 0 
            END), 0) FROM jawaban_siswa js2 JOIN soal s2 ON js2.soal_id = s2.id WHERE js2.hasil_ujian_id = hu.id) as nilai_otomatis
        FROM hasil_ujian hu
        JOIN siswa s ON hu.siswa_id = s.id
        JOIN ujian u ON hu.ujian_id = u.id
        JOIN mata_pelajaran mp ON u.mapel_id = mp.id
        WHERE hu.id = ?
    ");
    $stmt->execute([$hasil_ujian_id]);
    $hasil = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hasil) {
        logDebug("Detail hasil not found", ['hasil_ujian_id' => $hasil_ujian_id]);
        echo json_encode(['success' => false, 'message' => 'Data detail tidak ditemukan']);
        exit();
    }
    
    logDebug("Detail hasil fetched", ['nilai' => $hasil['nilai'], 'total_soal_dijawab' => $hasil['total_soal_dijawab']]);
    
    // Ambil daftar soal dan jawaban
    $stmt_soal = $pdo->prepare("
        SELECT 
            su.urutan,
            s.id as soal_id,
            s.pertanyaan,
            s.jenis_soal,
            s.opsi_a,
            s.opsi_b,
            s.opsi_c,
            s.opsi_d,
            s.opsi_e,
            s.jawaban_benar,
            s.jawaban_kompleks,
            s.skor,
            s.skor_per_jawaban,
            s.gambar_soal,
            s.video_soal,
            js.jawaban_siswa,
            js.jawaban_essay,
            je.skor_essay,
            je.komentar_guru,
            je.status_koreksi
        FROM soal_ujian su
        JOIN soal s ON su.soal_id = s.id
        LEFT JOIN jawaban_siswa js ON js.hasil_ujian_id = ? AND js.soal_id = s.id
        LEFT JOIN jawaban_essay je ON je.hasil_ujian_id = ? AND je.soal_id = s.id
        WHERE su.ujian_id = ?
        ORDER BY su.urutan
    ");
    $stmt_soal->execute([$hasil_ujian_id, $hasil_ujian_id, $ujian_id_dari_db]);
    $soal_list = $stmt_soal->fetchAll(PDO::FETCH_ASSOC);
    
    logDebug("Soal list fetched", ['count' => count($soal_list)]);
    
    // Hitung ulang nilai jika perlu
    $nilai_database = $hasil['nilai'] ?? 0;
    $nilai_otomatis = $hasil['nilai_otomatis'] ?? 0;
    $total_skor_maksimal = $hasil['total_skor_maksimal'] ?? 100;
    
    if ($total_skor_maksimal > 0) {
        $nilai_seharusnya = ($nilai_otomatis / $total_skor_maksimal) * 100;
        $nilai_seharusnya = round($nilai_seharusnya, 2);
        
        // Jika nilai database berbeda, update
        if (abs($nilai_database - $nilai_seharusnya) > 0.01 && $nilai_seharusnya > 0) {
            $update_nilai = $pdo->prepare("UPDATE hasil_ujian SET nilai = ? WHERE id = ?");
            $update_nilai->execute([$nilai_seharusnya, $hasil_ujian_id]);
            $hasil['nilai'] = $nilai_seharusnya;
            logDebug("Nilai updated", ['old' => $nilai_database, 'new' => $nilai_seharusnya]);
        }
    }
    
    // Format response
    $response = [
        'success' => true,
        'hasil' => [
            'id' => $hasil['id'],
            'ujian_id' => $hasil['ujian_id'],
            'siswa_id' => $hasil['siswa_id'],
            'nilai' => floatval($hasil['nilai'] ?? 0),
            'waktu_mulai' => $hasil['waktu_mulai'],
            'waktu_selesai' => $hasil['waktu_selesai'],
            'status' => $hasil['status'],
            'nisn' => $hasil['nisn'],
            'nama_siswa' => $hasil['nama_siswa'],
            'kelas' => $hasil['kelas'],
            'jenis_kelamin' => $hasil['jenis_kelamin'],
            'judul_ujian' => $hasil['judul_ujian'],
            'durasi' => $hasil['durasi'],
            'ujian_mulai' => $hasil['ujian_mulai'],
            'ujian_selesai' => $hasil['ujian_selesai'],
            'kelas_target' => $hasil['kelas_target'],
            'nama_mapel' => $hasil['nama_mapel'],
            'kode_mapel' => $hasil['kode_mapel'],
            'total_soal_dijawab' => intval($hasil['total_soal_dijawab'] ?? 0),
            'total_skor_maksimal' => intval($hasil['total_skor_maksimal'] ?? 0),
            'nilai_otomatis' => floatval($hasil['nilai_otomatis'] ?? 0)
        ],
        'soal_list' => $soal_list
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    logDebug("Database error", ['error' => $e->getMessage()]);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logDebug("General error", ['error' => $e->getMessage()]);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
exit();
?>