<?php
// kelola_siswa.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Kelola Siswa";
include 'templates/header.php';
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-users me-2"></i>Kelola Siswa</h2>
        <div>
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importSiswaModal">
                <i class="fas fa-file-excel me-2"></i>Import Excel
            </button>
            <button class="btn btn-info me-2" onclick="cetakKartuUjian()">
                <i class="fas fa-print me-2"></i>Cetak Kartu Ujian
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahSiswaModal">
                <i class="fas fa-plus me-2"></i>Tambah Siswa
            </button>
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
    
    // Tampilkan error detail import jika ada
    if (isset($_SESSION['import_errors'])) {
        echo '<div class="alert alert-warning">';
        echo '<h6>Detail Error Import:</h6>';
        echo '<ul class="mb-0">';
        foreach ($_SESSION['import_errors'] as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        unset($_SESSION['import_errors']);
    }
    ?>

    <!-- Info Format Import -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Panduan Import Data Siswa</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Format Header Excel/CSV:</strong></p>
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>NISN</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Kelas</th>
                                <th>Jenis Kelamin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>20240001</td>
                                <td>Nama Lengkap</td>
                                <td>username</td>
                                <td>X IPA 1</td>
                                <td>L atau P</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <p><strong>Download Template:</strong></p>
                    <a href="download_template_siswa.php" class="btn btn-outline-primary btn-sm mb-2">
                        <i class="fas fa-download me-2"></i>Download Template Excel
                    </a>
                    <p class="mt-3"><strong>Ketentuan:</strong></p>
                    <ul class="small">
                        <li>Password default: <code>siswa123</code></li>
                        <li>NISN harus angka dan unik</li>
                        <li>Username harus unik</li>
                        <li>Jenis Kelamin: L (Laki-laki) atau P (Perempuan)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Pencarian dan Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cari siswa...">
                </div>
                <div class="col-md-3">
                    <select id="filterKelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php
                        $kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll();
                        foreach ($kelas_list as $kelas): 
                        ?>
                        <option value="<?= $kelas['kelas'] ?>"><?= $kelas['kelas'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label" for="selectAll">
                            Pilih Semua untuk Cetak Kartu
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="tabelSiswa">
                    <thead class="table-dark">
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="checkAll">
                            </th>
                            <th width="50">No</th>
                            <th>NISN</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Kelas</th>
                            <th>Jenis Kelamin</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("
                                SELECT s.*, u.username 
                                FROM siswa s 
                                JOIN users u ON s.user_id = u.id 
                                ORDER BY s.kelas, s.nama
                            ");
                            $no = 1;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                                $jenis_kelamin = $row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan';
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="siswa-check" value="<?= $row['id'] ?>" 
                                       data-nisn="<?= $row['nisn'] ?>" 
                                       data-nama="<?= $row['nama'] ?>" 
                                       data-kelas="<?= $row['kelas'] ?>" 
                                       data-username="<?= $row['username'] ?>">
                            </td>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nisn']) ?></td>
                            <td><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['kelas']) ?></td>
                            <td><?= $jenis_kelamin ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editSiswaModal" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-nisn="<?= $row['nisn'] ?>" 
                                            data-nama="<?= $row['nama'] ?>" 
                                            data-kelas="<?= $row['kelas'] ?>" 
                                            data-jk="<?= $row['jenis_kelamin'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-info" onclick="cetakKartuIndividu(<?= $row['id'] ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="hapusSiswa(<?= $row['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                            
                            if ($no == 1): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-users fa-3x mb-3"></i><br>
                                Belum ada data siswa<br>
                                <small>Silakan tambah siswa manual atau import dari Excel</small>
                            </td>
                        </tr>
                        <?php endif;
                            
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='8' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Import Siswa - VERSI SIMPLE TANPA PREVIEW -->
<div class="modal fade" id="importSiswaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Siswa dari Excel/CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_import_siswa.php" method="POST" enctype="multipart/form-data" id="formImport">
                <div class="modal-body">
                    <!-- Download Template Section -->
                    <div class="mb-4 p-3 border rounded bg-light">
                        <label class="form-label fw-bold">Download Template:</label>
                        <div class="d-grid">
                            <a href="download_template_siswa.php" class="btn btn-success btn-sm">
                                <i class="fas fa-download me-2"></i>Download Template Excel/CSV
                            </a>
                        </div>
                        <small class="text-muted">Gunakan template ini untuk memastikan format file benar</small>
                    </div>

                    <!-- File Upload Section -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="file_excel" accept=".xlsx,.xls,.csv" required id="fileInput">
                        <div class="form-text">
                            Format yang didukung: .xlsx, .xls, .csv (Maksimal 5MB)
                        </div>
                    </div>
                    
                    <!-- Import Options -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Opsi Import</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="skip_duplicate" id="skipDuplicate" checked>
                            <label class="form-check-label" for="skipDuplicate">
                                Lewati data duplikat (NISN/Username sudah ada)
                            </label>
                        </div>
                    </div>

                    <!-- Info Alert -->
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Pastikan file Excel/CSV memiliki kolom:</strong><br>
                            NISN, Nama, Username, Kelas, Jenis Kelamin
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnImport">
                        <i class="fas fa-upload me-2"></i>Import Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Siswa -->
<div class="modal fade" id="tambahSiswaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Siswa Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_siswa.php?action=tambah" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">NISN <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nisn" required maxlength="20" placeholder="Contoh: 202400001">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" required placeholder="Username login">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" required placeholder="Nama lengkap siswa">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="kelas" required placeholder="Contoh: X IPA 1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select class="form-select" name="jenis_kelamin" required>
                                    <option value="">Pilih</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required value="siswa123">
                        <div class="form-text">Password default: siswa123</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Siswa -->
<div class="modal fade" id="editSiswaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Data Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_siswa.php?action=edit" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">NISN <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nisn" id="edit_nisn" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" id="edit_nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kelas" id="edit_kelas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select class="form-select" name="jenis_kelamin" id="edit_jk" required>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" class="form-control" name="password" placeholder="Kosongkan jika tidak diubah">
                        <div class="form-text">Biarkan kosong untuk menggunakan password lama</div>
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
// Fungsi dasar
function hapusSiswa(id) {
    if (confirm('Apakah Anda yakin ingin menghapus siswa ini?')) {
        window.location.href = 'proses_siswa.php?action=hapus&id=' + id;
    }
}

function cetakKartuIndividu(id) {
    window.open('cetak_kartu_ujian.php?id=' + id, '_blank');
}

// Cetak kartu ujian massal
function cetakKartuUjian() {
    const selectedSiswa = [];
    document.querySelectorAll('.siswa-check:checked').forEach(checkbox => {
        selectedSiswa.push({
            id: checkbox.value,
            nisn: checkbox.dataset.nisn,
            nama: checkbox.dataset.nama,
            kelas: checkbox.dataset.kelas,
            username: checkbox.dataset.username
        });
    });
    
    if (selectedSiswa.length === 0) {
        alert('Pilih minimal satu siswa untuk dicetak kartu ujiannya!');
        return;
    }
    
    // Simpan data ke sessionStorage dan buka halaman cetak
    sessionStorage.setItem('siswaTerpilih', JSON.stringify(selectedSiswa));
    window.open('cetak_kartu_ujian.php?massal=1', '_blank');
}

// Checkbox handler
document.addEventListener('DOMContentLoaded', function() {
    // Check all
    document.getElementById('checkAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.siswa-check');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
    
    // Modal edit handler
    const editSiswaModal = document.getElementById('editSiswaModal');
    editSiswaModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('edit_id').value = button.getAttribute('data-id');
        document.getElementById('edit_nisn').value = button.getAttribute('data-nisn');
        document.getElementById('edit_nama').value = button.getAttribute('data-nama');
        document.getElementById('edit_kelas').value = button.getAttribute('data-kelas');
        document.getElementById('edit_jk').value = button.getAttribute('data-jk');
    });
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#tabelSiswa tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    
    // Filter kelas
    document.getElementById('filterKelas').addEventListener('change', function() {
        const filter = this.value;
        const rows = document.querySelectorAll('#tabelSiswa tbody tr');
        
        rows.forEach(row => {
            if (!filter) {
                row.style.display = '';
                return;
            }
            
            const kelas = row.cells[5].textContent;
            row.style.display = kelas === filter ? '' : 'none';
        });
    });

    // SIMPLE FILE VALIDATION untuk import
    const fileInput = document.getElementById('fileInput');
    const btnImport = document.getElementById('btnImport');
    
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Validasi file type
        if (!file.name.match(/\.(xlsx|xls|csv)$/)) {
            alert('Harap pilih file Excel (.xlsx, .xls) atau CSV (.csv)');
            fileInput.value = '';
            btnImport.disabled = false;
            return;
        }

        // Validasi file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file maksimal 5MB');
            fileInput.value = '';
            btnImport.disabled = false;
            return;
        }

        // Jika valid, enable tombol import
        btnImport.disabled = false;
    });

    // Handle form submit - TAMPILKAN LOADING
    document.getElementById('formImport').addEventListener('submit', function(e) {
        const file = fileInput.files[0];
        if (!file) {
            e.preventDefault();
            alert('Harap pilih file terlebih dahulu');
            return;
        }

        // Tampilkan loading
        const originalText = btnImport.innerHTML;
        btnImport.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
        btnImport.disabled = true;

        // Reset setelah 5 detik (jika ada masalah)
        setTimeout(() => {
            btnImport.innerHTML = originalText;
            btnImport.disabled = false;
        }, 5000);
    });

    // Reset form ketika modal ditutup
    const importModal = document.getElementById('importSiswaModal');
    importModal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('formImport').reset();
        btnImport.disabled = false;
        btnImport.innerHTML = '<i class="fas fa-upload me-2"></i>Import Data';
    });
});
</script>

<?php include 'templates/footer.php'; ?>