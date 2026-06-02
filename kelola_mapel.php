<?php
// kelola_mapel.php - DENGAN FITUR KKM
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Kelola Mata Pelajaran";
include 'templates/header.php';
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-book me-2"></i>Kelola Mata Pelajaran</h2>
        <div>
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importMapelModal">
                <i class="fas fa-file-import me-2"></i>Import CSV
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahMapelModal">
                <i class="fas fa-plus me-2"></i>Tambah Mapel
            </button>
        </div>
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

    <!-- Info -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Mata Pelajaran</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <p><strong>Kode Mapel:</strong> identifikasi singkat (contoh: MTK, BIN, BIG)</p>
                    <p><strong>Nama Mapel:</strong> nama lengkap mata pelajaran</p>
                </div>
                <div class="col-md-3">
                    <p><strong>ID Mapel:</strong> digunakan saat import soal dari Excel</p>
                    <p><strong>Deskripsi:</strong> penjelasan tambahan (opsional)</p>
                </div>
                <div class="col-md-3">
                    <p><strong>KKM (Kriteria Ketuntasan Minimal):</strong> nilai minimal untuk dinyatakan tuntas</p>
                    <p><strong>Range KKM:</strong> 0 - 100 (default: 70)</p>
                </div>
                <div class="col-md-3">
                    <p><strong>Import CSV:</strong> tambah banyak mapel sekaligus</p>
                    <p><strong>Template:</strong> <a href="template_mapel.php" class="btn btn-sm btn-outline-primary">Download Template</a></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th width="60">ID</th>
                            <th width="100">Kode Mapel</th>
                            <th>Nama Mata Pelajaran</th>
                            <th>Deskripsi</th>
                            <th width="80">KKM</th>
                            <th width="120">Tanggal Dibuat</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Cek apakah kolom kkm ada, jika tidak buat dulu
                        try {
                            $pdo->query("SELECT kkm FROM mata_pelajaran LIMIT 1");
                        } catch (PDOException $e) {
                            $pdo->exec("ALTER TABLE mata_pelajaran ADD COLUMN kkm INT DEFAULT 70 AFTER deskripsi");
                        }
                        
                        $stmt = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY kode_mapel");
                        $no = 1;
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $kkm = isset($row['kkm']) ? $row['kkm'] : 70;
                            $badge_color = $kkm >= 70 ? 'success' : ($kkm >= 60 ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary"><?= $row['id'] ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-uppercase"><?= htmlspecialchars($row['kode_mapel']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['nama_mapel']) ?></div>
                            </td>
                            <td>
                                <?= !empty($row['deskripsi']) ? htmlspecialchars($row['deskripsi']) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $badge_color ?> fs-6"><?= $kkm ?></span>
                            </td>
                            <td>
                                <small><?= date('d/m/Y', strtotime($row['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editMapelModal" 
                                            data-id="<?= $row['id'] ?>"
                                            data-kode_mapel="<?= htmlspecialchars($row['kode_mapel']) ?>"
                                            data-nama_mapel="<?= htmlspecialchars($row['nama_mapel']) ?>"
                                            data-deskripsi="<?= htmlspecialchars($row['deskripsi']) ?>"
                                            data-kkm="<?= $kkm ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="hapusMapel(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_mapel']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($stmt->rowCount() == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-book fa-3x mb-3"></i><br>
                                Belum ada mata pelajaran<br>
                                <small>Silakan tambah mata pelajaran terlebih dahulu</small>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Mapel -->
<div class="modal fade" id="tambahMapelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Mata Pelajaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_mapel.php?action=tambah" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Mapel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_mapel" required 
                               placeholder="Contoh: MTK, BIN, BIG" maxlength="10"
                               oninput="this.value = this.value.toUpperCase()">
                        <div class="form-text">Kode singkat (maksimal 10 karakter)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mapel" required 
                               placeholder="Contoh: Matematika, Bahasa Indonesia">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" rows="3" 
                                  placeholder="Deskripsi tambahan (opsional)"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">KKM (Kriteria Ketuntasan Minimal) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="kkm" value="70" min="0" max="100" required>
                        <div class="form-text">Nilai minimal untuk dinyatakan tuntas (0-100, default: 70)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Mapel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Mapel -->
<div class="modal fade" id="editMapelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Mata Pelajaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_mapel.php?action=edit" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Mapel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_mapel" id="edit_kode_mapel" required 
                               maxlength="10" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mapel" id="edit_nama_mapel" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">KKM (Kriteria Ketuntasan Minimal) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="kkm" id="edit_kkm" min="0" max="100" required>
                        <div class="form-text">Nilai minimal untuk dinyatakan tuntas (0-100)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update Mapel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Mapel -->
<div class="modal fade" id="importMapelModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Mata Pelajaran dari CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_mapel.php?action=import" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Template Sudah Optimal untuk Excel!</h6>
                        <p class="mb-0">Template menggunakan format <strong>CSV dengan delimiter titik-koma (;)</strong> sehingga langsung rapi di Excel tanpa pengaturan tambahan.</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">File CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="file_excel" accept=".csv" required>
                        <div class="form-text">Format: CSV dengan delimiter ; (maksimal 2MB)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Download Template</label>
                        <div>
                            <a href="template_mapel.php" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Download Template CSV
                            </a>
                            <small class="text-muted ms-2">Template sudah format Excel-friendly</small>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Preview Format CSV:</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="table-success">
                                        <tr>
                                            <th width="25%">KODE_MAPEL</th>
                                            <th width="35%">NAMA_MAPEL</th>
                                            <th width="25%">DESKRIPSI</th>
                                            <th width="15%">KKM</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><code>MTK</code></td>
                                            <td>Matematika</td>
                                            <td>Mata pelajaran matematika dasar</td>
                                            <td>70</td>
                                        </tr>
                                        <tr>
                                            <td><code>BIN</code></td>
                                            <td>Bahasa Indonesia</td>
                                            <td>Mata pelajaran bahasa Indonesia</td>
                                            <td>75</td>
                                        </tr>
                                        <tr>
                                            <td><code>BIG</code></td>
                                            <td>Bahasa Inggris</td>
                                            <td>Mata pelajaran bahasa Inggris</td>
                                            <td>70</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Kolom KKM bersifat opsional, jika tidak diisi akan default 70
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-file-import me-2"></i>Import Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function hapusMapel(id, nama) {
    if (confirm(`Apakah Anda yakin ingin menghapus mata pelajaran "${nama}"?\n\nPERHATIAN: Penghapusan mata pelajaran akan mempengaruhi semua soal yang terkait!`)) {
        window.location.href = 'proses_mapel.php?action=hapus&id=' + id;
    }
}

// Modal edit handler
document.addEventListener('DOMContentLoaded', function() {
    var editMapelModal = document.getElementById('editMapelModal');
    editMapelModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        
        document.getElementById('edit_id').value = button.getAttribute('data-id');
        document.getElementById('edit_kode_mapel').value = button.getAttribute('data-kode_mapel');
        document.getElementById('edit_nama_mapel').value = button.getAttribute('data-nama_mapel');
        document.getElementById('edit_deskripsi').value = button.getAttribute('data-deskripsi') || '';
        document.getElementById('edit_kkm').value = button.getAttribute('data-kkm') || 70;
    });

    // File validation
    const fileInput = document.querySelector('input[name="file_excel"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileSize = file.size / 1024 / 1024; // MB
                const fileName = file.name;
                const fileExt = fileName.split('.').pop().toLowerCase();
                
                if (fileExt !== 'csv') {
                    alert('Format file harus CSV!');
                    e.target.value = '';
                    return;
                }
                
                if (fileSize > 2) {
                    alert('Ukuran file maksimal 2MB');
                    e.target.value = '';
                    return;
                }
            }
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>