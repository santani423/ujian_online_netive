<?php
// kelola_soal_ujian.php - Mengelola soal dalam ujian tertentu
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$ujian_id = $_GET['id'] ?? 0;

// Ambil data ujian
$stmt = $pdo->prepare("SELECT u.*, mp.nama_mapel, mp.kode_mapel 
                      FROM ujian u 
                      JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
                      WHERE u.id = ? AND u.created_by = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    flashMessage('danger', 'Ujian tidak ditemukan!');
    redirect('kelola_ujian.php');
}

$title = "Kelola Soal - " . $ujian['judul_ujian'];
include 'templates/header.php';

// Handle tambah soal ke ujian
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_soal'])) {
    $soal_id = $_POST['soal_id'];
    
    try {
        // Cek apakah soal sudah ada di ujian
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal_ujian WHERE ujian_id = ? AND soal_id = ?");
        $stmt->execute([$ujian_id, $soal_id]);
        
        if ($stmt->fetchColumn() == 0) {
            // Get current max urutan
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 as urutan_baru FROM soal_ujian WHERE ujian_id = ?");
            $stmt->execute([$ujian_id]);
            $urutan = $stmt->fetchColumn();
            
            // Tambahkan soal ke ujian
            $stmt = $pdo->prepare("INSERT INTO soal_ujian (ujian_id, soal_id, urutan) VALUES (?, ?, ?)");
            $stmt->execute([$ujian_id, $soal_id, $urutan]);
            
            flashMessage('success', 'Soal berhasil ditambahkan ke ujian!');
        } else {
            flashMessage('warning', 'Soal sudah ada dalam ujian!');
        }
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal menambahkan soal: ' . $e->getMessage());
    }
    
    redirect("kelola_soal_ujian.php?id=$ujian_id");
}

// Handle hapus soal dari ujian
if (isset($_GET['hapus_soal'])) {
    $soal_ujian_id = $_GET['hapus_soal'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM soal_ujian WHERE id = ? AND ujian_id = ?");
        $stmt->execute([$soal_ujian_id, $ujian_id]);
        
        flashMessage('success', 'Soal berhasil dihapus dari ujian!');
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal menghapus soal: ' . $e->getMessage());
    }
    
    redirect("kelola_soal_ujian.php?id=$ujian_id");
}
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-cog me-2"></i>Kelola Soal Ujian</h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($ujian['judul_ujian']) ?></p>
        </div>
        <div>
            <a href="detail_ujian.php?id=<?= $ujian_id ?>" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <a href="kelola_soal.php" class="btn btn-primary" target="_blank">
                <i class="fas fa-plus me-2"></i>Buat Soal Baru
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row">
        <!-- Daftar Soal dalam Ujian -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Soal dalam Ujian</h5>
                    <?php
                    $stmt_jumlah = $pdo->prepare("SELECT COUNT(*) FROM soal_ujian WHERE ujian_id = ?");
                    $stmt_jumlah->execute([$ujian_id]);
                    $jumlah_soal = $stmt_jumlah->fetchColumn();
                    ?>
                    <span class="badge bg-light text-dark"><?= $jumlah_soal ?> Soal</span>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Ambil soal-soal dalam ujian
                    $stmt_soal = $pdo->prepare("SELECT su.id as soal_ujian_id, s.*, su.urutan, mp.kode_mapel
                                               FROM soal_ujian su 
                                               JOIN soal s ON su.soal_id = s.id 
                                               JOIN mata_pelajaran mp ON s.mapel_id = mp.id
                                               WHERE su.ujian_id = ? 
                                               ORDER BY su.urutan");
                    $stmt_soal->execute([$ujian_id]);
                    $soal_ujian = $stmt_soal->fetchAll();
                    
                    if (count($soal_ujian) > 0): 
                    ?>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="60" class="text-center">No</th>
                                    <th>Pertanyaan</th>
                                    <th width="120" class="text-center">Mapel</th>
                                    <th width="100" class="text-center">Kelas</th>
                                    <th width="100" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soal_ujian as $index => $soal): ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= $soal['urutan'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark mb-1">
                                            <?= htmlspecialchars(substr($soal['pertanyaan'], 0, 80)) ?><?= strlen($soal['pertanyaan']) > 80 ? '...' : '' ?>
                                        </div>
                                        <div class="text-muted small">
                                            Jawaban: <span class="badge bg-success"><?= strtoupper($soal['jawaban_benar']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= $soal['kode_mapel'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <small><?= htmlspecialchars($soal['kelas']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="hapusSoalDariUjian(<?= $soal['soal_ujian_id'] ?>)"
                                                title="Hapus dari ujian">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <h5>Belum ada soal dalam ujian</h5>
                            <p class="mb-0">Tambahkan soal dari bank soal di sidebar</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar - Tambah Soal -->
        <div class="col-lg-4">
            <!-- Form Tambah Soal -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Tambah Soal ke Ujian</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Soal</label>
                            <select class="form-select" name="soal_id" required>
                                <option value="">Pilih Soal dari Bank Soal</option>
                                <?php
                                // Ambil soal yang belum ada di ujian ini
                                $stmt_soal_bank = $pdo->prepare("SELECT s.*, mp.kode_mapel, mp.nama_mapel 
                                                                FROM soal s 
                                                                JOIN mata_pelajaran mp ON s.mapel_id = mp.id 
                                                                WHERE s.mapel_id = ? 
                                                                AND s.id NOT IN (
                                                                    SELECT soal_id FROM soal_ujian WHERE ujian_id = ?
                                                                )
                                                                ORDER BY s.created_at DESC");
                                $stmt_soal_bank->execute([$ujian['mapel_id'], $ujian_id]);
                                $soal_bank = $stmt_soal_bank->fetchAll();
                                
                                if (count($soal_bank) > 0):
                                    foreach ($soal_bank as $soal): 
                                ?>
                                <option value="<?= $soal['id'] ?>">
                                    [<?= $soal['kode_mapel'] ?>] <?= htmlspecialchars(substr($soal['pertanyaan'], 0, 50)) ?>...
                                </option>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                <option value="" disabled>Tidak ada soal tersedia</option>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">
                                Pilih soal dari bank soal mata pelajaran <?= $ujian['nama_mapel'] ?>
                            </div>
                        </div>
                        
                        <?php if (count($soal_bank) > 0): ?>
                        <button type="submit" name="tambah_soal" class="btn btn-success w-100">
                            <i class="fas fa-plus me-2"></i>Tambah ke Ujian
                        </button>
                        <?php else: ?>
                        <a href="kelola_soal.php" class="btn btn-warning w-100" target="_blank">
                            <i class="fas fa-plus me-2"></i>Buat Soal Baru
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Info Ujian -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi</h5>
                </div>
                <div class="card-body">
                    <p><strong>Mata Pelajaran:</strong><br>
                    <?= htmlspecialchars($ujian['kode_mapel']) ?> - <?= htmlspecialchars($ujian['nama_mapel']) ?></p>
                    
                    <p><strong>Total Soal:</strong><br>
                    <?= $jumlah_soal ?> soal</p>
                    
                    <p><strong>Status:</strong><br>
                    <span class="badge bg-<?= $ujian['status'] == 'published' ? 'success' : 'secondary' ?>">
                        <?= ucfirst($ujian['status']) ?>
                    </span></p>
                    
                    <div class="alert alert-warning mt-3">
                        <small>
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian:</strong> Pastikan soal sudah lengkap sebelum mempublish ujian.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function hapusSoalDariUjian(soalUjianId) {
    if (confirm('Apakah Anda yakin ingin menghapus soal ini dari ujian?')) {
        window.location.href = 'kelola_soal_ujian.php?id=<?= $ujian_id ?>&hapus_soal=' + soalUjianId;
    }
}
</script>

<?php include 'templates/footer.php'; ?>