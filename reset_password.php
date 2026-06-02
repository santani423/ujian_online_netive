<?php
// reset_password.php - Sistem reset password yang compatible

// Gunakan config.php yang sudah ada
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    die("File config.php tidak ditemukan!");
}

// Debug mode (opsional)
$debug = isset($_GET['debug']) && $_GET['debug'] == 'true';

// Jika sudah login, redirect ke dashboard berdasarkan role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'guru') {
        header("Location: dashboard_guru.php");
    } elseif ($_SESSION['role'] == 'siswa') {
        header("Location: dashboard_siswa.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

// Ambil profil sekolah
$profil_sekolah = getProfilSekolah();

// Inisialisasi variabel
$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$error = '';
$success = '';
$user_info = null;

// Step 1: Request reset (username/NISN verification)
if ($step == 'request' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        $error = "Username/NISN tidak boleh kosong!";
    } else {
        try {
            // Cari user berdasarkan username atau NISN siswa
            $sql = "SELECT u.*, s.nisn, s.nama as nama_siswa 
                    FROM users u 
                    LEFT JOIN siswa s ON u.id = s.user_id 
                    WHERE u.username = ? OR s.nisn = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token (simplified untuk kompatibilitas)
                $token = bin2hex(random_bytes(16));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Simpan di session (bisa diganti dengan database table jika mau lebih aman)
                $_SESSION['reset_token'] = $token;
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_expires'] = $expires;
                $_SESSION['reset_username'] = $user['username'];
                $_SESSION['reset_role'] = $user['role'];
                $_SESSION['reset_name'] = $user['role'] == 'siswa' ? $user['nama_siswa'] : $user['nama_lengkap'];
                
                // Log aktivitas
                error_log("[" . date('Y-m-d H:i:s') . "] Reset password requested - User: {$user['username']}, Role: {$user['role']}, IP: {$_SERVER['REMOTE_ADDR']}");
                
                // Redirect ke step verification
                header("Location: reset_password.php?step=verify&token=" . $token);
                exit();
            } else {
                $error = "Username/NISN tidak ditemukan!";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
            error_log("Reset password error: " . $e->getMessage());
        }
    }
}

// Step 2: Verify token and set new password
if ($step == 'verify') {
    $token = $_GET['token'] ?? '';
    
    // Verifikasi token dari session
    if (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $token) {
        $error = "Token reset tidak valid atau sudah kadaluarsa!";
        $step = 'request';
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_expires']);
    } elseif (strtotime($_SESSION['reset_expires']) < time()) {
        $error = "Token reset sudah kadaluarsa!";
        $step = 'request';
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_expires']);
    } else {
        // Ambil info user dari session
        $user_info = [
            'id' => $_SESSION['reset_user_id'],
            'username' => $_SESSION['reset_username'],
            'role' => $_SESSION['reset_role'],
            'name' => $_SESSION['reset_name']
        ];
    }
    
    // Proses reset password
    if ($step == 'verify' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (strlen($new_password) < 6) {
            $error = "Password minimal 6 karakter!";
        } elseif ($new_password !== $confirm_password) {
            $error = "Password tidak cocok!";
        } else {
            try {
                // Hash password baru menggunakan password_hash
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password di database
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);
                
                // Log aktivitas
                error_log("[" . date('Y-m-d H:i:s') . "] Password reset successful - User ID: {$_SESSION['reset_user_id']}, Username: {$_SESSION['reset_username']}");
                
                // Clear session reset data
                unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_expires'], $_SESSION['reset_username']);
                
                $success = "Password berhasil direset! Silakan login dengan password baru.";
                $step = 'success';
            } catch (PDOException $e) {
                $error = "Gagal mereset password. Silakan coba lagi.";
                error_log("Password reset update error: " . $e->getMessage());
            }
        }
    }
}

// Step 3: Success
if ($step == 'success') {
    // Tampilkan pesan sukses
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo htmlspecialchars($profil_sekolah['nama_sekolah'] ?? 'Sistem Ujian Online'); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        
        .reset-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .reset-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .reset-header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 20px;
            background: white;
            border-radius: 20px 20px 0 0;
        }
        
        .reset-icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .reset-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .reset-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .reset-body {
            padding: 40px 30px;
        }
        
        .user-info-box {
            background: var(--light-color);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .user-info-box strong {
            color: var(--primary-color);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-color);
        }
        
        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .input-group-text {
            background: var(--light-color);
            border: 2px solid #e1e5e9;
            border-right: none;
        }
        
        .password-toggle {
            background: none;
            border: 2px solid #e1e5e9;
            border-left: none;
            color: var(--dark-color);
            cursor: pointer;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.85rem;
        }
        
        .strength-meter {
            height: 5px;
            background: #e1e5e9;
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 5px;
            transition: width 0.3s, background 0.3s;
        }
        
        .password-requirements {
            font-size: 0.85rem;
            color: #666;
            margin-top: 10px;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            gap: 8px;
        }
        
        .requirement.valid {
            color: var(--success-color);
        }
        
        .requirement.invalid {
            color: var(--danger-color);
        }
        
        .btn-reset {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-reset:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .btn-back {
            display: block;
            width: 100%;
            background: var(--light-color);
            color: var(--dark-color);
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-back:hover {
            background: #e9ecef;
            color: var(--dark-color);
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #ffeaea;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-success {
            background: #d4edda;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .alert-info {
            background: #d1ecf1;
            color: var(--info-color);
            border-left: 4px solid var(--info-color);
        }
        
        .success-screen {
            text-align: center;
            padding: 30px 20px;
        }
        
        .success-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 20px;
            display: block;
        }
        
        .success-message {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark-color);
        }
        
        .reset-footer {
            padding: 20px;
            text-align: center;
            background: var(--light-color);
            border-top: 1px solid #e1e5e9;
        }
        
        .school-logo {
            max-width: 80px;
            height: auto;
            margin-bottom: 15px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        @media (max-width: 576px) {
            .reset-body {
                padding: 30px 20px;
            }
            
            .reset-header {
                padding: 20px 15px;
            }
            
            .reset-title {
                font-size: 1.5rem;
            }
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-top: 15px;
            font-size: 0.8rem;
            color: #666;
            font-family: monospace;
            display: <?php echo $debug ? 'block' : 'none'; ?>;
        }
        
        /* Style untuk input error */
        .is-invalid {
            border-color: var(--danger-color) !important;
        }
        
        .invalid-feedback {
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <!-- Header -->
            <div class="reset-header">
                <?php if (!empty($profil_sekolah['logo']) && file_exists($profil_sekolah['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($profil_sekolah['logo']); ?>" 
                         alt="Logo <?php echo htmlspecialchars($profil_sekolah['nama_sekolah']); ?>" 
                         class="school-logo">
                <?php endif; ?>
                
                <i class="fas fa-key reset-icon"></i>
                <h1 class="reset-title">Reset Password</h1>
                <div class="reset-subtitle">
                    <?php echo htmlspecialchars($profil_sekolah['nama_sekolah'] ?? 'Sistem Ujian Online'); ?>
                </div>
            </div>
            
            <!-- Body -->
            <div class="reset-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step == 'request'): ?>
                    <!-- Step 1: Request Reset -->
                    <form method="POST" id="requestForm">
                        <div class="mb-4">
                            <label class="form-label">Username atau NISN</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       class="form-control <?php echo $error && empty($username) ? 'is-invalid' : ''; ?>" 
                                       name="username" 
                                       placeholder="Masukkan username atau NISN"
                                       required
                                       autofocus
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                            <?php if ($error && empty($username)): ?>
                                <div class="invalid-feedback">Username/NISN tidak boleh kosong</div>
                            <?php endif; ?>
                            <div class="password-requirements mt-2">
                                <small>
                                    <i class="fas fa-info-circle"></i>
                                    Masukkan username atau NISN Anda. Untuk siswa gunakan NISN.
                                </small>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tips:</strong> 
                            <ul class="mb-0 mt-2">
                                <li>Guru: gunakan username Anda</li>
                                <li>Siswa: gunakan NISN sebagai username</li>
                                <li>Admin: username <code>admin</code></li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn-reset" id="requestButton">
                            <i class="fas fa-paper-plane me-2"></i> Lanjutkan
                        </button>
                        
                        <a href="login.php" class="btn-back">
                            <i class="fas fa-arrow-left me-2"></i> Kembali ke Login
                        </a>
                    </form>
                    
                <?php elseif ($step == 'verify' && $user_info): ?>
                    <!-- Step 2: Set New Password -->
                    <div class="user-info-box">
                        <strong><i class="fas fa-user-circle me-2"></i> Informasi Akun:</strong><br>
                        Nama: <?php echo htmlspecialchars($user_info['name']); ?><br>
                        Username: <?php echo htmlspecialchars($user_info['username']); ?><br>
                        Role: <?php echo htmlspecialchars(ucfirst($user_info['role'])); ?>
                    </div>
                    
                    <form method="POST" id="resetForm">
                        <div class="mb-4">
                            <label class="form-label">Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control <?php echo $error && strlen($new_password ?? '') < 6 ? 'is-invalid' : ''; ?>" 
                                       name="new_password" 
                                       id="newPassword"
                                       placeholder="Masukkan password baru (minimal 6 karakter)"
                                       required>
                                <button type="button" class="btn password-toggle" onclick="togglePassword('newPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if ($error && strlen($new_password ?? '') < 6): ?>
                                <div class="invalid-feedback">Password minimal 6 karakter</div>
                            <?php endif; ?>
                            <div class="password-strength">
                                <small>Kekuatan password: <span id="strengthText">Lemah</span></small>
                                <div class="strength-meter">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Konfirmasi Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control <?php echo $error && ($new_password ?? '') !== ($confirm_password ?? '') ? 'is-invalid' : ''; ?>" 
                                       name="confirm_password" 
                                       id="confirmPassword"
                                       placeholder="Ulangi password baru"
                                       required>
                                <button type="button" class="btn password-toggle" onclick="togglePassword('confirmPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if ($error && ($new_password ?? '') !== ($confirm_password ?? '')): ?>
                                <div class="invalid-feedback">Password tidak cocok</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="password-requirements" id="passwordRequirements">
                            <div class="requirement invalid" id="reqLength">
                                <i class="fas fa-times-circle"></i>
                                Minimal 6 karakter
                            </div>
                            <div class="requirement invalid" id="reqMatch">
                                <i class="fas fa-times-circle"></i>
                                Password cocok
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-reset" id="resetButton" disabled>
                            <i class="fas fa-save me-2"></i> Simpan Password Baru
                        </button>
                        
                        <a href="reset_password.php" class="btn-back">
                            <i class="fas fa-redo me-2"></i> Batalkan & Ulangi
                        </a>
                    </form>
                    
                <?php elseif ($step == 'success'): ?>
                    <!-- Step 3: Success -->
                    <div class="success-screen">
                        <i class="fas fa-check-circle success-icon"></i>
                        <h2 class="success-message">Password Berhasil Direset!</h2>
                        <p class="mb-4">Password Anda telah berhasil diperbarui. Silakan login dengan password baru Anda.</p>
                        
                        <a href="login.php" class="btn-reset">
                            <i class="fas fa-sign-in-alt me-2"></i> Login Sekarang
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Debug Info (hanya tampil jika debug=true) -->
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    Step: <?php echo $step; ?><br>
                    Session ID: <?php echo session_id(); ?><br>
                    Server Time: <?php echo date('Y-m-d H:i:s'); ?><br>
                    <?php if (isset($_SESSION['reset_token'])): ?>
                        Token: <?php echo substr($_SESSION['reset_token'], 0, 10) . '...'; ?><br>
                        Expires: <?php echo $_SESSION['reset_expires']; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="reset-footer">
                <small class="text-muted">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($profil_sekolah['nama_sekolah'] ?? 'Sistem Ujian Online'); ?><br>
                    <i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($profil_sekolah['telepon'] ?? 'Hubungi Administrator'); ?>
                </small>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Fungsi toggle password visibility
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleBtn = passwordField.parentElement.querySelector('.password-toggle i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.classList.remove('fa-eye');
                toggleBtn.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleBtn.classList.remove('fa-eye-slash');
                toggleBtn.classList.add('fa-eye');
            }
        }
        
        // Fungsi cek kekuatan password
        function checkPasswordStrength(password) {
            let strength = 0;
            const strengthText = document.getElementById('strengthText');
            const strengthFill = document.getElementById('strengthFill');
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update tampilan
            let color, text, width;
            switch(strength) {
                case 0:
                case 1:
                    color = '#dc3545'; // Red
                    text = 'Lemah';
                    width = '20%';
                    break;
                case 2:
                    color = '#ffc107'; // Yellow
                    text = 'Cukup';
                    width = '40%';
                    break;
                case 3:
                    color = '#17a2b8'; // Blue
                    text = 'Baik';
                    width = '60%';
                    break;
                case 4:
                    color = '#28a745'; // Green
                    text = 'Kuat';
                    width = '80%';
                    break;
                case 5:
                    color = '#28a745'; // Green
                    text = 'Sangat Kuat';
                    width = '100%';
                    break;
                default:
                    color = '#e1e5e9';
                    text = 'Lemah';
                    width = '0%';
            }
            
            strengthText.textContent = text;
            strengthFill.style.backgroundColor = color;
            strengthFill.style.width = width;
        }
        
        // Validasi form
        function validateForm() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const resetButton = document.getElementById('resetButton');
            
            // Requirement 1: Minimal 6 karakter
            const reqLength = document.getElementById('reqLength');
            const isLengthValid = newPassword.length >= 6;
            
            if (isLengthValid) {
                reqLength.className = 'requirement valid';
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> Minimal 6 karakter';
            } else {
                reqLength.className = 'requirement invalid';
                reqLength.innerHTML = '<i class="fas fa-times-circle"></i> Minimal 6 karakter';
            }
            
            // Requirement 2: Password cocok
            const reqMatch = document.getElementById('reqMatch');
            const isMatchValid = newPassword === confirmPassword && newPassword.length > 0;
            
            if (isMatchValid) {
                reqMatch.className = 'requirement valid';
                reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Password cocok';
            } else {
                reqMatch.className = 'requirement invalid';
                reqMatch.innerHTML = '<i class="fas fa-times-circle"></i> Password cocok';
            }
            
            // Enable/disable submit button
            resetButton.disabled = !(isLengthValid && isMatchValid);
            
            // Update password strength
            checkPasswordStrength(newPassword);
            
            return isLengthValid && isMatchValid;
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Untuk step verify
            const newPasswordField = document.getElementById('newPassword');
            const confirmPasswordField = document.getElementById('confirmPassword');
            
            if (newPasswordField && confirmPasswordField) {
                newPasswordField.addEventListener('input', validateForm);
                confirmPasswordField.addEventListener('input', validateForm);
                
                // Inisialisasi
                validateForm();
            }
            
            // Loading state untuk form submit
            const requestForm = document.getElementById('requestForm');
            if (requestForm) {
                requestForm.addEventListener('submit', function(e) {
                    const username = this.querySelector('input[name="username"]').value.trim();
                    const requestBtn = document.getElementById('requestButton');
                    const originalHtml = requestBtn.innerHTML;
                    
                    if (!username) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading
                    requestBtn.innerHTML = '<span class="loading-spinner"></span> Memproses...';
                    requestBtn.disabled = true;
                    
                    // Auto reset setelah 10 detik
                    setTimeout(() => {
                        requestBtn.innerHTML = originalHtml;
                        requestBtn.disabled = false;
                    }, 10000);
                });
            }
            
            const resetForm = document.getElementById('resetForm');
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        return false;
                    }
                    
                    const resetBtn = document.getElementById('resetButton');
                    const originalHtml = resetBtn.innerHTML;
                    
                    // Show loading
                    resetBtn.innerHTML = '<span class="loading-spinner"></span> Menyimpan...';
                    resetBtn.disabled = true;
                    
                    // Auto reset setelah 10 detik
                    setTimeout(() => {
                        resetBtn.innerHTML = originalHtml;
                        resetBtn.disabled = false;
                    }, 10000);
                });
            }
            
            // Auto-focus username field
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField) {
                usernameField.focus();
            }
            
            // Auto-fill admin untuk testing (jika ada parameter debug)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('debug') === 'admin') {
                if (usernameField) {
                    usernameField.value = 'admin';
                    console.log('Admin username auto-filled for testing');
                }
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter untuk submit form aktif
            if (e.ctrlKey && e.key === 'Enter') {
                const activeForm = document.querySelector('form');
                if (activeForm) {
                    activeForm.submit();
                }
            }
            
            // Escape untuk kembali ke login
            if (e.key === 'Escape') {
                window.location.href = 'login.php';
            }
        });
        
        // Auto detect dan suggest username
        const usernameInput = document.querySelector('input[name="username"]');
        if (usernameInput) {
            usernameInput.addEventListener('blur', function() {
                const username = this.value.trim().toLowerCase();
                if (username === 'admin') {
                    console.log('Admin username detected');
                }
            });
        }
    </script>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>