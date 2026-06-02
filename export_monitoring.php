<?php
// export_monitoring.php - Export data monitoring ujian ke PDF
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

// Ambil parameter filter
$ujian_id = $_GET['ujian_id'] ?? '';
$kelas = $_GET['kelas'] ?? '';

if (empty($ujian_id)) {
    die('Ujian tidak dipilih!');
}

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Load TCPDF
require_once('tcpdf/tcpdf.php');

// Ambil data ujian
$stmt_ujian = $pdo->prepare("
    SELECT u.*, mp.nama_mapel, mp.kode_mapel 
    FROM ujian u 
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
    WHERE u.id = ?
");
$stmt_ujian->execute([$ujian_id]);
$ujian = $stmt_ujian->fetch(PDO::FETCH_ASSOC);

if (!$ujian) {
    die('Data ujian tidak ditemukan!');
}

// Update status ujian yang sudah melebihi waktu selesai
$update_expired = $pdo->prepare("
    UPDATE hasil_ujian hu
    JOIN ujian u ON hu.ujian_id = u.id
    SET hu.status = 'waktu_habis', 
        hu.waktu_selesai = u.waktu_selesai
    WHERE hu.ujian_id = ? 
    AND hu.status = 'sedang_ujian'
    AND u.waktu_selesai < NOW()
");
$update_expired->execute([$ujian_id]);

// Ambil data siswa untuk monitoring
$sql_siswa = "
    SELECT 
        s.id as siswa_id,
        s.nisn,
        s.nama as nama_siswa,
        s.kelas,
        s.jenis_kelamin,
        hu.id as hasil_ujian_id,
        hu.status,
        hu.nilai,
        hu.waktu_mulai,
        hu.waktu_selesai,
        hu.waktu_mulai_pengerjaan,
        u.judul_ujian,
        u.durasi,
        u.waktu_mulai as ujian_mulai,
        u.waktu_selesai as ujian_selesai,
        mp.nama_mapel,
        mp.kode_mapel,
        CASE 
            WHEN hu.waktu_mulai_pengerjaan IS NOT NULL AND hu.waktu_mulai_pengerjaan != '0000-00-00 00:00:00' 
            THEN TIMESTAMPDIFF(MINUTE, hu.waktu_mulai_pengerjaan, NOW())
            ELSE 0
        END as waktu_berjalan,
        CASE 
            WHEN hu.status = 'selesai' OR hu.status = 'waktu_habis' THEN 0
            WHEN NOW() >= u.waktu_selesai THEN 0
            WHEN hu.status = 'sedang_ujian' AND NOW() < u.waktu_selesai
            THEN TIMESTAMPDIFF(MINUTE, NOW(), u.waktu_selesai)
            ELSE 0
        END as sisa_waktu,
        COALESCE((SELECT COUNT(*) FROM log_kecurangan WHERE hasil_ujian_id = hu.id), 0) as jumlah_kecurangan
    FROM siswa s
    LEFT JOIN hasil_ujian hu ON hu.siswa_id = s.id AND hu.ujian_id = ?
    CROSS JOIN ujian u ON u.id = ?
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id
    WHERE u.id = ?
    AND s.kelas = u.kelas_target
";

$params_siswa = [$ujian_id, $ujian_id, $ujian_id];

if ($kelas) {
    $sql_siswa .= " AND s.kelas = ?";
    $params_siswa[] = $kelas;
}

$sql_siswa .= " ORDER BY s.kelas, s.nama";

$stmt_siswa = $pdo->prepare($sql_siswa);
$stmt_siswa->execute($params_siswa);
$siswa_list = $stmt_siswa->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$total_siswa = count($siswa_list);
$sedang_ujian = 0;
$selesai = 0;
$waktu_habis = 0;
$belum_mulai = 0;
$total_kecurangan = 0;

foreach ($siswa_list as $siswa) {
    if ($siswa['status'] == 'sedang_ujian') {
        $sedang_ujian++;
    } elseif ($siswa['status'] == 'selesai') {
        $selesai++;
    } elseif ($siswa['status'] == 'waktu_habis') {
        $waktu_habis++;
    } else {
        $belum_mulai++;
    }
    $total_kecurangan += $siswa['jumlah_kecurangan'];
}

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
        'logo' => '',
        'kepala_sekolah' => 'Kepala Sekolah'
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

// Buat PDF dengan orientasi Landscape
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setSekolahData($profil);

$pdf->SetCreator('Sistem Ujian Online');
$pdf->SetAuthor($profil['nama_sekolah']);
$pdf->SetTitle('Monitoring Ujian - ' . $ujian['judul_ujian']);
$pdf->SetSubject('Laporan Monitoring Ujian');

$pdf->SetMargins(10, 45, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 20);

$pdf->AddPage();

// ==================== JUDUL ====================
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'LAPORAN MONITORING UJIAN', 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 7, strtoupper($ujian['judul_ujian']), 0, 1, 'C');

$pdf->SetFont('helvetica', '', 11);
$info_text = 'Mata Pelajaran: ' . $ujian['nama_mapel'] . ' (' . $ujian['kode_mapel'] . ')';
$pdf->Cell(0, 6, $info_text, 0, 1, 'C');

$waktu_text = 'Waktu Ujian: ' . date('d/m/Y H:i', strtotime($ujian['waktu_mulai'])) . ' - ' . date('H:i', strtotime($ujian['waktu_selesai']));
$pdf->Cell(0, 6, $waktu_text, 0, 1, 'C');

$pdf->Cell(0, 6, 'Kelas Target: ' . $ujian['kelas_target'], 0, 1, 'C');
$pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Ln(8);

// ==================== STATISTIK ====================
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(70, 130, 200);
$pdf->SetTextColor(255, 255, 255);

$col_width = 45;
$pdf->Cell($col_width, 8, 'Total Peserta: ' . $total_siswa, 1, 0, 'C', 1);
$pdf->Cell($col_width, 8, 'Sedang Ujian: ' . $sedang_ujian, 1, 0, 'C', 1);
$pdf->Cell($col_width, 8, 'Selesai: ' . $selesai, 1, 0, 'C', 1);
$pdf->Cell($col_width, 8, 'Waktu Habis: ' . $waktu_habis, 1, 0, 'C', 1);
$pdf->Cell($col_width, 8, 'Belum Mulai: ' . $belum_mulai, 1, 0, 'C', 1);
$pdf->Cell($col_width, 8, 'Total Kecurangan: ' . $total_kecurangan, 1, 1, 'C', 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);

// ==================== TABEL SISWA ====================
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(200, 200, 200);

// Lebar kolom untuk landscape
$w_no = 8;
$w_nisn = 25;
$w_nama = 50;
$w_kelas = 20;
$w_status = 30;
$w_mulai = 25;
$w_sisa = 25;
$w_kecurangan = 25;
$w_aksi = 30;

$pdf->Cell($w_no, 8, 'NO', 1, 0, 'C', 1);
$pdf->Cell($w_nisn, 8, 'NISN', 1, 0, 'C', 1);
$pdf->Cell($w_nama, 8, 'NAMA SISWA', 1, 0, 'C', 1);
$pdf->Cell($w_kelas, 8, 'KELAS', 1, 0, 'C', 1);
$pdf->Cell($w_status, 8, 'STATUS', 1, 0, 'C', 1);
$pdf->Cell($w_mulai, 8, 'WAKTU MULAI', 1, 0, 'C', 1);
$pdf->Cell($w_sisa, 8, 'SISA WAKTU', 1, 0, 'C', 1);
$pdf->Cell($w_kecurangan, 8, 'KECURANGAN', 1, 0, 'C', 1);
$pdf->Cell($w_aksi, 8, 'KETERANGAN', 1, 1, 'C', 1);

$pdf->SetFont('helvetica', '', 8);
$no = 1;

foreach ($siswa_list as $siswa) {
    if ($pdf->GetY() > 180) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell($w_no, 8, 'NO', 1, 0, 'C', 1);
        $pdf->Cell($w_nisn, 8, 'NISN', 1, 0, 'C', 1);
        $pdf->Cell($w_nama, 8, 'NAMA SISWA', 1, 0, 'C', 1);
        $pdf->Cell($w_kelas, 8, 'KELAS', 1, 0, 'C', 1);
        $pdf->Cell($w_status, 8, 'STATUS', 1, 0, 'C', 1);
        $pdf->Cell($w_mulai, 8, 'WAKTU MULAI', 1, 0, 'C', 1);
        $pdf->Cell($w_sisa, 8, 'SISA WAKTU', 1, 0, 'C', 1);
        $pdf->Cell($w_kecurangan, 8, 'KECURANGAN', 1, 0, 'C', 1);
        $pdf->Cell($w_aksi, 8, 'KETERANGAN', 1, 1, 'C', 1);
        $pdf->SetFont('helvetica', '', 8);
    }
    
    // Tentukan status
    $status = $siswa['status'];
    $sisa_waktu = $siswa['sisa_waktu'];
    $waktu_ujian_selesai = $siswa['ujian_selesai'];
    
    $waktu_sekarang = new DateTime();
    $waktu_selesai_dt = new DateTime($waktu_ujian_selesai);
    
    if ($status == 'sedang_ujian' && $waktu_sekarang > $waktu_selesai_dt) {
        $status = 'waktu_habis';
    }
    
    // Cek penalti
    $penalti_aktif = false;
    if ($siswa['hasil_ujian_id']) {
        $stmt_penalti = $pdo->prepare("
            SELECT COUNT(*) as ada_penalti 
            FROM log_kecurangan 
            WHERE hasil_ujian_id = ? 
            AND jenis_pelanggaran = 'penalty_activated'
            AND waktu_pelanggaran > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt_penalti->execute([$siswa['hasil_ujian_id']]);
        $penalti_aktif = $stmt_penalti->fetchColumn() > 0;
    }
    
    if ($status == 'sedang_ujian' && $penalti_aktif) {
        $status_aktual = 'ditunda';
    } elseif ($status == null && $siswa['hasil_ujian_id'] == null) {
        $status_aktual = 'belum_mulai';
    } else {
        $status_aktual = $status;
    }
    
    // Status text
    switch ($status_aktual) {
        case 'sedang_ujian':
            $status_text = 'Sedang Ujian';
            $status_color = array(255, 193, 7);
            $status_text_color = array(0, 0, 0);
            break;
        case 'selesai':
            $status_text = 'Selesai';
            $status_color = array(40, 167, 69);
            $status_text_color = array(255, 255, 255);
            break;
        case 'waktu_habis':
            $status_text = 'Waktu Habis';
            $status_color = array(220, 53, 69);
            $status_text_color = array(255, 255, 255);
            break;
        case 'ditunda':
            $status_text = 'Ditunda';
            $status_color = array(108, 117, 125);
            $status_text_color = array(255, 255, 255);
            break;
        default:
            $status_text = 'Belum Mulai';
            $status_color = array(108, 117, 125);
            $status_text_color = array(255, 255, 255);
    }
    
    // Format waktu mulai
    $waktu_mulai_text = '-';
    if (!empty($siswa['waktu_mulai_pengerjaan']) && $siswa['waktu_mulai_pengerjaan'] != '0000-00-00 00:00:00') {
        $waktu_mulai_text = date('H:i:s', strtotime($siswa['waktu_mulai_pengerjaan']));
    } elseif (!empty($siswa['waktu_mulai']) && $siswa['waktu_mulai'] != '0000-00-00 00:00:00') {
        $waktu_mulai_text = date('H:i:s', strtotime($siswa['waktu_mulai']));
    }
    
    // Sisa waktu
    if ($status_aktual == 'sedang_ujian' && $sisa_waktu > 0) {
        $sisa_text = $sisa_waktu . ' menit';
    } elseif ($status_aktual == 'sedang_ujian') {
        $sisa_text = 'Waktu Habis';
    } elseif ($status_aktual == 'selesai') {
        $sisa_text = 'Selesai';
    } elseif ($status_aktual == 'waktu_habis') {
        $sisa_text = 'Waktu Habis';
    } elseif ($status_aktual == 'ditunda') {
        $sisa_text = 'Penalti';
    } else {
        $sisa_text = '-';
    }
    
    $jumlah_kecurangan = $siswa['jumlah_kecurangan'];
    
    // Nama dipotong
    $nama = mb_strimwidth($siswa['nama_siswa'], 0, 25, '...');
    
    $pdf->Cell($w_no, 7, $no++, 1, 0, 'C');
    $pdf->Cell($w_nisn, 7, $siswa['nisn'], 1, 0, 'L');
    $pdf->Cell($w_nama, 7, $nama, 1, 0, 'L');
    $pdf->Cell($w_kelas, 7, $siswa['kelas'], 1, 0, 'C');
    
    $pdf->SetTextColor($status_text_color[0], $status_text_color[1], $status_text_color[2]);
    $pdf->SetFillColor($status_color[0], $status_color[1], $status_color[2]);
    $pdf->Cell($w_status, 7, $status_text, 1, 0, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    
    $pdf->Cell($w_mulai, 7, $waktu_mulai_text, 1, 0, 'C');
    $pdf->Cell($w_sisa, 7, $sisa_text, 1, 0, 'C');
    
    // Kecurangan
    if ($jumlah_kecurangan > 0) {
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell($w_kecurangan, 7, $jumlah_kecurangan . ' kali', 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $pdf->Cell($w_kecurangan, 7, '0', 1, 0, 'C');
    }
    
    // Keterangan
    $keterangan = '';
    if ($status_aktual == 'selesai' && $siswa['nilai'] !== null) {
        $keterangan = 'Nilai: ' . number_format($siswa['nilai'], 2);
    } elseif ($status_aktual == 'waktu_habis' && $siswa['nilai'] !== null) {
        $keterangan = 'Nilai: ' . number_format($siswa['nilai'], 2);
    } elseif ($status_aktual == 'ditunda') {
        $keterangan = 'Sedang penalti';
    } elseif ($status_aktual == 'belum_mulai') {
        $keterangan = 'Belum mengerjakan';
    }
    $keterangan = mb_strimwidth($keterangan, 0, 20, '...');
    $pdf->Cell($w_aksi, 7, $keterangan, 1, 1, 'C');
}

// ==================== TANDA TANGAN ====================
$pdf->Ln(20);

// Mengetahui di tengah
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'Mengetahui,', 0, 1, 'C');
$pdf->Ln(15);

// Buat tabel 2 kolom untuk tanda tangan (sejajar kiri dan kanan)
$pdf->SetFont('helvetica', '', 10);

// ========== KOLOM KIRI (Guru Pengawas) ==========
$pdf->SetX(25);
$pdf->Cell(75, 0.5, '', 'T', 0, 'C');  // Garis

$pdf->SetY($pdf->GetY() + 5);
$pdf->SetX(25);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(75, 6, 'Guru Pengawas', 0, 0, 'C');

$pdf->SetY($pdf->GetY() + 6);
$pdf->SetX(25);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(75, 5, 'Guru Pengawas', 0, 1, 'C');

// Kembali ke posisi Y awal untuk kolom kanan
$pdf->SetY($pdf->GetY() - 17);

// ========== KOLOM KANAN (Kepala Sekolah) ==========
$pdf->SetX(120);
$pdf->Cell(75, 0.5, '', 'T', 0, 'C');  // Garis

$pdf->SetY($pdf->GetY() + 5);
$pdf->SetX(120);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(75, 6, $profil['kepala_sekolah'] ?? 'Kepala Sekolah', 0, 0, 'C');

$pdf->SetY($pdf->GetY() + 6);
$pdf->SetX(120);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(75, 5, 'Kepala Sekolah', 0, 1, 'C');

// Output PDF
$filename = 'Monitoring_Ujian_' . $ujian['judul_ujian'] . '_' . date('Ymd_His') . '.pdf';
$filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
$pdf->Output($filename, 'I');
exit();
?>