<?php
// ajax_detail_soal.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit();
}

$soal_id = $_GET['id'] ?? 0;
$jenis_soal = $_GET['jenis'] ?? 'pilihan_ganda';

if (!$soal_id) {
    echo '<div class="alert alert-danger">ID soal tidak valid</div>';
    exit();
}

$stmt = $pdo->prepare("SELECT s.*, mp.nama_mapel, mp.kode_mapel 
                       FROM soal s 
                       LEFT JOIN mata_pelajaran mp ON s.mapel_id = mp.id 
                       WHERE s.id = ?");
$stmt->execute([$soal_id]);
$soal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$soal) {
    echo '<div class="alert alert-danger">Soal tidak ditemukan</div>';
    exit();
}

// Deteksi jenis soal
if ($jenis_soal == 'pilihan_ganda' && !empty($soal['jawaban_kompleks']) && $soal['jawaban_kompleks'] != '[]') {
    $jenis_soal = 'pilihan_ganda_kompleks';
}
if ($jenis_soal == 'pilihan_ganda' && trim($soal['opsi_a'] ?? '') == 'Benar' && trim($soal['opsi_b'] ?? '') == 'Salah') {
    $jenis_soal = 'benar_salah';
}
if ($jenis_soal == 'pilihan_ganda' && !empty($soal['pasangan_jodoh']) && $soal['pasangan_jodoh'] != '-') {
    $jenis_soal = 'menjodohkan';
}

$jawabanKompleksArray = [];
if (!empty($soal['jawaban_kompleks']) && $soal['jawaban_kompleks'] != '[]') {
    $jawabanKompleksArray = json_decode($soal['jawaban_kompleks'], true);
    if (!is_array($jawabanKompleksArray)) $jawabanKompleksArray = [];
}

// Nama guru
$stmt_guru = $pdo->prepare("SELECT nama FROM guru WHERE user_id = ?");
$stmt_guru->execute([$soal['created_by']]);
$guru = $stmt_guru->fetch();
$nama_guru = $guru ? $guru['nama'] : 'Admin';
?>

<style>
    .detail-card { border: 1px solid #e3e6f0; border-radius: 10px; margin-bottom: 20px; overflow: hidden; }
    .detail-card-header { background: #f8f9fc; padding: 12px 20px; border-bottom: 1px solid #e3e6f0; font-weight: bold; }
    .detail-card-body { padding: 20px; }
    .detail-label { font-weight: 600; color: #4e73df; font-size: 12px; text-transform: uppercase; margin-bottom: 5px; }
    .detail-value { margin-bottom: 15px; }
    .option-card { border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-bottom: 10px; }
    .option-card.correct { background: #d4edda; border-color: #28a745; }
    .badge-correct { background: #28a745; color: white; padding: 3px 8px; border-radius: 20px; font-size: 11px; }
</style>

<div class="detail-card">
    <div class="detail-card-header"><i class="fas fa-info-circle me-2"></i> INFORMASI SOAL</div>
    <div class="detail-card-body">
        <div class="row">
            <div class="col-md-4"><div class="detail-label">MATA PELAJARAN</div><div class="detail-value"><span class="badge bg-primary"><?= htmlspecialchars($soal['kode_mapel'] ?? '-') ?></span> <?= htmlspecialchars($soal['nama_mapel'] ?? '-') ?></div></div>
            <div class="col-md-3"><div class="detail-label">KELAS</div><div class="detail-value"><span class="badge bg-secondary"><?= htmlspecialchars($soal['kelas'] ?? '-') ?></span></div></div>
            <div class="col-md-3"><div class="detail-label">JENIS SOAL</div><div class="detail-value"><span class="badge bg-<?= $jenis_soal == 'pilihan_ganda' ? 'primary' : ($jenis_soal == 'pilihan_ganda_kompleks' ? 'purple' : ($jenis_soal == 'essay' ? 'info' : ($jenis_soal == 'menjodohkan' ? 'warning' : 'danger'))) ?>"><?= strtoupper(str_replace('_', ' ', $jenis_soal)) ?></span></div></div>
            <div class="col-md-2"><div class="detail-label">SKOR</div><div class="detail-value"><span class="badge bg-warning text-dark"><?= $soal['skor'] ?> POIN</span></div></div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6"><div class="detail-label">DIBUAT OLEH</div><div class="detail-value"><i class="fas fa-user me-1"></i> <?= htmlspecialchars($nama_guru) ?></div></div>
            <div class="col-md-6"><div class="detail-label">DIBUAT PADA</div><div class="detail-value"><i class="fas fa-calendar me-1"></i> <?= date('d-m-Y H:i', strtotime($soal['created_at'])) ?></div></div>
        </div>
    </div>
</div>

<div class="detail-card">
    <div class="detail-card-header"><i class="fas fa-question-circle me-2"></i> PERTANYAAN</div>
    <div class="detail-card-body">
        <div class="detail-value"><?= nl2br(htmlspecialchars($soal['pertanyaan'])) ?></div>
        <?php if (!empty($soal['gambar_soal']) && file_exists($soal['gambar_soal'])): ?>
            <div class="mt-3"><img src="<?= $soal['gambar_soal'] ?>" style="max-height: 200px;" class="img-thumbnail"></div>
        <?php endif; ?>
        <?php if (!empty($soal['video_soal'])): ?>
            <div class="mt-3"><a href="<?= $soal['video_soal'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-video me-1"></i> Buka Video</a></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($jenis_soal == 'pilihan_ganda'): ?>
<div class="detail-card">
    <div class="detail-card-header"><i class="fas fa-list-ul me-2"></i> OPSI JAWABAN (A-E)</div>
    <div class="detail-card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="option-card <?= $soal['jawaban_benar'] == 'a' ? 'correct' : '' ?>"><strong>A.</strong> <?= nl2br(htmlspecialchars($soal['opsi_a'])) ?> <?php if($soal['jawaban_benar']=='a'): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
                <div class="option-card <?= $soal['jawaban_benar'] == 'c' ? 'correct' : '' ?>"><strong>C.</strong> <?= nl2br(htmlspecialchars($soal['opsi_c'])) ?> <?php if($soal['jawaban_benar']=='c'): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
                <div class="option-card <?= $soal['jawaban_benar'] == 'e' ? 'correct' : '' ?>"><strong>E.</strong> <?= nl2br(htmlspecialchars($soal['opsi_e'] ?? '-')) ?> <?php if($soal['jawaban_benar']=='e'): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
            </div>
            <div class="col-md-6">
                <div class="option-card <?= $soal['jawaban_benar'] == 'b' ? 'correct' : '' ?>"><strong>B.</strong> <?= nl2br(htmlspecialchars($soal['opsi_b'])) ?> <?php if($soal['jawaban_benar']=='b'): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
                <div class="option-card <?= $soal['jawaban_benar'] == 'd' ? 'correct' : '' ?>"><strong>D.</strong> <?= nl2br(htmlspecialchars($soal['opsi_d'])) ?> <?php if($soal['jawaban_benar']=='d'): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($jenis_soal == 'pilihan_ganda_kompleks'): ?>
<div class="detail-card">
    <div class="detail-card-header"><i class="fas fa-check-double me-2"></i> OPSI JAWABAN (A-E)</div>
    <div class="detail-card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="option-card <?= in_array('a', $jawabanKompleksArray) ? 'correct' : '' ?>"><strong>A.</strong> <?= nl2br(htmlspecialchars($soal['opsi_a'])) ?> <?php if(in_array('a', $jawabanKompleksArray)): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
                <div class="option-card <?= in_array('c', $jawabanKompleksArray) ? 'correct' : '' ?>"><strong>C.</strong> <?= nl2br(htmlspecialchars($soal['opsi_c'])) ?> <?php if(in_array('c', $jawabanKompleksArray)): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
                <div class="option-card <?= in_array('e', $jawabanKompleksArray) ? 'correct' : '' ?>"><strong>E.</strong> <?= nl2br(htmlspecialchars($soal['opsi_e'] ?? '-')) ?> <?php if(in_array('e', $jawabanKompleksArray)): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
            </div>
            <div class="col-md-6">
                <div class="option-card <?= in_array('b', $jawabanKompleksArray) ? 'correct' : '' ?>"><strong>B.</strong> <?= nl2br(htmlspecialchars($soal['opsi_b'])) ?> <?php if(in_array('b', $jawabanKompleksArray)): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
                <div class="option-card <?= in_array('d', $jawabanKompleksArray) ? 'correct' : '' ?>"><strong>D.</strong> <?= nl2br(htmlspecialchars($soal['opsi_d'])) ?> <?php if(in_array('d', $jawabanKompleksArray)): ?><span class="badge-correct float-end"><i class="fas fa-check"></i> Benar</span><?php endif; ?></div>
            </div>
        </div>
        <div class="alert alert-info mt-3"><i class="fas fa-info-circle me-2"></i> Setiap jawaban benar mendapat <strong><?= $soal['skor_per_jawaban'] ?></strong> poin. Total <?= count($jawabanKompleksArray) ?> jawaban benar × <?= $soal['skor_per_jawaban'] ?> = <strong><?= $soal['skor'] ?> poin</strong>.</div>
    </div>
</div>

<?php elseif ($jenis_soal == 'essay'): ?>
<div class="detail-card">
    <div class="detail-card-header"><i class="fas fa-edit me-2"></i> PETUNJUK JAWABAN</div>
    <div class="detail-card-body">
        <div class="detail-value"><?= (!empty($soal['opsi_a']) && $soal['opsi_a'] != '-') ? nl2br(htmlspecialchars($soal['opsi_a'])) : '<em class="text-muted">Tidak ada petunjuk khusus</em>' ?></div>
        <div class="alert alert-warning mt-3"><i class="fas fa-exclamation-triangle me-2"></i> Soal essay akan dikoreksi secara manual oleh guru.</div>
    </div>
</div>

<?php elseif ($jenis_soal == 'menjodohkan'): ?>
<div class="detail-card">
    <div class="detail-card-header"><i class="fas fa-random me-2"></i> PASANGAN MENJODOHKAN</div>
    <div class="detail-card-body">
        <div class="row">
            <div class="col-md-6"><div class="detail-label">PERNYATAAN KIRI</div><div class="detail-value" style="white-space:pre-wrap;"><?= nl2br(htmlspecialchars($soal['opsi_a'])) ?></div></div>
            <div class="col-md-6"><div class="detail-label">PERNYATAAN KANAN</div><div class="detail-value" style="white-space:pre-wrap;"><?= nl2br(htmlspecialchars($soal['opsi_b'])) ?></div></div>
        </div>
        <div class="mt-3"><div class="detail-label">PASANGAN JAWABAN</div><div class="detail-value"><code><?= htmlspecialchars($soal['pasangan_jodoh']) ?></code></div></div>
    </div>
</div>

<?php elseif ($jenis_soal == 'benar_salah'): ?>
<div class="detail-card">
    <div class="detail-card-header"><i class="fas fa-check-circle me-2"></i> JAWABAN</div>
    <div class="detail-card-body">
        <div class="row">
            <div class="col-md-6"><div class="option-card <?= $soal['jawaban_benar'] == 'a' ? 'correct' : '' ?> text-center"><h4>✅ BENAR</h4><?php if($soal['jawaban_benar']=='a'): ?><span class="badge-correct">Jawaban Benar</span><?php endif; ?></div></div>
            <div class="col-md-6"><div class="option-card <?= $soal['jawaban_benar'] == 'b' ? 'correct' : '' ?> text-center"><h4>❌ SALAH</h4><?php if($soal['jawaban_benar']=='b'): ?><span class="badge-correct">Jawaban Benar</span><?php endif; ?></div></div>
        </div>
    </div>
</div>
<?php endif; ?>