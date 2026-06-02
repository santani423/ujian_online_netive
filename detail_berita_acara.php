<?php
// detail_berita_acara.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Ambil data berita acara
$query = "SELECT ba.*, u.judul_ujian, g.nama as nama_guru 
          FROM berita_acara ba 
          JOIN ujian u ON ba.ujian_id = u.id 
          JOIN guru g ON ba.guru_pengawas = g.id 
          WHERE ba.id = ?";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        flashMessage('danger', 'Data tidak ditemukan!');
        header("Location: berita_acara.php");
        exit();
    }
} catch (PDOException $e) {
    flashMessage('danger', 'Error: ' . $e->getMessage());
    header("Location: berita_acara.php");
    exit();
}

// Ambil profil sekolah
$profil_sekolah = getProfilSekolah();

$title = "Detail Berita Acara";
include 'templates/header.php';
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-file-alt me-2"></i>Detail Berita Acara</h2>
        <div>
            <a href="cetak_berita_acara.php?id=<?= $id ?>" target="_blank" class="btn btn-success me-2">
                <i class="fas fa-print me-2"></i>Cetak
            </a>
            <a href="berita_acara.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Detail Berita Acara -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">BERITA ACARA PELAKSANAAN UJIAN</h4>
        </div>
        <div class="card-body">
            <!-- Header Sekolah -->
            <div class="row mb-4">
                <div class="col-md-12 text-center">
                    <h5><?= htmlspecialchars($profil_sekolah['nama_sekolah']) ?></h5>
                    <?php if ($profil_sekolah['alamat']): ?>
                        <p class="mb-0"><?= htmlspecialchars($profil_sekolah['alamat']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informasi Umum -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 40%">Nama Ujian</th>
                            <td><?= htmlspecialchars($data['judul_ujian']) ?></td>
                        </tr>
                        <tr>
                            <th>Tanggal Ujian</th>
                            <td><?= date('d/m/Y', strtotime($data['tanggal_ujian'])) ?></td>
                        </tr>
                        <tr>
                            <th>Waktu</th>
                            <td><?= $data['waktu_mulai'] . ' - ' . $data['waktu_selesai'] ?></td>
                        </tr>
                        <tr>
                            <th>Kelas</th>
                            <td><?= htmlspecialchars($data['kelas']) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 40%">Guru Pengawas</th>
                            <td><?= htmlspecialchars($data['nama_guru']) ?></td>
                        </tr>
                        <tr>
                            <th>Ruangan</th>
                            <td><?= htmlspecialchars($data['ruangan']) ?></td>
                        </tr>
                        <tr>
                            <th>Total Peserta</th>
                            <td><?= $data['jumlah_peserta'] ?> siswa</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php
                                $status_class = [
                                    'draft' => 'secondary',
                                    'selesai' => 'primary',
                                    'diverifikasi' => 'success'
                                ];
                                $status_text = [
                                    'draft' => 'Draft',
                                    'selesai' => 'Selesai',
                                    'diverifikasi' => 'Diverifikasi'
                                ];
                                ?>
                                <span class="badge bg-<?= $status_class[$data['status']] ?>">
                                    <?= $status_text[$data['status']] ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Statistik Peserta -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h5 class="border-bottom pb-2">DATA PESERTA UJIAN</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Total Peserta</h6>
                                    <h2><?= $data['jumlah_peserta'] ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Hadir</h6>
                                    <h2><?= $data['jumlah_hadir'] ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-danger text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Tidak Hadir</h6>
                                    <h2><?= $data['jumlah_tidak_hadir'] ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-info text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Persentase Hadir</h6>
                                    <h2>
                                        <?php 
                                        if ($data['jumlah_peserta'] > 0) {
                                            echo round(($data['jumlah_hadir'] / $data['jumlah_peserta']) * 100, 2) . '%';
                                        } else {
                                            echo '0%';
                                        }
                                        ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catatan -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h5 class="border-bottom pb-2">CATATAN PELAKSANAAN</h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kejadian Penting:</label>
                        <div class="card">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($data['kejadian_penting'] ?: '- Tidak ada kejadian penting -')) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kendala Teknis:</label>
                        <div class="card">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($data['kendala_teknis'] ?: '- Tidak ada kendala teknis -')) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tindak Lanjut:</label>
                        <div class="card">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($data['tindak_lanjut'] ?: '- Tidak ada tindak lanjut -')) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Sistem -->
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Dibuat Pada:</label>
                        <p><?= formatWaktu($data['created_at']) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Terakhir Diupdate:</label>
                        <p><?= formatWaktu($data['updated_at']) ?></p>
                    </div>
                </div>
                
                <div class="col-md-4 text-center">
                    <div class="border p-4">
                        <p class="mb-5">Hormat Kami,</p>
                        <p class="fw-bold"><?= htmlspecialchars($data['nama_guru']) ?></p>
                        <p>Guru Pengawas Ujian</p>
                        <p>NIP: -</p>
                    </div>
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <div>
                            <?php if ($data['status'] == 'draft'): ?>
                                <button class="btn btn-success" onclick="selesaikanBeritaAcara(<?= $id ?>)">
                                    <i class="fas fa-check me-2"></i>Tandai Selesai
                                </button>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="berita_acara.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selesaikanBeritaAcara(id) {
    if (confirm('Apakah Anda yakin ingin menandai berita acara ini sebagai SELESAI?')) {
        window.location.href = 'proses_berita_acara.php?action=edit&selesai=' + id;
    }
}
</script>

<?php include 'templates/footer.php'; ?>