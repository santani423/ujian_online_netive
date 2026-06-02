<?php
// tambah_berita_acara.php
session_start();
require_once 'config/database.php';
require_once 'templates/header.php';

// Cek role
if ($_SESSION['role'] != 'guru') {
    header('Location: dashboard.php');
    exit();
}

// Ambil data guru yang sedang login
$guru_id = $_SESSION['user_id'];
$guru_query = $conn->prepare("SELECT id, nama FROM guru WHERE user_id = ?");
$guru_query->execute([$guru_id]);
$guru_data = $guru_query->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Tangani form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $ujian_id = $_POST['ujian_id'];
        $tanggal_ujian = $_POST['tanggal_ujian'];
        $waktu_mulai = $_POST['waktu_mulai'];
        $waktu_selesai = $_POST['waktu_selesai'];
        $kelas = $_POST['kelas'];
        $ruangan = $_POST['ruangan'];
        $kejadian_penting = $_POST['kejadian_penting'];
        $kendala_teknis = $_POST['kendala_teknis'];
        $tindak_lanjut = $_POST['tindak_lanjut'];
        
        // Hitung jumlah peserta dari tabel siswa
        $peserta_query = $conn->prepare("SELECT COUNT(*) as total FROM siswa WHERE kelas = ?");
        $peserta_query->execute([$kelas]);
        $jumlah_peserta = $peserta_query->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Hitung jumlah hadir dari absensi
        $hadir_query = $conn->prepare("SELECT COUNT(*) as hadir FROM absensi_ujian a 
                                       JOIN ujian u ON a.ujian_id = u.id 
                                       WHERE a.ujian_id = ? AND a.status_hadir = 'hadir'");
        $hadir_query->execute([$ujian_id]);
        $jumlah_hadir = $hadir_query->fetch(PDO::FETCH_ASSOC)['hadir'];
        
        $jumlah_tidak_hadir = $jumlah_peserta - $jumlah_hadir;
        
        $query = "INSERT INTO berita_acara 
                  (ujian_id, guru_pengawas, tanggal_ujian, waktu_mulai, waktu_selesai, 
                   jumlah_peserta, jumlah_hadir, jumlah_tidak_hadir, kelas, ruangan,
                   kejadian_penting, kendala_teknis, tindak_lanjut, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $ujian_id, $guru_data['id'], $tanggal_ujian, $waktu_mulai, $waktu_selesai,
            $jumlah_peserta, $jumlah_hadir, $jumlah_tidak_hadir, $kelas, $ruangan,
            $kejadian_penting, $kendala_teknis, $tindak_lanjut
        ]);
        
        $success = "Berita acara berhasil ditambahkan!";
        
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Ambil daftar ujian
$ujian_query = $conn->query("SELECT id, judul_ujian FROM ujian WHERE status = 'published' ORDER BY judul_ujian");
$daftar_ujian = $ujian_query->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar kelas unik
$kelas_query = $conn->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas");
$daftar_kelas = $kelas_query->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-file-alt me-2"></i>Tambah Berita Acara</h2>
        <p class="text-muted">Buat laporan berita acara pelaksanaan ujian</p>
    </div>
</div>

<div class="row">
    <div class="col-md-10 offset-md-1">
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ujian_id" class="form-label">Ujian <span class="text-danger">*</span></label>
                            <select class="form-select" id="ujian_id" name="ujian_id" required>
                                <option value="">-- Pilih Ujian --</option>
                                <?php foreach($daftar_ujian as $ujian): ?>
                                    <option value="<?php echo $ujian['id']; ?>">
                                        <?php echo htmlspecialchars($ujian['judul_ujian']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="guru_pengawas" class="form-label">Guru Pengawas</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($guru_data['nama']); ?>" readonly>
                            <input type="hidden" name="guru_pengawas" value="<?php echo $guru_data['id']; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="tanggal_ujian" class="form-label">Tanggal Ujian <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="tanggal_ujian" name="tanggal_ujian" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="waktu_mulai" class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="waktu_mulai" name="waktu_mulai" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="waktu_selesai" class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="waktu_selesai" name="waktu_selesai" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="kelas" class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" id="kelas" name="kelas" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach($daftar_kelas as $kelas): ?>
                                    <option value="<?php echo htmlspecialchars($kelas['kelas']); ?>">
                                        <?php echo htmlspecialchars($kelas['kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="ruangan" class="form-label">Ruangan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ruangan" name="ruangan" placeholder="Contoh: Ruang 1, Lab Komputer" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kejadian_penting" class="form-label">Kejadian Penting Selama Ujian</label>
                        <textarea class="form-control" id="kejadian_penting" name="kejadian_penting" rows="3" placeholder="Catat kejadian penting yang terjadi selama ujian"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kendala_teknis" class="form-label">Kendala Teknis</label>
                        <textarea class="form-control" id="kendala_teknis" name="kendala_teknis" rows="3" placeholder="Catat kendala teknis yang dialami"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tindak_lanjut" class="form-label">Tindak Lanjut</label>
                        <textarea class="form-control" id="tindak_lanjut" name="tindak_lanjut" rows="3" placeholder="Rencana tindak lanjut yang akan dilakukan"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="berita_acara.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Berita Acara
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Set tanggal hari ini sebagai default
document.getElementById('tanggal_ujian').valueAsDate = new Date();
</script>

<?php require_once 'templates/footer.php'; ?>