<?php
// export_hasil.php - Ekspor Hasil Ujian (PDF & CSV)
require_once 'config.php';

// ===== CEK DAN LOAD LIBRARY =====
$tcpdfLoaded = file_exists('tcpdf/tcpdf.php');
define('TCPDF_LOADED', $tcpdfLoaded);

if ($tcpdfLoaded) {
    require_once 'tcpdf/tcpdf.php';
}

// ===== CEK LOGIN DAN ROLE =====
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

$ujian_id = $_GET['ujian_id'] ?? 0;
$format = $_GET['format'] ?? '';

if (!$ujian_id) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Ujian ID tidak ditemukan'
    ];
    header('Location: input_nilai.php');
    exit();
}

// Ambil data
$data = getExportData($ujian_id);
list($ujian_data, $hasil_list, $soal_list) = $data;

// Ambil data sekolah untuk logo
$sekolah_data = getSekolahData();

// Proses berdasarkan format
switch ($format) {
    case 'pdf':
        if (!TCPDF_LOADED) {
            die("ERROR: TCPDF tidak tersedia. Pastikan folder tcpdf ada di root.");
        }
        exportPDF($ujian_data, $hasil_list, $soal_list, $sekolah_data);
        break;
    case 'csv':
        exportCSV($ujian_data, $hasil_list, $soal_list, $sekolah_data);
        break;
    default:
        showExportPage($ujian_data, $hasil_list, $soal_list, $sekolah_data);
}

// ================= FUNGSI DATA =================
function getExportData($ujian_id) {
    global $pdo;
    
    // Data ujian
    $stmt = $pdo->prepare("
        SELECT u.*, mp.nama_mapel, mp.kode_mapel 
        FROM ujian u 
        JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$ujian_id]);
    $ujian_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ujian_data) {
        die("Ujian tidak ditemukan");
    }
    
    // Hasil ujian
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
            COALESCE(SUM(soal.skor), 0) as total_skor_maksimal,
            hu.nilai as persentase
        FROM hasil_ujian hu
        JOIN siswa s ON hu.siswa_id = s.id
        LEFT JOIN jawaban_siswa js ON js.hasil_ujian_id = hu.id
        LEFT JOIN soal ON js.soal_id = soal.id
        LEFT JOIN jawaban_essay je ON je.hasil_ujian_id = hu.id AND je.soal_id = soal.id
        WHERE hu.ujian_id = ?
        GROUP BY hu.id
        ORDER BY s.kelas, s.nama
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ujian_id]);
    $hasil_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daftar soal
    $stmt_soal = $pdo->prepare("
        SELECT su.urutan, s.id, s.jenis_soal, s.skor, s.pertanyaan
        FROM soal_ujian su 
        JOIN soal s ON su.soal_id = s.id 
        WHERE su.ujian_id = ? 
        ORDER BY su.urutan
    ");
    $stmt_soal->execute([$ujian_id]);
    $soal_list = $stmt_soal->fetchAll(PDO::FETCH_ASSOC);
    
    return [$ujian_data, $hasil_list, $soal_list];
}

function getSekolahData() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM profil_sekolah ORDER BY id DESC LIMIT 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        $data = [
            'nama_sekolah' => 'SISTEM UJIAN ONLINE',
            'alamat' => '',
            'telepon' => '',
            'email' => '',
            'website' => '',
            'logo' => ''
        ];
    }
    
    return $data;
}

// ================= EKSPOR PDF =================
function exportPDF($ujian_data, $hasil_list, $soal_list, $sekolah_data) {
    // Class PDF Kustom
    class PDF_Hasil extends TCPDF {
        public $sekolah_data;
        public $ujian_data;
        
        public function setData($sekolah, $ujian) {
            $this->sekolah_data = $sekolah;
            $this->ujian_data = $ujian;
        }
        
        // Header dengan kop surat
        public function Header() {
            if ($this->sekolah_data) {
                // Logo (di kiri, tidak terpotong garis)
                $logo_path = !empty($this->sekolah_data['logo']) && file_exists($this->sekolah_data['logo']) ? $this->sekolah_data['logo'] : '';
                if (!empty($logo_path)) {
                    $this->Image($logo_path, 15, 8, 22, 22, '', '', '', false, 300, '', false, false, 0);
                }
                
                // Nama Sekolah (di tengah)
                $this->SetY(12);
                $this->SetFont('helvetica', 'B', 14);
                $this->Cell(0, 6, strtoupper($this->sekolah_data['nama_sekolah']), 0, 1, 'C');
                
                // Alamat
                $this->SetFont('helvetica', '', 9);
                if (!empty($this->sekolah_data['alamat'])) {
                    $this->Cell(0, 5, $this->sekolah_data['alamat'], 0, 1, 'C');
                }
                
                // Telepon, Email
                $info = array();
                if (!empty($this->sekolah_data['telepon'])) $info[] = "Telp: " . $this->sekolah_data['telepon'];
                if (!empty($this->sekolah_data['email'])) $info[] = "Email: " . $this->sekolah_data['email'];
                if (!empty($info)) {
                    $this->Cell(0, 5, implode(' | ', $info), 0, 1, 'C');
                }
                
                // Garis kop (panjang penuh, tidak memotong logo)
                $this->SetDrawColor(0, 0, 0);
                $y = $this->GetY() + 2;
                $this->Line(10, $y, 200, $y);
                $this->Line(10, $y + 1, 200, $y + 1);
                $this->SetY($y + 8);
                
                // Judul Laporan (tanpa garis bawah)
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 8, 'LAPORAN HASIL UJIAN', 0, 1, 'C');
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
    $pdf = new PDF_Hasil('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setData($sekolah_data, $ujian_data);
    $pdf->SetCreator($sekolah_data['nama_sekolah']);
    $pdf->SetAuthor('Guru');
    $pdf->SetTitle('Hasil Ujian: ' . $ujian_data['judul_ujian']);
    $pdf->SetMargins(15, 55, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->AddPage();
    
    // ===== INFORMASI UJIAN (tanpa durasi) =====
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Cell(0, 8, 'INFORMASI UJIAN', 0, 1, 'L', true);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(255, 255, 255);
    
    $info_data = [
        ['Judul Ujian', $ujian_data['judul_ujian']],
        ['Mata Pelajaran', $ujian_data['nama_mapel'] . ' (' . $ujian_data['kode_mapel'] . ')'],
        ['Kelas Target', $ujian_data['kelas_target']],
        ['Waktu Ujian', date('d-m-Y H:i', strtotime($ujian_data['waktu_mulai'])) . ' s.d ' . date('H:i', strtotime($ujian_data['waktu_selesai']))]
    ];
    
    foreach ($info_data as $info) {
        $pdf->Cell(35, 6, $info[0], 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, $info[1], 0, 1);
    }
    
    $pdf->Ln(5);
    
    // ===== STATISTIK =====
    $stats = calculateStats($hasil_list);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Cell(0, 8, 'STATISTIK HASIL UJIAN', 0, 1, 'L', true);
    
    // Box statistik
    $pdf->SetFillColor(245, 245, 245);
    $box_width = 55;
    $start_x = 15;
    $start_y = $pdf->GetY();
    
    // Box 1: Total Peserta
    $pdf->Rect($start_x, $start_y, $box_width, 20, 'D');
    $pdf->SetXY($start_x + 5, $start_y + 3);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($box_width - 10, 5, 'Total Peserta', 0, 1);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY($start_x + 5, $start_y + 8);
    $pdf->Cell($box_width - 10, 8, $stats['total'], 0, 1, 'C');
    
    // Box 2: Lulus
    $pdf->Rect($start_x + $box_width + 5, $start_y, $box_width, 20, 'D');
    $pdf->SetXY($start_x + $box_width + 10, $start_y + 3);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($box_width - 10, 5, 'Lulus', 0, 1);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY($start_x + $box_width + 10, $start_y + 8);
    $pdf->Cell($box_width - 10, 8, $stats['lulus'] . ' (' . $stats['persen_lulus'] . '%)', 0, 1, 'C');
    
    // Box 3: Rata-rata
    $pdf->Rect($start_x + ($box_width + 5) * 2, $start_y, $box_width, 20, 'D');
    $pdf->SetXY($start_x + ($box_width + 5) * 2 + 5, $start_y + 3);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($box_width - 10, 5, 'Rata-rata Nilai', 0, 1);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY($start_x + ($box_width + 5) * 2 + 5, $start_y + 8);
    $pdf->Cell($box_width - 10, 8, $stats['rata_rata'] . '%', 0, 1, 'C');
    
    // Box 4: Total Soal
    $pdf->Rect($start_x + ($box_width + 5) * 3, $start_y, $box_width, 20, 'D');
    $pdf->SetXY($start_x + ($box_width + 5) * 3 + 5, $start_y + 3);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($box_width - 10, 5, 'Total Soal', 0, 1);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY($start_x + ($box_width + 5) * 3 + 5, $start_y + 8);
    $pdf->Cell($box_width - 10, 8, count($soal_list), 0, 1, 'C');
    
    $pdf->SetY($start_y + 25);
    $pdf->Ln(5);
    
    // ===== TABEL HASIL SISWA =====
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Cell(0, 8, 'DAFTAR HASIL SISWA', 0, 1, 'L', true);
    $pdf->Ln(3);
    
    // Header tabel
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);
    
    $headers = ['NO', 'NISN', 'NAMA SISWA', 'KELAS', 'NILAI', '%', 'STATUS'];
    $widths = [10, 25, 70, 25, 20, 20, 30];
    
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
        $persentase = $hasil['persentase'] ?? 0;
        $status_nilai = ($persentase >= 60) ? 'LULUS' : 'TIDAK LULUS';
        
        // Warna baris bergantian
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
        $pdf->Cell($widths[5], 7, round($persentase, 1) . '%', 1, 0, 'C', true);
        
        // Status
        if ($status_nilai == 'LULUS') {
            $pdf->SetFillColor(46, 204, 113);
            $pdf->SetTextColor(255, 255, 255);
        } else {
            $pdf->SetFillColor(231, 76, 60);
            $pdf->SetTextColor(255, 255, 255);
        }
        $pdf->Cell($widths[6], 7, $status_nilai, 1, 0, 'C', true);
        
        $pdf->Ln();
        $pdf->SetTextColor(0, 0, 0);
        $fill = !$fill;
        $no++;
        
        // Cek page break
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
    
    // ===== TANDA TANGAN (Rapi dalam satu lembar) =====
    // Hitung sisa ruang di halaman
    $current_y = $pdf->GetY();
    $remaining_space = 280 - $current_y; // A4 Landscape tinggi ~210mm, margin bawah 15mm
    
    if ($remaining_space < 60) {
        // Jika tidak cukup ruang, tambah halaman baru
        $pdf->AddPage();
        $current_y = $pdf->GetY();
    }
    
    // Posisi tanda tangan di kanan bawah
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
    $filename = 'Laporan_Hasil_Ujian_' . sanitizeFilename($ujian_data['judul_ujian']) . '.pdf';
    $pdf->Output($filename, 'D');
    exit();
}

// ================= EKSPOR CSV =================
function exportCSV($ujian_data, $hasil_list, $soal_list, $sekolah_data) {
    $filename = 'Hasil_Ujian_' . sanitizeFilename($ujian_data['judul_ujian']) . '_' . date('Ymd_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    
    // Header dengan nama sekolah
    fputcsv($output, [strtoupper($sekolah_data['nama_sekolah'] ?? 'LAPORAN HASIL UJIAN')], ';');
    fputcsv($output, ['LAPORAN HASIL UJIAN'], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Judul Ujian:', $ujian_data['judul_ujian']], ';');
    fputcsv($output, ['Mata Pelajaran:', $ujian_data['nama_mapel'] . ' (' . $ujian_data['kode_mapel'] . ')'], ';');
    fputcsv($output, ['Kelas:', $ujian_data['kelas_target']], ';');
    fputcsv($output, ['Waktu Ujian:', date('d/m/Y H:i', strtotime($ujian_data['waktu_mulai'])) . ' - ' . date('H:i', strtotime($ujian_data['waktu_selesai']))], ';');
    fputcsv($output, ['Tanggal Ekspor:', date('d/m/Y H:i:s')], ';');
    fputcsv($output, [], ';');
    
    // Data header
    $headers = ['No', 'NISN', 'Nama', 'Kelas', 'Status', 'Total Nilai', 'Persentase', 'Status Nilai'];
    fputcsv($output, $headers, ';');
    
    // Data
    $no = 1;
    foreach ($hasil_list as $hasil) {
        $total_nilai = ($hasil['nilai_otomatis'] ?? 0) + ($hasil['nilai_manual'] ?? 0);
        $persentase = $hasil['persentase'] ?? 0;
        $status_nilai = ($persentase >= 60) ? 'LULUS' : 'TIDAK LULUS';
        
        $row = [
            $no++,
            $hasil['nisn'],
            $hasil['nama_siswa'],
            $hasil['kelas_siswa'],
            $hasil['status'] == 'selesai' ? 'Selesai' : 'Sedang Ujian',
            $total_nilai,
            round($persentase, 2) . '%',
            $status_nilai
        ];
        
        fputcsv($output, $row, ';');
    }
    
    fputcsv($output, [], ';');
    fputcsv($output, ['Dicetak oleh:', $sekolah_data['nama_sekolah'] ?? 'Sistem Ujian Online'], ';');
    fputcsv($output, ['Tanggal:', date('d/m/Y H:i:s')], ';');
    
    fclose($output);
    exit();
}

// ================= HALAMAN PILIHAN =================
function showExportPage($ujian_data, $hasil_list, $soal_list, $sekolah_data) {
    $stats = calculateStats($hasil_list);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ekspor Hasil Ujian</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { background: #f0f2f5; padding: 20px; }
            .card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; border: none; }
            .stat-card { border-left: 4px solid; padding: 15px 20px; background: white; border-radius: 8px; margin-bottom: 15px; }
            .stat-card.primary { border-color: #0d6efd; }
            .stat-card.success { border-color: #198754; }
            .stat-card.info { border-color: #0dcaf0; }
            .stat-card.warning { border-color: #ffc107; }
            .export-option { text-align: center; padding: 30px 20px; transition: transform 0.2s; }
            .export-option:hover { transform: translateY(-5px); }
            .export-icon { font-size: 52px; margin-bottom: 15px; }
            .page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
            .table th { background: #f8f9fa; font-weight: 600; }
            .btn-export { padding: 12px 24px; font-weight: 500; }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- Header -->
            <div class="page-header">
                <div class="d-flex align-items-center">
                    <?php if (!empty($sekolah_data['logo']) && file_exists($sekolah_data['logo'])): ?>
                    <img src="<?= htmlspecialchars($sekolah_data['logo']) ?>" alt="Logo" style="height: 55px; margin-right: 15px; background: white; padding: 5px; border-radius: 8px;">
                    <?php endif; ?>
                    <div>
                        <h4 class="mb-1"><?= htmlspecialchars($sekolah_data['nama_sekolah'] ?? 'Sistem Ujian Online') ?></h4>
                        <h6 class="mb-0 opacity-75">Ekspor Hasil Ujian</h6>
                    </div>
                </div>
            </div>
            
            <?php if (!TCPDF_LOADED): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Peringatan:</strong> Library TCPDF tidak ditemukan. Fitur ekspor PDF tidak tersedia.
            </div>
            <?php endif; ?>
            
            <!-- Info Ujian -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h5 class="text-primary mb-1"><?= htmlspecialchars($ujian_data['judul_ujian']) ?></h5>
                            <p class="mb-0 text-muted">
                                <span class="badge bg-info me-2"><?= htmlspecialchars($ujian_data['nama_mapel']) ?></span>
                                <span class="badge bg-secondary">Kelas: <?= htmlspecialchars($ujian_data['kelas_target']) ?></span>
                            </p>
                        </div>
                        <a href="input_nilai.php?ujian_id=<?= $_GET['ujian_id'] ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Statistik -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Peserta</h6>
                                <h3 class="mb-0"><?= $stats['total'] ?></h3>
                            </div>
                            <i class="fas fa-users fa-2x text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Tingkat Kelulusan</h6>
                                <h3 class="mb-0"><?= $stats['persen_lulus'] ?>%</h3>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Rata-rata Nilai</h6>
                                <h3 class="mb-0"><?= $stats['rata_rata'] ?>%</h3>
                            </div>
                            <i class="fas fa-chart-line fa-2x text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Soal</h6>
                                <h3 class="mb-0"><?= count($soal_list) ?></h3>
                            </div>
                            <i class="fas fa-question-circle fa-2x text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pilihan Ekspor -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-download me-2"></i>Pilih Format Ekspor</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-2 <?= !TCPDF_LOADED ? 'border-danger' : 'border-primary' ?>">
                                <div class="card-body export-option">
                                    <div class="export-icon <?= TCPDF_LOADED ? 'text-danger' : 'text-muted' ?>">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <h5>PDF Document</h5>
                                    <p class="text-muted small">Format PDF siap cetak dengan kop surat</p>
                                    <?php if (TCPDF_LOADED): ?>
                                    <a href="export_hasil.php?ujian_id=<?= $_GET['ujian_id'] ?>&format=pdf" 
                                       class="btn btn-danger w-100 btn-export">
                                        <i class="fas fa-download me-2"></i>Download PDF
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-times me-2"></i>Tidak Tersedia
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-body export-option">
                                    <div class="export-icon text-primary">
                                        <i class="fas fa-file-csv"></i>
                                    </div>
                                    <h5>CSV Format</h5>
                                    <p class="text-muted small">Format CSV untuk Microsoft Excel</p>
                                    <a href="export_hasil.php?ujian_id=<?= $_GET['ujian_id'] ?>&format=csv" 
                                       class="btn btn-primary w-100 btn-export">
                                        <i class="fas fa-download me-2"></i>Download CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Preview Data</h5>
                    <span class="badge bg-secondary"><?= $stats['total'] ?> Data</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>NISN</th>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                    <th>Nilai</th>
                                    <th>%</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hasil_list as $index => $hasil): 
                                    $total_nilai = ($hasil['nilai_otomatis'] ?? 0) + ($hasil['nilai_manual'] ?? 0);
                                    $persentase = $hasil['persentase'] ?? 0;
                                    $status_nilai = ($persentase >= 60) ? 'LULUS' : 'TIDAK LULUS';
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($hasil['nisn']) ?></td>
                                    <td><?= htmlspecialchars($hasil['nama_siswa']) ?></td>
                                    <td><?= htmlspecialchars($hasil['kelas_siswa']) ?></td>
                                    <td><strong><?= $total_nilai ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $persentase >= 80 ? 'success' : ($persentase >= 60 ? 'warning' : 'danger') ?>">
                                            <?= round($persentase, 1) ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_nilai == 'LULUS' ? 'success' : 'danger' ?>">
                                            <?= $status_nilai ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-center text-muted mt-4">
                <small><?= htmlspecialchars($sekolah_data['nama_sekolah'] ?? 'Sistem Ujian Online') ?> | <?= date('d/m/Y H:i:s') ?></small>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

// ================= FUNGSI HELPER =================
function calculateStats($hasil_list) {
    $total = count($hasil_list);
    $lulus = 0;
    $total_nilai = 0;
    
    foreach ($hasil_list as $hasil) {
        $nilai = $hasil['persentase'] ?? 0;
        $total_nilai += $nilai;
        if ($nilai >= 60) $lulus++;
    }
    
    return [
        'total' => $total,
        'lulus' => $lulus,
        'persen_lulus' => $total > 0 ? round(($lulus / $total) * 100, 1) : 0,
        'rata_rata' => $total > 0 ? round($total_nilai / $total, 1) : 0
    ];
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}
?>