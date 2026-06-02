<?php
// profile_siswa.php - MOBILE FRIENDLY (TANPA ANIMASI)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'siswa') {
    header("Location: login.php");
    exit();
}

$title = "Profile Siswa";
include 'templates/header_siswa.php';

// Ambil data siswa beserta data user
$stmt_siswa = $pdo->prepare("SELECT s.*, u.username 
                            FROM siswa s 
                            JOIN users u ON s.user_id = u.id 
                            WHERE s.user_id = ?");
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    flashMessage('danger', 'Data siswa tidak ditemukan!');
    redirect('logout.php');
}

// Handle update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama = trim($_POST['nama']);
    $nisn = trim($_POST['nisn']);
    $kelas = trim($_POST['kelas']);
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat = trim($_POST['alamat']);
    
    $errors = [];
    if (empty($nama)) $errors[] = 'Nama lengkap harus diisi';
    if (empty($nisn)) $errors[] = 'NISN harus diisi';
    if (empty($kelas)) $errors[] = 'Kelas harus diisi';
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE siswa SET nama = ?, nisn = ?, kelas = ?, jenis_kelamin = ?, tanggal_lahir = ?, alamat = ? WHERE user_id = ?");
            $stmt->execute([$nama, $nisn, $kelas, $jenis_kelamin, $tanggal_lahir, $alamat, $_SESSION['user_id']]);
            
            $_SESSION['nama_lengkap'] = $nama;
            
            flashMessage('success', 'Profile berhasil diperbarui!');
            redirect('profile_siswa.php');
        } catch (Exception $e) {
            flashMessage('danger', 'Gagal memperbarui profile: ' . $e->getMessage());
        }
    } else {
        flashMessage('danger', implode('<br>', $errors));
    }
}

// Handle update password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    $errors = [];
    if (empty($password_lama)) $errors[] = 'Password lama harus diisi';
    if (empty($password_baru)) $errors[] = 'Password baru harus diisi';
    if (empty($konfirmasi_password)) $errors[] = 'Konfirmasi password harus diisi';
    if ($password_baru !== $konfirmasi_password) $errors[] = 'Password baru dan konfirmasi tidak cocok';
    if (strlen($password_baru) < 6) $errors[] = 'Password baru minimal 6 karakter';
    
    if (empty($errors)) {
        $stmt_user = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_user->execute([$_SESSION['user_id']]);
        $user = $stmt_user->fetch();
        
        if ($user && password_verify($password_lama, $user['password'])) {
            $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update->execute([$password_hash, $_SESSION['user_id']]);
            
            flashMessage('success', 'Password berhasil diubah!');
            redirect('profile_siswa.php');
        } else {
            flashMessage('danger', 'Password lama tidak sesuai!');
        }
    } else {
        flashMessage('danger', implode('<br>', $errors));
    }
}

// Hitung statistik dengan KKM
$sql_stat = "SELECT 
            COUNT(hu.id) as total_ujian,
            AVG(CASE WHEN hu.status IN ('selesai', 'waktu_habis') THEN hu.nilai ELSE NULL END) as rata_rata,
            COUNT(CASE WHEN hu.status IN ('selesai', 'waktu_habis') AND hu.nilai >= mp.kkm THEN 1 END) as lulus
            FROM hasil_ujian hu
            JOIN ujian u ON hu.ujian_id = u.id
            JOIN mata_pelajaran mp ON u.mapel_id = mp.id
            WHERE hu.siswa_id = ?";

$stmt_stat = $pdo->prepare($sql_stat);
$stmt_stat->execute([$siswa['id']]);
$statistik = $stmt_stat->fetch();
?>

<!-- Mobile Friendly CSS - Tanpa Animasi -->
<style>
/* Reset & Base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background-color: #f0f2f5;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
}

/* Container */
.container-fluid-custom {
    padding: 16px;
}

/* Header */
.header-section {
    margin-bottom: 20px;
}

.header-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #2c3e50;
}

.btn-back {
    display: inline-block;
    background-color: #6c757d;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    text-align: center;
}

/* Card */
.card-custom {
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    overflow: hidden;
    margin-bottom: 16px;
}

.card-header-custom {
    padding: 14px 16px;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.card-header-custom i {
    margin-right: 8px;
}

.card-header-custom.bg-primary { background-color: #007bff; }
.card-header-custom.bg-warning { background-color: #ffc107; color: #212529; }
.card-header-custom.bg-info { background-color: #17a2b8; }
.card-header-custom.bg-success { background-color: #28a745; }

.card-body-custom {
    padding: 16px;
}

/* Form */
.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: #495057;
}

.form-label .required {
    color: #dc3545;
}

.form-control, .form-select {
    width: 100%;
    padding: 10px 12px;
    font-size: 14px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    background-color: white;
    font-family: inherit;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.form-control:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}

.form-text {
    font-size: 11px;
    color: #6c757d;
    margin-top: 4px;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

/* Button */
.btn {
    display: inline-block;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    text-decoration: none;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-warning {
    background-color: #ffc107;
    color: #212529;
}

.btn-outline-primary {
    background-color: transparent;
    border: 1px solid #007bff;
    color: #007bff;
}

.text-end {
    text-align: right;
}

/* Profile Avatar */
.profile-avatar {
    text-align: center;
    margin-bottom: 16px;
}

.avatar-circle {
    width: 80px;
    height: 80px;
    background-color: #007bff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.avatar-circle i {
    font-size: 32px;
    color: white;
}

/* Info Table */
.info-table {
    width: 100%;
    font-size: 13px;
}

.info-table td {
    padding: 8px 4px;
    vertical-align: top;
}

.info-table td:first-child {
    width: 90px;
    font-weight: 600;
    color: #495057;
}

/* Stat Box */
.stat-box {
    text-align: center;
    padding: 12px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-number.text-primary { color: #007bff; }
.stat-number.text-info { color: #17a2b8; }
.stat-number.text-success { color: #28a745; }

.stat-label {
    font-size: 11px;
    color: #6c757d;
}

/* Alert */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Grid */
.row-grid {
    display: flex;
    flex-wrap: wrap;
    margin: -8px;
}

.col-grid {
    padding: 8px;
}

.col-12 { width: 100%; }
.col-4 { width: 33.333%; }

/* Badge */
.badge-primary {
    background-color: #007bff;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    display: inline-block;
}

/* Spacing */
.mb-1 { margin-bottom: 4px; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }

/* Flex */
.d-flex {
    display: flex;
}

.justify-between {
    justify-content: space-between;
}

.align-center {
    align-items: center;
}

.flex-wrap {
    flex-wrap: wrap;
}

.gap-2 {
    gap: 8px;
}

/* Text */
.text-center { text-align: center; }
.text-end { text-align: right; }
.small { font-size: 11px; }

/* Responsive */
@media (min-width: 768px) {
    .container-fluid-custom {
        padding: 20px;
    }
    
    .header-title {
        font-size: 24px;
    }
    
    .col-md-8 {
        width: 66.666%;
    }
    
    .col-md-4 {
        width: 33.333%;
    }
    
    .stat-number {
        font-size: 28px;
    }
}

@media (max-width: 576px) {
    .btn {
        padding: 8px 16px;
        font-size: 13px;
    }
    
    .form-control, .form-select {
        padding: 8px 12px;
        font-size: 13px;
    }
    
    .info-table td {
        display: block;
        width: 100%;
        padding: 4px 0;
    }
    
    .info-table td:first-child {
        width: 100%;
        padding-bottom: 0;
    }
}
</style>

<div class="container-fluid-custom">
    
    <!-- Header -->
    <div class="d-flex justify-between align-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="header-title"><i class="fas fa-user me-2"></i>Profile Siswa</h1>
        </div>
        <div>
            <a href="dashboard_siswa.php" class="btn-back">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row-grid">
        <!-- Form Profile - Left Column -->
        <div class="col-12 col-md-8 col-grid">
            <!-- Edit Profile Form -->
            <div class="card-custom">
                <div class="card-header-custom bg-primary">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </div>
                <div class="card-body-custom">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="row-grid">
                            <div class="col-12 col-md-6 col-grid">
                                <div class="form-group">
                                    <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="nama" value="<?= htmlspecialchars($siswa['nama']) ?>" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-grid">
                                <div class="form-group">
                                    <label class="form-label">NISN <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="nisn" value="<?= htmlspecialchars($siswa['nisn']) ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row-grid">
                            <div class="col-12 col-md-6 col-grid">
                                <div class="form-group">
                                    <label class="form-label">Kelas <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="kelas" value="<?= htmlspecialchars($siswa['kelas']) ?>" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-grid">
                                <div class="form-group">
                                    <label class="form-label">Jenis Kelamin <span class="required">*</span></label>
                                    <select class="form-select" name="jenis_kelamin" required>
                                        <option value="L" <?= $siswa['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                        <option value="P" <?= $siswa['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row-grid">
                            <div class="col-12 col-md-6 col-grid">
                                <div class="form-group">
                                    <label class="form-label">Tanggal Lahir</label>
                                    <input type="date" class="form-control" name="tanggal_lahir" value="<?= $siswa['tanggal_lahir'] ?>">
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-grid">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($siswa['username']) ?>" disabled>
                                    <div class="form-text">Username tidak dapat diubah</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="3"><?= htmlspecialchars($siswa['alamat'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Change Password Form -->
            <div class="card-custom">
                <div class="card-header-custom bg-warning">
                    <i class="fas fa-lock"></i> Ubah Password
                </div>
                <div class="card-body-custom">
                    <form method="POST" id="formPassword">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Password Lama <span class="required">*</span></label>
                            <input type="password" class="form-control" name="password_lama" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password Baru <span class="required">*</span></label>
                            <input type="password" class="form-control" name="password_baru" id="password_baru" required>
                            <div class="form-text">Minimal 6 karakter</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password Baru <span class="required">*</span></label>
                            <input type="password" class="form-control" name="konfirmasi_password" id="konfirmasi_password" required>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Ubah Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Info - Right Column -->
        <div class="col-12 col-md-4 col-grid">
            <!-- Profile Card -->
            <div class="card-custom">
                <div class="card-header-custom bg-info">
                    <i class="fas fa-info-circle"></i> Informasi Siswa
                </div>
                <div class="card-body-custom">
                    <div class="profile-avatar">
                        <div class="avatar-circle">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    
                    <table class="info-table">
                        <tr>
                            <td>Nama</td>
                            <td>: <?= htmlspecialchars($siswa['nama']) ?></td>
                        </tr>
                        <tr>
                            <td>NISN</td>
                            <td>: <?= htmlspecialchars($siswa['nisn']) ?></td>
                        </tr>
                        <tr>
                            <td>Kelas</td>
                            <td>: <span class="badge-primary"><?= htmlspecialchars($siswa['kelas']) ?></span></td>
                        </tr>
                        <tr>
                            <td>Jenis Kelamin</td>
                            <td>: <?= $siswa['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                        </tr>
                        <tr>
                            <td>Username</td>
                            <td>: <?= htmlspecialchars($siswa['username']) ?></td>
                        </tr>
                        <?php if (!empty($siswa['tanggal_lahir'])): ?>
                        <tr>
                            <td>Tgl Lahir</td>
                            <td>: <?= date('d/m/Y', strtotime($siswa['tanggal_lahir'])) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <?php if (!empty($siswa['alamat'])): ?>
                    <div class="mt-3">
                        <strong>Alamat:</strong>
                        <p class="small mb-0 mt-1"><?= nl2br(htmlspecialchars($siswa['alamat'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistik Card -->
            <div class="card-custom">
                <div class="card-header-custom bg-success">
                    <i class="fas fa-chart-bar"></i> Statistik Singkat
                </div>
                <div class="card-body-custom">
                    <div class="row-grid">
                        <div class="col-4 col-grid">
                            <div class="stat-box">
                                <div class="stat-number text-primary"><?= $statistik['total_ujian'] ?? 0 ?></div>
                                <div class="stat-label">Total Ujian</div>
                            </div>
                        </div>
                        <div class="col-4 col-grid">
                            <div class="stat-box">
                                <div class="stat-number text-info"><?= number_format($statistik['rata_rata'] ?? 0, 1) ?></div>
                                <div class="stat-label">Rata-rata</div>
                            </div>
                        </div>
                        <div class="col-4 col-grid">
                            <div class="stat-box">
                                <div class="stat-number text-success"><?= $statistik['lulus'] ?? 0 ?></div>
                                <div class="stat-label">Lulus</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="riwayat_ujian.php" class="btn btn-outline-primary w-100" style="text-align: center;">
                            <i class="fas fa-history me-1"></i>Lihat Detail Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validasi form password
document.addEventListener('DOMContentLoaded', function() {
    const formPassword = document.getElementById('formPassword');
    
    if (formPassword) {
        formPassword.addEventListener('submit', function(e) {
            const passwordBaru = document.getElementById('password_baru').value;
            const konfirmasiPassword = document.getElementById('konfirmasi_password').value;
            let errors = [];
            
            if (passwordBaru !== konfirmasiPassword) {
                errors.push('Password baru dan konfirmasi password tidak cocok!');
            }
            
            if (passwordBaru.length < 6) {
                errors.push('Password baru minimal 6 karakter!');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert(errors.join('\n'));
                return false;
            }
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>