<?php
// pengaturan.php - Halaman pengaturan utama
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Pengaturan Sistem";
include 'templates/header.php';

// FUNGSI-FUNGSI KHUSUS UNTUK PENGATURAN (HANYA YANG TIDAK ADA DI config.php)

// Fungsi untuk mengoptimasi database
function optimizeDatabase() {
    global $pdo;
    try {
        $tables = ['ujian', 'soal', 'siswa', 'guru', 'hasil_ujian', 'absensi_ujian', 'berita_acara'];
        $optimized = [];
        
        foreach ($tables as $table) {
            $pdo->exec("OPTIMIZE TABLE $table");
            $optimized[] = $table;
        }
        
        // Vacuum (untuk SQLite) atau optimize lainnya
        $pdo->exec("ANALYZE TABLE ujian, soal, siswa, guru");
        
        flashMessage('success', 'Database berhasil dioptimasi! Tabel yang dioptimasi: ' . implode(', ', $optimized));
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal mengoptimasi database: ' . $e->getMessage());
    }
}

// Fungsi untuk membersihkan cache
function clearCache() {
    $cache_dirs = ['assets/cache/', 'tmp/'];
    $cleared = [];
    
    foreach ($cache_dirs as $dir) {
        if (file_exists($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && !str_contains($file, 'index.html')) {
                    unlink($file);
                }
            }
            $cleared[] = $dir;
        }
    }
    
    // Clear session cache
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION['cache_cleared'] = time();
    }
    
    flashMessage('success', 'Cache berhasil dibersihkan! Direktori: ' . implode(', ', $cleared));
}

// Tangani aksi yang dikirim dari form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'optimize':
            optimizeDatabase();
            break;
        case 'clear_cache':
            clearCache();
            break;
        case 'restart_app':
            // Panggil fungsi restartAplikasi() yang sudah ada di config.php
            if (function_exists('restartAplikasi')) {
                restartAplikasi();
            } else {
                // Fallback jika fungsi tidak ada
                try {
                    // Clear semua session
                    session_unset();
                    session_destroy();
                    
                    // Redirect ke halaman login
                    header("Location: login.php?restart=success");
                    exit();
                } catch (Exception $e) {
                    flashMessage('danger', 'Gagal restart aplikasi: ' . $e->getMessage());
                }
            }
            break;
        case 'save_settings':
            saveSettings();
            break;
        case 'save_profil':
            saveProfilSekolahForm();
            break;
        case 'sync_time':
            syncSystemTime();
            break;
    }
}

// Fungsi untuk menyimpan pengaturan
function saveSettings() {
    try {
        $settings = [
            'random_soal' => $_POST['random_soal'] ?? '0',
            'allow_restart' => $_POST['allow_restart'] ?? '0',
            'passing_grade' => $_POST['passing_grade'] ?? '60',
            'site_name' => $_POST['site_name'] ?? 'Sistem Ujian Online',
            'site_description' => $_POST['site_description'] ?? 'Platform Ujian Online Sekolah',
            'max_file_size' => $_POST['max_file_size'] ?? '5242880',
            'allowed_extensions' => $_POST['allowed_extensions'] ?? 'jpg,jpeg,png,pdf',
            'auto_calculate' => $_POST['auto_calculate'] ?? '1',
            'timezone' => $_POST['timezone'] ?? 'Asia/Jakarta'
        ];
        
        foreach ($settings as $key => $value) {
            updateSetting($key, $value); // Gunakan fungsi dari config.php
        }
        
        // Update timezone PHP
        date_default_timezone_set($settings['timezone']);
        
        flashMessage('success', 'Pengaturan berhasil disimpan!');
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal menyimpan pengaturan: ' . $e->getMessage());
    }
}

// Fungsi untuk menyimpan profil sekolah dari form - VERSI DIPERBAIKI
function saveProfilSekolahForm() {
    global $pdo;
    
    try {
        $data = [
            'nama_sekolah' => trim($_POST['nama_sekolah'] ?? ''),
            'npsn' => trim($_POST['npsn'] ?? ''),
            'alamat' => trim($_POST['alamat'] ?? ''),
            'telepon' => trim($_POST['telepon'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'kepala_sekolah' => trim($_POST['kepala_sekolah'] ?? ''),
            'nip_kepala' => trim($_POST['nip_kepala'] ?? ''),
            'visi' => trim($_POST['visi'] ?? ''),
            'misi' => trim($_POST['misi'] ?? '')
        ];
        
        // Validasi email
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            flashMessage('danger', 'Format email tidak valid!');
            return;
        }
        
        // Ambil profil lama
        $profil_lama = getProfilSekolah();
        $logo_path = $profil_lama['logo'] ?? 'assets/images/logo.png';
        
        // Debug
        error_log("Profil lama logo: " . $logo_path);
        error_log("POST hapus_logo: " . ($_POST['hapus_logo'] ?? 'tidak ada'));
        error_log("File logo: " . ($_FILES['logo']['name'] ?? 'tidak ada'));
        
        // Handle hapus logo jika checkbox di centang
        if (isset($_POST['hapus_logo']) && $_POST['hapus_logo'] == '1') {
            // Hapus logo lama
            $full_old_path = __DIR__ . '/' . $logo_path;
            if ($logo_path && $logo_path !== 'assets/images/logo.png' && file_exists($full_old_path)) {
                if (@unlink($full_old_path)) {
                    error_log("Logo lama berhasil dihapus: " . $full_old_path);
                } else {
                    error_log("Gagal hapus logo lama: " . $full_old_path);
                }
            }
            $data['logo'] = 'assets/images/logo.png'; // Set ke default
        }
        // Handle upload logo baru
        else if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $new_logo = uploadLogo($_FILES['logo'], $logo_path);
            if ($new_logo && $new_logo !== $logo_path) {
                $data['logo'] = $new_logo;
                // Tambahkan timestamp untuk cache busting
                $_SESSION['logo_cache_bust'] = time();
                error_log("Logo baru berhasil diupload: " . $new_logo);
            } else {
                $data['logo'] = $logo_path;
                error_log("Upload logo gagal atau tidak ada perubahan, tetap pakai: " . $logo_path);
            }
        } else {
            $data['logo'] = $logo_path;
            error_log("Tidak ada file logo baru diupload, tetap pakai: " . $logo_path);
        }
        
        // Simpan ke database menggunakan ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO profil_sekolah (
            nama_sekolah, npsn, alamat, telepon, email, 
            website, kepala_sekolah, nip_kepala, logo, 
            visi, misi, updated_at
        ) VALUES (
            :nama_sekolah, :npsn, :alamat, :telepon, :email,
            :website, :kepala_sekolah, :nip_kepala, :logo,
            :visi, :misi, NOW()
        ) ON DUPLICATE KEY UPDATE
            nama_sekolah = VALUES(nama_sekolah),
            npsn = VALUES(npsn),
            alamat = VALUES(alamat),
            telepon = VALUES(telepon),
            email = VALUES(email),
            website = VALUES(website),
            kepala_sekolah = VALUES(kepala_sekolah),
            nip_kepala = VALUES(nip_kepala),
            logo = VALUES(logo),
            visi = VALUES(visi),
            misi = VALUES(misi),
            updated_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':nama_sekolah' => $data['nama_sekolah'],
            ':npsn' => $data['npsn'],
            ':alamat' => $data['alamat'],
            ':telepon' => $data['telepon'],
            ':email' => $data['email'],
            ':website' => $data['website'],
            ':kepala_sekolah' => $data['kepala_sekolah'],
            ':nip_kepala' => $data['nip_kepala'],
            ':logo' => $data['logo'],
            ':visi' => $data['visi'],
            ':misi' => $data['misi']
        ]);
        
        if ($result) {
            flashMessage('success', 'Profil sekolah berhasil diperbarui!');
            // Refresh data profil sekolah
            $GLOBALS['profil_sekolah'] = getProfilSekolah();
            
            // Log untuk debugging
            error_log("Profil sekolah diperbarui - Logo: " . $data['logo']);
            
            // Redirect untuk menghindari resubmit form
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=school&updated=" . time());
            exit();
        } else {
            flashMessage('warning', 'Tidak ada perubahan data.');
        }
        
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal memperbarui profil sekolah: ' . $e->getMessage());
        error_log("Error saveProfilSekolahForm: " . $e->getMessage());
    }
}

// Fungsi untuk sinkronisasi waktu sistem
function syncSystemTime() {
    try {
        // Update timezone setting
        $timezone = $_POST['timezone'] ?? 'Asia/Jakarta';
        updateSetting('timezone', $timezone);
        
        // Set PHP timezone
        date_default_timezone_set($timezone);
        
        flashMessage('success', 'Waktu sistem berhasil disinkronisasi ke ' . $timezone);
    } catch (Exception $e) {
        flashMessage('danger', 'Gagal sinkronisasi waktu: ' . $e->getMessage());
    }
}

// Ambil data profil sekolah
$profil_sekolah = getProfilSekolah();

// Debug logo path - PERBAIKAN PATH
error_log("Profil sekolah logo path: " . ($profil_sekolah['logo'] ?? 'Not found'));

// Ambil semua pengaturan menggunakan fungsi dari config.php
$settings = [
    'random_soal' => getSetting('random_soal') ?? '1',
    'allow_restart' => getSetting('allow_restart') ?? '1',
    'passing_grade' => getSetting('passing_grade') ?? '60',
    'site_name' => getSetting('site_name') ?? 'Sistem Ujian Online',
    'site_description' => getSetting('site_description') ?? 'Platform Ujian Online Sekolah',
    'max_file_size' => getSetting('max_file_size') ?? '5242880',
    'allowed_extensions' => getSetting('allowed_extensions') ?? 'jpg,jpeg,png,pdf',
    'auto_calculate' => getSetting('auto_calculate') ?? '1',
    'timezone' => getSetting('timezone') ?? 'Asia/Jakarta'
];

// Cek status waktu sinkronisasi - menggunakan fungsi dari config.php
$time_sync = getTimeSyncStatus();

// List timezone
$timezones = [
    'Asia/Jakarta' => 'WIB (Jakarta)',
    'Asia/Makassar' => 'WITA (Makassar)',
    'Asia/Jayapura' => 'WIT (Jayapura)',
    'UTC' => 'UTC',
    'Asia/Singapore' => 'Singapore',
    'Asia/Kuala_Lumpur' => 'Kuala Lumpur'
];

// Cek tab aktif dari URL
$active_tab = $_GET['tab'] ?? 'general';
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-cog me-2"></i>Pengaturan Sistem</h2>
        <div>
            <?php if (getSetting('allow_restart') == '1'): ?>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#restartModal">
                <i class="fas fa-redo me-2"></i>Restart Aplikasi
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Tab Navigasi (MAINTENANCE DIHAPUS) -->
    <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab == 'general' ? 'active' : '' ?>" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                <i class="fas fa-sliders-h me-2"></i>Umum
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab == 'school' ? 'active' : '' ?>" id="school-tab" data-bs-toggle="tab" data-bs-target="#school" type="button">
                <i class="fas fa-school me-2"></i>Profil Sekolah
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="time-tab" data-bs-toggle="tab" data-bs-target="#time" type="button">
                <i class="fas fa-clock me-2"></i>Waktu Sistem
            </button>
        </li>
    </ul>

    <!-- Tab Content (MAINTENANCE DIHAPUS) -->
    <div class="tab-content" id="settingsTabContent">
        <!-- Tab Umum -->
        <div class="tab-pane fade <?= $active_tab == 'general' ? 'show active' : '' ?>" id="general" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Pengaturan Umum Sistem</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Sistem</label>
                                    <input type="text" class="form-control" name="site_name" 
                                           value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi Sistem</label>
                                    <input type="text" class="form-control" name="site_description" 
                                           value="<?= htmlspecialchars($settings['site_description']) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Passing Grade (%)</label>
                                    <input type="number" class="form-control" name="passing_grade" min="0" max="100" 
                                           value="<?= htmlspecialchars($settings['passing_grade']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Maksimal Ukuran File (bytes)</label>
                                    <input type="number" class="form-control" name="max_file_size" 
                                           value="<?= htmlspecialchars($settings['max_file_size']) ?>">
                                    <small class="text-muted">Default: 5242880 (5MB)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-select" name="timezone">
                                        <?php foreach ($timezones as $tz => $label): ?>
                                            <option value="<?= $tz ?>" <?= $settings['timezone'] == $tz ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ekstensi File yang Diizinkan</label>
                            <input type="text" class="form-control" name="allowed_extensions" 
                                   value="<?= htmlspecialchars($settings['allowed_extensions']) ?>">
                            <small class="text-muted">Pisahkan dengan koma (contoh: jpg,png,pdf,docx)</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="random_soal" value="1" 
                                           id="random_soal" <?= $settings['random_soal'] == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="random_soal">
                                        Acak soal saat ujian
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="allow_restart" value="1" 
                                           id="allow_restart" <?= $settings['allow_restart'] == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allow_restart">
                                        Izinkan restart aplikasi
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_calculate" value="1" 
                                           id="auto_calculate" <?= $settings['auto_calculate'] == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="auto_calculate">
                                        Hitung nilai otomatis
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Pengaturan
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab Profil Sekolah -->
        <div class="tab-pane fade <?= $active_tab == 'school' ? 'show active' : '' ?>" id="school" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-school me-2"></i>Profil Sekolah</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_profil">
                        
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <?php 
                                    // ============================================
                                    // PERBAIKAN UTAMA: PENANGANAN PATH LOGO
                                    // ============================================
                                    $logo_path_db = $profil_sekolah['logo'] ?? 'assets/images/logo.png';
                                    $logo_display_path = $logo_path_db;
                                    $logo_exists = false;
                                    
                                    // Debug info
                                    error_log("Logo dari DB: " . $logo_path_db);
                                    
                                    // Coba cari file di beberapa lokasi
                                    $search_paths = [
                                        $logo_path_db, // Path dari database
                                        'assets/uploads/logos/' . basename($logo_path_db), // Path relatif uploads
                                        'assets/images/logo.png', // Default
                                        __DIR__ . '/' . $logo_path_db, // Absolute path
                                        __DIR__ . '/assets/uploads/logos/' . basename($logo_path_db) // Absolute uploads
                                    ];
                                    
                                    foreach ($search_paths as $search_path) {
                                        // Jika sudah menemukan, break
                                        if ($logo_exists) break;
                                        
                                        if (file_exists($search_path)) {
                                            $logo_exists = true;
                                            $logo_display_path = $search_path;
                                            error_log("Logo ditemukan di: " . $search_path);
                                            
                                            // Konversi ke path relatif untuk ditampilkan
                                            if (strpos($search_path, __DIR__) === 0) {
                                                $logo_display_path = str_replace(__DIR__ . '/', '', $search_path);
                                                error_log("Konversi ke relatif: " . $logo_display_path);
                                            }
                                            break;
                                        }
                                    }
                                    
                                    // Jika tidak ditemukan sama sekali
                                    if (!$logo_exists) {
                                        error_log("Logo tidak ditemukan di semua lokasi");
                                        $logo_display_path = 'assets/images/logo.png';
                                        if (file_exists(__DIR__ . '/assets/images/logo.png')) {
                                            $logo_exists = true;
                                        }
                                    }
                                    
                                    // Tambahkan cache busting parameter
                                    $cache_bust = isset($_SESSION['logo_cache_bust']) ? '?v=' . $_SESSION['logo_cache_bust'] : '?v=' . time();
                                    $logo_display_url = $logo_display_path . $cache_bust;
                                    
                                    // Debug final
                                    error_log("Final display path: " . $logo_display_url);
                                    error_log("File exists: " . ($logo_exists ? 'YES' : 'NO'));
                                    
                                    if ($logo_exists): 
                                    ?>
                                        <img src="<?= htmlspecialchars($logo_display_url) ?>" 
                                             alt="Logo <?= htmlspecialchars($profil_sekolah['nama_sekolah'] ?? 'Sekolah') ?>" 
                                             class="img-fluid rounded mb-3 border" 
                                             style="max-height: 150px; max-width: 100%; object-fit: contain;"
                                             id="logoPreview"
                                             onerror="this.onerror=null; this.src='assets/images/logo.png'; this.alt='Logo default';">
                                        <div class="mb-2">
                                            <a href="<?= htmlspecialchars($logo_display_url) ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-external-link-alt me-1"></i>Lihat Logo
                                            </a>
                                            <?php if ($logo_path_db !== 'assets/images/logo.png'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger mt-1" 
                                                    onclick="if(confirm('Hapus logo saat ini?')) { 
                                                        document.getElementById('hapus_logo').value = '1'; 
                                                        document.getElementById('hapus_logo_checkbox').checked = true;
                                                    }">
                                                <i class="fas fa-trash me-1"></i>Hapus Logo
                                            </button>
                                            <input type="hidden" name="hapus_logo" id="hapus_logo" value="0">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" id="hapus_logo_checkbox" 
                                                       onchange="document.getElementById('hapus_logo').value = this.checked ? '1' : '0';">
                                                <label class="form-check-label" for="hapus_logo_checkbox">
                                                    Hapus logo saat ini
                                                </label>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="border rounded p-4 mb-3 text-muted d-flex flex-column justify-content-center align-items-center" 
                                             style="height: 150px;" id="logoPreview">
                                            <i class="fas fa-school fa-3x mb-2"></i>
                                            <small>Logo tidak ditemukan</small>
                                            <small class="text-danger small">(Path di DB: <?= htmlspecialchars($logo_path_db) ?>)</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Upload Logo Baru</label>
                                        <input type="file" class="form-control" name="logo" accept="image/*" onchange="previewLogo(this)">
                                        <small class="text-muted">Maksimal 2MB (JPG, PNG, GIF, WebP)</small>
                                        <?php if (isset($_SESSION['upload_error'])): ?>
                                        <div class="text-danger small mt-1">
                                            <?= htmlspecialchars($_SESSION['upload_error']) ?>
                                            <?php unset($_SESSION['upload_error']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Debug info (dapat diaktifkan jika perlu) -->
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleDebug()">
                                            <i class="fas fa-bug me-1"></i>Debug Info
                                        </button>
                                        <div id="debugInfo" style="display: none; font-size: 10px;" class="mt-2">
                                            <strong>Debug Info:</strong><br>
                                            Database Path: <?= htmlspecialchars($logo_path_db) ?><br>
                                            Display Path: <?= htmlspecialchars($logo_display_path) ?><br>
                                            File Exists: <?= $logo_exists ? 'Yes' : 'No' ?><br>
                                            Cache Bust: <?= $cache_bust ?><br>
                                            Full URL: <?= htmlspecialchars($logo_display_url) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nama_sekolah" 
                                                   value="<?= htmlspecialchars($profil_sekolah['nama_sekolah'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">NPSN</label>
                                            <input type="text" class="form-control" name="npsn" 
                                                   value="<?= htmlspecialchars($profil_sekolah['npsn'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Kepala Sekolah</label>
                                            <input type="text" class="form-control" name="kepala_sekolah" 
                                                   value="<?= htmlspecialchars($profil_sekolah['kepala_sekolah'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">NIP Kepala Sekolah</label>
                                            <input type="text" class="form-control" name="nip_kepala" 
                                                   value="<?= htmlspecialchars($profil_sekolah['nip_kepala'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Alamat Sekolah</label>
                                    <textarea class="form-control" name="alamat" rows="2"><?= htmlspecialchars($profil_sekolah['alamat'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Telepon</label>
                                            <input type="text" class="form-control" name="telepon" 
                                                   value="<?= htmlspecialchars($profil_sekolah['telepon'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?= htmlspecialchars($profil_sekolah['email'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Website</label>
                                            <input type="text" class="form-control" name="website" 
                                                   value="<?= htmlspecialchars($profil_sekolah['website'] ?? '') ?>">
                                            <small class="text-muted">Contoh: www.sekolah.sch.id</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Visi Sekolah</label>
                                    <textarea class="form-control" name="visi" rows="4"><?= htmlspecialchars($profil_sekolah['visi'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Misi Sekolah</label>
                                    <textarea class="form-control" name="misi" rows="4"><?= htmlspecialchars($profil_sekolah['misi'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Profil Sekolah
                            </button>
                            <button type="reset" class="btn btn-secondary" onclick="resetLogoPreview()">
                                <i class="fas fa-undo me-2"></i>Reset Form
                            </button>
                            <button type="button" class="btn btn-info" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh Halaman
                            </button>
                            <button type="button" class="btn btn-warning" onclick="clearLogoCache()">
                                <i class="fas fa-broom me-2"></i>Clear Cache Logo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab Waktu Sistem -->
        <div class="tab-pane fade" id="time" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pengaturan Waktu Sistem</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Status Waktu Saat Ini</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Waktu Server (PHP):</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-server"></i></span>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($time_sync['php_time'] ?? date('Y-m-d H:i:s')) ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Waktu Database:</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-database"></i></span>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($time_sync['db_time'] ?? 'Tidak tersedia') ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status Sinkronisasi:</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <?php $is_synced = $time_sync['in_sync'] ?? false; ?>
                                                <i class="fas fa-<?= $is_synced ? 'check' : 'times' ?> text-<?= $is_synced ? 'success' : 'danger' ?>"></i>
                                            </span>
                                            <input type="text" class="form-control" 
                                                   value="<?= $is_synced ? 'TERSINKRONISASI' : 'TIDAK TERSINKRONISASI' ?>" 
                                                   readonly
                                                   style="color: <?= $is_synced ? 'green' : 'red' ?>; font-weight: bold;">
                                        </div>
                                        <?php if (isset($time_sync['diff_seconds'])): ?>
                                        <small class="text-muted">
                                            Selisih waktu: <?= $time_sync['diff_seconds'] ?> detik
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Pengaturan Timezone</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="sync_time">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Pilih Timezone</label>
                                            <select class="form-select" name="timezone">
                                                <?php foreach ($timezones as $tz => $label): ?>
                                                    <option value="<?= $tz ?>" <?= $settings['timezone'] == $tz ? 'selected' : '' ?>>
                                                        <?= $label ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Timezone PHP Saat Ini:</label>
                                            <input type="text" class="form-control" value="<?= date_default_timezone_get() ?>" readonly>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync-alt me-2"></i>Sinkronisasi Waktu
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Penting:</strong> Pastikan waktu server dan database tersinkronisasi untuk kelancaran ujian.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Restart Aplikasi -->
<div class="modal fade" id="restartModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-redo me-2"></i>Restart Aplikasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                    <h5>Apakah Anda yakin?</h5>
                    <p class="text-muted">
                        Restart aplikasi akan:
                        <ul class="text-start">
                            <li>Membersihkan semua session pengguna</li>
                            <li>Mengarahkan semua pengguna ke halaman login</li>
                            <li>Mereset cache aplikasi</li>
                        </ul>
                        <strong class="text-danger">Pastikan tidak ada ujian yang sedang berlangsung!</strong>
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" style="display: inline;" onsubmit="return confirmRestart()">
                    <input type="hidden" name="action" value="restart_app">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-redo me-2"></i>Ya, Restart Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Aktifkan tab berdasarkan URL
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'general';
    const tabElement = document.getElementById(tab + '-tab');
    if (tabElement) {
        const bsTab = new bootstrap.Tab(tabElement);
        bsTab.show();
    }
    
    // Auto refresh jika ada parameter updated
    if (urlParams.has('updated')) {
        // Force reload image untuk menghindari cache
        const logoImg = document.getElementById('logoPreview');
        if (logoImg && logoImg.tagName === 'IMG') {
            const currentSrc = logoImg.src;
            logoImg.src = currentSrc.split('?')[0] + '?v=' + new Date().getTime();
        }
    }
});

// Fungsi preview logo
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    const hapusLogoInput = document.getElementById('hapus_logo');
    const hapusLogoCheckbox = document.getElementById('hapus_logo_checkbox');
    
    // Reset hapus logo jika ada file baru
    if (hapusLogoInput) hapusLogoInput.value = '0';
    if (hapusLogoCheckbox) hapusLogoCheckbox.checked = false;
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else if (preview.tagName === 'DIV') {
                // Ubah div menjadi img
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-fluid rounded mb-3 border';
                img.style.maxHeight = '150px';
                img.style.maxWidth = '100%';
                img.style.objectFit = 'contain';
                img.alt = 'Logo Preview';
                img.id = 'logoPreview';
                img.onerror = function() {
                    this.src = 'assets/images/logo.png';
                };
                
                // Ganti div dengan img
                preview.parentNode.replaceChild(img, preview);
            }
        };
        
        reader.readAsDataURL(input.files[0]);
        
        // Validasi ukuran file
        const file = input.files[0];
        const maxSize = 2 * 1024 * 1024; // 2MB
        if (file.size > maxSize) {
            alert('Ukuran file terlalu besar. Maksimal 2MB.');
            input.value = '';
            return false;
        }
    }
}

// Fungsi reset logo preview
function resetLogoPreview() {
    const logoPreview = document.getElementById('logoPreview');
    const originalLogoPath = '<?= htmlspecialchars($logo_display_path ?? "assets/images/logo.png") ?>';
    const cacheBust = '<?= isset($_SESSION['logo_cache_bust']) ? "?v=" . $_SESSION['logo_cache_bust'] : "" ?>';
    
    if (logoPreview) {
        if (logoPreview.tagName === 'IMG') {
            // Gunakan cache busting yang fresh
            const timestamp = new Date().getTime();
            logoPreview.src = originalLogoPath + '?v=' + timestamp;
        }
    }
    
    // Reset input file
    const fileInput = document.querySelector('input[name="logo"]');
    if (fileInput) {
        fileInput.value = '';
    }
    
    // Reset hapus logo checkbox
    const hapusLogoInput = document.getElementById('hapus_logo');
    const hapusLogoCheckbox = document.getElementById('hapus_logo_checkbox');
    if (hapusLogoInput) hapusLogoInput.value = '0';
    if (hapusLogoCheckbox) hapusLogoCheckbox.checked = false;
}

// Toggle debug info
function toggleDebug() {
    const debugDiv = document.getElementById('debugInfo');
    if (debugDiv.style.display === 'none') {
        debugDiv.style.display = 'block';
    } else {
        debugDiv.style.display = 'none';
    }
}

// Clear logo cache
function clearLogoCache() {
    // Hapus session cache bust
    fetch('?clear_logo_cache=1', { method: 'GET' })
        .then(() => {
            // Update cache bust timestamp
            const logoImg = document.getElementById('logoPreview');
            if (logoImg && logoImg.tagName === 'IMG') {
                const currentSrc = logoImg.src.split('?')[0];
                logoImg.src = currentSrc + '?v=' + new Date().getTime();
            }
            alert('Cache logo berhasil dibersihkan!');
        })
        .catch(error => {
            console.error('Error clearing cache:', error);
            alert('Gagal membersihkan cache.');
        });
}

// Confirm sebelum restart
function confirmRestart() {
    return confirm('Apakah Anda yakin ingin restart aplikasi?\n\nSemua pengguna akan logout dan diarahkan ke halaman login.\n\nData akan direset kecuali akun admin dan pengaturan dasar.');
}

// Tambahkan event listener untuk form submit
const form = document.querySelector('form[enctype="multipart/form-data"]');
if (form) {
    form.addEventListener('submit', function(e) {
        // Validasi ukuran file sebelum submit
        const fileInput = document.querySelector('input[name="logo"]');
        if (fileInput && fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const maxSize = 2 * 1024 * 1024; // 2MB
            
            if (file.size > maxSize) {
                e.preventDefault();
                alert('Ukuran file terlalu besar. Maksimal 2MB.');
                fileInput.value = '';
                return false;
            }
        }
        return true;
    });
}

// Handle clear logo cache parameter
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('clear_logo_cache')) {
    // Hapus session cache bust
    <?php 
    if (isset($_GET['clear_logo_cache'])) {
        unset($_SESSION['logo_cache_bust']);
        echo "// Cache cleared via PHP";
    }
    ?>
}
</script>

<?php include 'templates/footer.php'; ?>