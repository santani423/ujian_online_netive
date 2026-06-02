<?php
// laporan_nilai_pdf.php - Download laporan nilai ujian dalam PDF
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

require_once('tcpdf/tcpdf.php');

$ujian_id = $_GET['ujian_id'] ?? 0;
$filter_kelas = $_GET['kelas'] ?? '';

if (!$ujian_id) {
    die('Ujian ID tidak ditemukan');
}

// Ambil data ujian
$stmt = $pdo->prepare("
    SELECT u.*, mp.nama_mapel, mp.kode_mapel 
    FROM ujian u 
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
    WHERE u.id = ?
");
$stmt->execute([$ujian_id]);
$ujian = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ujian) {
    die('Data ujian tidak ditemukan');
}

// Ambil data hasil ujian
$where = "hu.ujian_id = ?";
$params = [$ujian_id];

if ($filter_kelas) {
    $where .= " AND s.kelas = ?";
    $params[] = $filter_kelas;
}

$sql = "
    SELECT 
        hu.*,
        s.nisn,
        s.nama as nama_siswa,
        s.kelas as kelas_siswa,
        COUNT(DISTINCT js.id) as total_soal_dijawab,
        COALESCE(SUM(
            CASE 
                WHEN soal.jenis_soal IN ('pilihan_ganda', 'benar_salah') THEN 
                    CASE WHEN js.jawaban_siswa = soal.jawaban_benar THEN soal.skor ELSE 0 END
                ELSE 0
            END
        ), 0) as nilai_otomatis,
        COALESCE(SUM(
            CASE 
                WHEN soal.jenis_soal IN ('essay', 'menjodohkan') THEN 
                    COALESCE(je.skor_essay, 0)
                ELSE 0
            END
        ), 0) as nilai_manual,
        COALESCE(SUM(soal.skor), 0) as total_skor_maksimal
    FROM hasil_ujian hu
    JOIN siswa s ON hu.siswa_id = s.id
    LEFT JOIN jawaban_siswa js ON js.hasil_ujian_id = hu.id
    LEFT JOIN soal ON js.soal_id = soal.id
    LEFT JOIN jawaban_essay je ON je.hasil_ujian_id = hu.id AND je.soal_id = soal.id
    WHERE $where
    GROUP BY hu.id
    ORDER BY hu.nilai DESC, s.nama
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hasil_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil profil sekolah
$stmt_profil = $pdo->query("SELECT * FROM profil_sekolah ORDER BY id DESC LIMIT 1");
$profil = $stmt_profil->fetch(PDO::FETCH_ASSOC);

if (!$profil) {
    $profil = [
        'nama_sekolah' => 'SEKOLAH ANDA',
        'alamat' => '',
        'telepon' => '',
        'email' => '',
        'logo' => ''
    ];
}

// Hitung statistik
$total_siswa = count($hasil_list);
$total_nilai = 0;
$lulus = 0;
$passing_grade = 60;

$stmt_pg = $pdo->query("SELECT value FROM pengaturan WHERE nama = 'passing_grade'");
$pg = $stmt_pg->fetch();
if ($pg) $passing_grade = (int)$pg['value'];

foreach ($hasil_list as $hasil) {
    $nilai = $hasil['nilai'] ?? 0;
    $total_nilai += $nilai;
    if ($nilai >= $passing_grade) $lulus++;
}
$rata_rata = $total_siswa > 0 ? $total_nilai / $total_siswa : 0;
$persen_lulus = $total_siswa > 0 ? ($lulus / $total_siswa) * 100 : 0;

// Class PDF Kustom
class PDF_Laporan extends TCPDF {
    public $sekolah_data;
    
    public function setSekolahData($data) {
        $this->sekolah_data = $data;
    }
    
    public function Header() {
        if ($this->sekolah_data) {
            $logo_path = !empty($this->sekolah_data['logo']) && file_exists($this->sekolah_data['logo']) ? $this->sekolah_data['logo'] : '';
            
            if (!empty($logo_path)) {
                $this->Image($logo_path, 15, 8, 22, 22, '', '', '', false, 300, '', false, false, 0);
            }
            
            $this->SetY(12);
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 6, strtoupper($this->sekolah_data['nama_sekolah']), 0, 1, 'C');
            
            $this->SetFont('helvetica', '', 9);
            if (!empty($this->sekolah_data['alamat'])) {
                $this->Cell(0, 5, $this->sekolah_data['alamat'], 0, 1, 'C');
            }
            
            $info = array();
            if (!empty($this->sekolah_data['telepon'])) $info[] = "Telp: " . $this->sekolah_data['telepon'];
            if (!empty($this->sekolah_data['email'])) $info[] = "Email: " . $this->sekolah_data['email'];
            if (!empty($info)) {
                $this->Cell(0, 5, implode(' | ', $info), 0, 1, 'C');
            }
            
            $this->SetDrawColor(0, 0, 0);
            $y = $this->GetY() + 2;
            $this->Line(10, $y, 200, $y);
            $this->Line(10, $y + 1, 200, $y + 1);
            $this->SetY($y + 8);
            
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 8, 'LAPORAN NILAI UJIAN', 0, 1, 'C');
            $this->Ln(5);
        }
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Dicetak: ' . date('d-m-Y H:i:s') . ' | Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Buat PDF
$pdf = new PDF_Laporan('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setSekolahData($profil);
$pdf->SetCreator($profil['nama_sekolah']);
$pdf->SetAuthor('Guru');
$pdf->SetTitle('Laporan Nilai Ujian - ' . $ujian['judul_ujian']);
$pdf->SetMargins(15, 55, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->AddPage();

// Informasi Ujian
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 248, 255);
$pdf->Cell(0, 8, 'INFORMASI UJIAN', 0, 1, 'L', true);
$pdf->SetFont('helvetica', '', 10);
$pdf->SetFillColor(255, 255, 255);

$info = [
    ['Judul Ujian', $ujian['judul_ujian']],
    ['Mata Pelajaran', $ujian['nama_mapel'] . ' (' . $ujian['kode_mapel'] . ')'],
    ['Kelas Target', $ujian['kelas_target'] . ($filter_kelas ? ' - Filter: ' . $filter_kelas : '')],
    ['Waktu Ujian', date('d-m-Y H:i', strtotime($ujian['waktu_mulai'])) . ' s.d ' . date('H:i', strtotime($ujian['waktu_selesai']))]
];

foreach ($info as $item) {
    $pdf->Cell(35, 6, $item[0], 0, 0);
    $pdf->Cell(5, 6, ':', 0, 0);
    $pdf->Cell(0, 6, $item[1], 0, 1);
}
$pdf->Ln(5);

// Statistik
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 248, 255);
$pdf->Cell(0, 8, 'STATISTIK NILAI', 0, 1, 'L', true);

$box_width = 65;
$start_x = 15;
$start_y = $pdf->GetY();

// Box Total Siswa
$pdf->Rect($start_x, $start_y, $box_width, 20, 'D');
$pdf->SetXY($start_x + 5, $start_y + 3);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($box_width - 10, 5, 'Total Peserta', 0, 1);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetXY($start_x + 5, $start_y + 8);
$pdf->Cell($box_width - 10, 8, $total_siswa, 0, 1, 'C');

// Box Rata-rata
$pdf->Rect($start_x + $box_width + 5, $start_y, $box_width, 20, 'D');
$pdf->SetXY($start_x + $box_width + 10, $start_y + 3);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($box_width - 10, 5, 'Rata-rata', 0, 1);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetXY($start_x + $box_width + 10, $start_y + 8);
$pdf->Cell($box_width - 10, 8, number_format($rata_rata, 1) . '%', 0, 1, 'C');

// Box Kelulusan
$pdf->Rect($start_x + ($box_width + 5) * 2, $start_y, $box_width, 20, 'D');
$pdf->SetXY($start_x + ($box_width + 5) * 2 + 5, $start_y + 3);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($box_width - 10, 5, 'Kelulusan', 0, 1);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetXY($start_x + ($box_width + 5) * 2 + 5, $start_y + 8);
$pdf->Cell($box_width - 10, 8, number_format($persen_lulus, 1) . '%', 0, 1, 'C');

$pdf->SetY($start_y + 25);
$pdf->Ln(5);

// Daftar Nilai Siswa
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 248, 255);
$pdf->Cell(0, 8, 'DAFTAR NILAI SISWA', 0, 1, 'L', true);
$pdf->Ln(3);

// Header tabel
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(52, 73, 94);
$pdf->SetTextColor(255, 255, 255);

$headers = ['NO', 'NISN', 'NAMA SISWA', 'KELAS', 'NILAI', 'PERSENTASE', 'STATUS'];
$widths = [10, 25, 70, 25, 20, 25, 30];

for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Data tabel
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);
$fill = false;
$no = 1;

foreach ($hasil_list as $hasil) {
    $total_nilai = ($hasil['nilai_otomatis'] ?? 0) + ($hasil['nilai_manual'] ?? 0);
    $persentase = $hasil['nilai'] ?? 0;
    $status = ($persentase >= $passing_grade) ? 'LULUS' : 'TIDAK LULUS';
    
    if ($fill) {
        $pdf->SetFillColor(245, 245, 245);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    $pdf->Cell($widths[0], 7, $no, 1, 0, 'C', $fill);
    $pdf->Cell($widths[1], 7, $hasil['nisn'], 1, 0, 'C', $fill);
    $pdf->Cell($widths[2], 7, substr($hasil['nama_siswa'], 0, 40), 1, 0, 'L', $fill);
    $pdf->Cell($widths[3], 7, $hasil['kelas_siswa'], 1, 0, 'C', $fill);
    $pdf->Cell($widths[4], 7, $total_nilai, 1, 0, 'C', $fill);
    
    // Persentase dengan warna
    if ($persentase >= 80) {
        $pdf->SetFillColor(46, 204, 113);
        $pdf->SetTextColor(255, 255, 255);
    } elseif ($persentase >= 60) {
        $pdf->SetFillColor(241, 196, 15);
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $pdf->SetFillColor(231, 76, 60);
        $pdf->SetTextColor(255, 255, 255);
    }
    $pdf->Cell($widths[5], 7, number_format($persentase, 1) . '%', 1, 0, 'C', true);
    
    // Status
    if ($status == 'LULUS') {
        $pdf->SetFillColor(46, 204, 113);
        $pdf->SetTextColor(255, 255, 255);
    } else {
        $pdf->SetFillColor(231, 76, 60);
        $pdf->SetTextColor(255, 255, 255);
    }
    $pdf->Cell($widths[6], 7, $status, 1, 0, 'C', true);
    
    $pdf->Ln();
    $pdf->SetTextColor(0, 0, 0);
    $fill = !$fill;
    $no++;
    
    if ($pdf->GetY() > 170) {
        $pdf->AddPage();
        // Ulang header tabel
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $fill = false;
    }
}

// Tanda tangan
$current_y = $pdf->GetY();
$remaining_space = 280 - $current_y;

if ($remaining_space < 60) {
    $pdf->AddPage();
    $current_y = $pdf->GetY();
}

$ttd_y = $current_y + 15;
$ttd_x = 140;

$pdf->SetY($ttd_y);
$pdf->SetX($ttd_x);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(80, 5, 'Mengetahui,', 0, 1, 'C');

$pdf->SetY($pdf->GetY() + 2);
$pdf->SetX($ttd_x);
$pdf->Cell(80, 5, 'Guru Pengampu', 0, 1, 'C');

$pdf->SetY($pdf->GetY() + 15);
$pdf->SetX($ttd_x);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(80, 5, '(_______________________)', 0, 1, 'C');

// Output PDF
$filename = 'Laporan_Nilai_Ujian_' . sanitizeFilename($ujian['judul_ujian']) . '_' . date('Ymd') . '.pdf';
$pdf->Output($filename, 'D');
exit();

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}
?>