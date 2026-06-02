<?php
// templates/header_guru.php - Header khusus untuk guru (bukan admin)
if (!isset($title)) {
    $title = "Guru - Ujian Online";
}

// Cek apakah guru ini adalah guru mapel (punya mapel dan kelas yang diassign)
$is_guru_mapel = $_SESSION['is_guru_mapel'] ?? false;
$mapel_diampu = $_SESSION['mapel_diampu'] ?? [];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? [];

// Tentukan role badge
$role_badge = $is_guru_mapel ? 'Guru Mapel' : 'Guru';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Ujian Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4e73df;
            --secondary: #6c757d;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        
        /* Sidebar - Fixed Position */
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, #224abe 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-content {
            padding: 20px 15px;
            position: relative;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 12px 15px;
            margin: 4px 0;
            border-radius: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: block;
            border: none;
            background: transparent;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.15);
            transform: none;
        }
        
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        
        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 8px;
        }
        
        /* Main Content - Stable Layout */
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            background-color: #f8f9fa;
            position: relative;
        }
        
        .content-area {
            padding: 20px;
            min-height: calc(100vh - 70px);
        }
        
        /* Navbar - Fixed Position */
        .navbar {
            height: 70px;
            background: white !important;
            border-bottom: 1px solid #dee2e6;
            position: relative;
            z-index: 999;
        }
        
        /* Stat Cards */
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Mobile Styles - Remove transitions that cause movement */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.mobile-active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .content-area {
                padding: 15px;
            }
            
            .navbar {
                position: sticky;
                top: 0;
            }
        }
        
        /* Ensure content doesn't shift */
        html, body {
            overflow-x: hidden;
        }
        
        .container-fluid {
            padding-left: 15px;
            padding-right: 15px;
        }
        
        /* Remove any transform effects that cause movement */
        .nav-link {
            transform: none !important;
        }
        
        /* Fix for dropdown menu */
        .dropdown-menu {
            border: 1px solid rgba(0,0,0,.15);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        /* Smooth animations only where needed */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Badge untuk role */
        .role-badge {
            font-size: 0.7em;
            padding: 3px 8px;
        }
        
        /* Info box di sidebar */
        .sidebar-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border-left: 3px solid #1cc88a;
        }
        
        .sidebar-info h6 {
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .sidebar-info .badge {
            font-size: 0.75em;
            margin: 2px;
        }
        
        /* Menu separator */
        .menu-separator {
            margin: 15px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 10px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            padding-left: 15px;
        }
        
        /* Badge notifikasi untuk monitoring */
        .nav-link .badge-notif {
            background-color: #e74a3b;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 10px;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-content">
            <div class="text-center mb-4">
                <h4><i class="fas fa-chalkboard-teacher me-2"></i>Ujian Online</h4>
                <hr class="bg-light my-2">
                <small class="text-white-50">
                    <?php echo $role_badge; ?>
                </small>
            </div>
            
            <ul class="nav flex-column">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard_guru.php' ? 'active' : ''; ?>" href="dashboard_guru.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                
                <!-- Menu untuk SEMUA guru -->
                <div class="menu-separator">Menu Guru</div>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'kelola_soal_guru.php' ? 'active' : ''; ?>" href="kelola_soal_guru.php">
                        <i class="fas fa-question-circle"></i> Input Soal
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'hasil_ujian_guru.php' ? 'active' : ''; ?>" href="hasil_ujian_guru.php">
                        <i class="fas fa-chart-line"></i> Hasil Ujian
                    </a>
                </li>
                
                <!-- MENU MONITORING UJIAN SISWA (BARU) -->
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'monitoring_ujian_guru.php' ? 'active' : ''; ?>" href="monitoring_ujian_guru.php">
                        <i class="fas fa-desktop"></i> Monitoring Ujian
                        <span class="badge-notif" id="monitoringNotif">Live</span>
                    </a>
                </li>
                
                <!-- Menu Pengawasan Ujian (untuk SEMUA guru) -->
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'absensi_ujian_guru.php' ? 'active' : ''; ?>" href="absensi_ujian_guru.php">
                        <i class="fas fa-clipboard-check"></i> Absensi Ujian
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'berita_acara_guru.php' ? 'active' : ''; ?>" href="berita_acara_guru.php">
                        <i class="fas fa-file-contract"></i> Berita Acara
                    </a>
                </li>
                
                <!-- Menu khusus untuk GURU MAPEL -->
                <?php if ($is_guru_mapel): ?>
                <div class="menu-separator">Menu Guru Mapel</div>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'input_nilai.php' ? 'active' : ''; ?>" href="input_nilai.php">
                        <i class="fas fa-edit"></i> Input Nilai
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Profile -->
                <div class="menu-separator">Pengaturan</div>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile_guru.php' ? 'active' : ''; ?>" href="profile_guru.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                
                <!-- Logout -->
                <li class="nav-item mt-4">
                    <a class="nav-link text-warning" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
            
            <?php if ($is_guru_mapel && (count($mapel_diampu) > 0 || count($kelas_diampu) > 0)): ?>
            <!-- Info mapel dan kelas yang diampu -->
            <div class="sidebar-info">
                <?php if (count($mapel_diampu) > 0): ?>
                <h6><i class="fas fa-book me-1"></i>Mata Pelajaran:</h6>
                <div class="mb-3">
                    <?php foreach ($mapel_diampu as $mapel): ?>
                        <span class="badge bg-light text-dark mb-1 me-1" style="font-size: 0.7em;">
                            <?php echo htmlspecialchars($mapel['kode_mapel']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (count($kelas_diampu) > 0): ?>
                <h6><i class="fas fa-users me-1"></i>Kelas:</h6>
                <div>
                    <?php foreach ($kelas_diampu as $kelas): ?>
                        <span class="badge bg-success mb-1 me-1" style="font-size: 0.7em;">
                            <?php echo htmlspecialchars($kelas['kelas']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white">
            <div class="container-fluid">
                <button class="btn btn-primary d-md-none" type="button" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Guru'); ?>
                            <span class="badge bg-primary ms-1 role-badge">
                                <?php echo $role_badge; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile_guru.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="dashboard_guru.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="monitoring_ujian_guru.php"><i class="fas fa-desktop me-2"></i>Monitoring Ujian</a></li>
                            <li><a class="dropdown-item" href="absensi_ujian_guru.php"><i class="fas fa-clipboard-check me-2"></i>Absensi Ujian</a></li>
                            <li><a class="dropdown-item" href="berita_acara_guru.php"><i class="fas fa-file-contract me-2"></i>Berita Acara</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area fade-in">