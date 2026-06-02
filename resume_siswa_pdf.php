<?php
// resume_siswa_pdf.php - Resume soal dan jawaban siswa dalam PDF (GAMBAR TIDAK MENUTUPI TEKS)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

require_once('tcpdf/tcpdf.php');

$hasil_ujian_id = $_GET['hasil_ujian_id'] ?? 0;
$ujian_id = $_GET['ujian_id'] ?? 0;

if (!$hasil_ujian_id || !$ujian_id) {
    die('Data tidak lengkap');
}

// Ambil data hasil ujian dan siswa (termasuk KKM)
$stmt = $pdo->prepare("
    SELECT hu.*, s.nisn, s.nama as nama_siswa, s.kelas, s.jenis_kelamin,
           u.judul_ujian, u.waktu_mulai, u.waktu_selesai, u.durasi,
           mp.nama_mapel, mp.kode_mapel, mp.kkm
    FROM hasil_ujian hu
    JOIN siswa s ON hu.siswa_id = s.id
    JOIN ujian u ON hu.ujian_id = u.id
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id
    WHERE hu.id = ? AND hu.ujian_id = ?
");
$stmt->execute([$hasil_ujian_id, $ujian_id]);
$hasil = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hasil) {
    die('Data hasil ujian tidak ditemukan');
}

$kkm_mapel = $hasil['kkm'] ?? 70;

// Ambil daftar soal dan jawaban siswa
$stmt = $pdo->prepare("
    SELECT 
        su.urutan, 
        s.id as soal_id,
        s.pertanyaan,
        s.jenis_soal,
        s.opsi_a,
        s.opsi_b,
        s.opsi_c,
        s.opsi_d,
        s.opsi_e,
        s.jawaban_benar,
        s.jawaban_kompleks,
        s.skor,
        s.skor_per_jawaban,
        s.gambar_soal,
        s.video_soal,
        js.jawaban_siswa, 
        js.jawaban_essay,
        je.skor_essay, 
        je.komentar_guru,
        je.status_koreksi
    FROM soal_ujian su
    JOIN soal s ON su.soal_id = s.id
    LEFT JOIN jawaban_siswa js ON js.hasil_ujian_id = ? AND js.soal_id = s.id
    LEFT JOIN jawaban_essay je ON je.hasil_ujian_id = ? AND je.soal_id = s.id
    WHERE su.ujian_id = ?
    ORDER BY su.urutan
");
$stmt->execute([$hasil_ujian_id, $hasil_ujian_id, $ujian_id]);
$soal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil profil sekolah
$stmt_profil = $pdo->query("SELECT * FROM profil_sekolah ORDER BY id DESC LIMIT 1");
$profil = $stmt_profil->fetch(PDO::FETCH_ASSOC);

if (!$profil) {
    $profil = [
        'nama_sekolah' => 'SEKOLAH ANDA',
        'npsn' => '',
        'alamat' => '',
        'telepon' => '',
        'email' => '',
        'logo' => ''
    ];
}

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
            
            if (!empty($logo_path)) {
                $this->Image($logo_path, 15, 8, 20, 20, '', '', '', false, 300, '', false, false, 0);
            }
            
            $this->SetY(12);
            $this->SetFont('dejavusans', 'B', 14);
            $this->Cell(0, 6, strtoupper($this->sekolah_data['nama_sekolah']), 0, 1, 'C');
            
            $this->SetFont('dejavusans', '', 9);
            if (!empty($this->sekolah_data['alamat'])) {
                $this->Cell(0, 5, $this->sekolah_data['alamat'], 0, 1, 'C');
            }
            
            $info = array();
            if (!empty($this->sekolah_data['telepon'])) $info[] = "Telp: " . $this->sekolah_data['telepon'];
            if (!empty($this->sekolah_data['email'])) $info[] = "Email: " . $this->sekolah_data['email'];
            if (!empty($this->sekolah_data['npsn'])) $info[] = "NPSN: " . $this->sekolah_data['npsn'];
            
            if (!empty($info)) {
                $this->Cell(0, 5, implode(' | ', $info), 0, 1, 'C');
            }
            
            $this->SetDrawColor(0, 0, 0);
            $y = $this->GetY() + 2;
            $this->Line(15, $y, 195, $y);
            $this->Line(15, $y + 1, 195, $y + 1);
            
            $this->SetY($y + 8);
        }
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 10, 'Dicetak: ' . date('d-m-Y H:i:s') . ' | Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Buat PDF
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setSekolahData($profil);
$pdf->SetCreator('Sistem Ujian Online');
$pdf->SetAuthor($_SESSION['nama_lengkap']);
$pdf->SetTitle('Resume Soal - ' . $hasil['nama_siswa']);
$pdf->SetMargins(15, 45, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

$pdf->SetFont('dejavusans', '', 10);
$pdf->AddPage();

// ==================== INFO SISWA ====================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 8, 'RESUME SOAL DAN JAWABAN SISWA', 0, 1, 'C');
$pdf->Ln(5);

// Box Info Siswa
$pdf->SetFillColor(240, 248, 255);
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell(0, 8, 'INFORMASI SISWA', 0, 1, 'L', true);
$pdf->SetFont('dejavusans', '', 10);
$pdf->SetFillColor(255, 255, 255);

$pdf->Cell(35, 7, 'Nama Siswa', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . strtoupper($hasil['nama_siswa']), 0, 1, 'L');

$pdf->Cell(35, 7, 'NISN', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . $hasil['nisn'], 0, 1, 'L');

$pdf->Cell(35, 7, 'Kelas', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . $hasil['kelas'], 0, 1, 'L');

$pdf->Cell(35, 7, 'Jenis Kelamin', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . ($hasil['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'), 0, 1, 'L');

$pdf->Ln(3);

// Box Info Ujian
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(240, 255, 240);
$pdf->Cell(0, 8, 'INFORMASI UJIAN', 0, 1, 'L', true);
$pdf->SetFont('dejavusans', '', 10);
$pdf->SetFillColor(255, 255, 255);

$pdf->Cell(35, 7, 'Mata Pelajaran', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . $hasil['nama_mapel'] . ' (' . $hasil['kode_mapel'] . ')', 0, 1, 'L');

$pdf->Cell(35, 7, 'KKM', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . $kkm_mapel, 0, 1, 'L');

$pdf->Cell(35, 7, 'Judul Ujian', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . $hasil['judul_ujian'], 0, 1, 'L');

$pdf->Cell(35, 7, 'Tanggal Ujian', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . date('d-m-Y H:i', strtotime($hasil['waktu_mulai'])), 0, 1, 'L');

$pdf->Cell(35, 7, 'Durasi', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->Cell(0, 7, ' ' . $hasil['durasi'] . ' menit', 0, 1, 'L');

$pdf->Ln(3);

// Box Nilai Akhir
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(255, 245, 245);
$pdf->Cell(0, 8, 'HASIL UJIAN', 0, 1, 'L', true);
$pdf->SetFont('dejavusans', '', 10);
$pdf->SetFillColor(255, 255, 255);

$nilai = $hasil['nilai'] ?? 0;
$is_lulus = $nilai >= $kkm_mapel;
$status_lulus = $is_lulus ? 'LULUS' : 'TIDAK LULUS';
$status_color = $is_lulus ? '#28a745' : '#dc3545';

$pdf->Cell(35, 7, 'Nilai Akhir', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 7, ' ' . number_format($nilai, 2), 0, 1, 'L');

$pdf->Cell(35, 7, 'KKM', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->SetTextColor($status_color);
$pdf->Cell(0, 7, ' ' . $kkm_mapel, 0, 1, 'L');

$pdf->Cell(35, 7, 'Status', 0, 0, 'L');
$pdf->Cell(5, 7, ':', 0, 0, 'C');
$pdf->SetTextColor($status_color);
$pdf->Cell(0, 7, ' ' . $status_lulus, 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(5);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// ==================== DAFTAR SOAL ====================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 8, 'DAFTAR SOAL DAN JAWABAN', 0, 1, 'C');
$pdf->Ln(3);

$total_soal = count($soal_list);
$soal_dijawab = 0;
$soal_benar = 0;
$total_skor_didapat = 0;
$total_skor_maksimal = 0;

$no = 1;
foreach ($soal_list as $soal) {
    // Cek jika perlu halaman baru (beri ruang cukup untuk gambar)
    if ($pdf->GetY() > 180) {
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 8, 'DAFTAR SOAL DAN JAWABAN (Lanjutan)', 0, 1, 'C');
        $pdf->Ln(3);
    }
    
    $jenis_soal = $soal['jenis_soal'];
    $jawaban_siswa = $soal['jawaban_siswa'] ?? $soal['jawaban_essay'] ?? null;
    $jawaban_benar = $soal['jawaban_benar'] ?? '-';
    $skor = 0;
    
    $is_dijawab = !empty($jawaban_siswa);
    if ($is_dijawab) $soal_dijawab++;
    
    $total_skor_maksimal += $soal['skor'];
    
    // Jenis display
    $jenis_display = '';
    switch($jenis_soal) {
        case 'pilihan_ganda': $jenis_display = 'Pilihan Ganda'; break;
        case 'pilihan_ganda_kompleks': $jenis_display = 'Pilihan Ganda Kompleks'; break;
        case 'essay': $jenis_display = 'Essay'; break;
        case 'menjodohkan': $jenis_display = 'Menjodohkan'; break;
        case 'benar_salah': $jenis_display = 'Benar/Salah'; break;
        default: $jenis_display = $jenis_soal;
    }
    
    // Hitung skor
    if ($jenis_soal == 'essay') {
        $skor = $soal['skor_essay'] ?? 0;
        $status = 'Essay';
        if ($skor > 0) $soal_benar++;
    } elseif ($jenis_soal == 'menjodohkan') {
        $skor = $soal['skor_essay'] ?? 0;
        $status = 'Menjodohkan';
        if ($skor > 0) $soal_benar++;
    } else {
        if ($is_dijawab && $jawaban_siswa == $jawaban_benar) {
            $skor = $soal['skor'];
            $status = 'BENAR';
            $soal_benar++;
        } elseif ($is_dijawab) {
            $skor = 0;
            $status = 'SALAH';
        } else {
            $skor = 0;
            $status = 'TIDAK DIJAWAB';
        }
    }
    
    $total_skor_didapat += $skor;
    
    // Header Soal
    if (!$is_dijawab) {
        $pdf->SetFillColor(255, 245, 245);
    } else {
        $pdf->SetFillColor(230, 230, 230);
    }
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Rect(15, $pdf->GetY(), 180, 8, 'DF');
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(0, 8, ' SOAL No. ' . $no . ' (' . $jenis_display . ')', 0, 1, 'L', true);
    
    // Pertanyaan
    $pdf->SetFont('dejavusans', '', 10);
    $pertanyaan = html_entity_decode($soal['pertanyaan'], ENT_QUOTES, 'UTF-8');
    $pertanyaan = strip_tags($pertanyaan);
    $pdf->MultiCell(0, 6, $pertanyaan, 0, 'L', 0, 1, '', '', true);
    $pdf->Ln(2);
    
    // ============================================================
    // PERBAIKAN UTAMA: GAMBAR DENGAN JARAK YANG CUKUP
    // ============================================================
    if (!empty($soal['gambar_soal']) && file_exists($soal['gambar_soal'])) {
        // Simpan posisi Y saat ini
        $current_y = $pdf->GetY();
        
        $pdf->SetFont('dejavusans', 'I', 8);
        $pdf->Cell(0, 5, 'Gambar Soal:', 0, 1, 'L');
        
        // Tampilkan gambar dengan lebar maksimal 70mm
        $pdf->Image($soal['gambar_soal'], 20, $pdf->GetY(), 70, 0, '', '', '', false, 300);
        
        // Hitung perkiraan tinggi gambar (asumsi rasio 4:3, tinggi sekitar 52.5mm untuk lebar 70mm)
        $estimated_image_height = 55; // mm
        
        // Set posisi Y setelah gambar dengan jarak aman
        $pdf->SetY($pdf->GetY() + $estimated_image_height + 10);
        
        // Pastikan tidak ada tumpang tindih
        if ($pdf->GetY() < $current_y + 20) {
            $pdf->SetY($current_y + 25);
        }
        
        $pdf->Ln(3);
    }
    
    // Opsi jawaban untuk pilihan ganda
    if ($jenis_soal == 'pilihan_ganda' || $jenis_soal == 'pilihan_ganda_kompleks') {
        $pdf->SetFont('dejavusans', '', 10);
        
        $opsi_a = html_entity_decode($soal['opsi_a'] ?? '', ENT_QUOTES, 'UTF-8');
        if (!empty($opsi_a) && $opsi_a != '-') {
            $pdf->Cell(8, 6, 'A.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, strip_tags($opsi_a), 0, 'L', 0, 1, '', '', true);
        }
        
        $opsi_b = html_entity_decode($soal['opsi_b'] ?? '', ENT_QUOTES, 'UTF-8');
        if (!empty($opsi_b) && $opsi_b != '-') {
            $pdf->Cell(8, 6, 'B.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, strip_tags($opsi_b), 0, 'L', 0, 1, '', '', true);
        }
        
        $opsi_c = html_entity_decode($soal['opsi_c'] ?? '', ENT_QUOTES, 'UTF-8');
        if (!empty($opsi_c) && $opsi_c != '-') {
            $pdf->Cell(8, 6, 'C.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, strip_tags($opsi_c), 0, 'L', 0, 1, '', '', true);
        }
        
        $opsi_d = html_entity_decode($soal['opsi_d'] ?? '', ENT_QUOTES, 'UTF-8');
        if (!empty($opsi_d) && $opsi_d != '-') {
            $pdf->Cell(8, 6, 'D.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, strip_tags($opsi_d), 0, 'L', 0, 1, '', '', true);
        }
        
        $opsi_e = html_entity_decode($soal['opsi_e'] ?? '', ENT_QUOTES, 'UTF-8');
        if (!empty($opsi_e) && $opsi_e != '-') {
            $pdf->Cell(8, 6, 'E.', 0, 0, 'L');
            $pdf->MultiCell(0, 6, strip_tags($opsi_e), 0, 'L', 0, 1, '', '', true);
        }
        $pdf->Ln(2);
    }
    
    // Jawaban Siswa
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(45, 7, 'Jawaban Siswa', 1, 0, 'L', true);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetFillColor(255, 255, 255);
    
    if (!$is_dijawab) {
        $pdf->SetTextColor(255, 165, 0);
        $pdf->Cell(0, 7, ' : TIDAK DIJAWAB', 1, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $jawaban_siswa_decoded = html_entity_decode($jawaban_siswa, ENT_QUOTES, 'UTF-8');
        $pdf->Cell(0, 7, ' : ' . $jawaban_siswa_decoded, 1, 1, 'L', true);
    }
    
    // Kunci Jawaban
    if ($jenis_soal != 'essay' && $jenis_soal != 'menjodohkan') {
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(45, 7, 'Kunci Jawaban', 1, 0, 'L', true);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetFillColor(255, 255, 255);
        
        $jawaban_benar_decoded = html_entity_decode($jawaban_benar, ENT_QUOTES, 'UTF-8');
        $pdf->Cell(0, 7, ' : ' . $jawaban_benar_decoded, 1, 1, 'L', true);
    }
    
    // Status
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(45, 7, 'Status', 1, 0, 'L', true);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetFillColor(255, 255, 255);
    
    if ($jenis_soal == 'essay') {
        $pdf->SetTextColor(0, 0, 255);
        $pdf->Cell(0, 7, ' : Essay (Koreksi Manual)', 1, 1, 'L', true);
    } elseif ($jenis_soal == 'menjodohkan') {
        $pdf->SetTextColor(0, 0, 255);
        $pdf->Cell(0, 7, ' : Menjodohkan (Koreksi Manual)', 1, 1, 'L', true);
    } elseif (!$is_dijawab) {
        $pdf->SetTextColor(255, 165, 0);
        $pdf->Cell(0, 7, ' : TIDAK DIJAWAB', 1, 1, 'L', true);
    } elseif ($status == 'BENAR') {
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(0, 7, ' : ' . $status, 1, 1, 'L', true);
    } else {
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(0, 7, ' : ' . $status, 1, 1, 'L', true);
    }
    $pdf->SetTextColor(0, 0, 0);
    
    // Skor
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(45, 7, 'Skor', 1, 0, 'L', true);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetFillColor(255, 255, 255);
    
    if ($jenis_soal == 'essay' || $jenis_soal == 'menjodohkan') {
        $pdf->Cell(0, 7, ' : ' . $skor . ' / ' . $soal['skor'], 1, 1, 'L', true);
    } elseif (!$is_dijawab) {
        $pdf->Cell(0, 7, ' : 0 / ' . $soal['skor'], 1, 1, 'L', true);
    } else {
        $pdf->Cell(0, 7, ' : ' . $skor . ' / ' . $soal['skor'], 1, 1, 'L', true);
    }
    
    // Komentar
    if (($jenis_soal == 'essay' || $jenis_soal == 'menjodohkan') && !empty($soal['komentar_guru'])) {
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(45, 6, 'Komentar', 1, 0, 'L', true);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetFillColor(255, 255, 255);
        
        $komentar = html_entity_decode($soal['komentar_guru'], ENT_QUOTES, 'UTF-8');
        $pdf->MultiCell(0, 6, ' : ' . strip_tags($komentar), 1, 'L', 1, 1, '', '', true);
    }
    
    $pdf->Ln(5);
    $no++;
}

// Ringkasan Akhir
$pdf->AddPage();
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 8, 'RINGKASAN HASIL UJIAN', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('dejavusans', '', 11);
$pdf->Cell(60, 7, 'Total Soal', 1, 0, 'L');
$pdf->Cell(0, 7, ': ' . $total_soal . ' soal', 1, 1, 'L');

$pdf->Cell(60, 7, 'Soal Dijawab', 1, 0, 'L');
$pdf->Cell(0, 7, ': ' . $soal_dijawab . ' soal', 1, 1, 'L');

$pdf->Cell(60, 7, 'Soal Tidak Dijawab', 1, 0, 'L');
$pdf->Cell(0, 7, ': ' . ($total_soal - $soal_dijawab) . ' soal', 1, 1, 'L');

$pdf->Cell(60, 7, 'Soal Benar', 1, 0, 'L');
$pdf->Cell(0, 7, ': ' . $soal_benar . ' soal', 1, 1, 'L');

$pdf->Cell(60, 7, 'Soal Salah', 1, 0, 'L');
$pdf->Cell(0, 7, ': ' . ($soal_dijawab - $soal_benar) . ' soal', 1, 1, 'L');

$pdf->Cell(60, 7, 'Total Skor Didapat', 1, 0, 'L');
$pdf->Cell(0, 7, ': ' . $total_skor_didapat . ' poin', 1, 1, 'L');

$pdf->Cell(60, 7, 'Total Skor Maksimal', 1, 0, 'L');
$pdf->Cell(0, 7, ': ' . $total_skor_maksimal . ' poin', 1, 1, 'L');

$pdf->Cell(60, 7, 'Nilai Akhir', 1, 0, 'L');
$nilai_akhir = $hasil['nilai'] ?? 0;
$pdf->Cell(0, 7, ': ' . number_format($nilai_akhir, 2) . ' %', 1, 1, 'L');

$pdf->Cell(60, 7, 'KKM', 1, 0, 'L');
$pdf->Cell(0, 7, ': ' . $kkm_mapel . ' %', 1, 1, 'L');

$pdf->Cell(60, 7, 'Status Kelulusan', 1, 0, 'L');
$status_text = $nilai_akhir >= $kkm_mapel ? 'LULUS' : 'TIDAK LULUS';
$status_color = $nilai_akhir >= $kkm_mapel ? '#28a745' : '#dc3545';
$pdf->SetTextColor($status_color);
$pdf->Cell(0, 7, ': ' . $status_text, 1, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

// Output PDF
$pdf->Output('resume_siswa_' . $hasil['nisn'] . '_' . date('Ymd') . '.pdf', 'I');
exit();
?>