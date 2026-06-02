<?php
// proses_soal_guru.php - Versi dengan support Multi Kelas untuk guru
ob_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Dapatkan guru_id dari tabel guru berdasarkan user_id
$stmt = $pdo->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$guru_data = $stmt->fetch(PDO::FETCH_ASSOC);
$guru_table_id = $guru_data['id'] ?? 0;

// Tentukan action dari POST atau GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Jika action dari form (method POST)
if ($action == 'tambah') {
    tambahSoal();
} elseif ($action == 'edit') {
    editSoal();
} elseif ($action == 'hapus') {
    hapusSoal();
} else {
    // Jika tidak ada action yang dikenali
    flashMessage('danger', 'Aksi tidak valid!');
    header("Location: kelola_soal_guru.php");
    exit();
}

/**
 * Tambah soal baru
 */
function tambahSoal() {
    global $pdo, $guru_table_id;
    
    $jenis_soal = $_POST['jenis_soal'] ?? 'pilihan_ganda';
    
    // Ambil kelas (bisa dari select biasa atau dari hidden field multi kelas)
    $kelas = $_POST['kelas'] ?? '';
    
    // Data dasar
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
    
    // Validasi berdasarkan jenis soal
    if ($jenis_soal == 'pilihan_ganda') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan']) || 
            empty($data['opsi_a']) || empty($data['opsi_b']) || empty($data['opsi_c']) || 
            empty($data['opsi_d']) || empty($data['opsi_e']) || empty($data['jawaban_benar'])) {
            flashMessage('danger', 'Semua field wajib diisi untuk pilihan ganda (A-E)!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
    } 
    elseif ($jenis_soal == 'pilihan_ganda_kompleks') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan'])) {
            flashMessage('danger', 'Field wajib diisi untuk pilihan ganda kompleks!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        
        if (empty($data['jawaban_kompleks']) || $data['jawaban_kompleks'] == '[]') {
            flashMessage('danger', 'Pilih minimal satu jawaban benar!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        
        $data['skor_per_jawaban'] = (int)$data['skor_per_jawaban'];
        if ($data['skor_per_jawaban'] < 1) {
            flashMessage('danger', 'Skor per jawaban harus minimal 1!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        
        $jawabanArray = json_decode($data['jawaban_kompleks'], true);
        $jumlahJawabanBenar = is_array($jawabanArray) ? count($jawabanArray) : 0;
        $data['skor'] = $data['skor_per_jawaban'] * $jumlahJawabanBenar;
    }
    elseif ($jenis_soal == 'essay') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan'])) {
            flashMessage('danger', 'Field wajib diisi untuk essay!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        $data['jawaban_benar'] = 'a';
    }
    elseif ($jenis_soal == 'menjodohkan') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan']) ||
            empty($data['opsi_a']) || empty($data['opsi_b']) || empty($data['pasangan_jodoh'])) {
            flashMessage('danger', 'Semua field wajib diisi untuk soal menjodohkan!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        $data['jawaban_benar'] = 'a';
    }
    elseif ($jenis_soal == 'benar_salah') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan']) ||
            empty($data['jawaban_benar'])) {
            flashMessage('danger', 'Field wajib diisi untuk soal benar/salah!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
    }
    
    // Validasi skor
    $data['skor'] = (int)$data['skor'];
    if ($data['skor'] < 1 || $data['skor'] > 100) {
        flashMessage('danger', 'Skor harus antara 1-100');
        header("Location: kelola_soal_guru.php");
        exit();
    }
    
    // Upload gambar jika ada (gunakan fungsi dari config.php)
    $gambar_soal = null;
    if (isset($_FILES['gambar_soal']) && $_FILES['gambar_soal']['error'] == 0) {
        $gambar_soal = uploadGambarSoal($_FILES['gambar_soal'], 'soal');
    }
    
    try {
        $sql = "INSERT INTO soal (mapel_id, kelas, pertanyaan, gambar_soal, video_soal,
                opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, jawaban_benar, jawaban_kompleks,
                skor_per_jawaban, pasangan_jodoh, created_by, skor, jenis_soal)
                VALUES (:mapel_id, :kelas, :pertanyaan, :gambar_soal, :video_soal,
                :opsi_a, :opsi_b, :opsi_c, :opsi_d, :opsi_e, :jawaban_benar, :jawaban_kompleks,
                :skor_per_jawaban, :pasangan_jodoh, :created_by, :skor, :jenis_soal)";
        
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
            ':created_by' => $guru_table_id,
            ':skor' => $data['skor'],
            ':jenis_soal' => $jenis_soal
        ]);
        
        flashMessage('success', 'Soal berhasil ditambahkan!');
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Gagal menambah soal: ' . $e->getMessage());
    }
    
    header("Location: kelola_soal_guru.php");
    exit();
}

/**
 * Edit soal
 */
function editSoal() {
    global $pdo, $guru_table_id;
    
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        flashMessage('danger', 'ID soal tidak valid!');
        header("Location: kelola_soal_guru.php");
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
    
    // Validasi berdasarkan jenis soal
    if ($jenis_soal == 'pilihan_ganda') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan']) || 
            empty($data['opsi_a']) || empty($data['opsi_b']) || empty($data['opsi_c']) || 
            empty($data['opsi_d']) || empty($data['opsi_e']) || empty($data['jawaban_benar'])) {
            flashMessage('danger', 'Semua field wajib diisi untuk pilihan ganda (A-E)!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
    } 
    elseif ($jenis_soal == 'pilihan_ganda_kompleks') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan'])) {
            flashMessage('danger', 'Field wajib diisi untuk pilihan ganda kompleks!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        
        if (empty($data['jawaban_kompleks']) || $data['jawaban_kompleks'] == '[]') {
            flashMessage('danger', 'Pilih minimal satu jawaban benar!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        
        $data['skor_per_jawaban'] = (int)$data['skor_per_jawaban'];
        if ($data['skor_per_jawaban'] < 1) {
            flashMessage('danger', 'Skor per jawaban harus minimal 1!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        
        $jawabanArray = json_decode($data['jawaban_kompleks'], true);
        $jumlahJawabanBenar = is_array($jawabanArray) ? count($jawabanArray) : 0;
        $data['skor'] = $data['skor_per_jawaban'] * $jumlahJawabanBenar;
    }
    elseif ($jenis_soal == 'essay') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan'])) {
            flashMessage('danger', 'Field wajib diisi untuk essay!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        $data['jawaban_benar'] = 'a';
    }
    elseif ($jenis_soal == 'menjodohkan') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan']) ||
            empty($data['opsi_a']) || empty($data['opsi_b']) || empty($data['pasangan_jodoh'])) {
            flashMessage('danger', 'Semua field wajib diisi untuk soal menjodohkan!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
        $data['jawaban_benar'] = 'a';
    }
    elseif ($jenis_soal == 'benar_salah') {
        if (empty($data['mapel_id']) || empty($data['kelas']) || empty($data['pertanyaan']) ||
            empty($data['jawaban_benar'])) {
            flashMessage('danger', 'Field wajib diisi untuk soal benar/salah!');
            header("Location: kelola_soal_guru.php");
            exit();
        }
    }
    
    // Validasi skor
    $data['skor'] = (int)$data['skor'];
    if ($data['skor'] < 1 || $data['skor'] > 100) {
        flashMessage('danger', 'Skor harus antara 1-100');
        header("Location: kelola_soal_guru.php");
        exit();
    }
    
    // Cek kepemilikan soal
    $stmt = $pdo->prepare("SELECT created_by, gambar_soal FROM soal WHERE id = ?");
    $stmt->execute([$id]);
    $soal_lama = $stmt->fetch();
    
    if (!$soal_lama) {
        flashMessage('danger', 'Soal tidak ditemukan!');
        header("Location: kelola_soal_guru.php");
        exit();
    }
    
    if ($soal_lama['created_by'] != $guru_table_id) {
        flashMessage('danger', 'Anda tidak memiliki akses untuk mengedit soal ini!');
        header("Location: kelola_soal_guru.php");
        exit();
    }
    
    // Upload gambar baru jika ada (gunakan fungsi dari config.php)
    $gambar_soal = $soal_lama['gambar_soal'];
    if (isset($_FILES['gambar_soal']) && $_FILES['gambar_soal']['error'] == 0) {
        $gambar_baru = uploadGambarSoal($_FILES['gambar_soal'], 'soal');
        if ($gambar_baru) {
            if ($gambar_soal && file_exists($gambar_soal)) {
                unlink($gambar_soal);
            }
            $gambar_soal = $gambar_baru;
        }
    }
    
    if (isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] == '1') {
        if ($gambar_soal && file_exists($gambar_soal)) {
            unlink($gambar_soal);
        }
        $gambar_soal = null;
    }
    
    try {
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
                jawaban_kompleks = :jawaban_kompleks,
                skor_per_jawaban = :skor_per_jawaban,
                pasangan_jodoh = :pasangan_jodoh,
                skor = :skor,
                jenis_soal = :jenis_soal
                WHERE id = :id";
        
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
        
        flashMessage('success', 'Soal berhasil diperbarui!');
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Gagal memperbarui soal: ' . $e->getMessage());
    }
    
    header("Location: kelola_soal_guru.php");
    exit();
}

/**
 * Hapus soal
 */
function hapusSoal() {
    global $pdo, $guru_table_id;
    
    $id = $_GET['id'] ?? 0;
    
    if (empty($id)) {
        flashMessage('danger', 'ID soal tidak valid!');
        header("Location: kelola_soal_guru.php");
        exit();
    }
    
    // Cek kepemilikan soal
    $stmt = $pdo->prepare("SELECT created_by, gambar_soal FROM soal WHERE id = ?");
    $stmt->execute([$id]);
    $soal = $stmt->fetch();
    
    if (!$soal) {
        flashMessage('danger', 'Soal tidak ditemukan!');
        header("Location: kelola_soal_guru.php");
        exit();
    }
    
    if ($soal['created_by'] != $guru_table_id) {
        flashMessage('danger', 'Anda tidak memiliki akses untuk menghapus soal ini!');
        header("Location: kelola_soal_guru.php");
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
        
    } catch (PDOException $e) {
        flashMessage('danger', 'Gagal menghapus soal: ' . $e->getMessage());
    }
    
    header("Location: kelola_soal_guru.php");
    exit();
}
?>