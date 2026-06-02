<?php
// absensi_ujian.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Absensi Ujian";
include 'templates/header.php';

// Ambil parameter filter
$filter_kelas = $_GET['kelas'] ?? '';
$filter_ujian = $_GET['ujian_id'] ?? '';
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');

// Ambil data untuk filter
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll();
$ujian_list = $pdo->query("SELECT id, judul_ujian FROM ujian WHERE status = 'published' ORDER BY judul_ujian")->fetchAll();

// Query untuk mendapatkan daftar siswa berdasarkan filter kelas
$where_conditions = [];
$params_siswa = [];

if ($filter_kelas) {
    $where_conditions[] = "s.kelas = ?";
    $params_siswa[] = $filter_kelas;
}

$where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query_siswa = "SELECT s.id, s.nisn, s.nama, s.kelas FROM siswa s $where_sql ORDER BY s.kelas, s.nama";
$stmt_siswa = $pdo->prepare($query_siswa);
$stmt_siswa->execute($params_siswa);
$siswa_list = $stmt_siswa->fetchAll();

// Query untuk mendapatkan absensi yang sudah ada
$absensi_existing = [];
if ($filter_ujian && $filter_tanggal) {
    $query_absensi = "SELECT a.*, s.nisn 
                     FROM absensi_ujian a 
                     JOIN siswa s ON a.siswa_id = s.id 
                     WHERE a.ujian_id = ? AND DATE(a.created_at) = ?";
    $stmt_absensi = $pdo->prepare($query_absensi);
    $stmt_absensi->execute([$filter_ujian, $filter_tanggal]);
    $absensi_existing_data = $stmt_absensi->fetchAll();
    
    // Konversi ke format array untuk akses mudah
    foreach ($absensi_existing_data as $absensi) {
        $absensi_existing[$absensi['siswa_id']] = $absensi;
    }
}

// Proses data ujian terpilih
$ujian_data = null;
if ($filter_ujian) {
    $stmt_ujian = $pdo->prepare("SELECT * FROM ujian WHERE id = ?");
    $stmt_ujian->execute([$filter_ujian]);
    $ujian_data = $stmt_ujian->fetch();
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Absensi Ujian</h2>
        <div>
            <?php if ($filter_ujian && $filter_kelas && count($siswa_list) > 0): ?>
            <a href="cetak_absensi_pdf.php?kelas=<?= urlencode($filter_kelas) ?>&ujian_id=<?= $filter_ujian ?>&tanggal=<?= $filter_tanggal ?>" 
               class="btn btn-danger" target="_blank">
                <i class="fas fa-file-pdf me-2"></i>Cetak PDF
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php 
    // Tampilkan pesan flash
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

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Absensi</h5>
        </div>
        <div class="card-body">
            <form method="GET" id="filterForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Pilih Kelas</label>
                            <select name="kelas" class="form-select" id="selectKelas" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= $kelas['kelas'] ?>" <?= $filter_kelas == $kelas['kelas'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kelas['kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Pilih Ujian</label>
                            <select name="ujian_id" class="form-select" id="selectUjian" required>
                                <option value="">-- Pilih Ujian --</option>
                                <?php foreach ($ujian_list as $ujian): ?>
                                <option value="<?= $ujian['id'] ?>" <?= $filter_ujian == $ujian['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ujian['judul_ujian']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Tanggal Ujian</label>
                            <input type="date" class="form-control" name="tanggal" value="<?= htmlspecialchars($filter_tanggal) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Terapkan Filter
                        </button>
                        <a href="absensi_ujian.php" class="btn btn-secondary">
                            <i class="fas fa-redo me-2"></i>Reset Filter
                        </a>
                        
                        <?php if ($filter_ujian && $filter_kelas && count($siswa_list) > 0): ?>
                        <button type="button" class="btn btn-success" onclick="saveAllAbsensi()">
                            <i class="fas fa-save me-2"></i>Simpan Semua Perubahan
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <!-- Info Ujian -->
            <?php if ($ujian_data): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="alert alert-info mb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Ujian:</strong> <?= htmlspecialchars($ujian_data['judul_ujian']) ?><br>
                                <strong>Tanggal:</strong> <?= date('d/m/Y', strtotime($ujian_data['waktu_mulai'])) ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Jumlah Siswa:</strong> <?= count($siswa_list) ?> siswa<br>
                                <strong>Kelas:</strong> <?= htmlspecialchars($filter_kelas) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabel Absensi -->
    <?php if ($filter_ujian && $filter_kelas): ?>
    
    <?php if (count($siswa_list) > 0): ?>
    <div class="card">
        <div class="card-header bg-success text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Daftar Siswa - Kelas <?= htmlspecialchars($filter_kelas) ?></h5>
            </div>
        </div>
        <div class="card-body">
            <form id="absensiForm">
                <input type="hidden" name="ujian_id" value="<?= $filter_ujian ?>">
                <input type="hidden" name="tanggal" value="<?= $filter_tanggal ?>">
                
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="50">No</th>
                                <th>NISN</th>
                                <th>Nama Siswa</th>
                                <th width="100">Kelas</th>
                                <th width="150">Status Kehadiran</th>
                                <th width="200">Keterangan</th>
                                <th width="150">Waktu Hadir</th>
                                <th width="180">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($siswa_list as $siswa): 
                                $absensi = $absensi_existing[$siswa['id']] ?? null;
                                $status = $absensi ? $absensi['status_hadir'] : 'hadir';
                                $keterangan = $absensi ? $absensi['keterangan'] : '';
                                $waktu_hadir = $absensi ? $absensi['waktu_hadir'] : date('Y-m-d H:i:s');
                            ?>
                            <tr>
                                <td class="text-center align-middle"><?= $no++ ?></td>
                                <td class="align-middle"><?= htmlspecialchars($siswa['nisn']) ?></td>
                                <td class="align-middle"><?= htmlspecialchars($siswa['nama']) ?></td>
                                <td class="text-center align-middle"><?= htmlspecialchars($siswa['kelas']) ?></td>
                                <td class="text-center align-middle">
                                    <select class="form-select form-select-sm status-select" 
                                            name="status[<?= $siswa['id'] ?>]" 
                                            data-siswa-id="<?= $siswa['id'] ?>"
                                            onchange="updateStatusColor(this)">
                                        <option value="hadir" <?= $status == 'hadir' ? 'selected' : '' ?>>Hadir</option>
                                        <option value="tidak_hadir" <?= $status == 'tidak_hadir' ? 'selected' : '' ?>>Tidak Hadir</option>
                                        <option value="izin" <?= $status == 'izin' ? 'selected' : '' ?>>Izin</option>
                                        <option value="sakit" <?= $status == 'sakit' ? 'selected' : '' ?>>Sakit</option>
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <input type="text" class="form-control form-control-sm keterangan-input" 
                                           name="keterangan[<?= $siswa['id'] ?>]" 
                                           value="<?= htmlspecialchars($keterangan) ?>"
                                           placeholder="Masukkan keterangan">
                                </td>
                                <td class="text-center align-middle">
                                    <input type="datetime-local" class="form-control form-control-sm waktu-input" 
                                           name="waktu_hadir[<?= $siswa['id'] ?>]" 
                                           value="<?= date('Y-m-d\TH:i', strtotime($waktu_hadir)) ?>"
                                           <?= $status != 'hadir' ? 'disabled' : '' ?>>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-sm btn-success" onclick="setStatusCepat(<?= $siswa['id'] ?>, 'hadir')">
                                            <i class="fas fa-check me-1"></i>Hadir
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="setStatusCepat(<?= $siswa['id'] ?>, 'izin')">
                                            <i class="fas fa-envelope me-1"></i>Izin
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" onclick="setStatusCepat(<?= $siswa['id'] ?>, 'sakit')">
                                            <i class="fas fa-hospital me-1"></i>Sakit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="setStatusCepat(<?= $siswa['id'] ?>, 'tidak_hadir')">
                                            <i class="fas fa-times me-1"></i>Tdk Hadir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
        <h5 class="text-muted">Tidak ada siswa di kelas ini</h5>
        <p class="text-muted">Silakan pilih kelas lain atau tambahkan siswa terlebih dahulu.</p>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
        <h5 class="text-muted">Silakan Pilih Kelas dan Ujian</h5>
        <p class="text-muted">Gunakan filter di atas untuk menampilkan daftar siswa.</p>
    </div>
    <?php endif; ?>
</div>

<script>
// Fungsi untuk update warna border select berdasarkan status
function updateStatusColor(selectElement) {
    const colors = {
        'hadir': '#198754',
        'tidak_hadir': '#dc3545',
        'izin': '#ffc107',
        'sakit': '#0dcaf0'
    };
    
    selectElement.style.borderColor = colors[selectElement.value] || '#dee2e6';
    selectElement.style.borderWidth = '2px';
    
    // Enable/disable waktu hadir
    const row = selectElement.closest('tr');
    const waktuInput = row.querySelector('.waktu-input');
    if (waktuInput) {
        waktuInput.disabled = selectElement.value !== 'hadir';
        if (selectElement.value !== 'hadir') {
            waktuInput.value = '';
        }
    }
}

// Fungsi untuk set status cepat
function setStatusCepat(siswaId, status) {
    const select = document.querySelector(`select[name="status[${siswaId}]"]`);
    if (select) {
        select.value = status;
        updateStatusColor(select);
        
        // Set warna border
        const colors = {
            'hadir': '#198754',
            'tidak_hadir': '#dc3545',
            'izin': '#ffc107',
            'sakit': '#0dcaf0'
        };
        select.style.borderColor = colors[status] || '#dee2e6';
        
        // Jika tidak hadir, izin, atau sakit, hapus waktu hadir
        const row = select.closest('tr');
        const waktuInput = row.querySelector('.waktu-input');
        if (waktuInput) {
            waktuInput.disabled = status !== 'hadir';
            if (status !== 'hadir') {
                waktuInput.value = '';
            }
        }
        
        // Fokus ke keterangan jika perlu
        const keteranganInput = row.querySelector('.keterangan-input');
        if (keteranganInput && (status === 'izin' || status === 'sakit')) {
            keteranganInput.focus();
        }
    }
}

// Fungsi untuk save semua absensi
function saveAllAbsensi() {
    if (!confirm('Apakah Anda yakin ingin menyimpan semua perubahan absensi?')) {
        return;
    }
    
    const formData = new FormData();
    
    // Tambahkan data ke FormData
    formData.append('ujian_id', '<?= $filter_ujian ?>');
    formData.append('tanggal', '<?= $filter_tanggal ?>');
    formData.append('action', 'save_all');
    
    // Kumpulkan data absensi
    const absensiData = [];
    document.querySelectorAll('.status-select').forEach(select => {
        const siswaId = select.dataset.siswaId;
        const status = select.value;
        const row = select.closest('tr');
        const keterangan = row.querySelector('.keterangan-input').value;
        const waktuHadir = row.querySelector('.waktu-input').value;
        
        absensiData.push({
            siswa_id: siswaId,
            status: status,
            keterangan: keterangan,
            waktu_hadir: waktuHadir
        });
    });
    
    formData.append('absensi_data', JSON.stringify(absensiData));
    
    // Tampilkan loading
    const btnSave = document.querySelector('button[onclick="saveAllAbsensi()"]');
    const originalText = btnSave.innerHTML;
    btnSave.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';
    btnSave.disabled = true;
    
    // Kirim data via AJAX
    fetch('proses_absensi.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Reload halaman untuk update data
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btnSave.innerHTML = originalText;
            btnSave.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat menyimpan data');
        btnSave.innerHTML = originalText;
        btnSave.disabled = false;
    });
}

// Initialize saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    // Update semua status color
    document.querySelectorAll('.status-select').forEach(select => {
        updateStatusColor(select);
    });
});
</script>

<?php include 'templates/footer.php'; ?>