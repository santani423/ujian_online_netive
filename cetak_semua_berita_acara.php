<?php
// cetak_semua_berita_acara.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Include TCPDF library
require_once('tcpdf/tcpdf.php');

// Ambil semua data berita acara
$query = "SELECT ba.*, u.judul_ujian, g.nama as nama_guru 
          FROM berita_acara ba 
          JOIN ujian u ON ba.ujian_id = u.id 
          JOIN guru g ON ba.guru_pengawas = g.id 
          ORDER BY ba.tanggal_ujian DESC, ba.waktu_mulai DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $berita_acara = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

// Ambil profil sekolah
$profil_sekolah = getProfilSekolah();

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
            $this->Line(10, $y, 200, $y);
            $this->SetLineWidth(0.2);
            $this->Line(10, $y + 1, 200, $y + 1);
            $this->SetLineWidth(0.1);
            
            $this->SetY($y + 8);
        }
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Dicetak: ' . date('d-m-Y H:i:s') . ' | Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document dengan ORIENTASI PORTRAIT (P)
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setSekolahData($profil_sekolah);

// Set document information
$pdf->SetCreator('Sistem Ujian Online');
$pdf->SetAuthor($profil_sekolah['nama_sekolah']);
$pdf->SetTitle('Laporan Berita Acara Ujian');
$pdf->SetSubject('Berita Acara Ujian');

// Set margins
$pdf->SetMargins(10, 45, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 20);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// ==================== JUDUL LAPORAN ====================
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'LAPORAN BERITA ACARA UJIAN', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Ln(8);

// ==================== TABEL BERITA ACARA ====================
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(200, 200, 200);
$pdf->SetTextColor(0, 0, 0);

// Header tabel
$header = array('No', 'Ujian', 'Tanggal', 'Waktu', 'Kelas', 'Ruangan', 'Peserta', 'Status');
$w = array(8, 48, 22, 18, 18, 22, 18, 16);

// Header
for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Data
$pdf->SetFont('helvetica', '', 8);
$no = 1;
foreach($berita_acara as $row) {
    if($pdf->GetY() > 250) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(200, 200, 200);
        for($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        $pdf->SetFont('helvetica', '', 8);
    }
    
    $judul_ujian = mb_strimwidth($row['judul_ujian'], 0, 28, '...');
    $ruangan = mb_strimwidth($row['ruangan'] ?? '-', 0, 15, '...');
    $kelas = mb_strimwidth($row['kelas'] ?? '-', 0, 12, '...');
    
    $pdf->Cell($w[0], 6, $no, 1, 0, 'C');
    $pdf->Cell($w[1], 6, $judul_ujian, 1, 0, 'L');
    $pdf->Cell($w[2], 6, date('d/m/Y', strtotime($row['tanggal_ujian'])), 1, 0, 'C');
    $pdf->Cell($w[3], 6, substr($row['waktu_mulai'], 0, 5), 1, 0, 'C');
    $pdf->Cell($w[4], 6, $kelas, 1, 0, 'C');
    $pdf->Cell($w[5], 6, $ruangan, 1, 0, 'C');
    $pdf->Cell($w[6], 6, ($row['jumlah_hadir'] ?? 0) . '/' . ($row['jumlah_peserta'] ?? 0), 1, 0, 'C');
    
    $status_color = array();
    switch ($row['status']) {
        case 'draft':
            $status_color = array(108, 117, 125);
            $status_text = 'Draft';
            break;
        case 'selesai':
            $status_color = array(0, 123, 255);
            $status_text = 'Selesai';
            break;
        case 'diverifikasi':
            $status_color = array(40, 167, 69);
            $status_text = 'Diverifikasi';
            break;
        default:
            $status_color = array(108, 117, 125);
            $status_text = ucfirst($row['status']);
    }
    
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor($status_color[0], $status_color[1], $status_color[2]);
    $pdf->Cell($w[7], 6, $status_text, 1, 0, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    
    $pdf->Ln();
    $no++;
}

// ==================== STATISTIK ====================
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 10);

$total = count($berita_acara);
$selesai = count(array_filter($berita_acara, function($item) { 
    return $item['status'] == 'selesai'; 
}));
$diverifikasi = count(array_filter($berita_acara, function($item) { 
    return $item['status'] == 'diverifikasi'; 
}));
$draft = count(array_filter($berita_acara, function($item) { 
    return $item['status'] == 'draft'; 
}));

$pdf->SetFillColor(70, 130, 200);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'STATISTIK BERITA ACARA', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(50, 6, 'Total Berita Acara:', 0, 0);
$pdf->Cell(0, 6, $total . ' item', 0, 1);
$pdf->Cell(50, 6, 'Status Selesai:', 0, 0);
$pdf->Cell(0, 6, $selesai . ' item', 0, 1);
$pdf->Cell(50, 6, 'Status Diverifikasi:', 0, 0);
$pdf->Cell(0, 6, $diverifikasi . ' item', 0, 1);
$pdf->Cell(50, 6, 'Status Draft:', 0, 0);
$pdf->Cell(0, 6, $draft . ' item', 0, 1);

// ==================== TANDA TANGAN ====================
$pdf->Ln(15);

// Layout tanda tangan: Mengetahui di kiri, Kepala Sekolah di kanan
$pdf->SetFont('helvetica', '', 10);

// Baris 1: Mengetahui (kiri) dan (kanan kosong untuk tanda tangan Kepala Sekolah)
$pdf->Cell(95, 6, 'Mengetahui,', 0, 0, 'L');
$pdf->Cell(95, 6, '', 0, 1, 'R');

// Baris 2: (kosong) untuk garis tanda tangan
$pdf->Ln(15);

// Baris 3: Garis tanda tangan
$pdf->SetLineWidth(0.2);
$pdf->Cell(85, 0.5, '', 'T', 0, 'C');
$pdf->Cell(10, 0.5, '', 0, 0);
$pdf->Cell(85, 0.5, '', 'T', 1, 'C');

// Baris 4: Nama yang menandatangani
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(85, 6, 'Guru Pengawas', 0, 0, 'C');
$pdf->Cell(10, 6, '', 0, 0);
$pdf->Cell(85, 6, $profil_sekolah['kepala_sekolah'] ?? 'Kepala Sekolah', 0, 1, 'C');

// Baris 5: Jabatan
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(85, 5, 'Guru Pengawas', 0, 0, 'C');
$pdf->Cell(10, 5, '', 0, 0);
$pdf->Cell(85, 5, 'Kepala Sekolah', 0, 1, 'C');

// Output PDF
$pdf->Output('Laporan_Berita_Acara_' . date('Ymd_His') . '.pdf', 'I');
exit();
?>