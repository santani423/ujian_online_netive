<?php
// download_resume_soal_pdf.php - Resume bank soal dengan support Arab dan simbol MTK (GAMBAR TIDAK MENUTUPI TEKS)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// Load TCPDF
require_once('tcpdf/tcpdf.php');

// Ambil data profil sekolah dari database
$stmt_profil = $pdo->query("SELECT * FROM profil_sekolah ORDER BY id DESC LIMIT 1");
$profil = $stmt_profil->fetch(PDO::FETCH_ASSOC);

// Jika tidak ada data profil, gunakan default
if (!$profil) {
    $profil = [
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

// Extend TCPDF untuk header kustom
class MYPDF extends TCPDF {
    public $sekolah_data;
    public $filter_text;
    public $total_soal;
    
    public function setSekolahData($data, $filter, $total) {
        $this->sekolah_data = $data;
        $this->filter_text = $filter;
        $this->total_soal = $total;
    }
    
    // Page header
    public function Header() {
        if ($this->sekolah_data) {
            $logo_path = !empty($this->sekolah_data['logo']) && file_exists($this->sekolah_data['logo']) ? $this->sekolah_data['logo'] : '';
            
            $this->SetY(10);
            
            // LOGO (di kiri)
            if (!empty($logo_path)) {
                $this->Image($logo_path, 15, 8, 20, 20, '', '', '', false, 300, '', false, false, 0);
            }
            
            // NAMA SEKOLAH (di tengah)
            $this->SetY(12);
            $this->SetFont('dejavusans', 'B', 14);
            $this->Cell(0, 6, strtoupper($this->sekolah_data['nama_sekolah']), 0, 1, 'C');
            
            // Alamat
            $this->SetFont('dejavusans', '', 9);
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
            
            // Garis bawah kop
            $this->SetDrawColor(0, 0, 0);
            $y = $this->GetY() + 2;
            $this->Line(15, $y, 195, $y);
            $this->Line(15, $y + 1, 195, $y + 1);
            
            $this->SetY($y + 8);
            
            // Judul Laporan
            $this->SetFont('dejavusans', 'B', 12);
            $this->Cell(0, 6, 'RESUME BANK SOAL', 0, 1, 'C');
            
            // Filter informasi
            $this->SetFont('dejavusans', '', 9);
            if (!empty($this->filter_text)) {
                $this->Cell(0, 5, 'Filter: ' . $this->filter_text, 0, 1, 'C');
            }
            $this->Cell(0, 5, 'Total Soal: ' . $this->total_soal . ' Soal', 0, 1, 'C');
            
            $this->Ln(5);
            $this->SetDrawColor(200, 200, 200);
            $this->Line(15, $this->GetY(), 195, $this->GetY());
            $this->Ln(5);
        }
    }
    
    // Page footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 10, 'Dicetak: ' . date('d-m-Y H:i:s') . ' | Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Fungsi untuk mendeteksi jenis soal
function deteksiJenisSoal($soal) {
    if (!empty($soal['jenis_soal']) && $soal['jenis_soal'] != 'pilihan_ganda') {
        return $soal['jenis_soal'];
    }
    
    if (!empty($soal['jawaban_kompleks']) && $soal['jawaban_kompleks'] != '[]' && $soal['jawaban_kompleks'] != 'null') {
        return 'pilihan_ganda_kompleks';
    }
    
    $opsi_a_trim = trim($soal['opsi_a'] ?? '');
    $opsi_b_trim = trim($soal['opsi_b'] ?? '');
    if ($opsi_a_trim == 'Benar' && $opsi_b_trim == 'Salah') {
        return 'benar_salah';
    }
    
    $opsi_a_lines = explode("\n", $soal['opsi_a'] ?? '');
    $opsi_b_lines = explode("\n", $soal['opsi_b'] ?? '');
    
    $has_numbered_pattern_a = false;
    $has_numbered_pattern_b = false;
    
    foreach ($opsi_a_lines as $line) {
        if (preg_match('/^\s*\d+\./', trim($line))) {
            $has_numbered_pattern_a = true;
            break;
        }
    }
    
    foreach ($opsi_b_lines as $line) {
        if (preg_match('/^\s*\d+\./', trim($line))) {
            $has_numbered_pattern_b = true;
            break;
        }
    }
    
    $opsi_c_empty = empty($soal['opsi_c']) || trim($soal['opsi_c'] ?? '') == '' || trim($soal['opsi_c'] ?? '') == '-';
    $opsi_d_empty = empty($soal['opsi_d']) || trim($soal['opsi_d'] ?? '') == '' || trim($soal['opsi_d'] ?? '') == '-';
    $opsi_e_empty = empty($soal['opsi_e']) || trim($soal['opsi_e'] ?? '') == '' || trim($soal['opsi_e'] ?? '') == '-';
    
    if ($has_numbered_pattern_a && $has_numbered_pattern_b) {
        if ($opsi_c_empty && $opsi_d_empty && $opsi_e_empty) {
            return 'menjodohkan';
        }
    }
    
    if (!empty($soal['pasangan_jodoh']) && trim($soal['pasangan_jodoh']) != '' && $soal['pasangan_jodoh'] != '-') {
        if (preg_match('/\d+\-\d+/', $soal['pasangan_jodoh'])) {
            return 'menjodohkan';
        }
    }
    
    if ($opsi_c_empty && $opsi_d_empty && $opsi_e_empty) {
        if ($opsi_a_trim != 'Benar' && $opsi_b_trim != 'Salah') {
            if (!($has_numbered_pattern_a && $has_numbered_pattern_b)) {
                return 'essay';
            }
        }
    }
    
    return 'pilihan_ganda';
}

// Fungsi untuk membersihkan dan mendecode teks
function cleanText($text) {
    if (empty($text)) return '';
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = strip_tags($text);
    return $text;
}

// Buat query dengan filter
$where = "WHERE 1=1";
$params = [];

if (isset($_GET['mapel_id']) && !empty($_GET['mapel_id'])) {
    $where .= " AND s.mapel_id = ?";
    $params[] = $_GET['mapel_id'];
}

if (isset($_GET['kelas']) && !empty($_GET['kelas'])) {
    $where .= " AND s.kelas = ?";
    $params[] = $_GET['kelas'];
}

if (isset($_GET['media_type']) && !empty($_GET['media_type'])) {
    if ($_GET['media_type'] == 'gambar') {
        $where .= " AND s.gambar_soal IS NOT NULL AND s.gambar_soal != ''";
    } elseif ($_GET['media_type'] == 'video') {
        $where .= " AND s.video_soal IS NOT NULL AND s.video_soal != ''";
    } elseif ($_GET['media_type'] == 'tanpa_media') {
        $where .= " AND (s.gambar_soal IS NULL OR s.gambar_soal = '') AND (s.video_soal IS NULL OR s.video_soal = '')";
    }
}

$sql = "SELECT s.*, mp.nama_mapel, mp.kode_mapel 
        FROM soal s 
        LEFT JOIN mata_pelajaran mp ON s.mapel_id = mp.id 
        $where 
        ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$soal_list = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $jenis = deteksiJenisSoal($row);
    
    if (isset($_GET['jenis_soal']) && !empty($_GET['jenis_soal'])) {
        if ($jenis != $_GET['jenis_soal']) {
            continue;
        }
    }
    
    $soal_list[] = [
        'soal' => $row,
        'jenis' => $jenis
    ];
}

// Ambil info filter untuk judul
$filter_info = [];
if (isset($_GET['mapel_id']) && !empty($_GET['mapel_id'])) {
    $stmt_mapel = $pdo->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = ?");
    $stmt_mapel->execute([$_GET['mapel_id']]);
    $mapel = $stmt_mapel->fetch();
    $filter_info[] = "Mapel: " . ($mapel['nama_mapel'] ?? 'Unknown');
}
if (isset($_GET['kelas']) && !empty($_GET['kelas'])) {
    $filter_info[] = "Kelas: " . $_GET['kelas'];
}
if (isset($_GET['jenis_soal']) && !empty($_GET['jenis_soal'])) {
    $jenis_text = [
        'pilihan_ganda' => 'Pilihan Ganda',
        'pilihan_ganda_kompleks' => 'PG Kompleks',
        'essay' => 'Essay',
        'menjodohkan' => 'Menjodohkan',
        'benar_salah' => 'Benar/Salah'
    ];
    $filter_info[] = "Jenis: " . ($jenis_text[$_GET['jenis_soal']] ?? $_GET['jenis_soal']);
}
if (isset($_GET['media_type']) && !empty($_GET['media_type'])) {
    $media_text = [
        'gambar' => 'Dengan Gambar',
        'video' => 'Dengan Video',
        'tanpa_media' => 'Tanpa Media'
    ];
    $filter_info[] = "Media: " . ($media_text[$_GET['media_type']] ?? $_GET['media_type']);
}

$filter_text = !empty($filter_info) ? implode(' | ', $filter_info) : 'Semua Soal';

// Buat PDF
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set default font ke DejaVu Sans
$pdf->SetFont('dejavusans', '', 10);

// Set data sekolah untuk header
$pdf->setSekolahData($profil, $filter_text, count($soal_list));

// Set document information
$pdf->SetCreator('Sistem Ujian Online');
$pdf->SetAuthor($_SESSION['nama_lengkap'] ?? 'Guru');
$pdf->SetTitle('Resume Bank Soal - ' . $profil['nama_sekolah']);
$pdf->SetSubject('Bank Soal Ujian Online');

// Set margins
$pdf->SetMargins(15, 50, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 20);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// ==================== TAMPILAN SOAL ====================
$no = 1;
foreach ($soal_list as $item) {
    $soal = $item['soal'];
    $jenis = $item['jenis'];
    
    // Cek jika perlu halaman baru (beri ruang untuk gambar)
    if ($pdf->getY() > 180) {
        $pdf->AddPage();
    }
    
    // Nomor Soal dan Pertanyaan
    $pdf->SetFont('dejavusans', 'B', 11);
    $pertanyaan = cleanText($soal['pertanyaan']);
    $pdf->MultiCell(0, 6, $no . '. ' . $pertanyaan, 0, 'L', 0, 1, '', '', true);
    $pdf->Ln(2);
    
    // ============================================================
    // PERBAIKAN: TAMPILKAN GAMBAR DENGAN JARAK YANG CUKUP
    // ============================================================
    if (!empty($soal['gambar_soal']) && file_exists($soal['gambar_soal'])) {
        // Simpan posisi Y saat ini
        $current_y = $pdf->GetY();
        
        $pdf->SetFont('dejavusans', 'I', 8);
        $pdf->Cell(0, 5, 'Gambar Soal:', 0, 1, 'L');
        
        // Tampilkan gambar dengan lebar maksimal 60mm (lebih kecil)
        $pdf->Image($soal['gambar_soal'], 20, $pdf->GetY(), 60, 0, '', '', '', false, 300);
        
        // Hitung perkiraan tinggi gambar (asumsi rasio 4:3, tinggi sekitar 45mm untuk lebar 60mm)
        $estimated_image_height = 50; // mm
        
        // Set posisi Y setelah gambar dengan jarak aman
        $pdf->SetY($pdf->GetY() + $estimated_image_height + 10);
        
        // Pastikan tidak ada tumpang tindih
        if ($pdf->GetY() < $current_y + 20) {
            $pdf->SetY($current_y + 25);
        }
        
        $pdf->Ln(2);
    }
    
    // Tampilkan video soal jika ada
    if (!empty($soal['video_soal'])) {
        $pdf->SetFont('dejavusans', 'I', 8);
        $video_url = cleanText($soal['video_soal']);
        $pdf->Cell(0, 5, 'Video Soal: ' . $video_url, 0, 1, 'L');
        $pdf->Ln(2);
    }
    
    // Tampilkan opsi sesuai jenis
    $pdf->SetFont('dejavusans', '', 10);
    
    if ($jenis == 'pilihan_ganda' || $jenis == 'pilihan_ganda_kompleks') {
        $opsi_a = cleanText($soal['opsi_a'] ?? '');
        if (!empty($opsi_a) && $opsi_a != '-') {
            $pdf->Cell(8, 6, 'A.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, $opsi_a, 0, 'L', 0, 1, '', '', true);
        }
        
        $opsi_b = cleanText($soal['opsi_b'] ?? '');
        if (!empty($opsi_b) && $opsi_b != '-') {
            $pdf->Cell(8, 6, 'B.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, $opsi_b, 0, 'L', 0, 1, '', '', true);
        }
        
        $opsi_c = cleanText($soal['opsi_c'] ?? '');
        if (!empty($opsi_c) && $opsi_c != '-') {
            $pdf->Cell(8, 6, 'C.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, $opsi_c, 0, 'L', 0, 1, '', '', true);
        }
        
        $opsi_d = cleanText($soal['opsi_d'] ?? '');
        if (!empty($opsi_d) && $opsi_d != '-') {
            $pdf->Cell(8, 6, 'D.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, $opsi_d, 0, 'L', 0, 1, '', '', true);
        }
        
        $opsi_e = cleanText($soal['opsi_e'] ?? '');
        if (!empty($opsi_e) && $opsi_e != '-') {
            $pdf->Cell(8, 6, 'E.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, $opsi_e, 0, 'L', 0, 1, '', '', true);
        }
    } elseif ($jenis == 'benar_salah') {
        $pdf->Cell(0, 6, 'A. Benar', 0, 1, 'L');
        $pdf->Cell(0, 6, 'B. Salah', 0, 1, 'L');
    }
    
    $pdf->Ln(2);
    
    // Tampilkan jawaban
    $pdf->SetFont('dejavusans', 'I', 9);
    
    if ($jenis == 'pilihan_ganda') {
        $jawaban = strtoupper($soal['jawaban_benar'] ?? '?');
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(0, 5, '✓ Jawaban: ' . $jawaban, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
    } elseif ($jenis == 'pilihan_ganda_kompleks') {
        $jawaban_kompleks = json_decode($soal['jawaban_kompleks'] ?? '[]', true);
        $jawaban_text = is_array($jawaban_kompleks) ? implode(', ', array_map('strtoupper', $jawaban_kompleks)) : '';
        $skor_per_jawaban = $soal['skor_per_jawaban'] ?? 0;
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(0, 5, '✓ Jawaban: ' . $jawaban_text . ' (' . $skor_per_jawaban . ' poin/jawaban benar)', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
    } elseif ($jenis == 'essay') {
        $pdf->SetTextColor(0, 0, 255);
        $pdf->Cell(0, 5, '✎ Jawaban: Essay (Koreksi Manual)', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        if (!empty($soal['opsi_a']) && $soal['opsi_a'] != '-') {
            $petunjuk = cleanText($soal['opsi_a']);
            $pdf->Cell(0, 5, 'Petunjuk: ' . $petunjuk, 0, 1, 'L');
        }
    } elseif ($jenis == 'menjodohkan') {
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(0, 5, '⇄ Menjodohkan', 0, 1, 'L');
        $pasangan = cleanText($soal['pasangan_jodoh'] ?? '-');
        if ($pasangan != '-') {
            $pdf->Cell(0, 5, 'Pasangan: ' . $pasangan, 0, 1, 'L');
        }
        $pdf->SetTextColor(0, 0, 0);
    } elseif ($jenis == 'benar_salah') {
        $jawaban_bs = ($soal['jawaban_benar'] == 'a') ? 'Benar' : 'Salah';
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(0, 5, '✓ Jawaban: ' . $jawaban_bs, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
    }
    
    // Skor
    $pdf->SetFont('dejavusans', '', 9);
    $skor = $soal['skor'] ?? 10;
    $pdf->Cell(0, 5, 'Skor: ' . $skor . ' poin', 0, 1, 'L');
    
    // Informasi mata pelajaran dan kelas
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $info_text = '';
    if (!empty($soal['nama_mapel'])) {
        $info_text .= 'Mapel: ' . $soal['nama_mapel'];
    }
    if (!empty($soal['kelas'])) {
        $info_text .= ($info_text ? ' | ' : '') . 'Kelas: ' . $soal['kelas'];
    }
    if (!empty($info_text)) {
        $pdf->Cell(0, 4, $info_text, 0, 1, 'L');
    }
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Ln(3);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(8);
    
    $no++;
}

// Output PDF
$pdf->Output('resume_bank_soal_' . date('Ymd_His') . '.pdf', 'I');
exit();
?>