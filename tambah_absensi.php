<?php
// tambah_absensi.php
session_start();
require_once 'config/database.php';
require_once 'templates/header.php';

// Cek role
if ($_SESSION['role'] != 'guru') {
    header('Location: dashboard.php');
    exit();
}

$success = '';
$error = '';

// Tangani form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $ujian_id = $_POST['ujian_id'];
        $siswa_id = $_POST['siswa_id'];
        $status_hadir = $_POST['status_hadir'];
        $keterangan = $_POST['keterangan'];
        
        // Cek apakah absensi sudah ada
        $check_query = "SELECT id FROM absensi_ujian WHERE ujian_id = ? AND siswa_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$ujian_id, $siswa_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Absensi untuk siswa ini sudah ada!";
        } else {
            // Set waktu hadir jika status hadir
            $waktu_hadir = null;
            if ($status_hadir == 'hadir') {
                $waktu_hadir = date('Y-m-d H:i:s');
            }
            
            $query = "INSERT INTO absensi_ujian (ujian_id, siswa_id, status_hadir, waktu_hadir, keterangan) 
                      VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$ujian_id, $siswa_id, $status_hadir, $waktu_hadir, $keterangan]);
            
            $success = "Absensi berhasil ditambahkan!";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Ambil daftar ujian
$ujian_query = $conn->query("SELECT id, judul_ujian FROM ujian WHERE status = 'published' ORDER BY judul_ujian");
$daftar_ujian = $ujian_query->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar siswa
$siswa_query = $conn->query("SELECT id, nisn, nama, kelas FROM siswa ORDER BY nama");
$daftar_siswa = $siswa_query->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-clipboard-check me-2"></i>Tambah Absensi Ujian</h2>
        <p class="text-muted">Tambah data kehadiran peserta ujian</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card stat-card fade-in">
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="ujian_id" class="form-label">Pilih Ujian <span class="text-danger">*</span></label>
                        <select class="form-select" id="ujian_id" name="ujian_id" required>
                            <option value="">-- Pilih Ujian --</option>
                            <?php foreach($daftar_ujian as $ujian): ?>
                                <option value="<?php echo $ujian['id']; ?>">
                                    <?php echo htmlspecialchars($ujian['judul_ujian']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="siswa_id" class="form-label">Pilih Siswa <span class="text-danger">*</span></label>
                        <select class="form-select" id="siswa_id" name="siswa_id" required>
                            <option value="">-- Pilih Siswa --</option>
                            <?php foreach($daftar_siswa as $siswa): ?>
                                <option value="<?php echo $siswa['id']; ?>">
                                    <?php echo htmlspecialchars($siswa['nama'] . ' (' . $siswa['nisn'] . ') - ' . $siswa['kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_hadir" class="form-label">Status Kehadiran <span class="text-danger">*</span></label>
                        <select class="form-select" id="status_hadir" name="status_hadir" required>
                            <option value="hadir">Hadir</option>
                            <option value="tidak_hadir">Tidak Hadir</option>
                            <option value="izin">Izin</option>
                            <option value="sakit">Sakit</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Masukkan keterangan jika diperlukan"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="absensi_ujian.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>