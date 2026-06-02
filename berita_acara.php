<?php
// berita_acara.php - Halaman daftar berita acara dengan cetak per kelas
require_once 'config.php';

// Debug: Cek session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan user sudah login dan role adalah guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Berita Acara Ujian";

// Include header
if (file_exists('templates/header.php')) {
    include 'templates/header.php';
} else {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $title; ?> - Sistem Ujian Online</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { background-color: #f8f9fa; }
            .card { border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .table th { background-color: #f8f9fa; }
        </style>
    </head>
    <body>
    <?php
}

// Ambil data berita acara
$query = "SELECT ba.*, u.judul_ujian, u.kelas_target, g.nama as nama_guru, g.nip
          FROM berita_acara ba 
          JOIN ujian u ON ba.ujian_id = u.id 
          JOIN guru g ON ba.guru_pengawas = g.id 
          ORDER BY ba.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $berita_acara = $stmt->fetchAll();
} catch (PDOException $e) {
    $berita_acara = [];
    error_log("Database error in berita_acara query: " . $e->getMessage());
}

// Ambil profil sekolah untuk header
$profil_sekolah = getProfilSekolah();

// Ambil daftar kelas dari siswa
$kelas_list = [];
try {
    $kelas_query = $pdo->query("SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas");
    $kelas_list = $kelas_query->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching kelas: " . $e->getMessage());
}

// Ambil daftar ujian yang published
$ujian_list = [];
try {
    $ujian_query = $pdo->query("SELECT id, judul_ujian, kelas_target FROM ujian WHERE status = 'published' ORDER BY judul_ujian");
    $ujian_list = $ujian_query->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching ujian: " . $e->getMessage());
}

// Ambil daftar guru dari tabel guru
$guru_list = [];
try {
    $guru_query = $pdo->query("SELECT id, nama, nip FROM guru ORDER BY nama");
    $guru_list = $guru_query->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching guru: " . $e->getMessage());
}

// Cari data guru yang sedang login
$current_guru_id = null;
$current_guru_name = $_SESSION['nama_lengkap'] ?? 'Guru';

try {
    $guru_query = $pdo->prepare("SELECT id, nama FROM guru WHERE user_id = ?");
    $guru_query->execute([$_SESSION['user_id']]);
    $current_guru = $guru_query->fetch();
    
    if ($current_guru) {
        $current_guru_id = $current_guru['id'];
        $current_guru_name = $current_guru['nama'];
    }
} catch (PDOException $e) {
    error_log("Error fetching current guru: " . $e->getMessage());
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-file-alt me-2"></i>Berita Acara Ujian</h2>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahBeritaAcaraModal">
                <i class="fas fa-plus me-2"></i>Tambah Berita Acara
            </button>
        </div>
    </div>

    <?php 
    if (function_exists('displayFlashMessage')) {
        displayFlashMessage();
    }
    ?>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cari ujian/guru/ruangan...">
                </div>
                <div class="col-md-3">
                    <select id="filterStatus" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="selesai">Selesai</option>
                        <option value="diverifikasi">Diverifikasi</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterKelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php foreach($kelas_list as $kelas): ?>
                        <option value="<?= htmlspecialchars($kelas['kelas']) ?>"><?= htmlspecialchars($kelas['kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="resetFilter()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistik -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Berita Acara</h6>
                    <h2><?= count($berita_acara) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6 class="card-title">Selesai</h6>
                    <h2><?= count(array_filter($berita_acara, function($item) { return $item['status'] == 'selesai'; })) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h6 class="card-title">Draft</h6>
                    <h2><?= count(array_filter($berita_acara, function($item) { return $item['status'] == 'draft'; })) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h6 class="card-title">Diverifikasi</h6>
                    <h2><?= count(array_filter($berita_acara, function($item) { return $item['status'] == 'diverifikasi'; })) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Berita Acara -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="tabelBeritaAcara">
                    <thead class="table-dark">
                        <tr>
                            <th width="50">No</th>
                            <th>Ujian</th>
                            <th>Guru Pengawas</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Kelas</th>
                            <th>Ruangan</th>
                            <th>Peserta</th>
                            <th>Status</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($berita_acara) > 0): ?>
                            <?php $no = 1; ?>
                            <?php foreach($berita_acara as $row): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['judul_ujian']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['nama_guru']) ?>
                                        <?php if ($row['nip']): ?>
                                            <br><small class="text-muted">NIP: <?= $row['nip'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_ujian'])) ?></td>
                                    <td><?= substr($row['waktu_mulai'], 0, 5) . ' - ' . substr($row['waktu_selesai'], 0, 5) ?></td>
                                    <td><?= htmlspecialchars($row['kelas']) ?></td>
                                    <td><?= htmlspecialchars($row['ruangan']) ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= $row['jumlah_hadir'] ?> Hadir</span>
                                        <span class="badge bg-danger"><?= $row['jumlah_tidak_hadir'] ?> Tidak</span>
                                     </small></td>
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
                                        <span class="badge bg-<?= $status_class[$row['status']] ?>">
                                            <?= $status_text[$row['status']] ?>
                                        </span>
                                     </small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detail_berita_acara.php?id=<?= $row['id'] ?>" 
                                               class="btn btn-info" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <!-- PERBAIKAN: Kirim parameter ujian_id dan kelas -->
                                            <a href="cetak_berita_acara.php?ujian_id=<?= $row['ujian_id'] ?>&kelas=<?= urlencode($row['kelas']) ?>" 
                                               target="_blank" class="btn btn-success" title="Cetak">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <?php if ($_SESSION['user_id'] == $row['guru_pengawas'] || $_SESSION['role'] == 'admin'): ?>
                                            <button class="btn btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editBeritaAcaraModal"
                                                    data-id="<?= $row['id'] ?>"
                                                    data-ujian="<?= htmlspecialchars($row['judul_ujian']) ?>"
                                                    data-status="<?= $row['status'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger" onclick="hapusBeritaAcara(<?= $row['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                     </small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="fas fa-file-alt fa-3x mb-3"></i><br>
                                    Belum ada data berita acara<br>
                                    <small>Silakan tambah data berita acara</small>
                                 </small></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Berita Acara -->
<div class="modal fade" id="tambahBeritaAcaraModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Berita Acara Ujian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_berita_acara.php?action=tambah" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pilih Ujian <span class="text-danger">*</span></label>
                            <select class="form-select" name="ujian_id" id="ujianSelect" required>
                                <option value="">-- Pilih Ujian --</option>
                                <?php foreach ($ujian_list as $ujian): ?>
                                <option value="<?= $ujian['id'] ?>" data-kelas="<?= htmlspecialchars($ujian['kelas_target']) ?>">
                                    <?= htmlspecialchars($ujian['judul_ujian']) ?> 
                                    <?php if ($ujian['kelas_target']): ?>
                                        (Kelas: <?= htmlspecialchars($ujian['kelas_target']) ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guru Pengawas <span class="text-danger">*</span></label>
                            <select class="form-select" name="guru_pengawas" id="guruSelect" required>
                                <option value="">-- Pilih Guru Pengawas --</option>
                                <?php foreach($guru_list as $guru): ?>
                                    <?php 
                                    $selected = ($guru['id'] == $current_guru_id) ? 'selected' : '';
                                    $display_text = htmlspecialchars($guru['nama']);
                                    if ($guru['nip']) {
                                        $display_text .= ' (NIP: ' . htmlspecialchars($guru['nip']) . ')';
                                    }
                                    ?>
                                    <option value="<?= $guru['id'] ?>" <?= $selected ?>>
                                        <?= $display_text ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Pilih guru yang bertugas mengawasi ujian</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Ujian <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal_ujian" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="waktu_mulai" id="waktuMulai" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="waktu_selesai" id="waktuSelesai" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kelas" id="kelasInput" 
                                   placeholder="Kelas akan terisi otomatis" required readonly>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ruangan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ruangan" 
                                   placeholder="Contoh: Ruang 1, Lab Komputer" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Peserta</label>
                            <input type="number" class="form-control" name="jumlah_peserta" id="jumlahPeserta" 
                                   min="0" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Hadir</label>
                            <input type="number" class="form-control" name="jumlah_hadir" 
                                   min="0" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Tidak Hadir</label>
                            <input type="number" class="form-control" name="jumlah_tidak_hadir" 
                                   min="0" value="0" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kejadian Penting Selama Ujian</label>
                        <textarea class="form-control" name="kejadian_penting" rows="3" 
                                  placeholder="Catat kejadian penting yang terjadi selama ujian"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kendala Teknis</label>
                        <textarea class="form-control" name="kendala_teknis" rows="3" 
                                  placeholder="Catat kendala teknis yang dialami"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tindak Lanjut</label>
                        <textarea class="form-control" name="tindak_lanjut" rows="3" 
                                  placeholder="Rencana tindak lanjut yang akan dilakukan"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Berita Acara</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Berita Acara -->
<div class="modal fade" id="editBeritaAcaraModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Status Berita Acara</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_berita_acara.php?action=edit" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ujian</label>
                        <input type="text" class="form-control" id="edit_ujian" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" id="edit_status" required>
                            <option value="draft">Draft</option>
                            <option value="selesai">Selesai</option>
                            <option value="diverifikasi">Diverifikasi</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function hapusBeritaAcara(id) {
    if (confirm('Apakah Anda yakin ingin menghapus data berita acara ini?')) {
        window.location.href = 'proses_berita_acara.php?action=hapus&id=' + id;
    }
}

function resetFilter() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterKelas').value = '';
    
    const rows = document.querySelectorAll('#tabelBeritaAcara tbody tr');
    rows.forEach(row => row.style.display = '');
}

document.addEventListener('DOMContentLoaded', function() {
    // Set waktu default untuk modal tambah
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const waktuSekarang = hours + ':' + minutes;
    
    document.getElementById('waktuMulai').value = waktuSekarang;
    
    const endTime = new Date(now.getTime() + 60 * 60 * 1000);
    const endHours = endTime.getHours().toString().padStart(2, '0');
    const endMinutes = endTime.getMinutes().toString().padStart(2, '0');
    document.getElementById('waktuSelesai').value = endHours + ':' + endMinutes;
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#tabelBeritaAcara tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    
    // Filter status
    document.getElementById('filterStatus').addEventListener('change', function() {
        const filter = this.value;
        const rows = document.querySelectorAll('#tabelBeritaAcara tbody tr');
        
        rows.forEach(row => {
            if (!filter) {
                row.style.display = '';
                return;
            }
            const statusCell = row.cells[8];
            const status = statusCell ? statusCell.textContent.trim().toLowerCase() : '';
            row.style.display = status === filter ? '' : 'none';
        });
    });
    
    // Filter kelas
    document.getElementById('filterKelas').addEventListener('change', function() {
        const filter = this.value;
        const rows = document.querySelectorAll('#tabelBeritaAcara tbody tr');
        
        rows.forEach(row => {
            if (!filter) {
                row.style.display = '';
                return;
            }
            const kelasCell = row.cells[5];
            const kelas = kelasCell ? kelasCell.textContent : '';
            row.style.display = kelas === filter ? '' : 'none';
        });
    });
    
    // Modal edit handler
    const editBeritaAcaraModal = document.getElementById('editBeritaAcaraModal');
    editBeritaAcaraModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const ujian = button.getAttribute('data-ujian');
        const status = button.getAttribute('data-status');
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_ujian').value = ujian;
        document.getElementById('edit_status').value = status;
    });
    
    // Auto fill kelas berdasarkan ujian yang dipilih
    document.getElementById('ujianSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const kelas = selectedOption.getAttribute('data-kelas');
        
        if (kelas) {
            document.getElementById('kelasInput').value = kelas;
            
            fetch('get_jumlah_siswa.php?kelas=' + encodeURIComponent(kelas))
                .then(response => response.json())
                .then(data => {
                    if (data.jumlah !== undefined) {
                        document.getElementById('jumlahPeserta').value = data.jumlah;
                        document.querySelector('input[name="jumlah_hadir"]').value = data.jumlah;
                        document.querySelector('input[name="jumlah_tidak_hadir"]').value = 0;
                    }
                })
                .catch(error => console.error('Error:', error));
        } else {
            document.getElementById('kelasInput').value = '';
            document.getElementById('jumlahPeserta').value = 0;
        }
    });
    
    // Auto-calculate jumlah tidak hadir
    const jumlahPesertaInput = document.getElementById('jumlahPeserta');
    const jumlahHadirInput = document.querySelector('input[name="jumlah_hadir"]');
    const jumlahTidakHadirInput = document.querySelector('input[name="jumlah_tidak_hadir"]');
    
    if (jumlahHadirInput) {
        jumlahHadirInput.addEventListener('input', function() {
            const peserta = parseInt(jumlahPesertaInput.value) || 0;
            const hadir = parseInt(this.value) || 0;
            const tidakHadir = peserta - hadir;
            if (jumlahTidakHadirInput) jumlahTidakHadirInput.value = Math.max(0, tidakHadir);
        });
    }
    
    if (jumlahTidakHadirInput) {
        jumlahTidakHadirInput.addEventListener('input', function() {
            const peserta = parseInt(jumlahPesertaInput.value) || 0;
            const tidakHadir = parseInt(this.value) || 0;
            const hadir = peserta - tidakHadir;
            if (jumlahHadirInput) jumlahHadirInput.value = Math.max(0, hadir);
        });
    }
});
</script>

<?php 
if (file_exists('templates/footer.php')) {
    include 'templates/footer.php';
} else {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>