<?php
// templates/header_siswa.php - Header untuk siswa (MOBILE FRIENDLY, TANPA ANIMASI)
if (!isset($title)) {
    $title = "Siswa - Ujian Online";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $title; ?> - Ujian Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ============================================
           CSS TANPA ANIMASI - MOBILE FRIENDLY
           ============================================ */
        
        /* Root Variables */
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --border: #dee2e6;
        }
        
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background-color: var(--light);
            overflow-x: hidden;
        }
        
        /* ============================================
           SIDEBAR - STABLE (TIDAK BERGESER)
           ============================================ */
        .sidebar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
        }
        
        /* Sidebar scroll styling */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .sidebar-content {
            padding: 20px 12px;
        }
        
        .sidebar-logo {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .sidebar-logo h4 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .sidebar-logo hr {
            margin: 12px 0;
            background-color: rgba(255,255,255,0.2);
        }
        
        .sidebar-logo small {
            font-size: 11px;
            opacity: 0.7;
        }
        
        /* Nav Links */
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 10px 12px;
            margin: 2px 0;
            border-radius: 8px;
            text-decoration: none;
            display: block;
            font-size: 13px;
            font-weight: 500;
            background: transparent;
            border: none;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.12);
        }
        
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        
        .sidebar .nav-link i {
            width: 28px;
            text-align: center;
            margin-right: 8px;
            font-size: 14px;
        }
        
        .sidebar .nav-link.text-warning {
            color: #ffc107 !important;
        }
        
        .sidebar .nav-link.text-warning:hover {
            background: rgba(255,193,7,0.15);
        }
        
        /* Divider in sidebar */
        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 12px 0;
        }
        
        /* ============================================
           MAIN CONTENT - STABLE
           ============================================ */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background-color: var(--light);
        }
        
        /* ============================================
           NAVBAR - STICKY
           ============================================ */
        .navbar-custom {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 0 20px;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .navbar-custom .btn-menu {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .navbar-custom .btn-menu:active {
            background-color: var(--primary-dark);
        }
        
        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            background: transparent;
            border: none;
        }
        
        .user-dropdown:hover {
            background-color: var(--light);
        }
        
        .user-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .user-badge {
            background-color: var(--success);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }
        
        /* ============================================
           CONTENT AREA
           ============================================ */
        .content-area {
            padding: 20px;
            min-height: calc(100vh - 60px);
        }
        
        /* ============================================
           CARD STYLES
           ============================================ */
        .card-custom {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .card-header-custom {
            padding: 14px 16px;
            background-color: white;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 14px;
        }
        
        .card-body-custom {
            padding: 16px;
        }
        
        /* ============================================
           STAT CARD
           ============================================ */
        .stat-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .stat-card .stat-body {
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 4px;
        }
        
        .stat-card .stat-label {
            font-size: 12px;
            color: var(--gray);
        }
        
        .stat-card .stat-icon {
            font-size: 32px;
            opacity: 0.6;
        }
        
        /* Stat Card Colors */
        .stat-card.bg-primary .stat-body { background-color: var(--primary); color: white; }
        .stat-card.bg-success .stat-body { background-color: var(--success); color: white; }
        .stat-card.bg-info .stat-body { background-color: var(--info); color: white; }
        .stat-card.bg-warning .stat-body { background-color: var(--warning); color: #212529; }
        .stat-card.bg-danger .stat-body { background-color: var(--danger); color: white; }
        
        /* ============================================
           BUTTON STYLES
           ============================================ */
        .btn-custom {
            display: inline-block;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary-custom { background-color: var(--primary); color: white; }
        .btn-success-custom { background-color: var(--success); color: white; }
        .btn-warning-custom { background-color: var(--warning); color: #212529; }
        .btn-danger-custom { background-color: var(--danger); color: white; }
        .btn-secondary-custom { background-color: var(--gray); color: white; }
        
        .btn-custom:active {
            opacity: 0.85;
        }
        
        /* ============================================
           BADGE STYLES
           ============================================ */
        .badge-custom {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-success { background-color: #d4edda; color: #155724; }
        .badge-danger { background-color: #f8d7da; color: #721c24; }
        .badge-warning { background-color: #fff3cd; color: #856404; }
        .badge-info { background-color: #d1ecf1; color: #0c5460; }
        .badge-primary { background-color: #cce5ff; color: #004085; }
        
        /* ============================================
           TABLE STYLES
           ============================================ */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .data-table th,
        .data-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .data-table th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--dark);
        }
        
        .data-table tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        /* ============================================
           FORM STYLES
           ============================================ */
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--dark);
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: white;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* ============================================
           ALERT STYLES
           ============================================ */
        .alert-custom {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        
        .alert-success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-warning { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; }
        .alert-info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        
        /* ============================================
           GRID & UTILITY
           ============================================ */
        .row-grid {
            display: flex;
            flex-wrap: wrap;
            margin: -8px;
        }
        
        .col-grid {
            padding: 8px;
        }
        
        .col-6 { width: 50%; }
        .col-12 { width: 100%; }
        
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-muted { color: var(--gray); }
        .small { font-size: 11px; }
        .fw-bold { font-weight: 600; }
        
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        
        .d-flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .align-center { align-items: center; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 8px; }
        
        /* ============================================
           MOBILE RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                transition: transform 0.3s ease;
            }
            
            .sidebar.mobile-active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-area {
                padding: 12px;
            }
            
            .navbar-custom {
                padding: 0 12px;
                height: 55px;
            }
            
            .user-name {
                display: none;
            }
            
            .stat-card .stat-number {
                font-size: 22px;
            }
            
            .stat-card .stat-icon {
                font-size: 28px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 8px;
                font-size: 12px;
            }
            
            .btn-custom {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
        
        @media (min-width: 769px) {
            .btn-menu {
                display: none !important;
            }
        }
        
        @media (min-width: 992px) {
            .col-lg-4 { width: 33.333%; }
            .col-lg-6 { width: 50%; }
        }
        
        /* ============================================
           NO ANIMATIONS - KECUALI SIDEBAR TOGGLE
           ============================================ */
        .no-transition {
            transition: none !important;
        }
        
        /* Override Bootstrap animations */
        .fade {
            transition: none !important;
        }
        
        .modal.fade .modal-dialog {
            transition: none !important;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-logo">
            <h4><i class="fas fa-graduation-cap me-2"></i>Ujian Online</h4>
            <hr>
            <small>Panel Siswa</small>
        </div>
        
        <div class="sidebar-divider"></div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard_siswa.php' ? 'active' : ''; ?>" href="dashboard_siswa.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'daftar_ujian.php' ? 'active' : ''; ?>" href="daftar_ujian.php">
                    <i class="fas fa-clipboard-list"></i> Daftar Ujian
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'riwayat_ujian.php' ? 'active' : ''; ?>" href="riwayat_ujian.php">
                    <i class="fas fa-history"></i> Riwayat Ujian
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile_siswa.php' ? 'active' : ''; ?>" href="profile_siswa.php">
                    <i class="fas fa-user"></i> Profile
                </a>
            </li>
            
            <div class="sidebar-divider"></div>
            
            <li class="nav-item">
                <a class="nav-link text-warning" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Navbar -->
    <nav class="navbar-custom d-flex justify-content-between align-center">
        <div>
            <button class="btn-menu" id="sidebarToggle" type="button">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="user-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle fa-lg" style="color: var(--primary);"></i>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Siswa'); ?></span>
            <span class="user-badge">Siswa</span>
            <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--gray);"></i>
        </div>
        
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile_siswa.php"><i class="fas fa-user me-2"></i>Profile</a></li>
            <li><a class="dropdown-item" href="dashboard_siswa.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </nav>

    <!-- Content Area -->
    <div class="content-area">