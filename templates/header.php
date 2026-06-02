<?php
// templates/header.php
if (!isset($title)) {
    $title = "Ujian Online";
}

// Dapatkan nama file saat ini
$current_page = basename($_SERVER['PHP_SELF']);

// Fungsi untuk mengecek apakah halaman aktif
function isActivePage($page_name) {
    global $current_page;
    return $current_page == $page_name;
}

// Fungsi untuk mengecek apakah halaman ada dalam array
function isActivePages($page_array) {
    global $current_page;
    return in_array($current_page, $page_array);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Ujian Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
        }
        
        /* PERBAIKAN: Label kategori tidak terpengaruh active state */
        .sidebar .menu-category {
            color: rgba(255,255,255,0.6) !important;
            padding: 8px 20px;
            margin-top: 15px;
            margin-bottom: 5px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Pastikan small tag di dalam nav-link tidak mewarisi warna */
        .sidebar .nav-link.active small,
        .sidebar .nav-link:hover small {
            color: rgba(255,255,255,0.6) !important;
        }
        
        /* Target khusus untuk kategori menu */
        .sidebar .nav-item.menu-category-item small {
            color: rgba(255,255,255,0.6) !important;
            pointer-events: none; /* Tidak bisa diklik */
            user-select: none; /* Tidak bisa diselect */
        }
        
        /* Override untuk bootstrap */
        .sidebar .text-muted {
            color: rgba(255,255,255,0.6) !important;
        }
        
        .main-content {
            margin-left: 250px;
            transition: all 0.3s;
            min-height: 100vh;
        }
        
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Badge untuk menu baru */
        .badge-menu {
            font-size: 0.7rem;
            padding: 3px 8px;
        }
        
        /* Navbar atas */
        .top-navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 999;
        }
        
        /* Icon khusus untuk dashboard */
        .sidebar .nav-link.active[href="dashboard.php"] {
            background: rgba(255,255,255,0.15);
            border-left: 4px solid rgba(255,255,255,0.8);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar p-3">
            <div class="text-center mb-4">
                <h4><i class="fas fa-graduation-cap me-2"></i>Ujian Online</h4>
                <hr class="bg-light">
            </div>
            
            <ul class="nav flex-column">
                <!-- Menu Dashboard - SELALU ADA -->
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                
                <?php if ($_SESSION['role'] == 'guru'): ?>
                <!-- MENU UNTUK GURU - SELALU TAMPIL -->
                <!-- Menu Pengelolaan -->
                <li class="nav-item menu-category-item mt-2">
                    <small class="menu-category">PENGELOLAAN</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('kelola_guru.php') ? 'active' : ''; ?>" href="kelola_guru.php">
                        <i class="fas fa-chalkboard-teacher"></i> Kelola Guru
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('kelola_siswa.php') ? 'active' : ''; ?>" href="kelola_siswa.php">
                        <i class="fas fa-users"></i> Kelola Siswa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('kelola_mapel.php') ? 'active' : ''; ?>" href="kelola_mapel.php">
                        <i class="fas fa-book"></i> Kelola Mapel
                    </a>
                </li>
                
                <!-- Menu Ujian -->
                <li class="nav-item menu-category-item mt-2">
                    <small class="menu-category">UJIAN</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('kelola_soal.php') ? 'active' : ''; ?>" href="kelola_soal.php">
                        <i class="fas fa-question-circle"></i> Kelola Soal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('kelola_ujian.php') ? 'active' : ''; ?>" href="kelola_ujian.php">
                        <i class="fas fa-clipboard-list"></i> Kelola Ujian
                    </a>
                </li>
                
                <!-- Menu Baru: Absensi Ujian -->
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePages(['absensi_ujian.php', 'proses_absensi.php']) ? 'active' : ''; ?>" href="absensi_ujian.php">
                        <i class="fas fa-clipboard-check"></i> Absensi Ujian
                        <span class="badge bg-warning badge-menu float-end">New</span>
                    </a>
                </li>
                
                <!-- Menu Baru: Berita Acara -->
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePages(['berita_acara.php', 'detail_berita_acara.php', 'proses_berita_acara.php']) ? 'active' : ''; ?>" href="berita_acara.php">
                        <i class="fas fa-file-alt"></i> Berita Acara
                        <span class="badge bg-warning badge-menu float-end">New</span>
                    </a>
                </li>
                
                <!-- Menu Laporan -->
                <li class="nav-item menu-category-item mt-2">
                    <small class="menu-category">LAPORAN</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('hasil_ujian.php') ? 'active' : ''; ?>" href="hasil_ujian.php">
                        <i class="fas fa-chart-bar"></i> Hasil Ujian
                    </a>
                </li>
                
                <!-- Menu Pengaturan -->
                <li class="nav-item menu-category-item mt-2">
                    <small class="menu-category">PENGATURAN</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePages(['pengaturan.php', 'profil_sekolah.php']) ? 'active' : ''; ?>" href="pengaturan.php">
                        <i class="fas fa-cog"></i> Pengaturan
                    </a>
                </li>
                
                <?php else: ?>
                <!-- MENU UNTUK SISWA - SELALU TAMPIL -->
                <!-- Menu Ujian Siswa -->
                <li class="nav-item menu-category-item mt-2">
                    <small class="menu-category">UJIAN</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('daftar_ujian.php') ? 'active' : ''; ?>" href="daftar_ujian.php">
                        <i class="fas fa-clipboard-list"></i> Daftar Ujian
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('riwayat_ujian.php') ? 'active' : ''; ?>" href="riwayat_ujian.php">
                        <i class="fas fa-history"></i> Riwayat Ujian
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Menu Profil - SELALU ADA UNTUK SEMUA ROLE -->
                <li class="nav-item menu-category-item mt-2">
                    <small class="menu-category">AKUN</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('profile.php') ? 'active' : ''; ?>" href="profile.php">
                        <i class="fas fa-user"></i> Profil
                    </a>
                </li>
                
                <!-- Menu Logout - SELALU ADA -->
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content w-100">
            <!-- Navbar Atas -->
            <nav class="navbar navbar-expand-lg top-navbar">
                <div class="container-fluid">
                    <button class="btn btn-primary d-md-none" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                            <li class="breadcrumb-item active"><?php echo $title; ?></li>
                        </ol>
                    </nav>
                    
                    <!-- User Profile -->
                    <div class="navbar-nav ms-auto">
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
                                <span class="badge bg-<?php echo $_SESSION['role'] == 'guru' ? 'primary' : 'success'; ?> ms-1">
                                    <?php echo ucfirst($_SESSION['role']); ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="pengaturan.php"><i class="fas fa-cog me-2"></i>Pengaturan</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Content akan dimasukkan di sini -->
            <div class="container-fluid p-4">