<?php
// detail_ujian.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Ambil data ujian
$stmt = $pdo->prepare("SELECT u.*, mp.nama_mapel, mp.kode_mapel, g.nama as nama_guru,
                      (SELECT COUNT(*) FROM soal_ujian su WHERE su.ujian_id = u.id) as jumlah_soal,
                      (SELECT COUNT(*) FROM hasil_ujian hu WHERE hu.ujian_id = u.id) as jumlah_peserta
                      FROM ujian u 
                      JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
                      JOIN guru g ON u.created_by = g.id
                      WHERE u.id = ? AND u.created_by = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    flashMessage('danger', 'Ujian tidak ditemukan!');
    redirect('kelola_ujian.php');
}

$title = "Detail Ujian - " . $ujian['judul_ujian'];
include 'templates/header.php';
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-clipboard-list me-2"></i>Detail Ujian</h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($ujian['judul_ujian']) ?></p>
        </div>
        <div>
            <a href="kelola_ujian.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <a href="kelola_soal_ujian.php?id=<?= $ujian['id'] ?>" class="btn btn-primary">
                <i class="fas fa-cog me-2"></i>Kelola Soal
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row">
        <!-- Informasi Ujian -->
        <div class="col-lg-8">
            <!-- Card Informasi Utama -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Ujian</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="140"><strong>Judul Ujian:</strong></td>
                                    <td><?= htmlspecialchars($ujian['judul_ujian']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Mata Pelajaran:</strong></td>
                                    <td>
                                        <span class="badge bg-info"><?= $ujian['kode_mapel'] ?></span>
                                        <?= htmlspecialchars($ujian['nama_mapel']) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Pengajar:</strong></td>
                                    <td><?= htmlspecialchars($ujian['nama_guru']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-<?= $ujian['status'] == 'published' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($ujian['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="140"><strong>Waktu Mulai:</strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($ujian['waktu_mulai'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Waktu Selesai:</strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($ujian['waktu_selesai'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Durasi:</strong></td>
                                    <td><?= $ujian['durasi'] ?> menit</td>
                                </tr>
                                <tr>
                                    <td><strong>Dibuat:</strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($ujian['created_at'])) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($ujian['deskripsi'])): ?>
                    <div class="mt-3">
                        <strong>Deskripsi:</strong>
                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($ujian['deskripsi'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Daftar Soal dalam Ujian -->
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Soal</h5>
                    <span class="badge bg-light text-dark"><?= $ujian['jumlah_soal'] ?> Soal</span>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Ambil soal-soal dalam ujian
                    $stmt_soal = $pdo->prepare("SELECT s.*, su.urutan, mp.kode_mapel
                                               FROM soal_ujian su 
                                               JOIN soal s ON su.soal_id = s.id 
                                               JOIN mata_pelajaran mp ON s.mapel_id = mp.id
                                               WHERE su.ujian_id = ? 
                                               ORDER BY su.urutan");
                    $stmt_soal->execute([$ujian['id']]);
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
                                    <th width="80" class="text-center">Jawaban</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soal_ujian as $index => $soal): ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= $index + 1 ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark mb-1">
                                            <?= strip_tags(substr($soal['pertanyaan'], 0, 80)) ?><?= strlen($soal['pertanyaan']) > 80 ? '...' : '' ?>
                                        </div>
                                        <div class="text-muted small">
                                            <span class="me-2"><strong>A:</strong> <?= substr(strip_tags($soal['opsi_a']), 0, 20) ?>...</span>
                                            <span class="me-2"><strong>B:</strong> <?= substr(strip_tags($soal['opsi_b']), 0, 20) ?>...</span>
                                            <span class="me-2"><strong>C:</strong> <?= substr(strip_tags($soal['opsi_c']), 0, 20) ?>...</span>
                                            <span><strong>D:</strong> <?= substr(strip_tags($soal['opsi_d']), 0, 20) ?>...</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= $soal['kode_mapel'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <small><?= htmlspecialchars($soal['kelas']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?= strtoupper($soal['jawaban_benar']) ?></span>
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
                            <h5>Belum ada soal</h5>
                            <p class="mb-3">Tambahkan soal ke ujian ini untuk memulai</p>
                            <a href="kelola_soal_ujian.php?id=<?= $ujian['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Tambah Soal
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Statistik -->
        <div class="col-lg-4">
            <!-- Statistik Ujian -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistik Ujian</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3 bg-light">
                                <h3 class="text-primary mb-1"><?= $ujian['jumlah_soal'] ?></h3>
                                <small class="text-muted">Total Soal</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3 bg-light">
                                <h3 class="text-success mb-1"><?= $ujian['jumlah_peserta'] ?></h3>
                                <small class="text-muted">Peserta</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // Hitung statistik peserta
                    $stmt_peserta = $pdo->prepare("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
                        SUM(CASE WHEN status = 'sedang_ujian' THEN 1 ELSE 0 END) as sedang_ujian
                        FROM hasil_ujian WHERE ujian_id = ?");
                    $stmt_peserta->execute([$ujian['id']]);
                    $stat_peserta = $stmt_peserta->fetch();
                    ?>
                    
                    <div class="mt-3">
                        <strong>Status Peserta:</strong>
                        <div class="progress mt-2" style="height: 20px;">
                            <?php if ($stat_peserta['total'] > 0): ?>
                            <div class="progress-bar bg-success" style="width: <?= ($stat_peserta['selesai'] / $stat_peserta['total']) * 100 ?>%">
                                Selesai: <?= $stat_peserta['selesai'] ?>
                            </div>
                            <div class="progress-bar bg-warning" style="width: <?= ($stat_peserta['sedang_ujian'] / $stat_peserta['total']) * 100 ?>%">
                                Sedang: <?= $stat_peserta['sedang_ujian'] ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="kelola_soal_ujian.php?id=<?= $ujian['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-cog text-primary me-3"></i>
                            <div>
                                <div class="fw-bold">Kelola Soal Ujian</div>
                                <small class="text-muted">Tambah/hapus soal</small>
                            </div>
                        </a>
                        <a href="edit_ujian.php?id=<?= $ujian['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-edit text-warning me-3"></i>
                            <div>
                                <div class="fw-bold">Edit Ujian</div>
                                <small class="text-muted">Ubah informasi ujian</small>
                            </div>
                        </a>
                        <a href="hasil_ujian.php?ujian_id=<?= $ujian['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-chart-bar text-success me-3"></i>
                            <div>
                                <div class="fw-bold">Lihat Hasil</div>
                                <small class="text-muted">Nilai dan statistik</small>
                            </div>
                        </a>
                        <?php if ($ujian['status'] == 'draft'): ?>
                        <a href="proses_ujian.php?action=publish&id=<?= $ujian['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center" 
                           onclick="return confirm('Publish ujian ini?')">
                            <i class="fas fa-check text-success me-3"></i>
                            <div>
                                <div class="fw-bold">Publish Ujian</div>
                                <small class="text-muted">Tampilkan ke siswa</small>
                            </div>
                        </a>
                        <?php else: ?>
                        <a href="proses_ujian.php?action=unpublish&id=<?= $ujian['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center"
                           onclick="return confirm('Unpublish ujian ini?')">
                            <i class="fas fa-times text-danger me-3"></i>
                            <div>
                                <div class="fw-bold">Unpublish Ujian</div>
                                <small class="text-muted">Sembunyikan dari siswa</small>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Info Waktu -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Status Waktu</h5>
                </div>
                <div class="card-body text-center">
                    <?php
                    $waktu_mulai = strtotime($ujian['waktu_mulai']);
                    $waktu_selesai = strtotime($ujian['waktu_selesai']);
                    $sekarang = time();
                    
                    if ($sekarang < $waktu_mulai) {
                        $status = 'akan_datang';
                        $warna = 'warning';
                        $teks = 'Akan Datang';
                        $selisih = $waktu_mulai - $sekarang;
                        $hari = floor($selisih / (60 * 60 * 24));
                        $jam = floor(($selisih % (60 * 60 * 24)) / (60 * 60));
                    } elseif ($sekarang > $waktu_selesai) {
                        $status = 'selesai';
                        $warna = 'danger';
                        $teks = 'Selesai';
                        $hari = 0;
                        $jam = 0;
                    } else {
                        $status = 'berlangsung';
                        $warna = 'success';
                        $teks = 'Berlangsung';
                        $selisih = $waktu_selesai - $sekarang;
                        $hari = floor($selisih / (60 * 60 * 24));
                        $jam = floor(($selisih % (60 * 60 * 24)) / (60 * 60));
                    }
                    ?>
                    
                    <div class="mb-3">
                        <span class="badge bg-<?= $warna ?> fs-6"><?= $teks ?></span>
                    </div>
                    
                    <?php if ($status == 'akan_datang'): ?>
                    <div class="text-muted">
                        <small>Ujian dimulai dalam:</small>
                        <h4 class="text-warning"><?= $hari ?> hari <?= $jam ?> jam</h4>
                    </div>
                    <?php elseif ($status == 'berlangsung'): ?>
                    <div class="text-muted">
                        <small>Sisa waktu:</small>
                        <h4 class="text-success"><?= $hari ?> hari <?= $jam ?> jam</h4>
                    </div>
                    <?php else: ?>
                    <div class="text-muted">
                        <small>Ujian telah berakhir</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>