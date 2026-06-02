<?php
// cetak_berita_acara.php - Cetak berita acara per kelas (FIXED - No HTML Table)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Ambil parameter
$ujian_id = isset($_GET['ujian_id']) ? intval($_GET['ujian_id']) : 0;
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// Pastikan ada parameter ujian_id dan kelas
if ($ujian_id == 0 || empty($kelas)) {
    die("Parameter tidak lengkap. Silakan pilih ujian dan kelas terlebih dahulu.");
}

// Ambil data berita acara berdasarkan ujian_id dan kelas
$query = "SELECT ba.*, u.judul_ujian, u.waktu_mulai as ujian_mulai, u.waktu_selesai as ujian_selesai, u.kelas_target,
          g.nama as nama_guru, g.nip
          FROM berita_acara ba 
          JOIN ujian u ON ba.ujian_id = u.id 
          JOIN guru g ON ba.guru_pengawas = g.id 
          WHERE ba.ujian_id = ? AND ba.kelas = ?";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ujian_id, $kelas]);
    $berita_acara = $stmt->fetch();
    
    if (!$berita_acara) {
        die("Data berita acara untuk kelas $kelas tidak ditemukan. Silakan buat berita acara terlebih dahulu.");
    }
} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

// Ambil daftar absensi untuk ujian dan kelas ini
$query_absensi = "SELECT s.nama, s.nisn, au.status_hadir, au.keterangan 
                  FROM siswa s
                  LEFT JOIN absensi_ujian au ON au.siswa_id = s.id AND au.ujian_id = ?
                  WHERE s.kelas = ?
                  ORDER BY s.nama";
$stmt_absensi = $pdo->prepare($query_absensi);
$stmt_absensi->execute([$ujian_id, $kelas]);
$absensi = $stmt_absensi->fetchAll();

// Hitung ulang statistik untuk kelas ini
$jumlah_peserta = count($absensi);
$jumlah_hadir = 0;
$jumlah_tidak_hadir = 0;
$jumlah_izin = 0;
$jumlah_sakit = 0;

foreach ($absensi as $siswa) {
    switch ($siswa['status_hadir']) {
        case 'hadir':
            $jumlah_hadir++;
            break;
        case 'tidak_hadir':
            $jumlah_tidak_hadir++;
            break;
        case 'izin':
            $jumlah_izin++;
            break;
        case 'sakit':
            $jumlah_sakit++;
            break;
    }
}

// Ambil profil sekolah
$profil_sekolah = getProfilSekolah();

// Include TCPDF library
require_once('tcpdf/tcpdf.php');

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Sistem Ujian Online');
$pdf->SetAuthor($profil_sekolah['nama_sekolah']);
$pdf->SetTitle('Berita Acara Ujian - Kelas ' . $kelas . ' - ' . $berita_acara['judul_ujian']);
$pdf->SetSubject('Berita Acara Ujian');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 16);

// Header sekolah
$logo_path = !empty($profil_sekolah['logo']) && file_exists($profil_sekolah['logo']) ? $profil_sekolah['logo'] : '';
if ($logo_path) {
    $pdf->Image($logo_path, 15, 10, 20, 20, '', '', '', false, 300, '', false, false, 0);
    $pdf->SetY(15);
    $pdf->Cell(0, 8, strtoupper($profil_sekolah['nama_sekolah']), 0, 1, 'C');
} else {
    $pdf->Cell(0, 8, strtoupper($profil_sekolah['nama_sekolah']), 0, 1, 'C');
}

$pdf->SetFont('helvetica', '', 10);
if (!empty($profil_sekolah['npsn'])) {
    $pdf->Cell(0, 5, 'NPSN: ' . $profil_sekolah['npsn'], 0, 1, 'C');
}
if (!empty($profil_sekolah['alamat'])) {
    $pdf->Cell(0, 5, $profil_sekolah['alamat'], 0, 1, 'C');
}
if (!empty($profil_sekolah['telepon']) || !empty($profil_sekolah['email'])) {
    $info = '';
    if (!empty($profil_sekolah['telepon'])) $info .= 'Telp: ' . $profil_sekolah['telepon'];
    if (!empty($profil_sekolah['email'])) $info .= ' | Email: ' . $profil_sekolah['email'];
    $pdf->Cell(0, 5, $info, 0, 1, 'C');
}

// Line separator
$pdf->Ln(5);
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(8);

// Judul berita acara
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'BERITA ACARA UJIAN', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, $berita_acara['judul_ujian'], 0, 1, 'C');

// Informasi ujian - Menggunakan Cell (bukan writeHTML)
$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 11);

// Tabel informasi ujian dengan garis
$pdf->SetFillColor(245, 245, 245);
$pdf->SetLineWidth(0.2);

// Baris 1: Tanggal Ujian
$pdf->Cell(50, 8, 'Tanggal Ujian', 1, 0, 'L', true);
$pdf->Cell(0, 8, ': ' . date('d F Y', strtotime($berita_acara['tanggal_ujian'])), 1, 1, 'L');

// Baris 2: Waktu Pelaksanaan
$pdf->Cell(50, 8, 'Waktu Pelaksanaan', 1, 0, 'L', true);
$pdf->Cell(0, 8, ': ' . substr($berita_acara['waktu_mulai'], 0, 5) . ' - ' . substr($berita_acara['waktu_selesai'], 0, 5) . ' WIB', 1, 1, 'L');

// Baris 3: Guru Pengawas
$pdf->Cell(50, 8, 'Guru Pengawas', 1, 0, 'L', true);
$guru_text = $berita_acara['nama_guru'];
if ($berita_acara['nip']) $guru_text .= ' (NIP: ' . $berita_acara['nip'] . ')';
$pdf->Cell(0, 8, ': ' . $guru_text, 1, 1, 'L');

// Baris 4: Kelas
$pdf->Cell(50, 8, 'Kelas', 1, 0, 'L', true);
$pdf->Cell(0, 8, ': ' . $kelas, 1, 1, 'L');

// Baris 5: Ruangan
$pdf->Cell(50, 8, 'Ruangan', 1, 0, 'L', true);
$pdf->Cell(0, 8, ': ' . ($berita_acara['ruangan'] ?: '-'), 1, 1, 'L');

// Baris 6: Kelas Target Ujian
$pdf->Cell(50, 8, 'Kelas Target Ujian', 1, 0, 'L', true);
$pdf->Cell(0, 8, ': ' . ($berita_acara['kelas_target'] ?: '-'), 1, 1, 'L');

// Statistik peserta
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'STATISTIK PESERTA UJIAN', 0, 1, 'L');

$persentase_kehadiran = $jumlah_peserta > 0 ? round(($jumlah_hadir / $jumlah_peserta) * 100, 2) : 0;

// Tabel Statistik
$pdf->SetFont('helvetica', '', 10);
$pdf->SetFillColor(52, 152, 219); // Biru untuk header
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);

// Header tabel statistik
$col_width = 180 / 5; // 36mm per kolom
$pdf->Cell($col_width, 8, 'Jumlah Peserta', 1, 0, 'C', true);
$pdf->Cell($col_width, 8, 'Hadir', 1, 0, 'C', true);
$pdf->Cell($col_width, 8, 'Tidak Hadir', 1, 0, 'C', true);
$pdf->Cell($col_width, 8, 'Izin', 1, 0, 'C', true);
$pdf->Cell($col_width, 8, 'Sakit', 1, 1, 'C', true);

// Data statistik
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell($col_width, 8, $jumlah_peserta, 1, 0, 'C');
$pdf->Cell($col_width, 8, $jumlah_hadir, 1, 0, 'C');
$pdf->Cell($col_width, 8, $jumlah_tidak_hadir, 1, 0, 'C');
$pdf->Cell($col_width, 8, $jumlah_izin, 1, 0, 'C');
$pdf->Cell($col_width, 8, $jumlah_sakit, 1, 1, 'C');

// Baris persentase
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($col_width * 5, 8, 'Persentase Kehadiran: ' . $persentase_kehadiran . '%', 1, 1, 'C', true);

// Daftar Absensi
if (count($absensi) > 0) {
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'DAFTAR ABSENSI PESERTA - KELAS ' . $kelas, 0, 1, 'L');
    
    // Set font untuk tabel
    $pdf->SetFont('helvetica', '', 9);
    
    // Lebar kolom
    $col_no = 10;       // No
    $col_nisn = 28;     // NISN
    $col_nama = 75;     // Nama Siswa
    $col_status = 25;   // Status
    $col_keterangan = 42; // Keterangan
    
    // Header tabel absensi
    $pdf->SetFillColor(52, 73, 94); // Dark gray
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    
    $pdf->Cell($col_no, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell($col_nisn, 8, 'NISN', 1, 0, 'C', true);
    $pdf->Cell($col_nama, 8, 'Nama Siswa', 1, 0, 'C', true);
    $pdf->Cell($col_status, 8, 'Status', 1, 0, 'C', true);
    $pdf->Cell($col_keterangan, 8, 'Keterangan', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', '', 9);
    
    $no = 1;
    foreach ($absensi as $siswa) {
        // Cek jika perlu page break
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            // Draw header lagi di halaman baru
            $pdf->SetFillColor(52, 73, 94);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell($col_no, 8, 'No', 1, 0, 'C', true);
            $pdf->Cell($col_nisn, 8, 'NISN', 1, 0, 'C', true);
            $pdf->Cell($col_nama, 8, 'Nama Siswa', 1, 0, 'C', true);
            $pdf->Cell($col_status, 8, 'Status', 1, 0, 'C', true);
            $pdf->Cell($col_keterangan, 8, 'Keterangan', 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetFont('helvetica', '', 9);
        }
        
        // Potong nama jika terlalu panjang
        $nama = $siswa['nama'];
        if (strlen($nama) > 32) {
            $nama = substr($nama, 0, 29) . '...';
        }
        
        // Potong keterangan jika terlalu panjang
        $keterangan = $siswa['keterangan'] ?: '-';
        if (strlen($keterangan) > 30) {
            $keterangan = substr($keterangan, 0, 27) . '...';
        }
        
        // Tentukan teks dan warna berdasarkan status
        switch ($siswa['status_hadir']) {
            case 'hadir':
                $status_text = 'HADIR';
                $status_color = array(212, 237, 218);
                break;
            case 'tidak_hadir':
                $status_text = 'TIDAK HADIR';
                $status_color = array(248, 215, 218);
                break;
            case 'izin':
                $status_text = 'IZIN';
                $status_color = array(255, 243, 205);
                break;
            case 'sakit':
                $status_text = 'SAKIT';
                $status_color = array(209, 236, 241);
                break;
            default:
                $status_text = 'BELUM';
                $status_color = array(255, 255, 255);
        }
        
        $pdf->Cell($col_no, 7, $no, 1, 0, 'C');
        $pdf->Cell($col_nisn, 7, $siswa['nisn'], 1, 0, 'C');
        $pdf->Cell($col_nama, 7, $nama, 1, 0, 'L');
        
        $pdf->SetFillColor($status_color[0], $status_color[1], $status_color[2]);
        $pdf->Cell($col_status, 7, $status_text, 1, 0, 'C', true);
        $pdf->SetFillColor(255, 255, 255);
        
        $pdf->Cell($col_keterangan, 7, $keterangan, 1, 1, 'L');
        
        $no++;
    }
}

// Catatan kejadian
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'CATATAN PELAKSANAAN UJIAN', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);

$catatan_ditampilkan = false;

if (!empty($berita_acara['kejadian_penting'])) {
    $pdf->Cell(0, 6, '1. Kejadian Penting:', 0, 1, 'L');
    $pdf->MultiCell(0, 6, htmlspecialchars($berita_acara['kejadian_penting']), 0, 'L');
    $pdf->Ln(3);
    $catatan_ditampilkan = true;
}

if (!empty($berita_acara['kendala_teknis'])) {
    $pdf->Cell(0, 6, '2. Kendala Teknis:', 0, 1, 'L');
    $pdf->MultiCell(0, 6, htmlspecialchars($berita_acara['kendala_teknis']), 0, 'L');
    $pdf->Ln(3);
    $catatan_ditampilkan = true;
}

if (!empty($berita_acara['tindak_lanjut'])) {
    $pdf->Cell(0, 6, '3. Tindak Lanjut:', 0, 1, 'L');
    $pdf->MultiCell(0, 6, htmlspecialchars($berita_acara['tindak_lanjut']), 0, 'L');
    $pdf->Ln(3);
    $catatan_ditampilkan = true;
}

if (!$catatan_ditampilkan) {
    $pdf->MultiCell(0, 6, 'Tidak ada catatan khusus selama pelaksanaan ujian.', 0, 'L');
    $pdf->Ln(3);
}

// Tanda tangan
$pdf->Ln(15);

// Lebar kolom untuk tanda tangan
$kolom_width = 85;
$page_margin = 15;
$kolom1_x = $page_margin;
$kolom2_x = $page_margin + $kolom_width + 10;

// Ambil nama untuk menghitung panjang garis
$nama_guru = trim($berita_acara['nama_guru']);
$nama_kepsek = trim($profil_sekolah['kepala_sekolah'] ?: 'Kepala Sekolah');

$max_line_length = 60;
$line_length_guru = min(strlen($nama_guru) * 0.8 + 10, $max_line_length);
$line_length_kepsek = min(strlen($nama_kepsek) * 0.8 + 10, $max_line_length);

// Kolom 1: Guru Pengawas
$pdf->SetX($kolom1_x);
$pdf->Cell($kolom_width, 6, 'Pengawas Ujian,', 0, 0, 'C');

// Kolom 2: Kepala Sekolah
$pdf->SetX($kolom2_x);
$pdf->Cell($kolom_width, 6, 'Kepala Sekolah,', 0, 1, 'C');

// Spasi untuk tanda tangan
$pdf->Ln(20);

// Garis tanda tangan GURU PENGAWAS
$garis_guru_x = $kolom1_x + ($kolom_width/2) - ($line_length_guru/2);
$pdf->SetX($garis_guru_x);
$pdf->Cell($line_length_guru, 1, '', 'B', 0, 'C');

// Garis tanda tangan KEPALA SEKOLAH
$garis_kepsek_x = $kolom2_x + ($kolom_width/2) - ($line_length_kepsek/2);
$pdf->SetX($garis_kepsek_x);
$pdf->Cell($line_length_kepsek, 1, '', 'B', 1, 'C');

// Nama di bawah garis
$pdf->SetFont('helvetica', 'B', 10);

// Nama Guru Pengawas
$pdf->SetX($garis_guru_x);
$pdf->Cell($line_length_guru, 6, $nama_guru, 0, 0, 'C');

// Nama Kepala Sekolah
$pdf->SetX($garis_kepsek_x);
$pdf->Cell($line_length_kepsek, 6, $nama_kepsek, 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);

// NIP Guru Pengawas
if ($berita_acara['nip']) {
    $pdf->SetX($garis_guru_x);
    $pdf->Cell($line_length_guru, 5, 'NIP: ' . $berita_acara['nip'], 0, 0, 'C');
}

// NIP Kepala Sekolah
if ($profil_sekolah['nip_kepala']) {
    $pdf->SetX($garis_kepsek_x);
    $pdf->Cell($line_length_kepsek, 5, 'NIP: ' . $profil_sekolah['nip_kepala'], 0, 1, 'C');
}

// Footer informasi
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 4, 'Dokumen ini dicetak secara otomatis dari Sistem Ujian Online', 0, 1, 'C');
$pdf->Cell(0, 4, 'Tanggal cetak: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Cell(0, 4, 'Status Berita Acara: ' . strtoupper($berita_acara['status']), 0, 1, 'C');

// Output PDF
$filename = 'Berita_Acara_Kelas_' . $kelas . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $berita_acara['judul_ujian']) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');
?>