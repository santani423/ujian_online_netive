<?php
// cetak_kartu_ujian.php - KARTU UKURAN KECIL (4-6 Kartu per Halaman)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Ambil data profil sekolah dari database
$stmt_profil = $pdo->query("SELECT * FROM profil_sekolah ORDER BY id DESC LIMIT 1");
$sekolah = $stmt_profil->fetch(PDO::FETCH_ASSOC);

// Jika tidak ada data profil, gunakan default
if (!$sekolah) {
    $sekolah = [
        'nama_sekolah' => 'SEKOLAH ANDA',
        'npsn' => '',
        'alamat' => '',
        'telepon' => '',
        'email' => '',
        'website' => '',
        'logo' => '',
        'kepala_sekolah' => '',
        'nip_kepala' => ''
    ];
}

// Ambil data siswa berdasarkan parameter
$siswa_list = [];

try {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT s.*, u.username FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $stmt->execute([$id]);
        $siswa = $stmt->fetch();
        if ($siswa) {
            $siswa_list = [$siswa];
        }
    } elseif (isset($_GET['massal'])) {
        if (isset($_POST['siswa_ids'])) {
            $ids = $_POST['siswa_ids'];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $pdo->prepare("SELECT s.*, u.username FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.id IN ($placeholders) ORDER BY s.kelas, s.nama");
            $stmt->execute($ids);
            $siswa_list = $stmt->fetchAll();
        } else {
            $stmt = $pdo->query("SELECT s.*, u.username FROM siswa s JOIN users u ON s.user_id = u.id ORDER BY s.kelas, s.nama");
            $siswa_list = $stmt->fetchAll();
        }
    } else {
        $stmt = $pdo->query("SELECT s.*, u.username FROM siswa s JOIN users u ON s.user_id = u.id ORDER BY s.kelas, s.nama");
        $siswa_list = $stmt->fetchAll();
    }
} catch (Exception $e) {
    die("Error mengambil data: " . $e->getMessage());
}

if (empty($siswa_list)) {
    die("Tidak ada data siswa yang ditemukan.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Ujian Siswa - <?= count($siswa_list) ?> Siswa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Arial', sans-serif;
            background: #e0e0e0;
            padding: 20px;
        }
        
        /* Container kartu - menggunakan grid untuk 4 kartu per baris */
        .kartu-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            max-width: 100%;
            margin: 0 auto;
        }
        
        /* Ukuran kartu: sekitar 85mm x 55mm dalam satuan cm */
        .kartu {
            width: 100%;
            max-width: 210px;
            min-width: 180px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 8px 10px;
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
            border: 1px solid #ddd;
        }
        
        /* Background gradasi atas */
        .kartu::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* Kop sekolah */
        .kop-sekolah {
            text-align: center;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #eee;
        }
        
        .kop-sekolah .logo {
            height: 22px;
            max-width: 25px;
            object-fit: contain;
            display: inline-block;
            vertical-align: middle;
            margin-right: 4px;
        }
        
        .kop-sekolah .nama-sekolah {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #2c3e50;
            display: inline-block;
            vertical-align: middle;
        }
        
        .kop-sekolah .alamat {
            font-size: 5.5px;
            color: #999;
            margin-top: 2px;
            line-height: 1.2;
        }
        
        /* Judul Kartu */
        .judul-kartu {
            text-align: center;
            margin-bottom: 5px;
        }
        
        .judul-kartu h5 {
            font-size: 7.5px;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 0.5px;
        }
        
        .judul-kartu .tahun {
            font-size: 5.5px;
            color: #999;
        }
        
        /* Informasi siswa */
        .info-siswa {
            margin-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 3px;
            font-size: 6.5px;
        }
        
        .label {
            font-weight: 600;
            color: #555;
            min-width: 50px;
            font-size: 6.5px;
        }
        
        .value {
            color: #333;
            font-weight: 500;
            text-align: right;
            flex: 1;
            font-size: 6.5px;
        }
        
        /* Box login */
        .login-box {
            background: #f5f5f5;
            padding: 4px 6px;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        
        .login-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            font-size: 6.5px;
        }
        
        .login-label {
            font-weight: 600;
            color: #555;
        }
        
        .login-value {
            font-family: monospace;
            font-weight: bold;
            color: #333;
        }
        
        .password-badge {
            background: #ff6b6b;
            color: white;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 5.5px;
            font-weight: bold;
        }
        
        /* Informasi penting */
        .info-penting {
            background: #fff8e1;
            border-left: 2px solid #ffc107;
            padding: 4px 5px;
            margin-bottom: 4px;
        }
        
        .info-penting h6 {
            font-size: 6px;
            font-weight: bold;
            color: #856404;
            margin-bottom: 2px;
        }
        
        .info-penting ul {
            list-style: none;
            padding-left: 8px;
        }
        
        .info-penting li {
            margin-bottom: 1.5px;
            position: relative;
            font-size: 5px;
            line-height: 1.2;
        }
        
        .info-penting li::before {
            content: '•';
            color: #856404;
            position: absolute;
            left: -6px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            font-size: 4.5px;
            color: #aaa;
            margin-top: 4px;
            padding-top: 3px;
            border-top: 1px dashed #eee;
        }
        
        /* Controls untuk cetak */
        .no-print {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            margin: 0 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; }
        
        /* Print style */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .kartu-container {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
                margin: 0;
            }
            .kartu {
                box-shadow: none;
                border: 1px solid #ccc;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            @page {
                size: A4;
                margin: 1cm;
            }
        }
        
        /* Responsive: jika layar kecil, kurangi jumlah kartu per baris */
        @media (max-width: 800px) {
            .kartu-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .kartu-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 400px) {
            .kartu-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Tombol kontrol (tidak tercetak) -->
    <div class="no-print">
        <div style="margin-bottom: 15px;">
            <?php if (!empty($sekolah['logo']) && file_exists($sekolah['logo'])): ?>
            <img src="<?= htmlspecialchars($sekolah['logo']) ?>" alt="Logo" style="height: 40px; vertical-align: middle; margin-right: 10px;">
            <?php endif; ?>
            <span style="font-size: 18px; font-weight: bold;"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></span>
        </div>
        <div>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> CETAK KARTU
            </button>
            <button class="btn btn-success" onclick="window.location.href='kelola_siswa.php'">
                <i class="fas fa-arrow-left"></i> KEMBALI
            </button>
            <button class="btn btn-danger" onclick="window.close()">
                <i class="fas fa-times"></i> TUTUP
            </button>
        </div>
        <div style="margin-top: 10px; color: #666; font-size: 12px;">
            <i class="fas fa-info-circle"></i> 
            Jumlah siswa: <strong><?= count($siswa_list) ?></strong> | 
            Ukuran kartu: ~85mm x 55mm | 
            <strong>4 kartu per halaman A4</strong> (hemat kertas)
        </div>
        <hr style="margin: 15px 0;">
    </div>

    <!-- Container Kartu - Grid 4 kolom -->
    <div class="kartu-container">
        <?php foreach ($siswa_list as $siswa): ?>
        <div class="kartu">
            <!-- Kop Sekolah -->
            <div class="kop-sekolah">
                <div>
                    <?php if (!empty($sekolah['logo']) && file_exists($sekolah['logo'])): ?>
                    <img src="<?= htmlspecialchars($sekolah['logo']) ?>" alt="Logo" class="logo">
                    <?php endif; ?>
                    <span class="nama-sekolah"><?= htmlspecialchars(substr($sekolah['nama_sekolah'], 0, 25)) ?></span>
                </div>
                <div class="alamat"><?= htmlspecialchars(substr($sekolah['alamat'], 0, 45)) ?></div>
            </div>
            
            <!-- Judul Kartu -->
            <div class="judul-kartu">
                <h5>KARTU PESERTA UJIAN</h5>
                <div class="tahun">TAHUN <?= date('Y') ?></div>
            </div>
            
            <!-- Info Siswa -->
            <div class="info-siswa">
                <div class="info-row">
                    <span class="label">Nama</span>
                    <span class="value"><?= htmlspecialchars(substr($siswa['nama'], 0, 20)) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">NISN</span>
                    <span class="value"><?= htmlspecialchars($siswa['nisn']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Kelas</span>
                    <span class="value"><?= htmlspecialchars($siswa['kelas']) ?></span>
                </div>
            </div>
            
            <!-- Login Info -->
            <div class="login-box">
                <div class="login-row">
                    <span class="login-label">Username</span>
                    <span class="login-value"><?= htmlspecialchars($siswa['username']) ?></span>
                </div>
                <div class="login-row">
                    <span class="login-label">Password</span>
                    <span class="login-value"><span class="password-badge">siswa123</span></span>
                </div>
            </div>
            
            <!-- Info Penting -->
            <div class="info-penting">
                <h6>INFORMASI:</h6>
                <ul>
                    <li>Kartu harus dibawa saat ujian</li>
                    <li>Jaga kerahasiaan login</li>
                </ul>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <i class="fas fa-qrcode"></i> Berlaku untuk semua ujian online
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Auto print untuk single siswa -->
    <?php if (count($siswa_list) === 1): ?>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 500);
        };
    </script>
    <?php endif; ?>
</body>
</html>