<?php
// monitoring_ujian_guru.php - Monitoring ujian siswa dan kecurangan (DENGAN MULTI KELAS SUPPORT)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

$guru_id = $_SESSION['user_id'];
$nama_lengkap = $_SESSION['nama_lengkap'];
$is_guru_mapel = $_SESSION['is_guru_mapel'] ?? false;
$mapel_diampu = $_SESSION['mapel_diampu'] ?? [];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? [];

// Ambil filter
$filter_ujian = $_GET['ujian_id'] ?? '';
$filter_kelas = $_GET['kelas'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Ambil daftar ujian yang sedang berlangsung atau sudah selesai (DENGAN MULTI KELAS)
$sql = "
    SELECT u.*, mp.nama_mapel, mp.kode_mapel,
           COUNT(DISTINCT hu.id) as total_peserta,
           COUNT(DISTINCT CASE WHEN hu.status = 'sedang_ujian' THEN hu.id END) as sedang_ujian,
           COUNT(DISTINCT CASE WHEN hu.status = 'selesai' THEN hu.id END) as selesai,
           COUNT(DISTINCT CASE WHEN hu.status = 'waktu_habis' THEN hu.id END) as waktu_habis,
           (SELECT COUNT(*) FROM log_kecurangan lk WHERE lk.hasil_ujian_id IN (SELECT id FROM hasil_ujian WHERE ujian_id = u.id)) as total_kecurangan
    FROM ujian u
    JOIN mata_pelajaran mp ON u.mapel_id = mp.id
    LEFT JOIN hasil_ujian hu ON hu.ujian_id = u.id
    WHERE u.status = 'published'
";

$params = [];

if ($is_guru_mapel && !empty($mapel_diampu)) {
    $mapel_ids = array_column($mapel_diampu, 'id');
    $placeholders = str_repeat('?,', count($mapel_ids) - 1) . '?';
    $sql .= " AND u.mapel_id IN ($placeholders)";
    $params = array_merge($params, $mapel_ids);
}

if ($filter_ujian) {
    $sql .= " AND u.id = ?";
    $params[] = $filter_ujian;
}

$sql .= " GROUP BY u.id ORDER BY u.waktu_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ujian_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data siswa yang ujian (SEMUA SISWA DI KELAS TARGET - DENGAN MULTI KELAS)
$siswa_ujian = [];
$statistik_ujian = [];

if ($filter_ujian) {
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
    $update_expired->execute([$filter_ujian]);
    
    // Ambil data ujian untuk mengetahui kelas_target
    $stmt_ujian_target = $pdo->prepare("SELECT kelas_target FROM ujian WHERE id = ?");
    $stmt_ujian_target->execute([$filter_ujian]);
    $ujian_target_data = $stmt_ujian_target->fetch(PDO::FETCH_ASSOC);
    $kelas_target_ujian = $ujian_target_data['kelas_target'] ?? '';
    
    // PERBAIKAN: Ambil SEMUA SISWA dari kelas target ujian (multi kelas support)
    // Gunakan FIND_IN_SET untuk multi kelas
    $sql_siswa = "
        SELECT 
            s.id as siswa_id,
            s.nisn,
            s.nama as nama_siswa,
            s.kelas,
            s.jenis_kelamin,
            hu.id as hasil_ujian_id,
            hu.status,
            hu.waktu_mulai,
            hu.waktu_selesai,
            hu.waktu_mulai_pengerjaan,
            u.judul_ujian,
            u.durasi,
            u.waktu_mulai as ujian_mulai,
            u.waktu_selesai as ujian_selesai,
            u.kelas_target,
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
        LEFT JOIN mata_pelajaran mp ON u.mapel_id = mp.id
        WHERE u.id = ?
        AND (FIND_IN_SET(s.kelas, u.kelas_target) > 0 OR u.kelas_target = '' OR u.kelas_target IS NULL)
    ";
    
    $params_siswa = [$filter_ujian, $filter_ujian, $filter_ujian];
    
    // Filter kelas tambahan (jika ada)
    if ($filter_kelas) {
        $sql_siswa .= " AND s.kelas = ?";
        $params_siswa[] = $filter_kelas;
    } elseif ($is_guru_mapel && !empty($kelas_diampu)) {
        $kelas_values = array_column($kelas_diampu, 'kelas');
        $placeholders = str_repeat('?,', count($kelas_values) - 1) . '?';
        $sql_siswa .= " AND s.kelas IN ($placeholders)";
        $params_siswa = array_merge($params_siswa, $kelas_values);
    }
    
    // Filter status
    if ($filter_status == 'sedang') {
        $sql_siswa .= " AND hu.status = 'sedang_ujian'";
    } elseif ($filter_status == 'selesai') {
        $sql_siswa .= " AND hu.status = 'selesai'";
    } elseif ($filter_status == 'waktu_habis') {
        $sql_siswa .= " AND hu.status = 'waktu_habis'";
    } elseif ($filter_status == 'belum') {
        $sql_siswa .= " AND hu.id IS NULL";
    }
    
    $sql_siswa .= " ORDER BY s.kelas, s.nama";
    
    $stmt_siswa = $pdo->prepare($sql_siswa);
    $stmt_siswa->execute($params_siswa);
    $siswa_ujian = $stmt_siswa->fetchAll(PDO::FETCH_ASSOC);
    
    // Hitung statistik
    $total_siswa = count($siswa_ujian);
    $sedang_ujian = 0;
    $selesai = 0;
    $waktu_habis = 0;
    $belum_mulai = 0;
    $total_kecurangan = 0;
    $siswa_dengan_kecurangan = 0;
    
    foreach ($siswa_ujian as $siswa) {
        // Update status jika sisa waktu <= 0 dan status masih sedang_ujian
        if ($siswa['status'] == 'sedang_ujian' && $siswa['sisa_waktu'] <= 0) {
            $update_siswa = $pdo->prepare("UPDATE hasil_ujian SET status = 'waktu_habis', waktu_selesai = NOW() WHERE id = ?");
            $update_siswa->execute([$siswa['hasil_ujian_id']]);
            $siswa['status'] = 'waktu_habis';
        }
        
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
        if ($siswa['jumlah_kecurangan'] > 0) {
            $siswa_dengan_kecurangan++;
        }
    }
    
    $statistik_ujian = [
        'total_siswa' => $total_siswa,
        'sedang_ujian' => $sedang_ujian,
        'selesai' => $selesai,
        'waktu_habis' => $waktu_habis,
        'belum_mulai' => $belum_mulai,
        'total_kecurangan' => $total_kecurangan,
        'siswa_dengan_kecurangan' => $siswa_dengan_kecurangan
    ];
}

$title = "Monitoring Ujian Siswa";
include 'templates/header_guru.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="fas fa-desktop me-2"></i>Monitoring Ujian Siswa</h2>
    <div class="text-muted">
        <span class="badge bg-danger me-1"><i class="fas fa-exclamation-triangle"></i> Live Monitoring</span>
        Selamat datang, <span class="fw-bold"><?php echo htmlspecialchars($nama_lengkap); ?></span>
    </div>
</div>

<?php displayFlashMessage(); ?>

<!-- Filter Section -->
<div class="card stat-card mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Ujian</h5>
    </div>
    <div class="card-body">
        <form method="GET" id="filterForm" class="row g-3">
            <div class="col-md-4">
                <label class="form-label text-muted small">Pilih Ujian</label>
                <select name="ujian_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Pilih Ujian --</option>
                    <?php foreach ($ujian_list as $ujian): ?>
                    <option value="<?= $ujian['id'] ?>" <?= $filter_ujian == $ujian['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ujian['judul_ujian']) ?> 
                        (<?= date('d/m/Y H:i', strtotime($ujian['waktu_mulai'])) ?>)
                        - Kelas <?= htmlspecialchars($ujian['kelas_target']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filter_ujian): ?>
            <div class="col-md-3">
                <label class="form-label text-muted small">Kelas</label>
                <select name="kelas" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    <?php
                    $kelas_options = $is_guru_mapel && !empty($kelas_diampu) ? 
                        array_column($kelas_diampu, 'kelas') : 
                        $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($kelas_options as $kelas):
                    ?>
                    <option value="<?= htmlspecialchars($kelas) ?>" <?= $filter_kelas == $kelas ? 'selected' : '' ?>>
                        Kelas <?= htmlspecialchars($kelas) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small">Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="sedang" <?= $filter_status == 'sedang' ? 'selected' : '' ?>>Sedang Ujian</option>
                    <option value="selesai" <?= $filter_status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="waktu_habis" <?= $filter_status == 'waktu_habis' ? 'selected' : '' ?>>Waktu Habis</option>
                    <option value="belum" <?= $filter_status == 'belum' ? 'selected' : '' ?>>Belum Memulai</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="monitoring_ujian_guru.php" class="btn btn-secondary w-100">
                    <i class="fas fa-refresh me-2"></i>Reset
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($filter_ujian): ?>
    <?php 
    $selected_ujian = null;
    foreach ($ujian_list as $u) {
        if ($u['id'] == $filter_ujian) {
            $selected_ujian = $u;
            break;
        }
    }
    if ($selected_ujian): 
    ?>
    <!-- Informasi Ujian -->
    <div class="card stat-card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Ujian</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="detail-label">Judul Ujian</div>
                    <div class="fw-bold"><?= htmlspecialchars($selected_ujian['judul_ujian']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Mata Pelajaran</div>
                    <div><?= htmlspecialchars($selected_ujian['nama_mapel']) ?> (<?= htmlspecialchars($selected_ujian['kode_mapel']) ?>)</div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Waktu Ujian</div>
                    <div><?= date('d/m/Y H:i', strtotime($selected_ujian['waktu_mulai'])) ?> - <?= date('H:i', strtotime($selected_ujian['waktu_selesai'])) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="detail-label">Durasi</div>
                    <div><?= $selected_ujian['durasi'] ?> menit</div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <div class="detail-label">Kelas Target</div>
                    <div>
                        <?php 
                        $kelas_target_array = explode(',', $selected_ujian['kelas_target']);
                        foreach ($kelas_target_array as $kt):
                        ?>
                        <span class="badge bg-primary me-1">Kelas <?= htmlspecialchars(trim($kt)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistik Monitoring -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card border-primary">
                <div class="card-body text-center py-3">
                    <div class="h3 fw-bold text-primary"><?= $statistik_ujian['total_siswa'] ?></div>
                    <div class="text-muted small">Total Peserta</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-warning">
                <div class="card-body text-center py-3">
                    <div class="h3 fw-bold text-warning"><?= $statistik_ujian['sedang_ujian'] ?></div>
                    <div class="text-muted small">Sedang Ujian</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-success">
                <div class="card-body text-center py-3">
                    <div class="h3 fw-bold text-success"><?= $statistik_ujian['selesai'] ?></div>
                    <div class="text-muted small">Selesai</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-danger">
                <div class="card-body text-center py-3">
                    <div class="h3 fw-bold text-danger"><?= $statistik_ujian['waktu_habis'] ?></div>
                    <div class="text-muted small">Waktu Habis</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-secondary">
                <div class="card-body text-center py-3">
                    <div class="h3 fw-bold text-secondary"><?= $statistik_ujian['belum_mulai'] ?></div>
                    <div class="text-muted small">Belum Memulai</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-info">
                <div class="card-body text-center py-3">
                    <div class="h3 fw-bold text-info"><?= $statistik_ujian['total_kecurangan'] ?></div>
                    <div class="text-muted small">Total Kecurangan</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Daftar Siswa -->
    <div class="card stat-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Monitoring Ujian Siswa</h5>
            <div>
                <button class="btn btn-sm btn-primary me-1" onclick="refreshData()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-sm btn-info" onclick="exportData()">
                    <i class="fas fa-download me-1"></i>Export
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($siswa_ujian) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="monitoringTable">
                        <thead class="table-light">
                            <tr>
                                <th width="50">No</th>
                                <th>NISN</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Status</th>
                                <th>Waktu Mulai</th>
                                <th>Sisa Waktu</th>
                                <th>Kecurangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringTableBody">
                            <?php foreach ($siswa_ujian as $index => $siswa): 
                                $status = $siswa['status'];
                                $sisa_waktu = $siswa['sisa_waktu'];
                                $waktu_selesai = $siswa['waktu_selesai'];
                                $waktu_ujian_selesai = $siswa['ujian_selesai'];
                                
                                // CEK APAKAH WAKTU UJIAN SUDAH HABIS
                                $waktu_sekarang = new DateTime();
                                $waktu_selesai_dt = new DateTime($waktu_ujian_selesai);
                                
                                if ($status == 'sedang_ujian' && $waktu_sekarang > $waktu_selesai_dt) {
                                    $status = 'waktu_habis';
                                    // Update status di database
                                    $update_status_db = $pdo->prepare("UPDATE hasil_ujian SET status = 'waktu_habis', waktu_selesai = NOW() WHERE id = ?");
                                    $update_status_db->execute([$siswa['hasil_ujian_id']]);
                                }
                                
                                // CEK APAKAH SISWA SEDANG DALAM MASA PENALTI
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
                                
                                // Tentukan status sebenarnya
                                $status_aktual = $status;
                                if ($status == 'sedang_ujian' && $penalti_aktif) {
                                    $status_aktual = 'ditunda';
                                } elseif ($status == null && $siswa['hasil_ujian_id'] == null) {
                                    $status_aktual = 'belum_mulai';
                                }
                                
                                // Status badge dan teks
                                if ($status_aktual == 'sedang_ujian') {
                                    $status_badge = 'bg-warning';
                                    $status_text = 'Sedang Ujian';
                                    $status_icon = '<i class="fas fa-play-circle me-1"></i>';
                                } elseif ($status_aktual == 'selesai') {
                                    $status_badge = 'bg-success';
                                    $status_text = 'Selesai';
                                    $status_icon = '<i class="fas fa-check-circle me-1"></i>';
                                } elseif ($status_aktual == 'waktu_habis') {
                                    $status_badge = 'bg-danger';
                                    $status_text = 'Waktu Habis';
                                    $status_icon = '<i class="fas fa-hourglass-end me-1"></i>';
                                } elseif ($status_aktual == 'ditunda') {
                                    $status_badge = 'bg-secondary';
                                    $status_text = 'Ditunda (Penalti)';
                                    $status_icon = '<i class="fas fa-pause-circle me-1"></i>';
                                } else {
                                    $status_badge = 'bg-secondary';
                                    $status_text = 'Belum Memulai';
                                    $status_icon = '<i class="fas fa-clock me-1"></i>';
                                }
                                
                                // Format waktu mulai
                                $waktu_mulai_text = '-';
                                if (!empty($siswa['waktu_mulai_pengerjaan']) && $siswa['waktu_mulai_pengerjaan'] != '0000-00-00 00:00:00') {
                                    $waktu_mulai_text = date('H:i:s', strtotime($siswa['waktu_mulai_pengerjaan']));
                                } elseif (!empty($siswa['waktu_mulai']) && $siswa['waktu_mulai'] != '0000-00-00 00:00:00') {
                                    $waktu_mulai_text = date('H:i:s', strtotime($siswa['waktu_mulai']));
                                }
                                
                                // Sisa waktu
                                $sisa_waktu_text = '-';
                                $sisa_waktu_class = '';
                                
                                if ($status_aktual == 'sedang_ujian') {
                                    if ($sisa_waktu > 0) {
                                        $sisa_waktu_text = $sisa_waktu . ' menit';
                                        if ($sisa_waktu <= 5) {
                                            $sisa_waktu_class = 'text-danger fw-bold';
                                        } elseif ($sisa_waktu <= 10) {
                                            $sisa_waktu_class = 'text-warning fw-bold';
                                        }
                                    } else {
                                        $sisa_waktu_text = 'Waktu Habis';
                                        $sisa_waktu_class = 'text-danger fw-bold';
                                    }
                                } elseif ($status_aktual == 'selesai') {
                                    $sisa_waktu_text = 'Selesai';
                                    $sisa_waktu_class = 'text-success';
                                } elseif ($status_aktual == 'waktu_habis') {
                                    $sisa_waktu_text = 'Waktu Habis';
                                    $sisa_waktu_class = 'text-danger';
                                } elseif ($status_aktual == 'ditunda') {
                                    $sisa_waktu_text = 'Penalti Aktif';
                                    $sisa_waktu_class = 'text-secondary';
                                } elseif ($status_aktual == 'belum_mulai') {
                                    $sisa_waktu_text = 'Belum Mulai';
                                    $sisa_waktu_class = 'text-muted';
                                }
                                
                                $jumlah_kecurangan = $siswa['jumlah_kecurangan'] ?? 0;
                                
                                // Warna baris berdasarkan jumlah kecurangan
                                $row_class = '';
                                if ($jumlah_kecurangan >= 10) {
                                    $row_class = 'table-danger';
                                } elseif ($jumlah_kecurangan >= 5) {
                                    $row_class = 'table-warning';
                                } elseif ($jumlah_kecurangan >= 1) {
                                    $row_class = 'table-info';
                                }
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($siswa['nisn']) ?></td>
                                <td><strong><?= htmlspecialchars($siswa['nama_siswa']) ?></strong></td>
                                <td><?= htmlspecialchars($siswa['kelas']) ?></td>
                                <td><span class="badge <?= $status_badge ?>"><?= $status_icon ?> <?= $status_text ?></span></td>
                                <td><?= $waktu_mulai_text ?></td>
                                <td class="<?= $sisa_waktu_class ?>"><?= $sisa_waktu_text ?></td>
                                <td>
                                    <?php if ($jumlah_kecurangan > 0): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i><?= $jumlah_kecurangan ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>0
                                        </span>
                                    <?php endif; ?>
                                 </small></td>
                                <td class="text-nowrap">
                                    <?php if ($siswa['hasil_ujian_id']): ?>
                                    <button class="btn btn-sm btn-outline-info me-1" 
                                            onclick="detailSiswa(<?= $siswa['hasil_ujian_id'] ?>, '<?= htmlspecialchars($siswa['nama_siswa']) ?>', <?= $filter_ujian ?>)"
                                            title="Detail Siswa">
                                        <i class="fas fa-user"></i>
                                    </button>
                                    <?php if ($jumlah_kecurangan > 0): ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="detailKecurangan(<?= $siswa['hasil_ujian_id'] ?>, '<?= htmlspecialchars($siswa['nama_siswa']) ?>', <?= $filter_ujian ?>)"
                                            title="Lihat Kecurangan">
                                        <i class="fas fa-shield-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                 </small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                    <h6 class="text-muted">Tidak ada data siswa</h6>
                    <p class="text-muted small">Tidak ditemukan siswa dengan filter yang dipilih.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php else: ?>
    <div class="alert alert-warning">Ujian tidak ditemukan</div>
    <?php endif; ?>
<?php else: ?>
<!-- Pilih Ujian Dulu -->
<div class="card stat-card">
    <div class="card-body text-center py-5">
        <i class="fas fa-desktop fa-4x text-muted mb-3"></i>
        <h6 class="text-muted">Pilih Ujian Terlebih Dahulu</h6>
        <p class="text-muted small">Silakan pilih ujian dari filter di atas untuk memulai monitoring.</p>
    </div>
</div>
<?php endif; ?>

<!-- Modal Detail Siswa -->
<div class="modal fade" id="detailSiswaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-user-graduate me-2"></i>Detail Siswa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailSiswaContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Kecurangan -->
<div class="modal fade" id="detailKecuranganModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i>Detail Kecurangan Siswa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailKecuranganContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
.detail-label {
    font-size: 11px;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 3px;
}
</style>

<script>
// Fungsi refresh data
function refreshData() {
    location.reload();
}

// Fungsi export data
function exportData() {
    window.location.href = 'export_monitoring.php?ujian_id=<?= $filter_ujian ?>&kelas=<?= $filter_kelas ?>';
}

// Detail Siswa
function detailSiswa(hasilUjianId, namaSiswa, ujianId) {
    document.getElementById('detailSiswaContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Memuat data ${namaSiswa}...</p>
        </div>
    `;
    
    fetch(`ajax_get_detail_hasil.php?ujian_id=${ujianId}&hasil_ujian_id=${hasilUjianId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderDetailSiswa(data, namaSiswa);
            } else {
                document.getElementById('detailSiswaContent').innerHTML = '<div class="alert alert-danger">Gagal memuat data: ' + (data.message || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('detailSiswaContent').innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        });
    
    new bootstrap.Modal(document.getElementById('detailSiswaModal')).show();
}

function renderDetailSiswa(data, namaSiswa) {
    const hasil = data.hasil;
    const soalList = data.soal_list || [];
    
    let html = `
        <div class="mb-4">
            <h6 class="text-primary">${namaSiswa}</h6>
        </div>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center py-2">
                        <div class="h5 fw-bold text-primary">${parseFloat(hasil.nilai).toFixed(1)}</div>
                        <div class="text-muted small">Nilai</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center py-2">
                        <div class="h5 fw-bold text-success">${hasil.total_soal_dijawab || 0}</div>
                        <div class="text-muted small">Soal Dijawab</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center py-2">
                        <div class="h5 fw-bold text-info">${hasil.waktu_mulai ? new Date(hasil.waktu_mulai).toLocaleTimeString() : '-'}</div>
                        <div class="text-muted small">Mulai</div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    if (soalList.length > 0) {
        html += `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th class="text-muted small">No</th>
                            <th class="text-muted small">Jawaban</th>
                            <th class="text-muted small">Kunci</th>
                            <th class="text-muted small">Status</th>
                            <th class="text-muted small">Skor</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        for (let i = 0; i < soalList.length; i++) {
            const soal = soalList[i];
            const isCorrect = soal.jawaban_siswa === soal.jawaban_benar;
            const statusText = isCorrect ? 'Benar' : 'Salah';
            const statusClass = isCorrect ? 'bg-success' : 'bg-danger';
            
            html += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${soal.jawaban_siswa || '-'}</td>
                    <td>${soal.jawaban_benar || '-'}</td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                    <td><strong>${isCorrect ? soal.skor : 0}</strong></td>
                </tr>
            `;
        }
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    document.getElementById('detailSiswaContent').innerHTML = html;
}

// Detail Kecurangan
function detailKecurangan(hasilUjianId, namaSiswa, ujianId) {
    if (!ujianId) {
        alert('Ujian ID tidak ditemukan');
        return;
    }
    
    document.getElementById('detailKecuranganContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Memuat data kecurangan ${namaSiswa}...</p>
        </div>
    `;
    
    fetch(`ajax_get_kecurangan.php?hasil_ujian_id=${hasilUjianId}&ujian_id=${ujianId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderDetailKecurangan(data, namaSiswa);
            } else {
                document.getElementById('detailKecuranganContent').innerHTML = '<div class="alert alert-info">' + (data.message || 'Tidak ada data kecurangan untuk ujian ini') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('detailKecuranganContent').innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        });
    
    new bootstrap.Modal(document.getElementById('detailKecuranganModal')).show();
}

function renderDetailKecurangan(data, namaSiswa) {
    const logs = data.logs || [];
    
    if (logs.length === 0) {
        document.getElementById('detailKecuranganContent').innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>${namaSiswa}</strong> tidak melakukan kecurangan selama ujian.
            </div>
        `;
        return;
    }
    
    // Statistik kecurangan
    const jenisKecurangan = {};
    for (let i = 0; i < logs.length; i++) {
        const jenis = logs[i].jenis_pelanggaran;
        if (jenisKecurangan[jenis]) {
            jenisKecurangan[jenis]++;
        } else {
            jenisKecurangan[jenis] = 1;
        }
    }
    
    let html = `
        <div class="mb-4">
            <h6 class="text-danger">${namaSiswa}</h6>
            <p class="text-muted small">Total kecurangan: <strong class="text-danger">${logs.length}</strong> kali</p>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Statistik Kecurangan</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
    `;
    
    for (const jenis in jenisKecurangan) {
        let jenis_label = '';
        switch(jenis) {
            case 'keluar_tab': jenis_label = 'Keluar Tab'; break;
            case 'keluar_focus': jenis_label = 'Keluar Fokus'; break;
            case 'attempt_leave': jenis_label = 'Mencoba Keluar'; break;
            case 'penalty_activated': jenis_label = 'Penalti Diaktifkan'; break;
            default: jenis_label = jenis;
        }
        const jumlah = jenisKecurangan[jenis];
        html += `
            <div class="col-md-6 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span>${jenis_label}</span>
                    <span class="badge bg-danger">${jumlah} kali</span>
                </div>
                <div class="progress mt-1" style="height: 5px;">
                    <div class="progress-bar bg-danger" style="width: ${(jumlah / logs.length) * 100}%"></div>
                </div>
            </div>
        `;
    }
    
    html += `
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-sm">
                <thead class="table-light">
                    <tr>
                        <th class="text-muted small">Waktu</th>
                        <th class="text-muted small">Jenis Pelanggaran</th>
                        <th class="text-muted small">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    for (let i = 0; i < logs.length; i++) {
        const log = logs[i];
        let jenis_label = '';
        switch(log.jenis_pelanggaran) {
            case 'keluar_tab': jenis_label = '🚪 Keluar Tab'; break;
            case 'keluar_focus': jenis_label = '👁️ Keluar Fokus'; break;
            case 'attempt_leave': jenis_label = '🏃 Mencoba Keluar'; break;
            case 'penalty_activated': jenis_label = '⚠️ Penalti Diaktifkan'; break;
            default: jenis_label = log.jenis_pelanggaran;
        }
        
        html += `
            <tr>
                <td>${new Date(log.waktu_pelanggaran).toLocaleString()}</small></td>
                <td><strong class="text-danger">${jenis_label}</strong></td>
                <td>${log.keterangan || '-'}</small></td>
            </tr>
        `;
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('detailKecuranganContent').innerHTML = html;
}

// Auto refresh setiap 30 detik
let refreshInterval;
if (window.location.pathname.includes('monitoring_ujian_guru.php') && <?= $filter_ujian ? 'true' : 'false' ?>) {
    refreshInterval = setInterval(function() {
        refreshData();
    }, 30000);
}
</script>

<?php include 'templates/footer.php'; ?>