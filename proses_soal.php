<?php
// proses_soal.php - Versi dengan support Multi Kelas
ob_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action == 'tambah') {
    tambahSoal();
} elseif ($action == 'edit') {
    editSoal();
} elseif ($action == 'hapus') {
    hapusSoal();
} else {
    header("Location: kelola_soal.php");
    exit();
}

// ==================== FUNGSI TAMBAH SOAL ====================
function tambahSoal() {
    global $pdo;
    
    $jenis_soal = $_POST['jenis_soal'] ?? 'pilihan_ganda';
    
    // Ambil kelas (bisa dari select biasa atau dari hidden field multi kelas)
    $kelas = $_POST['kelas'] ?? '';
    
    $data = [
        'mapel_id' => $_POST['mapel_id'] ?? '',
        'kelas' => $kelas,
        'pertanyaan' => $_POST['pertanyaan'] ?? '',
        'video_soal' => $_POST['video_soal'] ?? '',
        'opsi_a' => $_POST['opsi_a'] ?? '',
        'opsi_b' => $_POST['opsi_b'] ?? '',
        'opsi_c' => $_POST['opsi_c'] ?? '',
        'opsi_d' => $_POST['opsi_d'] ?? '',
        'opsi_e' => $_POST['opsi_e'] ?? '',
        'jawaban_benar' => $_POST['jawaban_benar'] ?? '',
        'skor' => $_POST['skor'] ?? 10,
        'jawaban_kompleks' => $_POST['jawaban_kompleks'] ?? '',
        'skor_per_jawaban' => $_POST['skor_per_jawaban'] ?? 0,
        'pasangan_jodoh' => $_POST['pasangan_jodoh'] ?? ''
    ];
    
    // Validasi
    if ($jenis_soal == 'pilihan_ganda_kompleks') {
        if (empty($data['jawaban_kompleks']) || $data['jawaban_kompleks'] == '[]') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Pilih minimal satu jawaban benar!'];
            header("Location: kelola_soal.php");
            exit();
        }
        $jawabanArray = json_decode($data['jawaban_kompleks'], true);
        $jumlahJawabanBenar = is_array($jawabanArray) ? count($jawabanArray) : 0;
        $data['skor'] = $data['skor_per_jawaban'] * $jumlahJawabanBenar;
    }
    
    // Upload gambar - menggunakan fungsi dari config.php
    $gambar_soal = null;
    if (isset($_FILES['gambar_soal']) && $_FILES['gambar_soal']['error'] == 0) {
        $gambar_soal = uploadGambarSoal($_FILES['gambar_soal'], 'soal');
    }
    
    try {
        $sql = "INSERT INTO soal (mapel_id, kelas, pertanyaan, gambar_soal, video_soal, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, jawaban_benar, jawaban_kompleks, skor_per_jawaban, pasangan_jodoh, created_by, skor, jenis_soal) 
                VALUES (:mapel_id, :kelas, :pertanyaan, :gambar_soal, :video_soal, :opsi_a, :opsi_b, :opsi_c, :opsi_d, :opsi_e, :jawaban_benar, :jawaban_kompleks, :skor_per_jawaban, :pasangan_jodoh, :created_by, :skor, :jenis_soal)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
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
            ':jawaban_kompleks' => $data['jawaban_kompleks'],
            ':skor_per_jawaban' => $data['skor_per_jawaban'],
            ':pasangan_jodoh' => $data['pasangan_jodoh'],
            ':created_by' => $_SESSION['user_id'],
            ':skor' => $data['skor'],
            ':jenis_soal' => $jenis_soal
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Soal berhasil ditambahkan!'];
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Gagal menambah soal: ' . $e->getMessage()];
    }
    
    header("Location: kelola_soal.php");
    exit();
}

// ==================== FUNGSI EDIT SOAL ====================
function editSoal() {
    global $pdo;
    
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID soal tidak valid!'];
        header("Location: kelola_soal.php");
        exit();
    }
    
    $jenis_soal = $_POST['jenis_soal'] ?? 'pilihan_ganda';
    
    // Ambil kelas (bisa dari select biasa atau dari hidden field multi kelas)
    $kelas = $_POST['kelas'] ?? '';
    
    $data = [
        'id' => $id,
        'mapel_id' => $_POST['mapel_id'] ?? '',
        'kelas' => $kelas,
        'pertanyaan' => $_POST['pertanyaan'] ?? '',
        'video_soal' => $_POST['video_soal'] ?? '',
        'opsi_a' => $_POST['opsi_a'] ?? '',
        'opsi_b' => $_POST['opsi_b'] ?? '',
        'opsi_c' => $_POST['opsi_c'] ?? '',
        'opsi_d' => $_POST['opsi_d'] ?? '',
        'opsi_e' => $_POST['opsi_e'] ?? '',
        'jawaban_benar' => $_POST['jawaban_benar'] ?? '',
        'skor' => $_POST['skor'] ?? 10,
        'jawaban_kompleks' => $_POST['jawaban_kompleks'] ?? '',
        'skor_per_jawaban' => $_POST['skor_per_jawaban'] ?? 0,
        'pasangan_jodoh' => $_POST['pasangan_jodoh'] ?? ''
    ];
    
    // Validasi PG Kompleks
    if ($jenis_soal == 'pilihan_ganda_kompleks') {
        if (empty($data['jawaban_kompleks']) || $data['jawaban_kompleks'] == '[]') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Pilih minimal satu jawaban benar!'];
            header("Location: kelola_soal.php");
            exit();
        }
        $jawabanArray = json_decode($data['jawaban_kompleks'], true);
        $jumlahJawabanBenar = is_array($jawabanArray) ? count($jawabanArray) : 0;
        $data['skor'] = $data['skor_per_jawaban'] * $jumlahJawabanBenar;
    }
    
    // Cek kepemilikan soal
    $stmt = $pdo->prepare("SELECT created_by, gambar_soal FROM soal WHERE id = ?");
    $stmt->execute([$id]);
    $soal_lama = $stmt->fetch();
    
    if (!$soal_lama || $soal_lama['created_by'] != $_SESSION['user_id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Anda tidak memiliki akses!'];
        header("Location: kelola_soal.php");
        exit();
    }
    
    // Upload gambar baru - menggunakan fungsi dari config.php
    $gambar_soal = $soal_lama['gambar_soal'];
    if (isset($_FILES['gambar_soal']) && $_FILES['gambar_soal']['error'] == 0) {
        $gambar_baru = uploadGambarSoal($_FILES['gambar_soal'], 'soal');
        if ($gambar_baru) {
            if ($gambar_soal && file_exists($gambar_soal)) unlink($gambar_soal);
            $gambar_soal = $gambar_baru;
        }
    }
    
    if (isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] == '1') {
        if ($gambar_soal && file_exists($gambar_soal)) unlink($gambar_soal);
        $gambar_soal = null;
    }
    
    try {
        $sql = "UPDATE soal SET mapel_id = :mapel_id, kelas = :kelas, pertanyaan = :pertanyaan, gambar_soal = :gambar_soal, video_soal = :video_soal, opsi_a = :opsi_a, opsi_b = :opsi_b, opsi_c = :opsi_c, opsi_d = :opsi_d, opsi_e = :opsi_e, jawaban_benar = :jawaban_benar, jawaban_kompleks = :jawaban_kompleks, skor_per_jawaban = :skor_per_jawaban, pasangan_jodoh = :pasangan_jodoh, skor = :skor, jenis_soal = :jenis_soal WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
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
            ':jawaban_kompleks' => $data['jawaban_kompleks'],
            ':skor_per_jawaban' => $data['skor_per_jawaban'],
            ':pasangan_jodoh' => $data['pasangan_jodoh'],
            ':skor' => $data['skor'],
            ':jenis_soal' => $jenis_soal
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Soal berhasil diperbarui!'];
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Gagal memperbarui soal: ' . $e->getMessage()];
    }
    
    header("Location: kelola_soal.php");
    exit();
}

// ==================== FUNGSI HAPUS SOAL ====================
function hapusSoal() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    if (!$id) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID soal tidak valid!'];
        header("Location: kelola_soal.php");
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT created_by, gambar_soal FROM soal WHERE id = ?");
    $stmt->execute([$id]);
    $soal = $stmt->fetch();
    
    if (!$soal || $soal['created_by'] != $_SESSION['user_id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Anda tidak memiliki akses!'];
        header("Location: kelola_soal.php");
        exit();
    }
    
    if (!empty($soal['gambar_soal']) && file_exists($soal['gambar_soal'])) {
        unlink($soal['gambar_soal']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM soal WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Soal berhasil dihapus!'];
    header("Location: kelola_soal.php");
    exit();
}
?>