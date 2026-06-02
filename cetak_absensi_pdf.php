<?php
// cetak_absensi_pdf.php - Cetak absensi per kelas (Layout Portrait - sama dengan berita acara)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Ambil parameter
$kelas = $_GET['kelas'] ?? '';
$ujian_id = $_GET['ujian_id'] ?? '';
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

if (empty($kelas) || empty($ujian_id)) {
    die('Parameter tidak lengkap!');
}

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Load TCPDF
require_once('tcpdf/tcpdf.php');

// Extend TCPDF untuk header kustom
class MYPDF extends TCPDF {
    public $sekolah_data;
    
    public function setSekolahData($data) {
        $this->sekolah_data = $data;
    }
    
    public function Header() {
        if ($this->sekolah_data) {
            $logo_path = !empty($this->sekolah_data['logo']) && file_exists($this->sekolah_data['logo']) ? $this->sekolah_data['logo'] : '';
            
            $this->SetY(10);
            
            // Logo
            if (!empty($logo_path)) {
                $this->Image($logo_path, 15, 8, 20, 20, '', '', '', false, 300, '', false, false, 0);
            }
            
            // Nama Sekolah
            $this->SetY(12);
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 6, strtoupper($this->sekolah_data['nama_sekolah']), 0, 1, 'C');
            
            // Alamat
            $this->SetFont('helvetica', '', 9);
            if (!empty($this->sekolah_data['alamat'])) {
                $this->Cell(0, 5, $this->sekolah_data['alamat'], 0, 1, 'C');
            }
            
            // Telepon, Email, NPSN
            $info = array();
            if (!empty($this->sekolah_data['telepon'])) $info[] = "Telp: " . $this->sekolah_data['telepon'];
            if (!empty($this->sekolah_data['email'])) $info[] = "Email: " . $this->sekolah_data['email'];
            if (!empty($this->sekolah_data['npsn'])) $info[] = "NPSN: " . $this->sekolah_data['npsn'];
            
            if (!empty($info)) {
                $this->Cell(0, 5, implode(' | ', $info), 0, 1, 'C');
            }
            
            // Garis bawah
            $this->SetDrawColor(0, 0, 0);
            $y = $this->GetY() + 3;
            $this->SetLineWidth(0.5);
            $this->Line(15, $y, 195, $y);
            $this->SetLineWidth(0.2);
            $this->Line(15, $y + 1, 195, $y + 1);
            
            $this->SetY($y + 8);
        }
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Dicetak: ' . date('d-m-Y H:i:s') . ' | Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

try {
    // Ambil data ujian
    $stmt_ujian = $pdo->prepare("SELECT u.*, mp.nama_mapel FROM ujian u JOIN mata_pelajaran mp ON u.mapel_id = mp.id WHERE u.id = ?");
    $stmt_ujian->execute([$ujian_id]);
    $ujian_data = $stmt_ujian->fetch();
    
    if (!$ujian_data) {
        die('Data ujian tidak ditemukan!');
    }
    
    // Ambil data absensi
    $query_cetak = "SELECT a.*, s.nisn, s.nama, s.kelas 
                   FROM absensi_ujian a 
                   JOIN siswa s ON a.siswa_id = s.id 
                   WHERE a.ujian_id = ? AND DATE(a.created_at) = ? AND s.kelas = ?
                   ORDER BY s.nama";
    $stmt_cetak = $pdo->prepare($query_cetak);
    $stmt_cetak->execute([$ujian_id, $tanggal, $kelas]);
    $data_cetak = $stmt_cetak->fetchAll();
    
    // Jika tidak ada data absensi, ambil semua siswa di kelas
    if (empty($data_cetak)) {
        $query_cetak = "SELECT s.nisn, s.nama, s.kelas 
                       FROM siswa s 
                       WHERE s.kelas = ?
                       ORDER BY s.nama";
        $stmt_cetak = $pdo->prepare($query_cetak);
        $stmt_cetak->execute([$kelas]);
        $data_cetak = $stmt_cetak->fetchAll();
        
        foreach ($data_cetak as &$siswa) {
            $siswa['status_hadir'] = 'belum';
            $siswa['keterangan'] = '';
            $siswa['waktu_hadir'] = null;
        }
    }
    
    // Ambil data profil sekolah
    $profil_sekolah = getProfilSekolah();
    
    // Hitung statistik
    $total_siswa = count($data_cetak);
    $total_hadir = 0;
    $total_izin = 0;
    $total_sakit = 0;
    $total_tidak_hadir = 0;
    
    foreach ($data_cetak as $data) {
        switch ($data['status_hadir']) {
            case 'hadir': $total_hadir++; break;
            case 'izin': $total_izin++; break;
            case 'sakit': $total_sakit++; break;
            case 'tidak_hadir': $total_tidak_hadir++; break;
        }
    }
    
    // Buat instance PDF - ORIENTASI PORTRAIT (P)
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setSekolahData($profil_sekolah);
    
    $pdf->SetCreator('Sistem Ujian Online');
    $pdf->SetAuthor($profil_sekolah['nama_sekolah']);
    $pdf->SetTitle('Absensi Ujian - Kelas ' . $kelas . ' - ' . $ujian_data['judul_ujian']);
    $pdf->SetSubject('Laporan Absensi Ujian');
    
    $pdf->SetMargins(15, 45, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    
    $pdf->AddPage();
    
    // ==================== JUDUL ====================
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'ABSENSI UJIAN', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, strtoupper($ujian_data['judul_ujian']), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 6, 'Mata Pelajaran: ' . ($ujian_data['nama_mapel'] ?? '-'), 0, 1, 'C');
    $pdf->Cell(0, 6, 'Kelas: ' . $kelas . ' | Tanggal: ' . date('d/m/Y', strtotime($tanggal)), 0, 1, 'C');
    $pdf->Ln(8);
    
    // ==================== INFORMASI UJIAN ====================
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'INFORMASI UJIAN', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetLineWidth(0.2);
    
    // Tabel informasi ujian
    $pdf->Cell(50, 8, 'Judul Ujian', 1, 0, 'L', true);
    $pdf->Cell(0, 8, ': ' . $ujian_data['judul_ujian'], 1, 1, 'L');
    
    $pdf->Cell(50, 8, 'Waktu Ujian', 1, 0, 'L', true);
    $pdf->Cell(0, 8, ': ' . date('d/m/Y H:i', strtotime($ujian_data['waktu_mulai'])) . ' - ' . date('H:i', strtotime($ujian_data['waktu_selesai'])), 1, 1, 'L');
    
    $pdf->Cell(50, 8, 'Durasi', 1, 0, 'L', true);
    $pdf->Cell(0, 8, ': ' . ($ujian_data['durasi'] ?? 0) . ' menit', 1, 1, 'L');
    
    $pdf->Cell(50, 8, 'Kelas Target', 1, 0, 'L', true);
    $pdf->Cell(0, 8, ': ' . ($ujian_data['kelas_target'] ?? '-'), 1, 1, 'L');
    
    $pdf->Ln(8);
    
    // ==================== STATISTIK ====================
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'STATISTIK KEHADIRAN', 0, 1, 'L');
    
    $persentase_kehadiran = $total_siswa > 0 ? round(($total_hadir / $total_siswa) * 100, 2) : 0;
    
    // Lebar kolom untuk portrait (180mm / 3 = 60mm per kolom)
    $col_width1 = 45;  // Kolom 1
    $col_width2 = 45;  // Kolom 2
    $col_width3 = 45;  // Kolom 3
    $col_width4 = 45;  // Kolom 4
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    
    // Baris 1
    $pdf->Cell($col_width1, 8, 'Jumlah Siswa', 1, 0, 'C', true);
    $pdf->Cell($col_width2, 8, 'Hadir', 1, 0, 'C', true);
    $pdf->Cell($col_width3, 8, 'Izin', 1, 0, 'C', true);
    $pdf->Cell($col_width4, 8, 'Sakit', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', '', 10);
    
    // Baris data 1
    $pdf->Cell($col_width1, 8, $total_siswa, 1, 0, 'C');
    $pdf->Cell($col_width2, 8, $total_hadir, 1, 0, 'C');
    $pdf->Cell($col_width3, 8, $total_izin, 1, 0, 'C');
    $pdf->Cell($col_width4, 8, $total_sakit, 1, 1, 'C');
    
    // Baris 2
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($col_width1 * 2, 8, 'Tidak Hadir', 1, 0, 'C', true);
    $pdf->Cell($col_width2 * 2, 8, 'Persentase Kehadiran', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell($col_width1 * 2, 8, $total_tidak_hadir, 1, 0, 'C');
    $pdf->Cell($col_width2 * 2, 8, $persentase_kehadiran . '%', 1, 1, 'C');
    
    $pdf->Ln(8);
    
    // ==================== TABEL DATA SISWA ====================
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'DAFTAR SISWA KELAS ' . $kelas, 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 9);
    
    // Lebar kolom untuk portrait
    $w_no = 8;
    $w_nisn = 25;
    $w_nama = 55;
    $w_kelas = 20;
    $w_status = 25;
    $w_keterangan = 30;
    $w_waktu = 25;
    
    // Header tabel
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell($w_no, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell($w_nisn, 8, 'NISN', 1, 0, 'C', true);
    $pdf->Cell($w_nama, 8, 'Nama Siswa', 1, 0, 'C', true);
    $pdf->Cell($w_kelas, 8, 'Kelas', 1, 0, 'C', true);
    $pdf->Cell($w_status, 8, 'Status', 1, 0, 'C', true);
    $pdf->Cell($w_keterangan, 8, 'Keterangan', 1, 0, 'C', true);
    $pdf->Cell($w_waktu, 8, 'Waktu', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', '', 8);
    
    $no = 1;
    foreach ($data_cetak as $siswa) {
        // Cek jika perlu page break
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            // Draw header lagi di halaman baru
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(52, 73, 94);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($w_no, 8, 'No', 1, 0, 'C', true);
            $pdf->Cell($w_nisn, 8, 'NISN', 1, 0, 'C', true);
            $pdf->Cell($w_nama, 8, 'Nama Siswa', 1, 0, 'C', true);
            $pdf->Cell($w_kelas, 8, 'Kelas', 1, 0, 'C', true);
            $pdf->Cell($w_status, 8, 'Status', 1, 0, 'C', true);
            $pdf->Cell($w_keterangan, 8, 'Keterangan', 1, 0, 'C', true);
            $pdf->Cell($w_waktu, 8, 'Waktu', 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetFont('helvetica', '', 8);
        }
        
        // Potong nama jika terlalu panjang
        $nama = $siswa['nama'];
        if (strlen($nama) > 25) {
            $nama = substr($nama, 0, 22) . '...';
        }
        
        // Potong keterangan jika terlalu panjang
        $keterangan = $siswa['keterangan'] ?: '-';
        if (strlen($keterangan) > 18) {
            $keterangan = substr($keterangan, 0, 15) . '...';
        }
        
        // Tentukan teks dan warna berdasarkan status
        switch ($siswa['status_hadir']) {
            case 'hadir':
                $status_text = 'HADIR';
                $status_color = array(40, 167, 69);
                $status_text_color = array(255, 255, 255);
                break;
            case 'izin':
                $status_text = 'IZIN';
                $status_color = array(255, 193, 7);
                $status_text_color = array(0, 0, 0);
                break;
            case 'sakit':
                $status_text = 'SAKIT';
                $status_color = array(23, 162, 184);
                $status_text_color = array(255, 255, 255);
                break;
            case 'tidak_hadir':
                $status_text = 'TIDAK HADIR';
                $status_color = array(220, 53, 69);
                $status_text_color = array(255, 255, 255);
                break;
            default:
                $status_text = 'BELUM';
                $status_color = array(108, 117, 125);
                $status_text_color = array(255, 255, 255);
        }
        
        $waktu_text = '-';
        if (!empty($siswa['waktu_hadir'])) {
            $waktu_text = date('H:i:s', strtotime($siswa['waktu_hadir']));
        }
        
        $pdf->Cell($w_no, 7, $no, 1, 0, 'C');
        $pdf->Cell($w_nisn, 7, $siswa['nisn'], 1, 0, 'L');
        $pdf->Cell($w_nama, 7, $nama, 1, 0, 'L');
        $pdf->Cell($w_kelas, 7, $siswa['kelas'], 1, 0, 'C');
        
        $pdf->SetTextColor($status_text_color[0], $status_text_color[1], $status_text_color[2]);
        $pdf->SetFillColor($status_color[0], $status_color[1], $status_color[2]);
        $pdf->Cell($w_status, 7, $status_text, 1, 0, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        
        $pdf->Cell($w_keterangan, 7, $keterangan, 1, 0, 'L');
        $pdf->Cell($w_waktu, 7, $waktu_text, 1, 1, 'C');
        
        $no++;
    }
    
    // ==================== TANDA TANGAN ====================
    $pdf->Ln(15);
    
    $pdf->SetFont('helvetica', '', 10);
    
    // Baris 1: Mengetahui (kiri) dan (kanan kosong)
    $pdf->Cell(90, 6, 'Mengetahui,', 0, 0, 'L');
    $pdf->Cell(90, 6, '', 0, 1, 'R');
    
    // Spasi
    $pdf->Ln(15);
    
    // Garis tanda tangan
    $pdf->SetLineWidth(0.2);
    $pdf->Cell(80, 0.5, '', 'T', 0, 'C');
    $pdf->Cell(10, 0.5, '', 0, 0);
    $pdf->Cell(80, 0.5, '', 'T', 1, 'C');
    
    // Nama yang menandatangani
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(80, 6, 'Guru Pengawas', 0, 0, 'C');
    $pdf->Cell(10, 6, '', 0, 0);
    $pdf->Cell(80, 6, $profil_sekolah['kepala_sekolah'] ?? 'Kepala Sekolah', 0, 1, 'C');
    
    // Jabatan
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(80, 5, 'Guru Pengawas', 0, 0, 'C');
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(80, 5, 'Kepala Sekolah', 0, 1, 'C');
    
    // Output PDF
    $filename = 'Absensi_Kelas_' . str_replace(' ', '_', $kelas) . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output($filename, 'I');
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>