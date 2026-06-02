<?php
// kelola_ujian.php - VERSI SIMPLE
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Kelola Ujian";
include 'templates/header.php';

// Ambil daftar kelas unik dari siswa
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll();
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Kelola Ujian</h2>
        <a href="buat_ujian.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Buat Ujian Baru
        </a>
    </div>

    <?php 
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        unset($_SESSION['flash']);
    }
    ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th width="50">No</th>
                            <th>Judul Ujian</th>
                            <th width="120">Mata Pelajaran</th>
                            <th width="100">Kelas</th>
                            <th width="120">Waktu Mulai</th>
                            <th width="120">Waktu Selesai</th>
                            <th width="80">Durasi</th>
                            <th width="120">Status</th>
                            <th width="120">Peserta</th>
                            <th width="100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("SELECT u.*, mp.nama_mapel, mp.kode_mapel,
                                              (SELECT COUNT(*) FROM soal_ujian su WHERE su.ujian_id = u.id) as jumlah_soal,
                                              (SELECT COUNT(*) FROM hasil_ujian hu WHERE hu.ujian_id = u.id) as jumlah_peserta
                                              FROM ujian u 
                                              JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
                                              WHERE u.created_by = ?
                                              ORDER BY u.waktu_mulai DESC, u.created_at DESC");
                        $stmt->execute([$_SESSION['user_id']]);
                        $no = 1;
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $waktu_mulai = strtotime($row['waktu_mulai']);
                            $waktu_selesai = strtotime($row['waktu_selesai']);
                            $sekarang = time();
                            
                            // Tentukan status OTOMATIS berdasarkan waktu
                            $status_ujian = '';
                            $status_class = '';
                            $status_icon = '';
                            
                            if ($sekarang < $waktu_mulai) {
                                // Belum waktunya - Akan Datang
                                $status_ujian = 'Akan Datang';
                                $status_class = 'warning';
                                $status_icon = 'fa-clock';
                            } elseif ($sekarang >= $waktu_mulai && $sekarang <= $waktu_selesai) {
                                // Sedang berjalan - Berjalan
                                $status_ujian = 'Berjalan';
                                $status_class = 'success';
                                $status_icon = 'fa-play-circle';
                            } else {
                                // Sudah lewat - Selesai
                                $status_ujian = 'Selesai';
                                $status_class = 'secondary';
                                $status_icon = 'fa-check-circle';
                            }
                            
                            // Hitung jumlah siswa di kelas target
                            $jumlah_siswa_kelas = 0;
                            if (!empty($row['kelas_target'])) {
                                $stmt_siswa = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE kelas = ?");
                                $stmt_siswa->execute([$row['kelas_target']]);
                                $jumlah_siswa_kelas = $stmt_siswa->fetchColumn();
                            } else {
                                // Jika semua kelas, hitung total siswa
                                $stmt_siswa = $pdo->query("SELECT COUNT(*) FROM siswa");
                                $jumlah_siswa_kelas = $stmt_siswa->fetchColumn();
                            }
                            
                            $kelas_target = !empty($row['kelas_target']) ? $row['kelas_target'] : 'Semua Kelas';
                            
                            // Format durasi
                            $jam = floor($row['durasi'] / 60);
                            $menit = $row['durasi'] % 60;
                            $durasi_text = '';
                            if ($jam > 0) {
                                $durasi_text .= $jam . 'j ';
                            }
                            $durasi_text .= $menit . 'm';
                            
                            // Hitung countdown atau waktu tersisa
                            $waktu_info = '';
                            if ($sekarang < $waktu_mulai) {
                                $selisih = $waktu_mulai - $sekarang;
                                $hari = floor($selisih / (60 * 60 * 24));
                                $jam = floor(($selisih % (60 * 60 * 24)) / (60 * 60));
                                $waktu_info = "<br><small class='text-muted'>Mulai dalam: $hari hari $jam jam</small>";
                            } elseif ($sekarang <= $waktu_selesai) {
                                $selisih = $waktu_selesai - $sekarang;
                                $jam = floor($selisih / (60 * 60));
                                $menit = floor(($selisih % (60 * 60)) / 60);
                                $waktu_info = "<br><small class='text-muted'>Sisa: $jam jam $menit menit</small>";
                            } else {
                                $waktu_info = "<br><small class='text-muted'>Sudah berakhir</small>";
                            }
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['judul_ujian']) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= $row['kode_mapel'] ?></span><br>
                                <small><?= htmlspecialchars($row['nama_mapel']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?= htmlspecialchars($kelas_target) ?></span>
                            </td>
                            <td>
                                <?= date('d/m/Y H:i', $waktu_mulai) ?>
                            </td>
                            <td>
                                <?= date('d/m/Y H:i', $waktu_selesai) ?>
                            </td>
                            <td class="text-center">
                                <strong><?= $durasi_text ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-<?= $status_class ?>">
                                    <i class="fas <?= $status_icon ?> me-1"></i><?= $status_ujian ?>
                                </span>
                                <?= $waktu_info ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $row['jumlah_peserta'] > 0 ? 'info' : 'secondary' ?>">
                                    <?= $row['jumlah_peserta'] ?>/<?= $jumlah_siswa_kelas ?>
                                </span>
                                <br>
                                <small class="text-muted">Siswa</small>
                            </td>
                            <td>
                                <div class="btn-group-vertical btn-group-sm w-100">
                                    <a href="kelola_soal_ujian_auto.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm mb-1" title="Kelola Soal">
                                        <i class="fas fa-list me-1"></i>Soal
                                    </a>
                                    <?php if ($sekarang < $waktu_mulai): ?>
                                        <?php if ($row['status'] == 'draft'): ?>
                                        <button class="btn btn-success btn-sm mb-1" onclick="publishUjian(<?= $row['id'] ?>)" title="Publish Ujian">
                                            <i class="fas fa-check me-1"></i>Publish
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-warning btn-sm mb-1" onclick="unpublishUjian(<?= $row['id'] ?>)" title="Unpublish Ujian">
                                            <i class="fas fa-times me-1"></i>Unpublish
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button class="btn btn-danger btn-sm" onclick="hapusUjian(<?= $row['id'] ?>)" title="Hapus Ujian">
                                        <i class="fas fa-trash me-1"></i>Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($stmt->rowCount() == 0): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="fas fa-clipboard-list fa-3x mb-3"></i><br>
                                Belum ada ujian yang dibuat<br>
                                <a href="buat_ujian.php" class="btn btn-primary mt-2">Buat Ujian Pertama</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function hapusUjian(id) {
    if (confirm('Apakah Anda yakin ingin menghapus ujian ini?')) {
        window.location.href = 'proses_ujian.php?action=hapus&id=' + id;
    }
}

function publishUjian(id) {
    if (confirm('Apakah Anda yakin ingin mempublish ujian ini?')) {
        window.location.href = 'proses_ujian.php?action=publish&id=' + id;
    }
}

function unpublishUjian(id) {
    if (confirm('Apakah Anda yakin ingin unpublish ujian ini?')) {
        window.location.href = 'proses_ujian.php?action=unpublish&id=' + id;
    }
}

// Auto refresh halaman setiap 1 menit untuk update status real-time
setTimeout(function() {
    location.reload();
}, 60000);
</script>

<?php include 'templates/footer.php'; ?>