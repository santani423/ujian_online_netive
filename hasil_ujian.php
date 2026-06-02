<?php
// hasil_ujian.php - Rekap hasil ujian dengan KKM per mata pelajaran (AUTO SYNC - SAMA DENGAN input_nilai.php)

// Mulai output buffering di awal
ob_start();

require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

// ===================================================
// FUNGSI UTILITY - SAMA PERSIS DENGAN input_nilai.php
// ===================================================

/**
 * Deteksi jenis soal - SAMA DENGAN input_nilai.php
 */
function deteksiJenisSoal($soal) {
    if (!empty($soal['jenis_soal']) && $soal['jenis_soal'] != 'pilihan_ganda') {
        return $soal['jenis_soal'];
    }
    
    // Deteksi Pilihan Ganda Kompleks
    if (!empty($soal['jawaban_kompleks']) && $soal['jawaban_kompleks'] != '[]' && $soal['jawaban_kompleks'] != 'null') {
        return 'pilihan_ganda_kompleks';
    }
    
    if (trim($soal['opsi_a'] ?? '') == 'Benar' && trim($soal['opsi_b'] ?? '') == 'Salah') {
        return 'benar_salah';
    }
    
    $opsi_c_empty = empty($soal['opsi_c']) || trim($soal['opsi_c'] ?? '') == '-' || trim($soal['opsi_c'] ?? '') == '';
    $opsi_d_empty = empty($soal['opsi_d']) || trim($soal['opsi_d'] ?? '') == '-' || trim($soal['opsi_d'] ?? '') == '';
    $opsi_e_empty = empty($soal['opsi_e']) || trim($soal['opsi_e'] ?? '') == '-' || trim($soal['opsi_e'] ?? '') == '';
    
    if ($opsi_c_empty && $opsi_d_empty && $opsi_e_empty) {
        return 'essay';
    }
    
    return 'pilihan_ganda';
}

/**
 * Hitung nilai PG Kompleks - SAMA DENGAN input_nilai.php
 */
function hitungNilaiPGKompleks($jawaban_siswa, $jawaban_kompleks, $skor_per_jawaban) {
    if (empty($jawaban_siswa) || empty($jawaban_kompleks)) {
        return 0;
    }
    
    $jawabanArray = explode(',', $jawaban_siswa);
    $jawabanBenar = json_decode($jawaban_kompleks, true);
    if (!is_array($jawabanBenar)) {
        $jawabanBenar = [];
    }
    
    $jumlahBenar = 0;
    foreach ($jawabanArray as $jwb) {
        if (in_array($jwb, $jawabanBenar)) {
            $jumlahBenar++;
        }
    }
    
    return $jumlahBenar * $skor_per_jawaban;
}

/**
 * Hitung grade berdasarkan nilai
 */
function hitungGrade($nilai) {
    if ($nilai >= 90) return 'A';
    if ($nilai >= 80) return 'B';
    if ($nilai >= 70) return 'C';
    if ($nilai >= 60) return 'D';
    return 'E';
}

/**
 * Format nilai dengan 2 desimal
 */
function formatNilai($nilai) {
    if ($nilai == 0 || $nilai == null) return '0.00';
    return number_format($nilai, 2);
}

/**
 * Cek kelulusan berdasarkan KKM
 */
function cekKelulusan($nilai, $kkm) {
    if ($nilai == 0 || $nilai == null) {
        return ['status' => false, 'status_text' => 'Belum', 'status_class' => 'secondary'];
    }
    
    if ($nilai >= $kkm) {
        return ['status' => true, 'status_text' => 'LULUS', 'status_class' => 'success'];
    } else {
        return ['status' => false, 'status_text' => 'TIDAK LULUS', 'status_class' => 'danger'];
    }
}

/**
 * Ambil KKM dari mata pelajaran
 */
function getKKM($mapel_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT kkm FROM mata_pelajaran WHERE id = ?");
        $stmt->execute([$mapel_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['kkm'])) {
            return (int)$result['kkm'];
        }
    } catch (PDOException $e) {
        error_log("Error getKKM: " . $e->getMessage());
    }
    return 70;
}

/**
 * Hitung nilai akhir - SAMA PERSIS DENGAN input_nilai.php
 * @param int $hasil_ujian_id ID hasil ujian
 * @return array Hasil perhitungan lengkap
 */
function hitungNilaiAkhirLengkap($hasil_ujian_id, $soal_ujian_list = null, $ujian_id = null) {
    global $pdo;
    
    try {
        // Jika soal_ujian_list tidak diberikan, ambil dari database
        if ($soal_ujian_list === null && $ujian_id !== null) {
            $stmt_soal = $pdo->prepare("
                SELECT su.*, s.* 
                FROM soal_ujian su 
                JOIN soal s ON su.soal_id = s.id 
                WHERE su.ujian_id = ? 
                ORDER BY su.urutan
            ");
            $stmt_soal->execute([$ujian_id]);
            $soal_ujian_list = $stmt_soal->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Jika masih null, return 0
        if ($soal_ujian_list === null) {
            return ['nilai' => 0, 'total_skor' => 0, 'nilai_otomatis' => 0, 'nilai_manual' => 0];
        }
        
        // Ambil jawaban siswa
        $stmt_jawaban = $pdo->prepare("SELECT soal_id, jawaban_siswa, jawaban_essay FROM jawaban_siswa WHERE hasil_ujian_id = ?");
        $stmt_jawaban->execute([$hasil_ujian_id]);
        $jawaban_map = [];
        while ($jwb = $stmt_jawaban->fetch(PDO::FETCH_ASSOC)) {
            $jawaban_map[$jwb['soal_id']] = $jwb;
        }
        
        // Ambil nilai manual dari jawaban_essay
        $stmt_manual = $pdo->prepare("SELECT soal_id, skor_essay FROM jawaban_essay WHERE hasil_ujian_id = ?");
        $stmt_manual->execute([$hasil_ujian_id]);
        $manual_map = [];
        while ($manual = $stmt_manual->fetch(PDO::FETCH_ASSOC)) {
            $manual_map[$manual['soal_id']] = $manual['skor_essay'];
        }
        
        $nilai_otomatis = 0;
        $total_skor = 0;
        
        foreach ($soal_ujian_list as $soal) {
            $jenis = deteksiJenisSoal($soal);
            $skor_soal = $soal['skor'];
            $total_skor += $skor_soal;
            
            if ($jenis == 'pilihan_ganda') {
                // Pilihan ganda biasa
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                if ($jawaban == $soal['jawaban_benar']) {
                    $nilai_otomatis += $skor_soal;
                }
            } 
            elseif ($jenis == 'pilihan_ganda_kompleks') {
                // Pilihan ganda kompleks
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                $nilai_otomatis += hitungNilaiPGKompleks($jawaban, $soal['jawaban_kompleks'], $soal['skor_per_jawaban']);
            }
            elseif ($jenis == 'benar_salah') {
                // Benar salah
                $jawaban = isset($jawaban_map[$soal['id']]) ? $jawaban_map[$soal['id']]['jawaban_siswa'] : '';
                if ($jawaban == $soal['jawaban_benar']) {
                    $nilai_otomatis += $skor_soal;
                }
            }
            // Essay dan menjodohkan tidak ditambahkan ke nilai_otomatis
        }
        
        // Hitung nilai manual (dari jawaban_essay)
        $nilai_manual = 0;
        foreach ($manual_map as $skor) {
            $nilai_manual += $skor;
        }
        
        $total_nilai = $nilai_otomatis + $nilai_manual;
        $persentase = $total_skor > 0 ? ($total_nilai / $total_skor) * 100 : 0;
        
        return [
            'nilai' => round($persentase, 2),
            'total_skor' => $total_skor,
            'nilai_otomatis' => $nilai_otomatis,
            'nilai_manual' => $nilai_manual,
            'total_nilai' => $total_nilai
        ];
        
    } catch (PDOException $e) {
        error_log("Error hitungNilaiAkhirLengkap: " . $e->getMessage());
        return ['nilai' => 0, 'total_skor' => 0, 'nilai_otomatis' => 0, 'nilai_manual' => 0];
    }
}

/**
 * Sinkronkan semua nilai - OTOMATIS setiap load halaman
 */
function sinkronkanSemuaNilai($ujian_id = null) {
    global $pdo;
    
    try {
        // Ambil semua soal ujian untuk ujian ini
        $soal_ujian_map = [];
        if ($ujian_id) {
            $stmt = $pdo->prepare("
                SELECT su.*, s.* 
                FROM soal_ujian su 
                JOIN soal s ON su.soal_id = s.id 
                WHERE su.ujian_id = ? 
                ORDER BY su.urutan
            ");
            $stmt->execute([$ujian_id]);
            $soal_ujian_map[$ujian_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Ambil semua ujian yang memiliki hasil
            $stmt = $pdo->query("
                SELECT DISTINCT u.id 
                FROM ujian u 
                JOIN hasil_ujian hu ON hu.ujian_id = u.id 
                WHERE hu.status IN ('selesai', 'waktu_habis')
            ");
            $ujian_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($ujian_ids as $uid) {
                $stmt = $pdo->prepare("
                    SELECT su.*, s.* 
                    FROM soal_ujian su 
                    JOIN soal s ON su.soal_id = s.id 
                    WHERE su.ujian_id = ? 
                    ORDER BY su.urutan
                ");
                $stmt->execute([$uid]);
                $soal_ujian_map[$uid] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        // Query untuk mengambil hasil ujian yang perlu disinkronkan
        $where_condition = "WHERE hu.status IN ('selesai', 'waktu_habis')";
        $params = [];
        
        if ($ujian_id) {
            $where_condition .= " AND hu.ujian_id = ?";
            $params[] = $ujian_id;
        }
        
        $sql = "SELECT hu.id, hu.ujian_id, hu.nilai FROM hasil_ujian hu $where_condition";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $hasil_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated_count = 0;
        
        foreach ($hasil_list as $hasil) {
            $uid = $hasil['ujian_id'];
            $soal_list = $soal_ujian_map[$uid] ?? null;
            
            if ($soal_list === null) {
                continue;
            }
            
            $perhitungan = hitungNilaiAkhirLengkap($hasil['id'], $soal_list);
            $nilai_baru = $perhitungan['nilai'];
            $nilai_lama = floatval($hasil['nilai'] ?? 0);
            
            if (abs($nilai_lama - $nilai_baru) > 0.01) {
                $stmt_update = $pdo->prepare("UPDATE hasil_ujian SET nilai = ? WHERE id = ?");
                $stmt_update->execute([$nilai_baru, $hasil['id']]);
                $updated_count++;
                error_log("Nilai hasil_ujian id {$hasil['id']} diupdate: $nilai_lama -> $nilai_baru");
            }
        }
        
        return $updated_count;
        
    } catch (PDOException $e) {
        error_log("Error sinkronkanSemuaNilai: " . $e->getMessage());
        return 0;
    }
}

/**
 * Update status ujian yang waktu sudah habis
 */
function updateStatusWaktuHabis($ujian_id = null) {
    global $pdo;
    
    try {
        $sql = "
            UPDATE hasil_ujian hu
            JOIN ujian u ON hu.ujian_id = u.id
            SET hu.status = 'waktu_habis'
            WHERE hu.status = 'sedang_ujian'
              AND u.waktu_selesai < NOW()
        ";
        
        if ($ujian_id) {
            $sql .= " AND u.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ujian_id]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->rowCount();
        
    } catch (PDOException $e) {
        error_log("Error updateStatusWaktuHabis: " . $e->getMessage());
        return 0;
    }
}

/**
 * Cek apakah ujian memiliki soal essay/menjodohkan
 */
function cekKoreksiManual($ujian_id) {
    global $pdo;
    
    $sql = "
        SELECT COUNT(*) as total_essay
        FROM soal_ujian su
        JOIN soal s ON su.soal_id = s.id
        WHERE su.ujian_id = ? 
          AND s.jenis_soal IN ('essay', 'menjodohkan')
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ujian_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total_essay'] ?? 0;
}

// ===================================================
// AUTO SYNC - JALANKAN SETIAP KALI HALAMAN DIAKSES
// ===================================================

// Update status waktu habis
$expired_updated = updateStatusWaktuHabis();

// Sinkronkan nilai secara OTOMATIS
$auto_sync_updated = sinkronkanSemuaNilai();

// ===================================================
// PROSES CETAK PDF
// ===================================================

if (isset($_GET['print']) && $_GET['print'] == 'pdf') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $kelas = $_GET['kelas'] ?? '';
    $mapel = $_GET['mapel'] ?? '';
    $ujian_id = $_GET['ujian_id'] ?? '';
    
    // Sinkronkan sebelum cetak
    sinkronkanSemuaNilai($ujian_id);
    
    // Query data berdasarkan filter
    $where_conditions = [];
    $params = [];
    
    if ($kelas) {
        $where_conditions[] = "s.kelas = ?";
        $params[] = $kelas;
    }
    
    if ($mapel) {
        $where_conditions[] = "u.mapel_id = ?";
        $params[] = $mapel;
    }
    
    if ($ujian_id) {
        $where_conditions[] = "hu.ujian_id = ?";
        $params[] = $ujian_id;
    }
    
    $where_conditions[] = "hu.status IN ('selesai', 'waktu_habis')";
    $where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $sql = "
        SELECT 
            s.kelas,
            mp.nama_mapel,
            mp.kode_mapel,
            mp.kkm,
            u.judul_ujian,
            u.id as ujian_id,
            u.waktu_selesai as ujian_selesai,
            s.nisn,
            s.nama as nama_siswa,
            hu.nilai,
            hu.waktu_mulai,
            hu.waktu_selesai,
            hu.status
        FROM hasil_ujian hu
        JOIN ujian u ON hu.ujian_id = u.id
        JOIN mata_pelajaran mp ON u.mapel_id = mp.id
        JOIN siswa s ON hu.siswa_id = s.id
        $where_sql
        ORDER BY s.kelas, mp.nama_mapel, s.nama
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    if (empty($data)) {
        die('Tidak ada data untuk dicetak!');
    }
    
    require_once('tcpdf/tcpdf.php');
    
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
                $this->SetFont('helvetica', 'B', 14);
                $this->Cell(0, 6, strtoupper($this->sekolah_data['nama_sekolah']), 0, 1, 'C');
                
                $this->SetFont('helvetica', '', 9);
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
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Dicetak: ' . date('d-m-Y H:i:s') . ' | Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
    
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setSekolahData($profil);
    
    $pdf->SetCreator('Sistem Ujian Online');
    $pdf->SetAuthor('Sekolah');
    $pdf->SetTitle('Laporan Hasil Ujian');
    $pdf->SetSubject('Hasil Ujian Siswa');
    
    $pdf->SetMargins(10, 45, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'LAPORAN HASIL UJIAN', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    $filter_info = [];
    if ($kelas) $filter_info[] = "Kelas: $kelas";
    if ($mapel) {
        $stmt_mapel = $pdo->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = ?");
        $stmt_mapel->execute([$mapel]);
        $mapel_data = $stmt_mapel->fetch();
        if ($mapel_data) $filter_info[] = "Mata Pelajaran: " . $mapel_data['nama_mapel'];
    }
    if ($ujian_id) {
        $stmt_ujian = $pdo->prepare("SELECT judul_ujian FROM ujian WHERE id = ?");
        $stmt_ujian->execute([$ujian_id]);
        $ujian_data = $stmt_ujian->fetch();
        if ($ujian_data) $filter_info[] = "Ujian: " . $ujian_data['judul_ujian'];
    }
    
    if (!empty($filter_info)) {
        $pdf->Cell(0, 6, implode(' | ', $filter_info), 0, 1, 'C');
    }
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Tanggal Cetak: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    
    $w_no = 10;
    $w_nisn = 25;
    $w_nama = 50;
    $w_kelas = 20;
    $w_nilai = 25;
    $w_grade = 20;
    $w_kkm = 20;
    $w_status = 30;
    
    $pdf->Cell($w_no, 7, 'NO', 1, 0, 'C', 1);
    $pdf->Cell($w_nisn, 7, 'NISN', 1, 0, 'C', 1);
    $pdf->Cell($w_nama, 7, 'NAMA SISWA', 1, 0, 'C', 1);
    $pdf->Cell($w_kelas, 7, 'KELAS', 1, 0, 'C', 1);
    $pdf->Cell($w_nilai, 7, 'NILAI', 1, 0, 'C', 1);
    $pdf->Cell($w_grade, 7, 'GRADE', 1, 0, 'C', 1);
    $pdf->Cell($w_kkm, 7, 'KKM', 1, 0, 'C', 1);
    $pdf->Cell($w_status, 7, 'STATUS', 1, 1, 'C', 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $no = 1;
    
    foreach ($data as $row) {
        $nilai = $row['nilai'] ?? 0;
        $kkm = $row['kkm'] ?? 70;
        $grade = hitungGrade($nilai);
        $lulus = ($nilai >= $kkm);
        $status_text = $lulus ? 'LULUS' : 'TIDAK LULUS';
        
        $nama = mb_strimwidth($row['nama_siswa'], 0, 28, '...');
        
        $pdf->Cell($w_no, 7, $no++, 1, 0, 'C');
        $pdf->Cell($w_nisn, 7, $row['nisn'], 1, 0, 'L');
        $pdf->Cell($w_nama, 7, $nama, 1, 0, 'L');
        $pdf->Cell($w_kelas, 7, $row['kelas'], 1, 0, 'C');
        $pdf->Cell($w_nilai, 7, formatNilai($nilai), 1, 0, 'C');
        $pdf->Cell($w_grade, 7, $grade, 1, 0, 'C');
        $pdf->Cell($w_kkm, 7, $kkm, 1, 0, 'C');
        $pdf->Cell($w_status, 7, $status_text, 1, 1, 'C');
    }
    
    $filename = 'Hasil_Ujian_' . date('Ymd_His') . '.pdf';
    $pdf->Output($filename, 'I');
    exit();
}

// ===================================================
// TAMPILAN WEB NORMAL
// ===================================================

$title = "Hasil Ujian";

ob_clean();

include 'templates/header.php';

// Ambil parameter filter
$filter_kelas = $_GET['kelas'] ?? '';
$filter_mapel = $_GET['mapel'] ?? '';
$filter_ujian = $_GET['ujian_id'] ?? '';

// Ambil data untuk filter
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll();
$mapel_list = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// Ambil daftar ujian untuk filter
$ujian_list = $pdo->query("
    SELECT u.*, mp.nama_mapel, mp.kkm
    FROM ujian u 
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id 
    WHERE u.status = 'published' 
    ORDER BY u.waktu_mulai DESC
")->fetchAll();

// Query untuk rekap detail per siswa
$where_conditions = [];
$params = [];

if ($filter_kelas) {
    $where_conditions[] = "s.kelas = ?";
    $params[] = $filter_kelas;
}

if ($filter_mapel) {
    $where_conditions[] = "u.mapel_id = ?";
    $params[] = $filter_mapel;
}

if ($filter_ujian) {
    $where_conditions[] = "hu.ujian_id = ?";
    $params[] = $filter_ujian;
}

// Tampilkan hasil ujian yang sudah selesai ATAU waktu habis
$where_conditions[] = "hu.status IN ('selesai', 'waktu_habis')";

$where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query rekap detail per siswa dengan KKM
$sql_rekap = "
    SELECT 
        s.kelas,
        mp.nama_mapel,
        mp.kode_mapel,
        mp.kkm,
        u.judul_ujian,
        u.id as ujian_id,
        u.waktu_selesai as ujian_selesai,
        u.durasi as ujian_durasi,
        s.nisn,
        s.nama as nama_siswa,
        hu.id as hasil_id,
        hu.siswa_id,
        hu.nilai,
        hu.waktu_mulai,
        hu.waktu_selesai,
        hu.status,
        TIMESTAMPDIFF(MINUTE, hu.waktu_mulai, hu.waktu_selesai) as durasi_pengerjaan,
        (
            SELECT COUNT(*) 
            FROM jawaban_siswa js
            JOIN soal s2 ON js.soal_id = s2.id
            LEFT JOIN jawaban_essay je ON je.hasil_ujian_id = js.hasil_ujian_id AND je.soal_id = js.soal_id
            WHERE js.hasil_ujian_id = hu.id
              AND s2.jenis_soal IN ('essay', 'menjodohkan')
              AND (je.status_koreksi IS NULL OR je.status_koreksi = 'belum')
        ) as essay_belum_dikoreksi,
        (
            SELECT COUNT(*) 
            FROM jawaban_siswa js 
            WHERE js.hasil_ujian_id = hu.id
        ) as jumlah_dijawab,
        (
            SELECT COUNT(*) 
            FROM soal_ujian su 
            WHERE su.ujian_id = u.id
        ) as total_soal
    FROM hasil_ujian hu
    JOIN ujian u ON hu.ujian_id = u.id
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id
    JOIN siswa s ON hu.siswa_id = s.id
    $where_sql
    ORDER BY s.kelas, mp.nama_mapel, s.nama
";

$stmt_rekap = $pdo->prepare($sql_rekap);
$stmt_rekap->execute($params);
$rekap_data = $stmt_rekap->fetchAll();

// Kelompokkan data berdasarkan kelas dan mata pelajaran
$data_per_kelas_mapel = [];
foreach ($rekap_data as $data) {
    $key = $data['kelas'] . '|' . $data['nama_mapel'] . '|' . $data['ujian_id'];
    if (!isset($data_per_kelas_mapel[$key])) {
        $data_per_kelas_mapel[$key] = [
            'kelas' => $data['kelas'],
            'mapel' => $data['nama_mapel'],
            'kode_mapel' => $data['kode_mapel'],
            'kkm' => $data['kkm'],
            'judul_ujian' => $data['judul_ujian'],
            'ujian_id' => $data['ujian_id'],
            'ujian_selesai' => $data['ujian_selesai'],
            'siswa' => []
        ];
    }
    $data_per_kelas_mapel[$key]['siswa'][] = $data;
}
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-chart-bar me-2"></i>Rekap Hasil Ujian</h2>
            <p class="text-muted mb-0">Hasil ujian dengan koreksi otomatis & manual (essay) - KKM per mata pelajaran</p>
            <?php if ($auto_sync_updated > 0): ?>
            <p class="text-success small mt-1"><i class="fas fa-sync-alt me-1"></i>Sinkronisasi otomatis: <?= $auto_sync_updated ?> nilai diperbarui</p>
            <?php endif; ?>
            <?php if ($expired_updated > 0): ?>
            <p class="text-info small mt-1"><i class="fas fa-hourglass-end me-1"></i><?= $expired_updated ?> ujian diperbarui statusnya</p>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if (count($rekap_data) > 0): ?>
            <a href="hasil_ujian.php?print=pdf&kelas=<?= urlencode($filter_kelas) ?>&mapel=<?= urlencode($filter_mapel) ?>&ujian_id=<?= urlencode($filter_ujian) ?>" 
               class="btn btn-danger" target="_blank">
                <i class="fas fa-file-pdf me-2"></i>Cetak PDF
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php 
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">';
        echo '<i class="fas ' . ($flash['type'] == 'success' ? 'fa-check-circle' : 'fa-info-circle') . ' me-2"></i>';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['flash_message']);
    }
    ?>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Rekap Ujian</h5>
        </div>
        <div class="card-body">
            <form method="GET" id="filterForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Kelas</label>
                            <select name="kelas" class="form-select" id="selectKelas">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= htmlspecialchars($kelas['kelas']) ?>" <?= $filter_kelas == $kelas['kelas'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kelas['kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran</label>
                            <select name="mapel" class="form-select" id="selectMapel">
                                <option value="">Semua Mapel</option>
                                <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?= $mapel['id'] ?>" <?= $filter_mapel == $mapel['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mapel['nama_mapel']) ?> (KKM: <?= $mapel['kkm'] ?? 70 ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Ujian</label>
                            <select name="ujian_id" class="form-select" id="selectUjian">
                                <option value="">Semua Ujian</option>
                                <?php foreach ($ujian_list as $ujian): 
                                    $total_essay = cekKoreksiManual($ujian['id']);
                                ?>
                                <option value="<?= $ujian['id'] ?>" <?= $filter_ujian == $ujian['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ujian['judul_ujian']) ?> 
                                    (<?= date('d/m/Y', strtotime($ujian['waktu_mulai'])) ?>)
                                    <?php if ($total_essay > 0): ?>
                                        <span class="badge bg-warning ms-1">Essay: <?= $total_essay ?></span>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Terapkan Filter
                        </button>
                        <a href="hasil_ujian.php" class="btn btn-secondary">
                            <i class="fas fa-refresh me-2"></i>Reset Filter
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="printableArea">
        <!-- Statistik Ringkas -->
        <?php if (count($rekap_data) > 0): 
            $total_siswa = count($rekap_data);
            $total_nilai = 0;
            $lulus = 0;
            $tidak_lulus = 0;
            $total_essay_belum = 0;
            $waktu_habis_count = 0;
            $selesai_count = 0;
            $total_soal_terjawab = 0;
            $total_soal_keseluruhan = 0;
            
            foreach ($rekap_data as $data) {
                $nilai = $data['nilai'] ?? 0;
                $kkm = $data['kkm'] ?? 70;
                $total_nilai += $nilai;
                
                if ($nilai >= $kkm) {
                    $lulus++;
                } else {
                    $tidak_lulus++;
                }
                
                $total_essay_belum += $data['essay_belum_dikoreksi'] ?? 0;
                
                if ($data['status'] == 'waktu_habis') {
                    $waktu_habis_count++;
                } elseif ($data['status'] == 'selesai') {
                    $selesai_count++;
                }
                
                $total_soal_terjawab += $data['jumlah_dijawab'] ?? 0;
                $total_soal_keseluruhan += $data['total_soal'] ?? 0;
            }
            
            $rata_rata = $total_siswa > 0 ? round($total_nilai / $total_siswa, 1) : 0;
            $persentase_terjawab = $total_soal_keseluruhan > 0 ? round(($total_soal_terjawab / $total_soal_keseluruhan) * 100, 1) : 0;
            $persentase_lulus_global = $total_siswa > 0 ? round(($lulus / $total_siswa) * 100, 1) : 0;
        ?>
        <div class="row mb-4 g-3">
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-primary h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= $total_siswa ?></h4><p class="mb-0 small">Total Hasil Ujian</p></div>
                        <i class="fas fa-file-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-success h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= $lulus ?></h4><p class="mb-0 small">Peserta Lulus</p></div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-info h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= $rata_rata ?></h4><p class="mb-0 small">Rata-rata Nilai</p></div>
                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-warning h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= $persentase_lulus_global ?>%</h4><p class="mb-0 small">Tingkat Kelulusan</p></div>
                        <i class="fas fa-graduation-cap fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4 g-3">
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-secondary h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= count($data_per_kelas_mapel) ?></h4><p class="mb-0 small">Kombinasi Kelas-Mapel</p></div>
                        <i class="fas fa-layer-group fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-danger h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= $total_essay_belum ?></h4><p class="mb-0 small">Essay Belum Dikoreksi</p></div>
                        <i class="fas fa-pen-square fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-dark h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= $selesai_count ?></h4><p class="mb-0 small">Selesai Tepat Waktu</p></div>
                        <i class="fas fa-clock fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-secondary h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div><h4 class="mb-0"><?= $persentase_terjawab ?>%</h4><p class="mb-0 small">Soal Terjawab</p></div>
                        <i class="fas fa-check-double fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rekap Per Kelas dan Mapel -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Rekap Hasil Ujian Per Kelas & Mata Pelajaran</h5>
                    <div class="print-only" style="display: none;"><small>Dicetak: <?= date('d/m/Y H:i') ?></small></div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($data_per_kelas_mapel) > 0): ?>
                    <?php foreach ($data_per_kelas_mapel as $key => $kelas_mapel): 
                        $kelas = $kelas_mapel['kelas'];
                        $mapel = $kelas_mapel['mapel'];
                        $kode_mapel = $kelas_mapel['kode_mapel'];
                        $kkm = $kelas_mapel['kkm'] ?? 70;
                        $judul_ujian = $kelas_mapel['judul_ujian'];
                        $ujian_id = $kelas_mapel['ujian_id'];
                        $ujian_selesai = $kelas_mapel['ujian_selesai'];
                        $siswa_list = $kelas_mapel['siswa'];
                        
                        $total_siswa = count($siswa_list);
                        $total_nilai = 0;
                        $nilai_tertinggi = 0;
                        $nilai_terendah = 100;
                        $lulus = 0;
                        $tidak_lulus = 0;
                        $total_essay_belum = 0;
                        $waktu_habis_section = 0;
                        
                        foreach ($siswa_list as $siswa) {
                            $nilai = $siswa['nilai'] ?? 0;
                            $total_nilai += $nilai;
                            if ($nilai > $nilai_tertinggi) $nilai_tertinggi = $nilai;
                            if ($nilai < $nilai_terendah && $nilai > 0) $nilai_terendah = $nilai;
                            if ($nilai >= $kkm) { $lulus++; } else { $tidak_lulus++; }
                            $total_essay_belum += $siswa['essay_belum_dikoreksi'] ?? 0;
                            if ($siswa['status'] == 'waktu_habis') $waktu_habis_section++;
                        }
                        
                        $rata_rata = $total_siswa > 0 ? round($total_nilai / $total_siswa, 1) : 0;
                        $persentase_lulus = $total_siswa > 0 ? round(($lulus / $total_siswa) * 100, 1) : 0;
                    ?>
                    
                    <div class="kelas-mapel-section mb-5" id="section-<?= $key ?>">
                        <div class="row mb-3">
                            <div class="col-md-12 text-center">
                                <h3 class="text-dark mb-1">KELAS <?= htmlspecialchars($kelas) ?></h3>
                                <h4 class="text-primary mb-1"><?= htmlspecialchars($mapel) ?></h4>
                                <h5 class="text-secondary mb-3"><?= htmlspecialchars($judul_ujian) ?></h5>
                                <div class="mb-2">
                                    <span class="badge bg-info fs-6">KKM: <?= $kkm ?></span>
                                    <?php if (strtotime($ujian_selesai) < time() && $waktu_habis_section > 0): ?>
                                    <span class="badge bg-danger ms-2">⚠️ Ujian telah berakhir - <?= $waktu_habis_section ?> siswa waktu habis</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4 g-2">
                            <div class="col-md-3 col-sm-6">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <div class="stat-number fw-bold fs-3 text-primary"><?= $total_siswa ?></div>
                                    <div class="stat-label text-muted small">Total Peserta</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <div class="stat-number fw-bold fs-3 text-success"><?= $lulus ?></div>
                                    <div class="stat-label text-muted small">Lulus (≥ <?= $kkm ?>)</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <div class="stat-number fw-bold fs-3 text-danger"><?= $tidak_lulus ?></div>
                                    <div class="stat-label text-muted small">Tidak Lulus</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <div class="stat-number fw-bold fs-3 text-warning"><?= $rata_rata ?></div>
                                    <div class="stat-label text-muted small">Rata-rata Nilai</div>
                                </div>
                            </div>
                            <?php if ($total_essay_belum > 0): ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <div class="stat-number fw-bold fs-3 text-danger"><?= $total_essay_belum ?></div>
                                    <div class="stat-label text-muted small">Essay Belum Dikoreksi</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="50" class="text-center">No</th>
                                        <th>NISN</th>
                                        <th>Nama Siswa</th>
                                        <th width="100" class="text-center">Kelas</th>
                                        <th width="120" class="text-center">Nilai</th>
                                        <th width="80" class="text-center">Grade</th>
                                        <th width="80" class="text-center">KKM</th>
                                        <th width="120" class="text-center">Status</th>
                                        <th width="100" class="text-center">Koreksi</th>
                                        <th width="100" class="text-center">Jawaban</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($siswa_list as $siswa): 
                                        $nilai = $siswa['nilai'] ?? 0;
                                        $grade = hitungGrade($nilai);
                                        $warna_grade = 'secondary';
                                        if ($nilai > 0) {
                                            if ($grade == 'A') $warna_grade = 'success';
                                            elseif ($grade == 'B') $warna_grade = 'info';
                                            elseif ($grade == 'C') $warna_grade = 'primary';
                                            elseif ($grade == 'D') $warna_grade = 'warning';
                                            elseif ($grade == 'E') $warna_grade = 'danger';
                                        }
                                        $kelulusan = cekKelulusan($nilai, $kkm);
                                        $nilai_formatted = formatNilai($nilai);
                                        $essay_belum = $siswa['essay_belum_dikoreksi'] ?? 0;
                                        $persen_jawaban = $siswa['total_soal'] > 0 ? round(($siswa['jumlah_dijawab'] / $siswa['total_soal']) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td class="text-center align-middle"><?= $no++ ?></td>
                                        <td class="align-middle"><?= htmlspecialchars($siswa['nisn']) ?></td>
                                        <td class="align-middle"><?= htmlspecialchars($siswa['nama_siswa']) ?></td>
                                        <td class="text-center align-middle"><?= htmlspecialchars($siswa['kelas']) ?></td>
                                        <td class="text-center align-middle">
                                            <span class="badge bg-<?= $warna_grade ?> fs-6 p-2" style="min-width: 80px;"><?= $nilai_formatted ?></span>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if ($nilai > 0): ?>
                                            <span class="badge bg-<?= $warna_grade ?> p-2"><?= $grade ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary p-2">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle"><span class="badge bg-info p-2"><?= $kkm ?></span></td>
                                        <td class="text-center align-middle">
                                            <span class="badge bg-<?= $kelulusan['status_class'] ?> p-2" style="min-width: 100px;">
                                                <i class="fas <?= $kelulusan['status'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                                                <?= $kelulusan['status_text'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if ($essay_belum > 0): ?>
                                            <a href="input_nilai.php?ujian_id=<?= $ujian_id ?>&siswa_id=<?= $siswa['siswa_id'] ?? '' ?>&kelas=<?= urlencode($kelas) ?>" 
                                               class="btn btn-warning btn-sm" title="<?= $essay_belum ?> soal essay belum dikoreksi">
                                                <i class="fas fa-edit me-1"></i><?= $essay_belum ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="badge bg-success">Selesai</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge bg-info"><?= $siswa['jumlah_dijawab'] ?>/<?= $siswa['total_soal'] ?> (<?= $persen_jawaban ?>%)</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-3 text-center">
                                                <h6>Nilai Tertinggi</h6>
                                                <div class="display-6 text-success fw-bold"><?= formatNilai($nilai_tertinggi) ?></div>
                                                <small>Grade: <?= hitungGrade($nilai_tertinggi) ?></small>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <h6>Nilai Terendah</h6>
                                                <div class="display-6 text-danger fw-bold"><?= formatNilai($nilai_terendah == 100 ? 0 : $nilai_terendah) ?></div>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <h6>Persentase Lulus</h6>
                                                <div class="display-6 text-primary fw-bold"><?= $persentase_lulus ?>%</div>
                                                <small><?= $lulus ?> dari <?= $total_siswa ?> siswa</small>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <h6>KKM</h6>
                                                <div class="display-6 text-warning fw-bold"><?= $kkm ?></div>
                                                <small>Nilai minimal untuk lulus</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="page-break"></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada data rekap hasil ujian</h5>
                        <p class="text-muted">Data rekap akan muncul setelah ada siswa yang menyelesaikan ujian.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.stat-item { transition: transform 0.2s; border: 1px solid #dee2e6; }
.stat-item:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.stat-number { font-size: 28px; font-weight: bold; margin-bottom: 5px; }
.stat-label { font-size: 12px; color: #6c757d; }
.gap-2 { gap: 0.5rem; }
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body { background-color: white !important; }
    .page-break { page-break-after: always; }
    .btn { display: none !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectKelas = document.getElementById('selectKelas');
    const selectMapel = document.getElementById('selectMapel');
    const selectUjian = document.getElementById('selectUjian');
    const btnCetakPDF = document.getElementById('btnCetakPDF');
    
    function updateLinks() {
        if (btnCetakPDF) {
            var url = 'hasil_ujian.php?print=pdf';
            if (selectKelas && selectKelas.value) url += '&kelas=' + encodeURIComponent(selectKelas.value);
            if (selectMapel && selectMapel.value) url += '&mapel=' + encodeURIComponent(selectMapel.value);
            if (selectUjian && selectUjian.value) url += '&ujian_id=' + encodeURIComponent(selectUjian.value);
            btnCetakPDF.href = url;
        }
    }
    
    if (selectKelas) selectKelas.addEventListener('change', updateLinks);
    if (selectMapel) selectMapel.addEventListener('change', updateLinks);
    if (selectUjian) selectUjian.addEventListener('change', updateLinks);
    updateLinks();
});
</script>

<?php include 'templates/footer.php'; ?>