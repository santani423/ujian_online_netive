<?php
// edit_ujian.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Ambil data ujian
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND created_by = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    flashMessage('danger', 'Ujian tidak ditemukan!');
    redirect('kelola_ujian.php');
}

$title = "Edit Ujian";
include 'templates/header.php';

// Ambil data mata pelajaran
$mapel_stmt = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel");
$mapel_list = $mapel_stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mapel_id = $_POST['mapel_id'];
    $judul_ujian = $_POST['judul_ujian'];
    $deskripsi = $_POST['deskripsi'];
    $waktu_mulai = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];
    $durasi = $_POST['durasi'];
    
    try {
        $stmt = $pdo->prepare("UPDATE ujian SET mapel_id = ?, judul_ujian = ?, deskripsi = ?, 
                              waktu_mulai = ?, waktu_selesai = ?, durasi = ? 
                              WHERE id = ? AND created_by = ?");
        $stmt->execute([$mapel_id, $judul_ujian, $deskripsi, $waktu_mulai, $waktu_selesai, $durasi, $id, $_SESSION['user_id']]);
        
        flashMessage('success', 'Ujian berhasil diperbarui!');
        redirect('kelola_ujian.php');
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal memperbarui ujian: ' . $e->getMessage());
    }
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Edit Ujian</h2>
        <a href="kelola_ujian.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran</label>
                            <select name="mapel_id" class="form-select" required>
                                <option value="">Pilih Mata Pelajaran</option>
                                <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?= $mapel['id'] ?>" <?= $mapel['id'] == $ujian['mapel_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mapel['kode_mapel']) ?> - <?= htmlspecialchars($mapel['nama_mapel']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Judul Ujian</label>
                            <input type="text" name="judul_ujian" class="form-control" required 
                                   value="<?= htmlspecialchars($ujian['judul_ujian']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Ujian</label>
                            <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($ujian['deskripsi']) ?></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Waktu Mulai</label>
                            <input type="datetime-local" name="waktu_mulai" class="form-control" required 
                                   value="<?= date('Y-m-d\TH:i', strtotime($ujian['waktu_mulai'])) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Waktu Selesai</label>
                            <input type="datetime-local" name="waktu_selesai" class="form-control" required 
                                   value="<?= date('Y-m-d\TH:i', strtotime($ujian['waktu_selesai'])) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Durasi Ujian (menit)</label>
                            <input type="number" name="durasi" class="form-control" required 
                                   min="1" max="480" value="<?= $ujian['durasi'] ?>">
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>