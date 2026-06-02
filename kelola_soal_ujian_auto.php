<?php
// kelola_soal_ujian_auto.php - Auto isi soal berdasarkan kelas
ob_start();
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

// Handle auto tambah soal berdasarkan kelas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['auto_tambah_soal'])) {
    $jumlah_soal = (int)$_POST['jumlah_soal'] ?? 10;
    
    try {
        // Hapus soal lama dari ujian
        $stmt = $pdo->prepare("DELETE FROM soal_ujian WHERE ujian_id = ?");
        $stmt->execute([$ujian_id]);
        
        // PERBAIKAN: Query tanpa parameter binding untuk LIMIT
        $where_soal = "WHERE s.mapel_id = " . intval($ujian['mapel_id']);
        
        if (!empty($ujian['kelas_target'])) {
            $kelas_escaped = $pdo->quote($ujian['kelas_target']);
            $where_soal .= " AND s.kelas = $kelas_escaped";
        }
        
        // Gunakan query langsung untuk menghindari masalah LIMIT parameter binding
        $sql_soal = "SELECT s.id FROM soal s $where_soal ORDER BY RAND() LIMIT $jumlah_soal";
        
        $stmt_soal = $pdo->query($sql_soal);
        $soal_acak = $stmt_soal->fetchAll();
        
        // Tambahkan soal ke ujian
        $urutan = 1;
        foreach ($soal_acak as $soal) {
            $stmt_tambah = $pdo->prepare("INSERT INTO soal_ujian (ujian_id, soal_id, urutan) VALUES (?, ?, ?)");
            $stmt_tambah->execute([$ujian_id, $soal['id'], $urutan++]);
        }
        
        $jumlah_berhasil = count($soal_acak);
        flashMessage('success', "Berhasil menambahkan $jumlah_berhasil soal ke ujian secara otomatis!");
        
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal menambahkan soal: ' . $e->getMessage());
        // Untuk debugging, bisa uncomment line berikut:
        // flashMessage('danger', 'SQL Error: ' . $e->getMessage() . ' - Query: ' . $sql_soal);
    }
    
    redirect("kelola_soal_ujian_auto.php?id=$ujian_id");
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
    
    redirect("kelola_soal_ujian_auto.php?id=$ujian_id");
}

// Hitung jumlah soal dalam ujian
$stmt_jumlah = $pdo->prepare("SELECT COUNT(*) FROM soal_ujian WHERE ujian_id = ?");
$stmt_jumlah->execute([$ujian_id]);
$jumlah_soal = $stmt_jumlah->fetchColumn();

// Hitung total soal tersedia di bank soal - PERBAIKAN
$where_total = "WHERE s.mapel_id = " . intval($ujian['mapel_id']);

if (!empty($ujian['kelas_target'])) {
    $kelas_escaped = $pdo->quote($ujian['kelas_target']);
    $where_total .= " AND s.kelas = $kelas_escaped";
}

$sql_total = "SELECT COUNT(*) as total FROM soal s $where_total";
$stmt_total = $pdo->query($sql_total);
$total_soal_tersedia = $stmt_total->fetchColumn();
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-cog me-2"></i>Kelola Soal Ujian</h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($ujian['judul_ujian']) ?></p>
        </div>
        <div>
            <a href="kelola_ujian.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <a href="kelola_soal.php" class="btn btn-primary" target="_blank">
                <i class="fas fa-plus me-2"></i>Buat Soal Baru
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row">
        <!-- Auto Tambah Soal -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-magic me-2"></i>Auto Generate Soal</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Jumlah Soal</label>
                            <input type="number" class="form-control" name="jumlah_soal" value="10" min="1" max="<?= $total_soal_tersedia ?>" required>
                            <div class="form-text">
                                Maksimal: <?= $total_soal_tersedia ?> soal tersedia
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Informasi:</h6>
                            <small>
                                - Soal akan diambil secara acak dari bank soal<br>
                                - Mapel: <strong><?= $ujian['nama_mapel'] ?></strong><br>
                                - Kelas: <strong><?= !empty($ujian['kelas_target']) ? $ujian['kelas_target'] : 'Semua Kelas' ?></strong><br>
                                - Soal lama akan diganti dengan yang baru
                            </small>
                        </div>
                        
                        <button type="submit" name="auto_tambah_soal" class="btn btn-success w-100">
                            <i class="fas fa-bolt me-2"></i>Generate Soal Otomatis
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Ujian -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Ujian</h5>
                </div>
                <div class="card-body">
                    <p><strong>Mata Pelajaran:</strong><br>
                    <?= $ujian['kode_mapel'] ?> - <?= $ujian['nama_mapel'] ?></p>
                    
                    <p><strong>Kelas Target:</strong><br>
                    <span class="badge bg-primary"><?= !empty($ujian['kelas_target']) ? $ujian['kelas_target'] : 'Semua Kelas' ?></span></p>
                    
                    <p><strong>Total Soal:</strong><br>
                    <span class="badge bg-<?= $jumlah_soal > 0 ? 'success' : 'danger' ?>">
                        <?= $jumlah_soal ?> soal
                    </span></p>
                    
                    <p><strong>Soal Tersedia:</strong><br>
                    <span class="badge bg-<?= $total_soal_tersedia > 0 ? 'info' : 'warning' ?>">
                        <?= $total_soal_tersedia ?> soal
                    </span></p>
                    
                    <p><strong>Status:</strong><br>
                    <span class="badge bg-<?= $ujian['status'] == 'published' ? 'success' : 'secondary' ?>">
                        <?= ucfirst($ujian['status']) ?>
                    </span></p>
                    
                    <?php if ($jumlah_soal > 0 && $ujian['status'] == 'draft'): ?>
                    <div class="alert alert-success mt-3">
                        <small>
                            <i class="fas fa-check me-2"></i>
                            Ujian sudah memiliki soal. Anda bisa publish sekarang.
                        </small>
                        <br>
                        <a href="proses_ujian.php?action=publish&id=<?= $ujian_id ?>" 
                           class="btn btn-sm btn-success mt-2 w-100"
                           onclick="return confirm('Publish ujian ini?')">
                            <i class="fas fa-check me-2"></i>Publish Ujian
                        </a>
                    </div>
                    <?php elseif ($jumlah_soal == 0): ?>
                    <div class="alert alert-warning mt-3">
                        <small>
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian:</strong> Tambahkan soal sebelum mempublish ujian.
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Daftar Soal dalam Ujian -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Soal dalam Ujian</h5>
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
                                    <th width="100" class="text-center">Kelas</th>
                                    <th width="80" class="text-center">Jawaban</th>
                                    <th width="60" class="text-center">Aksi</th>
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
                                            <?= strip_tags(substr($soal['pertanyaan'], 0, 80)) ?><?= strlen($soal['pertanyaan']) > 80 ? '...' : '' ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <small><?= htmlspecialchars($soal['kelas']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?= strtoupper($soal['jawaban_benar']) ?></span>
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
                            <p class="mb-0">Gunakan form di sidebar untuk generate soal otomatis</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function hapusSoalDariUjian(soalUjianId) {
    if (confirm('Apakah Anda yakin ingin menghapus soal ini dari ujian?')) {
        window.location.href = 'kelola_soal_ujian_auto.php?id=<?= $ujian_id ?>&hapus_soal=' + soalUjianId;
    }
}
</script>

<?php include 'templates/footer.php'; ?>