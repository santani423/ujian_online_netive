<?php
// login.php - VERSI DIPERBAIKI UNTUK LOGIN SISWA
require_once 'config.php';

// Ambil profil sekolah
$profil_sekolah = getProfilSekolah();

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    // Redirect berdasarkan role
    if ($_SESSION['role'] == 'siswa') {
        header("Location: dashboard_siswa.php");
    } else if ($_SESSION['role'] == 'guru') {
        if ($_SESSION['is_admin'] ?? false) {
            header("Location: dashboard.php");
        } else {
            header("Location: dashboard_guru.php");
        }
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $login_success = false;
    $user = null;
    $error_message = '';
    
    try {
        // ===================================================
        // LOGIKA LOGIN YANG DIPERBAIKI
        // ===================================================
        
        // DEBUG: Log input login
        error_log("Login attempt - Username/NIP: $username");
        
        // 1. CEK APAKAH INPUT ADALAH NIP GURU
        $stmt = $pdo->prepare("
            SELECT g.*, u.* 
            FROM guru g 
            JOIN users u ON g.user_id = u.id 
            WHERE g.nip = ? AND u.role = 'guru'
        ");
        $stmt->execute([$username]);
        $guru_data = $stmt->fetch();
        
        if ($guru_data) {
            // Jika ditemukan guru dengan NIP ini
            $user = [
                'id' => $guru_data['id'],
                'username' => $guru_data['username'],
                'password' => $guru_data['password'],
                'role' => $guru_data['role'],
                'nama_lengkap' => $guru_data['nama_lengkap']
            ];
            error_log("Login: User ditemukan sebagai guru dengan NIP");
        } 
        // 2. JIKA BUKAN NIP, CEK SEBAGAI USERNAME BIASA
        else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                error_log("Login: User ditemukan dengan username");
            } else {
                error_log("Login: User tidak ditemukan dengan username/NIP: $username");
            }
        }
        
        if ($user) {
            // ===================================================
            // VERIFIKASI PASSWORD YANG DIPERBAIKI
            // ===================================================
            
            // DEBUG: Log tipe user dan hash password
            error_log("User role: " . $user['role'] . ", Password hash: " . substr($user['password'], 0, 20) . "...");
            
            // FLAG untuk menentukan metode verifikasi
            $password_matched = false;
            $needs_password_update = false;
            
            // 1. CEK PASSWORD_DEFAULT (password_verify)
            if (password_verify($password, $user['password'])) {
                $password_matched = true;
                error_log("Password verified via password_verify");
            }
            // 2. CEK UNTUK SISWA - Password plaintext atau default 'siswa123'
            else if ($user['role'] == 'siswa') {
                // Cek password plaintext langsung
                if ($password === $user['password']) {
                    $password_matched = true;
                    $needs_password_update = true; // Perlu dihash ulang
                    error_log("Password siswa plaintext verified");
                }
                // Cek password default siswa (jika sudah dihash)
                else if (password_verify('siswa123', $user['password'])) {
                    $password_matched = true;
                    error_log("Password siswa default verified");
                }
                // Cek jika password adalah 'siswa123' dalam plaintext
                else if ($password === 'siswa123') {
                    $password_matched = true;
                    $needs_password_update = true;
                    error_log("Password siswa default plaintext verified");
                }
            }
            // 3. CEK UNTUK GURU - Password plaintext (untuk kompatibilitas)
            else if ($user['role'] == 'guru') {
                // Cek password plaintext langsung
                if ($password === $user['password']) {
                    $password_matched = true;
                    $needs_password_update = true; // Perlu dihash ulang
                    error_log("Password guru plaintext verified");
                }
                // Cek untuk admin default
                else if ($user['username'] == 'admin' && $password === 'admin123') {
                    $password_matched = true;
                    $needs_password_update = true;
                    error_log("Admin default password verified");
                }
            }
            
            if ($password_matched) {
                // UPDATE PASSWORD JIKA MASIH PLAINTEXT
                if ($needs_password_update) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update_stmt->execute([$hashed_password, $user['id']]);
                    error_log("Password updated to hash for user ID: " . $user['id']);
                }
                
                // SET SESSION
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                
                error_log("Session set for user: " . $user['username'] . ", Role: " . $user['role']);
                
                // AMBIL DATA LENGKAP BERDASARKAN ROLE
                if ($user['role'] == 'guru') {
                    $stmt = $pdo->prepare("SELECT * FROM guru WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $guru = $stmt->fetch();
                    
                    if ($guru) {
                        $_SESSION['guru_id'] = $guru['id'];
                        $_SESSION['nip'] = $guru['nip'] ?? '';
                        
                        // Tentukan apakah ini admin
                        $_SESSION['is_admin'] = ($user['id'] == 1 || $user['username'] == 'admin');
                        error_log("Guru login - is_admin: " . ($_SESSION['is_admin'] ? 'true' : 'false'));
                        
                        // Cek guru mapel jika bukan admin
                        if (!$_SESSION['is_admin']) {
                            $stmt_mapel = $pdo->prepare("SELECT COUNT(*) FROM guru_mapel WHERE guru_id = ?");
                            $stmt_mapel->execute([$guru['id']]);
                            $is_guru_mapel = $stmt_mapel->fetchColumn() > 0;
                            $_SESSION['is_guru_mapel'] = $is_guru_mapel;
                            
                            if ($is_guru_mapel) {
                                $stmt_mapel = $pdo->prepare("
                                    SELECT mp.id, mp.kode_mapel, mp.nama_mapel 
                                    FROM guru_mapel gm 
                                    JOIN mata_pelajaran mp ON gm.mapel_id = mp.id 
                                    WHERE gm.guru_id = ?
                                ");
                                $stmt_mapel->execute([$guru['id']]);
                                $_SESSION['mapel_diampu'] = $stmt_mapel->fetchAll(PDO::FETCH_ASSOC);
                                
                                $stmt_kelas = $pdo->prepare("
                                    SELECT DISTINCT kelas 
                                    FROM guru_kelas 
                                    WHERE guru_id = ?
                                ");
                                $stmt_kelas->execute([$guru['id']]);
                                $_SESSION['kelas_diampu'] = $stmt_kelas->fetchAll(PDO::FETCH_ASSOC);
                            }
                        }
                    }
                } 
                else if ($user['role'] == 'siswa') {
                    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $siswa = $stmt->fetch();
                    
                    if ($siswa) {
                        $_SESSION['siswa_id'] = $siswa['id'];
                        $_SESSION['nisn'] = $siswa['nisn'];
                        $_SESSION['kelas'] = $siswa['kelas'];
                        error_log("Siswa login - ID: " . $siswa['id'] . ", NISN: " . $siswa['nisn']);
                    } else {
                        error_log("ERROR: Siswa data not found for user_id: " . $user['id']);
                        $error_message = 'Data siswa tidak ditemukan!';
                        $password_matched = false;
                    }
                }
                
                // REDIRECT JIKA BERHASIL
                if ($password_matched) {
                    if ($user['role'] == 'siswa') {
                        header("Location: dashboard_siswa.php");
                    } else if ($user['role'] == 'guru') {
                        if ($_SESSION['is_admin'] ?? false) {
                            header("Location: dashboard.php");
                        } else {
                            header("Location: dashboard_guru.php");
                        }
                    }
                    exit();
                }
            } else {
                $error_message = 'Password salah!';
                error_log("Password verification failed for user: " . $username);
            }
        } else {
            $error_message = 'Username/NIP tidak ditemukan!';
        }
        
        // JIKA ADA ERROR, SET FLASH MESSAGE
        if (!empty($error_message)) {
            flashMessage('danger', $error_message);
        }
        
    } catch (PDOException $e) {
        error_log("Database error during login: " . $e->getMessage());
        flashMessage('danger', 'Terjadi kesalahan sistem. Silakan coba lagi.');
    }
}

// TEST DATA UNTUK DEBUG (Hanya tampil jika ada parameter ?debug=1)
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    try {
        $test_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'");
        $total_siswa = $test_stmt->fetchColumn();
        
        $test_stmt2 = $pdo->query("SELECT username, password, role FROM users LIMIT 5");
        $sample_users = $test_stmt2->fetchAll();
        
        error_log("Debug info - Total siswa: $total_siswa, Sample users: " . print_r($sample_users, true));
    } catch (Exception $e) {
        error_log("Debug query error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($profil_sekolah['nama_sekolah'] ?? 'Ujian Online'); ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* School Header */
        .school-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .school-header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 20px;
            background: white;
            border-radius: 20px 20px 0 0;
        }
        
        .logo-container {
            margin-bottom: 20px;
        }
        
        .logo-wrapper {
            display: inline-block;
            width: 90px;
            height: 90px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            padding: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .school-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }
        
        .school-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .school-tagline {
            font-size: 1rem;
            opacity: 0.9;
            margin-top: 8px;
        }
        
        /* Login Form */
        .login-form {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 0.95rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            z-index: 2;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
            background: white;
            color: #333;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
        
        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
            position: relative;
            padding-left: 50px;
        }
        
        .alert::before {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 1.2rem;
        }
        
        .alert-danger {
            background: #ffeaea;
            color: #d63031;
            border-left: 4px solid #ff7675;
        }
        
        .alert-danger::before {
            content: '\f06a';
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-success::before {
            content: '\f058';
        }
        
        /* Password Hint */
        .password-hint {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #666;
        }
        
        /* Debug Info (Hanya tampil jika debug mode) */
        .debug-info {
            margin-top: 15px;
            padding: 10px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            font-size: 0.8rem;
            color: #721c24;
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            padding: 25px 30px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .copyright {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }
        
        .copyright strong {
            color: #333;
        }
        
        .contact-info {
            font-size: 0.85rem;
            color: #888;
        }
        
        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .login-container {
                max-width: 100%;
            }
            
            .login-form {
                padding: 30px 25px;
            }
            
            .school-header {
                padding: 30px 20px;
            }
            
            .school-name {
                font-size: 1.5rem;
            }
            
            .logo-wrapper {
                width: 80px;
                height: 80px;
                padding: 18px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .login-container {
                width: 100%;
                max-width: 100%;
            }
            
            .login-card {
                border-radius: 15px;
            }
            
            .login-form {
                padding: 25px 20px;
            }
            
            .school-header {
                padding: 25px 15px;
            }
            
            .school-name {
                font-size: 1.3rem;
            }
            
            .school-tagline {
                font-size: 0.9rem;
            }
            
            .form-control {
                padding: 14px 14px 14px 40px;
                font-size: 1rem;
            }
            
            .btn-login {
                padding: 15px;
                font-size: 1rem;
            }
            
            .login-footer {
                padding: 20px;
            }
        }
        
        @media (max-width: 360px) {
            .school-header {
                padding: 20px 15px;
            }
            
            .logo-wrapper {
                width: 70px;
                height: 70px;
                padding: 15px;
            }
            
            .login-form {
                padding: 20px 15px;
            }
            
            .form-control {
                padding: 12px 12px 12px 40px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- School Header -->
            <div class="school-header">
                <div class="logo-container">
                    <div class="logo-wrapper">
                        <?php 
                        $logo_path = $profil_sekolah['logo'] ?? 'assets/images/logo.png';
                        $logo_exists = false;
                        
                        $possible_paths = [
                            $logo_path,
                            __DIR__ . '/' . $logo_path,
                            'assets/uploads/logos/' . basename($logo_path),
                        ];
                        
                        foreach ($possible_paths as $path) {
                            if (file_exists($path)) {
                                $logo_exists = true;
                                $logo_path = $path;
                                if (strpos($path, __DIR__) === 0) {
                                    $logo_path = str_replace(__DIR__ . '/', '', $path);
                                }
                                break;
                            }
                        }
                        
                        if (!$logo_exists) {
                            $default_path = __DIR__ . '/assets/images/logo.png';
                            if (file_exists($default_path)) {
                                $logo_exists = true;
                                $logo_path = 'assets/images/logo.png';
                            }
                        }
                        
                        $cache_bust = isset($_SESSION['logo_cache_bust']) ? '?v=' . $_SESSION['logo_cache_bust'] : '?v=' . time();
                        $display_logo_path = $logo_path . $cache_bust;
                        
                        if ($logo_exists): 
                        ?>
                            <img src="<?php echo htmlspecialchars($display_logo_path); ?>" 
                                 alt="Logo <?php echo htmlspecialchars($profil_sekolah['nama_sekolah'] ?? 'Sekolah'); ?>" 
                                 class="school-logo"
                                 onerror="this.onerror=null; this.src='assets/images/logo.png'; console.log('Logo gagal dimuat: <?php echo htmlspecialchars($logo_path); ?>')">
                        <?php else: ?>
                            <i class="fas fa-school" style="font-size: 2.5rem; color: white; display: flex; align-items: center; justify-content: center; height: 100%;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h1 class="school-name">
                    <?php echo htmlspecialchars($profil_sekolah['nama_sekolah'] ?? 'Ujian Online'); ?>
                </h1>
                
                <div class="school-tagline">
                    <i class="fas fa-graduation-cap"></i>
                    Sistem Ujian Online
                </div>
            </div>
            
            <!-- Login Form -->
            <div class="login-form">
                <?php 
                // Tampilkan flash message
                if (isset($_SESSION['flash'])) {
                    $type = $_SESSION['flash']['type'] ?? 'info';
                    $message = $_SESSION['flash']['message'] ?? '';
                    
                    echo '<div class="alert alert-' . $type . '">';
                    echo htmlspecialchars($message);
                    echo '</div>';
                    
                    unset($_SESSION['flash']);
                }
                ?>
                
                <!-- Debug Info (Hanya tampil di debug mode) -->
                <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
                <div class="debug-info">
                    <strong><i class="fas fa-bug"></i> Debug Mode:</strong><br>
                    PHP Version: <?php echo phpversion(); ?><br>
                    Session ID: <?php echo session_id(); ?><br>
                    <?php 
                    try {
                        $test_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'");
                        echo "Total siswa: " . $test_stmt->fetchColumn();
                    } catch (Exception $e) {
                        echo "DB Error: " . $e->getMessage();
                    }
                    ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">Username / NIP Guru</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" 
                                   class="form-control" 
                                   name="username" 
                                   placeholder="Masukkan username atau NIP"
                                   required
                                   autofocus
                                   autocomplete="username"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   name="password" 
                                   id="password"
                                   placeholder="Masukkan password"
                                   required
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginButton">
                        <i class="fas fa-sign-in-alt"></i> Masuk ke Sistem
                    </button>
                </form>
                
                <!-- Footer -->
                <div class="login-footer">
                    <p class="copyright">
                        <strong>&copy; <?php echo date('Y'); ?> Viralytic Shop</strong>
                    </p>
                    <p class="contact-info">
                        <i class="fas fa-phone-alt"></i> 083846416866
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Fungsi toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle i');
            
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
        
        // Validasi form
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = this.querySelector('input[name="username"]').value.trim();
            const password = this.querySelector('input[name="password"]').value.trim();
            const loginBtn = document.getElementById('loginButton');
            const originalText = loginBtn.innerHTML;
            
            // Validasi input
            if (!username || !password) {
                e.preventDefault();
                
                // Highlight error
                const inputs = this.querySelectorAll('.form-control');
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.style.borderColor = '#ff7675';
                        input.focus();
                    }
                });
                
                // Remove error style saat user mulai mengetik
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.value.trim()) {
                            this.style.borderColor = '';
                        }
                    });
                });
                
                return false;
            }
            
            // Show loading
            loginBtn.innerHTML = '<span class="loading-spinner"></span> Memproses...';
            loginBtn.disabled = true;
            
            // Auto reset setelah 10 detik (jika stuck)
            setTimeout(() => {
                loginBtn.innerHTML = originalText;
                loginBtn.disabled = false;
            }, 10000);
            
            return true;
        });
        
        // Auto focus username field
        window.addEventListener('load', function() {
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
            
            // Center the login card vertically on mobile
            function centerOnMobile() {
                const isMobile = window.innerWidth <= 768;
                if (isMobile) {
                    document.body.style.alignItems = 'center';
                } else {
                    document.body.style.alignItems = 'center';
                }
            }
            
            centerOnMobile();
            window.addEventListener('resize', centerOnMobile);
        });
        
        // Enter key untuk submit password field
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>