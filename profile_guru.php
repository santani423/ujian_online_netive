<?php
// profile_guru.php
require_once 'config.php';

// Cek login dan role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

$guru_id = $_SESSION['guru_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Inisialisasi variabel
$error = '';
$success = '';
$guru_data = [];

// Ambil data guru dari database
try {
    $stmt = $pdo->prepare("
        SELECT g.*, u.username, u.nama_lengkap 
        FROM guru g 
        JOIN users u ON g.user_id = u.id 
        WHERE g.id = ?
    ");
    $stmt->execute([$guru_id]);
    $guru_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$guru_data) {
        setFlashMessage('error', 'Data guru tidak ditemukan.');
        redirect('dashboard_guru.php');
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    
    // Cek jika password diubah
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validasi
    if (empty($nama)) {
        $error = 'Nama tidak boleh kosong';
    } elseif (empty($email)) {
        $error = 'Email tidak boleh kosong';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (empty($nip)) {
        $error = 'NIP tidak boleh kosong';
    } elseif (empty($username)) {
        $error = 'Username tidak boleh kosong';
    } elseif (empty($nama_lengkap)) {
        $error = 'Nama lengkap tidak boleh kosong';
    } elseif ($password && $password != $confirm_password) {
        $error = 'Konfirmasi password tidak cocok';
    } else {
        try {
            // Cek apakah username sudah digunakan (selain oleh user ini)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->rowCount() > 0) {
                $error = 'Username sudah digunakan';
            } else {
                // Cek apakah NIP sudah digunakan (selain oleh guru ini)
                $stmt = $pdo->prepare("SELECT id FROM guru WHERE nip = ? AND id != ?");
                $stmt->execute([$nip, $guru_id]);
                if ($stmt->rowCount() > 0) {
                    $error = 'NIP sudah digunakan';
                } else {
                    // Cek apakah email sudah digunakan (selain oleh guru ini)
                    $stmt = $pdo->prepare("SELECT id FROM guru WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $guru_id]);
                    if ($stmt->rowCount() > 0) {
                        $error = 'Email sudah digunakan';
                    } else {
                        $pdo->beginTransaction();
                        
                        // Update tabel users
                        if ($password) {
                            // Update dengan password baru
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, nama_lengkap = ?, password = ? WHERE id = ?");
                            $stmt->execute([$username, $nama_lengkap, $hashed_password, $user_id]);
                        } else {
                            // Update tanpa password
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, nama_lengkap = ? WHERE id = ?");
                            $stmt->execute([$username, $nama_lengkap, $user_id]);
                        }
                        
                        // Update tabel guru
                        $stmt = $pdo->prepare("
                            UPDATE guru 
                            SET nip = ?, nama = ?, email = ?, no_telp = ?, alamat = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$nip, $nama, $email, $no_telp, $alamat, $guru_id]);
                        
                        $pdo->commit();
                        
                        // Update session
                        $_SESSION['nama_lengkap'] = $nama_lengkap;
                        $_SESSION['username'] = $username;
                        
                        $success = 'Profil berhasil diperbarui';
                        
                        // Refresh data
                        $stmt = $pdo->prepare("
                            SELECT g.*, u.username, u.nama_lengkap 
                            FROM guru g 
                            JOIN users u ON g.user_id = u.id 
                            WHERE g.id = ?
                        ");
                        $stmt->execute([$guru_id]);
                        $guru_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$title = "Profil Guru";
include 'templates/header_guru.php';
?>

<!-- KONTEN UTAMA PROFIL GURU -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="fas fa-user me-2"></i>Profil Guru</h2>
    <div>
        <a href="dashboard_guru.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <!-- Card Informasi Profil -->
        <div class="card stat-card mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Informasi Profil</h5>
            </div>
            <div class="card-body text-center">
                <div class="profile-icon mb-3">
                    <div class="rounded-circle profile-placeholder bg-primary text-white d-flex align-items-center justify-content-center mx-auto shadow" style="width: 120px; height: 120px;">
                        <i class="fas fa-user fa-3x"></i>
                    </div>
                </div>
                
                <h5 class="mb-1"><?php echo htmlspecialchars($guru_data['nama']); ?></h5>
                <p class="text-muted mb-3">
                    <i class="fas fa-id-card me-1"></i>
                    <?php echo htmlspecialchars($guru_data['nip']); ?>
                </p>
                
                <div class="border-top pt-3">
                    <p class="text-muted mb-1">
                        <i class="fas fa-envelope me-2"></i>
                        <?php echo htmlspecialchars($guru_data['email']); ?>
                    </p>
                    <?php if (!empty($guru_data['no_telp'])): ?>
                    <p class="text-muted mb-0">
                        <i class="fas fa-phone me-2"></i>
                        <?php echo htmlspecialchars($guru_data['no_telp']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Card Informasi Akun -->
        <div class="card stat-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Akun</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th width="40%" class="text-muted">Username</th>
                        <td><?php echo htmlspecialchars($guru_data['username']); ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Role</th>
                        <td><span class="badge bg-primary">Guru</span></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Status</th>
                        <td><span class="badge bg-success">Aktif</span></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Bergabung</th>
                        <td><?php echo date('d/m/Y', strtotime($guru_data['created_at'] ?? 'now')); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Form Edit Profil -->
        <div class="card stat-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profil</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <!-- Informasi Pribadi -->
                        <div class="col-md-12">
                            <h6 class="text-primary mb-3 border-bottom pb-2">
                                <i class="fas fa-user-circle me-2"></i>Informasi Pribadi
                            </h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" 
                                   value="<?php echo htmlspecialchars($guru_data['nama']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="nip" class="form-label">NIP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nip" name="nip" 
                                   value="<?php echo htmlspecialchars($guru_data['nip']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($guru_data['email']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="no_telp" class="form-label">No. Telepon</label>
                            <input type="text" class="form-control" id="no_telp" name="no_telp" 
                                   value="<?php echo htmlspecialchars($guru_data['no_telp']); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-4">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="2"><?php echo htmlspecialchars($guru_data['alamat']); ?></textarea>
                        </div>
                        
                        <!-- Informasi Akun -->
                        <div class="col-md-12">
                            <h6 class="text-primary mb-3 border-bottom pb-2">
                                <i class="fas fa-key me-2"></i>Informasi Akun
                            </h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($guru_data['username']); ?>" required>
                            <div class="form-text">Username untuk login ke sistem</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap Akun <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" 
                                   value="<?php echo htmlspecialchars($guru_data['nama_lengkap']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <!-- Tombol Aksi -->
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between">
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Informasi Tambahan -->
        <div class="card stat-card mt-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistik</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // Ambil statistik guru
                    try {
                        // Hitung total soal yang dibuat
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE created_by = ?");
                        $stmt->execute([$guru_id]);
                        $total_soal = $stmt->fetchColumn();
                        
                        // Hitung total ujian yang dibuat
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian WHERE created_by = ?");
                        $stmt->execute([$guru_id]);
                        $total_ujian = $stmt->fetchColumn();
                        
                        // Hitung total nilai yang diinput
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_ujian WHERE nilai IS NOT NULL");
                        $total_nilai = $stmt->fetchColumn();
                        
                        // Hitung rata-rata nilai
                        $stmt = $pdo->prepare("SELECT AVG(nilai) FROM hasil_ujian WHERE nilai IS NOT NULL");
                        $rata_nilai = $stmt->fetchColumn();
                        
                        // Untuk guru mapel, hitung mapel yang diampu
                        $is_guru_mapel = $_SESSION['is_guru_mapel'] ?? false;
                        $total_mapel = 0;
                        if ($is_guru_mapel) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM guru_mapel WHERE guru_id = ?");
                            $stmt->execute([$guru_id]);
                            $total_mapel = $stmt->fetchColumn();
                        }
                    } catch (PDOException $e) {
                        $total_soal = $total_ujian = $total_nilai = $total_mapel = 0;
                        $rata_nilai = 0;
                    }
                    ?>
                    
                    <div class="col-md-3 col-6 text-center mb-3">
                        <div class="stat-number text-primary"><?php echo $total_soal; ?></div>
                        <div class="text-muted small">Total Soal</div>
                    </div>
                    
                    <div class="col-md-3 col-6 text-center mb-3">
                        <div class="stat-number text-success"><?php echo $total_ujian; ?></div>
                        <div class="text-muted small">Total Ujian</div>
                    </div>
                    
                    <div class="col-md-3 col-6 text-center mb-3">
                        <div class="stat-number text-info"><?php echo $total_nilai; ?></div>
                        <div class="text-muted small">Nilai Diinput</div>
                    </div>
                    
                    <?php if ($is_guru_mapel && $total_mapel > 0): ?>
                    <div class="col-md-3 col-6 text-center mb-3">
                        <div class="stat-number text-warning"><?php echo $total_mapel; ?></div>
                        <div class="text-muted small">Mapel Diampu</div>
                    </div>
                    <?php else: ?>
                    <div class="col-md-3 col-6 text-center mb-3">
                        <div class="stat-number text-warning"><?php echo number_format($rata_nilai, 2); ?></div>
                        <div class="text-muted small">Rata-rata Nilai</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Informasi Mengajar untuk Guru Mapel -->
                <?php
                if ($is_guru_mapel) {
                    try {
                        // Ambil mapel yang diampu
                        $stmt = $pdo->prepare("
                            SELECT mp.kode_mapel, mp.nama_mapel 
                            FROM guru_mapel gm
                            JOIN mata_pelajaran mp ON gm.mapel_id = mp.id
                            WHERE gm.guru_id = ?
                        ");
                        $stmt->execute([$guru_id]);
                        $mapel_diampu = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Ambil kelas yang diampu
                        $stmt = $pdo->prepare("
                            SELECT DISTINCT kelas 
                            FROM guru_kelas 
                            WHERE guru_id = ?
                        ");
                        $stmt->execute([$guru_id]);
                        $kelas_diampu = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($mapel_diampu) > 0 || count($kelas_diampu) > 0):
                ?>
                <div class="border-top mt-3 pt-3">
                    <h6 class="text-muted mb-2">Informasi Mengajar:</h6>
                    <div class="row">
                        <?php if (count($mapel_diampu) > 0): ?>
                        <div class="col-md-6">
                            <p class="mb-1"><small class="text-muted">Mata Pelajaran:</small></p>
                            <?php foreach ($mapel_diampu as $mapel): ?>
                                <span class="badge bg-primary mb-1">
                                    <?php echo htmlspecialchars($mapel['kode_mapel']); ?>: <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (count($kelas_diampu) > 0): ?>
                        <div class="col-md-6">
                            <p class="mb-1"><small class="text-muted">Kelas:</small></p>
                            <?php foreach ($kelas_diampu as $kelas): ?>
                                <span class="badge bg-success mb-1">
                                    Kelas <?php echo htmlspecialchars($kelas['kelas']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                        endif;
                    } catch (PDOException $e) {
                        // Error handling
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Style untuk profil */
.profile-placeholder {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    font-size: 48px;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

/* Style form */
.form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-control:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

/* Responsive */
@media (max-width: 768px) {
    .profile-placeholder {
        width: 100px;
        height: 100px;
    }
    
    .profile-placeholder i {
        font-size: 36px;
    }
}
</style>

<script>
// Validasi form
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    // Validasi password
    form.addEventListener('submit', function(e) {
        if (password.value && password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Konfirmasi password tidak cocok!');
            confirmPassword.focus();
            return false;
        }
        
        // Validasi NIP (harus angka)
        const nip = document.getElementById('nip').value;
        if (nip && !/^\d+$/.test(nip)) {
            e.preventDefault();
            alert('NIP harus berupa angka!');
            return false;
        }
        
        // Validasi telepon
        const noTelp = document.getElementById('no_telp').value;
        if (noTelp && !/^[0-9+\-\s]+$/.test(noTelp)) {
            e.preventDefault();
            alert('Format nomor telepon tidak valid!');
            return false;
        }
    });
});
</script>

<?php include 'templates/footer.php'; ?>