<?php
// index.php - Redirect berdasarkan status login
require_once 'config.php';

// Cek jika user sudah login
if(isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Redirect berdasarkan role
    if(isset($_SESSION['role'])) {
        switch($_SESSION['role']) {
            case 'guru':
                redirect('dashboard.php');
                break;
            case 'siswa':
                // Untuk siswa, redirect ke halaman ujian atau dashboard siswa
                if(file_exists('ujian_siswa.php')) {
                    redirect('ujian_siswa.php');
                } else {
                    redirect('dashboard.php');
                }
                break;
            default:
                redirect('dashboard.php');
        }
    } else {
        redirect('dashboard.php');
    }
} else {
    // Jika belum login, redirect ke login page
    redirect('login.php');
}
exit();
?>