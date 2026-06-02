<?php
// proses_soal.php
ob_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$action = $_GET['action'] ?? '';
$id = $_POST['id'] ?? $_GET['id'] ?? 0;

try {
    switch ($action) {
        case 'tambah':
            tambahSoal();
            break;
            
        case 'edit':
            editSoal();
            break;
            
        case 'hapus':
            hapusSoal();
            break;
            
        default:
            flashMessage('danger', 'Aksi tidak valid!');
            header("Location: kelola_soal.php");
            exit();
    }
} catch (Exception $e) {
    flashMessage('danger', 'Terjadi kesalahan: ' . $e->getMessage());
    header("Location: kelola_soal.php");
    exit();
}

/**
 * Tambah soal baru dengan media dan dukungan opsi E
 */
function tambahSoal() {
    global $pdo;
    
    // Ambil data dari POST dengan opsi E
    $data = [
        'mapel_id' => $_POST['mapel_id'] ?? '',
        'kelas' => $_POST['kelas'] ?? '',
        'pertanyaan' => $_POST['pertanyaan'] ?? '',
        'video_soal' => $_POST['video_soal'] ?? '',
        'opsi_a' => $_POST['opsi_a'] ?? '',
        'opsi_b' => $_POST['opsi_b'] ?? '',
        'opsi_c' => $_POST['opsi_c'] ?? '',
        'opsi_d' => $_POST['opsi_d'] ?? '',
        'opsi_e' => $_POST['opsi_e'] ?? '', // TAMBAH: Opsi E
        'jawaban_benar' => $_POST['jawaban_benar'] ?? '',
        'skor' => $_POST['skor'] ?? 10
    ];
    
    // Validasi data berdasarkan jenis soal
    $jenis_soal = $_POST['jenis_soal'] ?? 'pilihan_ganda';
    
    if ($jenis_soal == 'pilihan_ganda') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan']) || 
            empty($data['opsi_a']) || empty($data['opsi_b']) || empty($data['opsi_c']) || 
            empty($data['opsi_d']) || empty($data['opsi_e']) || empty($data['jawaban_benar'])) {
            flashMessage('danger', 'Semua field wajib diisi untuk pilihan ganda (A-E)!');
            header("Location: kelola_soal.php");
            exit();
        }
    } else {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan'])) {
            flashMessage('danger', 'Field wajib diisi!');
            header("Location: kelola_soal.php");
            exit();
        }
    }
    
    // Validasi skor
    $data['skor'] = (int)$data['skor'];
    if ($data['skor'] < 1 || $data['skor'] > 100) {
        flashMessage('danger', 'Skor harus antara 1-100');
        header("Location: kelola_soal.php");
        exit();
    }
    
    // Upload gambar jika ada
    $gambar_soal = null;
    if (isset($_FILES['gambar_soal']) && $_FILES['gambar_soal']['error'] == 0) {
        $gambar_soal = uploadGambarSoal($_FILES['gambar_soal'], 'soal');
    }
    
    try {
        // Insert soal baru dengan opsi E
        $sql = "INSERT INTO soal (
                mapel_id, kelas, pertanyaan, gambar_soal, video_soal,
                opsi_a, opsi_b, opsi_c, opsi_d, opsi_e,
                jawaban_benar, created_by, skor
            ) VALUES (
                :mapel_id, :kelas, :pertanyaan, :gambar_soal, :video_soal,
                :opsi_a, :opsi_b, :opsi_c, :opsi_d, :opsi_e,
                :jawaban_benar, :created_by, :skor
            )";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            ':mapel_id' => $data['mapel_id'],
            ':kelas' => $data['kelas'],
            ':pertanyaan' => $data['pertanyaan'],
            ':gambar_soal' => $gambar_soal,
            ':video_soal' => $data['video_soal'],
            ':opsi_a' => $data['opsi_a'],
            ':opsi_b' => $data['opsi_b'],
            ':opsi_c' => $data['opsi_c'],
            ':opsi_d' => $data['opsi_d'],
            ':opsi_e' => $data['opsi_e'],
            ':jawaban_benar' => $data['jawaban_benar'],
            ':created_by' => $_SESSION['user_id'],
            ':skor' => $data['skor']
        ];
        
        $stmt->execute($params);
        $soal_id = $pdo->lastInsertId();
        
        flashMessage('success', 'Soal berhasil ditambahkan!');
        logWaktu("Soal ditambahkan", [
            'soal_id' => $soal_id,
            'mapel_id' => $data['mapel_id'],
            'kelas' => $data['kelas']
        ]);
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Gagal menambah soal: ' . $e->getMessage());
    }
    
    header("Location: kelola_soal.php");
    exit();
}

/**
 * Edit soal dengan media dan dukungan opsi E
 */
function editSoal() {
    global $pdo;
    
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        flashMessage('danger', 'ID soal tidak valid!');
        header("Location: kelola_soal.php");
        exit();
    }
    
    // Ambil data dari POST dengan opsi E
    $data = [
        'id' => $id,
        'mapel_id' => $_POST['mapel_id'] ?? '',
        'kelas' => $_POST['kelas'] ?? '',
        'pertanyaan' => $_POST['pertanyaan'] ?? '',
        'video_soal' => $_POST['video_soal'] ?? '',
        'opsi_a' => $_POST['opsi_a'] ?? '',
        'opsi_b' => $_POST['opsi_b'] ?? '',
        'opsi_c' => $_POST['opsi_c'] ?? '',
        'opsi_d' => $_POST['opsi_d'] ?? '',
        'opsi_e' => $_POST['opsi_e'] ?? '', // TAMBAH: Opsi E
        'jawaban_benar' => $_POST['jawaban_benar'] ?? '',
        'skor' => $_POST['skor'] ?? 10
    ];
    
    // Validasi data berdasarkan jenis soal
    $jenis_soal = $_POST['jenis_soal'] ?? 'pilihan_ganda';
    
    if ($jenis_soal == 'pilihan_ganda') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan']) || 
            empty($data['opsi_a']) || empty($data['opsi_b']) || empty($data['opsi_c']) || 
            empty($data['opsi_d']) || empty($data['opsi_e']) || empty($data['jawaban_benar'])) {
            flashMessage('danger', 'Semua field wajib diisi untuk pilihan ganda (A-E)!');
            header("Location: kelola_soal.php");
            exit();
        }
    } else {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan'])) {
            flashMessage('danger', 'Field wajib diisi!');
            header("Location: kelola_soal.php");
            exit();
        }
    }
    
    // Validasi skor
    $data['skor'] = (int)$data['skor'];
    if ($data['skor'] < 1 || $data['skor'] > 100) {
        flashMessage('danger', 'Skor harus antara 1-100');
        header("Location: kelola_soal.php");
        exit();
    }
    
    // Cek apakah soal milik user yang login
    $stmt = $pdo->prepare("SELECT created_by, gambar_soal FROM soal WHERE id = ?");
    $stmt->execute([$id]);
    $soal_lama = $stmt->fetch();
    
    if (!$soal_lama) {
        flashMessage('danger', 'Soal tidak ditemukan!');
        header("Location: kelola_soal.php");
        exit();
    }
    
    if ($soal_lama['created_by'] != $_SESSION['user_id']) {
        flashMessage('danger', 'Anda tidak memiliki akses untuk mengedit soal ini!');
        header("Location: kelola_soal.php");
        exit();
    }
    
    // Upload gambar baru jika ada
    $gambar_soal = $soal_lama['gambar_soal']; // default gambar lama
    
    if (isset($_FILES['gambar_soal']) && $_FILES['gambar_soal']['error'] == 0) {
        $gambar_baru = uploadGambarSoal($_FILES['gambar_soal'], 'soal');
        if ($gambar_baru) {
            // Hapus gambar lama
            if ($gambar_soal && file_exists($gambar_soal)) {
                unlink($gambar_soal);
            }
            $gambar_soal = $gambar_baru;
        }
    }
    
    // Jika checkbox hapus gambar dicentang
    if (isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] == 'on') {
        if ($gambar_soal && file_exists($gambar_soal)) {
            unlink($gambar_soal);
        }
        $gambar_soal = null;
    }
    
    try {
        // Update soal dengan opsi E
        $sql = "UPDATE soal SET 
                mapel_id = :mapel_id,
                kelas = :kelas,
                pertanyaan = :pertanyaan,
                gambar_soal = :gambar_soal,
                video_soal = :video_soal,
                opsi_a = :opsi_a,
                opsi_b = :opsi_b,
                opsi_c = :opsi_c,
                opsi_d = :opsi_d,
                opsi_e = :opsi_e,
                jawaban_benar = :jawaban_benar,
                skor = :skor
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            ':id' => $id,
            ':mapel_id' => $data['mapel_id'],
            ':kelas' => $data['kelas'],
            ':pertanyaan' => $data['pertanyaan'],
            ':gambar_soal' => $gambar_soal,
            ':video_soal' => $data['video_soal'],
            ':opsi_a' => $data['opsi_a'],
            ':opsi_b' => $data['opsi_b'],
            ':opsi_c' => $data['opsi_c'],
            ':opsi_d' => $data['opsi_d'],
            ':opsi_e' => $data['opsi_e'],
            ':jawaban_benar' => $data['jawaban_benar'],
            ':skor' => $data['skor']
        ];
        
        $stmt->execute($params);
        
        flashMessage('success', 'Soal berhasil diperbarui!');
        logWaktu("Soal diperbarui", [
            'soal_id' => $id,
            'mapel_id' => $data['mapel_id'],
            'kelas' => $data['kelas']
        ]);
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Gagal memperbarui soal: ' . $e->getMessage());
    }
    
    header("Location: kelola_soal.php");
    exit();
}

/**
 * Hapus soal dengan media
 */
function hapusSoal() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    
    if (empty($id)) {
        flashMessage('danger', 'ID soal tidak valid!');
        header("Location: kelola_soal.php");
        exit();
    }
    
    // Cek apakah soal milik user yang login
    $stmt = $pdo->prepare("SELECT created_by, gambar_soal FROM soal WHERE id = ?");
    $stmt->execute([$id]);
    $soal = $stmt->fetch();
    
    if (!$soal) {
        flashMessage('danger', 'Soal tidak ditemukan!');
        header("Location: kelola_soal.php");
        exit();
    }
    
    if ($soal['created_by'] != $_SESSION['user_id']) {
        flashMessage('danger', 'Anda tidak memiliki akses untuk menghapus soal ini!');
        header("Location: kelola_soal.php");
        exit();
    }
    
    try {
        // Hapus file gambar jika ada
        if (!empty($soal['gambar_soal']) && file_exists($soal['gambar_soal'])) {
            unlink($soal['gambar_soal']);
        }
        
        // Hapus dari database
        $stmt = $pdo->prepare("DELETE FROM soal WHERE id = ?");
        $stmt->execute([$id]);
        
        flashMessage('success', 'Soal berhasil dihapus!');
        logWaktu("Soal dihapus", ['soal_id' => $id]);
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Gagal menghapus soal: ' . $e->getMessage());
    }
    
    header("Location: kelola_soal.php");
    exit();
}
?>