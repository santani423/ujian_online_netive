<?php
// profile.php - Untuk admin/guru mengelola profil
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Profile Pengguna";
include 'templates/header.php';

// Ambil data user dan guru
$stmt_user = $pdo->prepare("SELECT u.*, g.nip, g.nama, g.email, g.no_telp, g.alamat, g.foto 
                           FROM users u 
                           LEFT JOIN guru g ON u.id = g.user_id 
                           WHERE u.id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$user = $stmt_user->fetch();

if (!$user) {
    flashMessage('danger', 'Data pengguna tidak ditemukan!');
    redirect('logout.php');
}

// Handle update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $no_telp = $_POST['no_telp'];
    $alamat = $_POST['alamat'];
    
    try {
        // Update data guru
        $stmt = $pdo->prepare("UPDATE guru SET nama = ?, email = ?, no_telp = ?, alamat = ? WHERE user_id = ?");
        $stmt->execute([$nama, $email, $no_telp, $alamat, $_SESSION['user_id']]);
        
        // Update session nama lengkap
        $_SESSION['nama_lengkap'] = $nama;
        
        flashMessage('success', 'Profile berhasil diperbarui!');
        redirect('profile.php');
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal memperbarui profile: ' . $e->getMessage());
    }
}

// Handle update password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    // Validasi
    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        flashMessage('danger', 'Semua field password harus diisi!');
    } elseif ($password_baru !== $konfirmasi_password) {
        flashMessage('danger', 'Password baru dan konfirmasi password tidak cocok!');
    } elseif (strlen($password_baru) < 6) {
        flashMessage('danger', 'Password baru minimal 6 karakter!');
    } else {
        // Verifikasi password lama
        $stmt_user = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_user->execute([$_SESSION['user_id']]);
        $user_data = $stmt_user->fetch();
        
        if ($user_data && password_verify($password_lama, $user_data['password'])) {
            // Update password
            $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update->execute([$password_hash, $_SESSION['user_id']]);
            
            flashMessage('success', 'Password berhasil diubah!');
            redirect('profile.php');
        } else {
            flashMessage('danger', 'Password lama tidak sesuai!');
        }
    }
}

// Handle upload foto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_foto'])) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto = $_FILES['foto'];
        
        // Validasi file
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($foto['type'], $allowed_types)) {
            flashMessage('danger', 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF.');
        } elseif ($foto['size'] > $max_size) {
            flashMessage('danger', 'Ukuran file terlalu besar. Maksimal 2MB.');
        } else {
            // Generate unique filename
            $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $upload_path = 'assets/uploads/profiles/' . $filename;
            
            // Create directory if not exists
            if (!is_dir('assets/uploads/profiles')) {
                mkdir('assets/uploads/profiles', 0777, true);
            }
            
            if (move_uploaded_file($foto['tmp_name'], $upload_path)) {
                // Delete old photo if exists
                if (!empty($user['foto']) && file_exists($user['foto'])) {
                    unlink($user['foto']);
                }
                
                // Update database
                $stmt_foto = $pdo->prepare("UPDATE guru SET foto = ? WHERE user_id = ?");
                $stmt_foto->execute([$upload_path, $_SESSION['user_id']]);
                
                flashMessage('success', 'Foto profile berhasil diupdate!');
                redirect('profile.php');
            } else {
                flashMessage('danger', 'Gagal mengupload foto.');
            }
        }
    } else {
        flashMessage('danger', 'Pilih file foto yang valid.');
    }
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-user me-2"></i>Profile Pengguna</h2>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
        </a>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row">
        <!-- Sidebar Profile -->
        <div class="col-lg-4">
            <!-- Card Foto Profile -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Foto Profile</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if (!empty($user['foto']) && file_exists($user['foto'])): ?>
                            <img src="<?= $user['foto'] ?>?t=<?= time() ?>" alt="Profile" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                                <i class="fas fa-user fa-4x text-white"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_foto" value="1">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="foto" accept="image/*">
                            <div class="form-text">
                                Format: JPG, PNG, GIF (Maks. 2MB)
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-upload me-2"></i>Update Foto
                        </button>
                    </form>
                </div>
            </div>

            <!-- Card Informasi Akun -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Akun</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td width="100"><strong>Username:</strong></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Role:</strong></td>
                            <td>
                                <span class="badge bg-<?= $user['role'] == 'guru' ? 'success' : 'primary' ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>NIP:</strong></td>
                            <td><?= !empty($user['nip']) ? htmlspecialchars($user['nip']) : '<span class="text-muted">-</span>' ?></td>
                        </tr>
                        <tr>
                            <td><strong>Bergabung:</strong></td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                    </table>
                    
                    <div class="mt-3">
                        <strong>Status Akun:</strong>
                        <div class="mt-2">
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i>Aktif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Edit Profile -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama" value="<?= htmlspecialchars($user['nama'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NIP</label>
                                    <input type="text" class="form-control" value="<?= !empty($user['nip']) ? htmlspecialchars($user['nip']) : '' ?>" disabled>
                                    <div class="form-text">NIP tidak dapat diubah</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">No. Telepon</label>
                                    <input type="text" class="form-control" name="no_telp" value="<?= htmlspecialchars($user['no_telp'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="3"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Ubah Password -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Ubah Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password_lama" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password_baru" required minlength="6">
                                    <div class="form-text">Minimal 6 karakter</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="konfirmasi_password" required minlength="6">
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Ubah Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistik Pengguna -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistik Pengguna</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Hitung statistik
                    $stmt_ujian = $pdo->prepare("SELECT COUNT(*) as total_ujian FROM ujian WHERE created_by = ?");
                    $stmt_ujian->execute([$_SESSION['user_id']]);
                    $total_ujian = $stmt_ujian->fetchColumn();

                    $stmt_soal = $pdo->prepare("SELECT COUNT(*) as total_soal FROM soal WHERE created_by = ?");
                    $stmt_soal->execute([$_SESSION['user_id']]);
                    $total_soal = $stmt_soal->fetchColumn();

                    $stmt_siswa = $pdo->query("SELECT COUNT(*) as total_siswa FROM siswa");
                    $total_siswa = $stmt_siswa->fetchColumn();

                    $stmt_aktif = $pdo->prepare("SELECT COUNT(*) as ujian_aktif FROM ujian WHERE created_by = ? AND status = 'published' AND waktu_selesai >= NOW()");
                    $stmt_aktif->execute([$_SESSION['user_id']]);
                    $ujian_aktif = $stmt_aktif->fetchColumn();
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-primary mb-1"><?= $total_ujian ?></h4>
                                <small class="text-muted">Total Ujian</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-success mb-1"><?= $total_soal ?></h4>
                                <small class="text-muted">Total Soal</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-info mb-1"><?= $total_siswa ?></h4>
                                <small class="text-muted">Total Siswa</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-warning mb-1"><?= $ujian_aktif ?></h4>
                                <small class="text-muted">Ujian Aktif</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Aktivitas Terbaru:</h6>
                        <ul class="list-group list-group-flush">
                            <?php
                            $stmt_aktivitas = $pdo->prepare("SELECT judul_ujian, created_at FROM ujian WHERE created_by = ? ORDER BY created_at DESC LIMIT 3");
                            $stmt_aktivitas->execute([$_SESSION['user_id']]);
                            $aktivitas = $stmt_aktivitas->fetchAll();
                            
                            if (count($aktivitas) > 0):
                                foreach ($aktivitas as $aktif):
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-clipboard-list text-primary me-2"></i>
                                    <small><?= htmlspecialchars($aktif['judul_ujian']) ?></small>
                                </div>
                                <small class="text-muted"><?= date('d/m H:i', strtotime($aktif['created_at'])) ?></small>
                            </li>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <li class="list-group-item text-center text-muted">
                                <small>Belum ada aktivitas</small>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validasi form password
document.addEventListener('DOMContentLoaded', function() {
    const formPassword = document.querySelector('form[name="update_password"]');
    
    if (formPassword) {
        formPassword.addEventListener('submit', function(e) {
            const passwordBaru = document.querySelector('input[name="password_baru"]').value;
            const konfirmasiPassword = document.querySelector('input[name="konfirmasi_password"]').value;
            
            if (passwordBaru !== konfirmasiPassword) {
                e.preventDefault();
                alert('Password baru dan konfirmasi password tidak cocok!');
                return false;
            }
            
            if (passwordBaru.length < 6) {
                e.preventDefault();
                alert('Password baru minimal 6 karakter!');
                return false;
            }
        });
    }
    
    // Preview foto sebelum upload
    const fotoInput = document.querySelector('input[name="foto"]');
    if (fotoInput) {
        fotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validasi ukuran file
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file terlalu besar. Maksimal 2MB.');
                    e.target.value = '';
                    return;
                }
                
                // Validasi tipe file
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file tidak didukung. Gunakan JPG, PNG, atau GIF.');
                    e.target.value = '';
                    return;
                }
            }
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>