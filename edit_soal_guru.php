<?php
// edit_soal_guru.php - Halaman edit soal oleh guru (dengan PG Kompleks)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

$guru_user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt->execute([$guru_user_id]);
$guru_data = $stmt->fetch(PDO::FETCH_ASSOC);
$guru_table_id = $guru_data['id'] ?? 0;

if (!$guru_table_id) die('Data guru tidak ditemukan');

$soal_id = $_GET['id'] ?? 0;
if (!$soal_id) die('ID soal tidak valid');

$stmt = $pdo->prepare("SELECT * FROM soal WHERE id = ?");
$stmt->execute([$soal_id]);
$soal = $stmt->fetch();

if (!$soal) die('Soal tidak ditemukan');
if ($soal['created_by'] != $guru_table_id) die('Anda tidak memiliki akses');

$mapel_list = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);

// Deteksi jenis soal
$jenis_soal = $soal['jenis_soal'];
if ($jenis_soal == 'pilihan_ganda') {
    if (!empty($soal['jawaban_kompleks']) && $soal['jawaban_kompleks'] != '[]') {
        $jenis_soal = 'pilihan_ganda_kompleks';
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mapel_id = $_POST['mapel_id'];
    $kelas = $_POST['kelas'];
    $pertanyaan = $_POST['pertanyaan'];
    $skor = $_POST['skor'];
    $video_soal = $_POST['video_soal'] ?? '';
    $jenis_soal_post = $_POST['jenis_soal'] ?? $jenis_soal;
    
    $opsi_a = $_POST['opsi_a'] ?? '';
    $opsi_b = $_POST['opsi_b'] ?? '';
    $opsi_c = $_POST['opsi_c'] ?? '';
    $opsi_d = $_POST['opsi_d'] ?? '';
    $opsi_e = $_POST['opsi_e'] ?? '';
    $jawaban_benar = $_POST['jawaban_benar'] ?? '';
    $pasangan_jodoh = $_POST['pasangan_jodoh'] ?? '';
    $jawaban_kompleks = $_POST['jawaban_kompleks'] ?? null;
    $skor_per_jawaban = $_POST['skor_per_jawaban'] ?? 0;
    
    // Hitung ulang skor untuk PG Kompleks
    if ($jenis_soal_post == 'pilihan_ganda_kompleks' && $jawaban_kompleks) {
        $jawabanArray = json_decode($jawaban_kompleks, true);
        $jumlahJawabanBenar = is_array($jawabanArray) ? count($jawabanArray) : 0;
        $skor = $skor_per_jawaban * $jumlahJawabanBenar;
    }
    
    // Handle gambar
    $gambar_soal = $soal['gambar_soal'];
    if (isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] == '1') {
        if ($gambar_soal && file_exists($gambar_soal)) unlink($gambar_soal);
        $gambar_soal = null;
    }
    if (isset($_FILES['gambar_soal']) && $_FILES['gambar_soal']['error'] == 0) {
        $upload_dir = 'assets/uploads/soal/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['gambar_soal']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['gambar_soal']['tmp_name'], $target_file)) {
            if ($gambar_soal && file_exists($gambar_soal)) unlink($gambar_soal);
            $gambar_soal = $target_file;
        }
    }
    
    $sql = "UPDATE soal SET 
            mapel_id = ?, kelas = ?, pertanyaan = ?, gambar_soal = ?, video_soal = ?,
            opsi_a = ?, opsi_b = ?, opsi_c = ?, opsi_d = ?, opsi_e = ?,
            jawaban_benar = ?, jawaban_kompleks = ?, skor_per_jawaban = ?,
            skor = ?, pasangan_jodoh = ?, jenis_soal = ?
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $mapel_id, $kelas, $pertanyaan, $gambar_soal, $video_soal,
        $opsi_a ?: '-', $opsi_b ?: '-', $opsi_c ?: '-', $opsi_d ?: '-', $opsi_e ?: '-',
        $jawaban_benar ?: 'a', $jawaban_kompleks, $skor_per_jawaban,
        $skor, $pasangan_jodoh, $jenis_soal_post, $soal_id
    ]);
    
    if ($result) {
        echo "<script>alert('Soal berhasil diperbarui!'); window.location.href='kelola_soal_guru.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal menyimpan!');</script>";
    }
}

$title = "Edit Soal";
include 'templates/header_guru.php';
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit me-2"></i>Edit Soal</h2>
        <a href="kelola_soal_guru.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="jenis_soal" value="<?= $jenis_soal ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Mata Pelajaran <span class="text-danger">*</span></label>
                            <select name="mapel_id" class="form-control" required>
                                <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?= $mapel['id'] ?>" <?= $soal['mapel_id'] == $mapel['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mapel['nama_mapel']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Kelas <span class="text-danger">*</span></label>
                            <select name="kelas" class="form-control" required>
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= $kelas ?>" <?= $soal['kelas'] == $kelas ? 'selected' : '' ?>><?= htmlspecialchars($kelas) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Skor <span class="text-danger">*</span></label>
                            <input type="number" name="skor" class="form-control" id="total_skor" min="1" max="100" value="<?= $soal['skor'] ?>" required>
                            <small class="text-muted">Untuk PG Kompleks akan dihitung otomatis</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Video Soal</label>
                            <input type="text" name="video_soal" class="form-control" value="<?= htmlspecialchars($soal['video_soal'] ?? '') ?>" placeholder="URL YouTube">
                        </div>
                        <div class="mb-3">
                            <label>Gambar Soal</label>
                            <?php if ($soal['gambar_soal'] && file_exists($soal['gambar_soal'])): ?>
                            <div class="mb-2">
                                <img src="<?= $soal['gambar_soal'] ?>" style="max-height: 80px;" class="img-thumbnail">
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="hapus_gambar" value="1" id="hapusGambar">
                                    <label for="hapusGambar">Hapus gambar</label>
                                </div>
                            </div>
                            <?php endif; ?>
                            <input type="file" name="gambar_soal" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label>Pertanyaan <span class="text-danger">*</span></label>
                    <textarea name="pertanyaan" class="form-control" rows="4" required><?= htmlspecialchars($soal['pertanyaan']) ?></textarea>
                </div>
                
                <!-- Pilihan Ganda -->
                <?php if ($jenis_soal == 'pilihan_ganda'): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light"><h5 class="mb-0">Opsi Jawaban (A-E)</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3"><label>A <span class="text-danger">*</span></label><textarea name="opsi_a" class="form-control" rows="2" required><?= htmlspecialchars(($soal['opsi_a'] != '-' ? $soal['opsi_a'] : '')) ?></textarea></div>
                                <div class="mb-3"><label>C <span class="text-danger">*</span></label><textarea name="opsi_c" class="form-control" rows="2" required><?= htmlspecialchars(($soal['opsi_c'] != '-' ? $soal['opsi_c'] : '')) ?></textarea></div>
                                <div class="mb-3"><label>E <span class="text-danger">*</span></label><textarea name="opsi_e" class="form-control" rows="2" required><?= htmlspecialchars(($soal['opsi_e'] != '-' ? ($soal['opsi_e'] ?? '') : '')) ?></textarea></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3"><label>B <span class="text-danger">*</span></label><textarea name="opsi_b" class="form-control" rows="2" required><?= htmlspecialchars(($soal['opsi_b'] != '-' ? $soal['opsi_b'] : '')) ?></textarea></div>
                                <div class="mb-3"><label>D <span class="text-danger">*</span></label><textarea name="opsi_d" class="form-control" rows="2" required><?= htmlspecialchars(($soal['opsi_d'] != '-' ? $soal['opsi_d'] : '')) ?></textarea></div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label>Jawaban Benar <span class="text-danger">*</span></label><br>
                            <div class="form-check form-check-inline"><input type="radio" name="jawaban_benar" value="a" <?= $soal['jawaban_benar'] == 'a' ? 'checked' : '' ?>> A</div>
                            <div class="form-check form-check-inline"><input type="radio" name="jawaban_benar" value="b" <?= $soal['jawaban_benar'] == 'b' ? 'checked' : '' ?>> B</div>
                            <div class="form-check form-check-inline"><input type="radio" name="jawaban_benar" value="c" <?= $soal['jawaban_benar'] == 'c' ? 'checked' : '' ?>> C</div>
                            <div class="form-check form-check-inline"><input type="radio" name="jawaban_benar" value="d" <?= $soal['jawaban_benar'] == 'd' ? 'checked' : '' ?>> D</div>
                            <div class="form-check form-check-inline"><input type="radio" name="jawaban_benar" value="e" <?= $soal['jawaban_benar'] == 'e' ? 'checked' : '' ?>> E</div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="jawaban_kompleks" value=""><input type="hidden" name="skor_per_jawaban" value="0"><input type="hidden" name="pasangan_jodoh" value="">
                
                <!-- Pilihan Ganda Kompleks -->
                <?php elseif ($jenis_soal == 'pilihan_ganda_kompleks'): 
                    $jawaban_kompleks_arr = json_decode($soal['jawaban_kompleks'] ?? '[]', true);
                    if (!is_array($jawaban_kompleks_arr)) $jawaban_kompleks_arr = [];
                ?>
                <div class="card mb-3">
                    <div class="card-header bg-light"><h5 class="mb-0">Opsi Jawaban (Centang yang benar)</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3"><label>A</label><textarea name="opsi_a" class="form-control" rows="2"><?= htmlspecialchars(($soal['opsi_a'] != '-' ? $soal['opsi_a'] : '')) ?></textarea></div>
                                <div class="mb-3"><label>C</label><textarea name="opsi_c" class="form-control" rows="2"><?= htmlspecialchars(($soal['opsi_c'] != '-' ? $soal['opsi_c'] : '')) ?></textarea></div>
                                <div class="mb-3"><label>E</label><textarea name="opsi_e" class="form-control" rows="2"><?= htmlspecialchars(($soal['opsi_e'] != '-' ? ($soal['opsi_e'] ?? '') : '')) ?></textarea></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3"><label>B</label><textarea name="opsi_b" class="form-control" rows="2"><?= htmlspecialchars(($soal['opsi_b'] != '-' ? $soal['opsi_b'] : '')) ?></textarea></div>
                                <div class="mb-3"><label>D</label><textarea name="opsi_d" class="form-control" rows="2"><?= htmlspecialchars(($soal['opsi_d'] != '-' ? $soal['opsi_d'] : '')) ?></textarea></div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label>Jawaban Benar (centang lebih dari satu)</label>
                            <div class="border rounded p-3 mt-1">
                                <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="a" <?= in_array('a', $jawaban_kompleks_arr) ? 'checked' : '' ?>> A</label>
                                <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="b" <?= in_array('b', $jawaban_kompleks_arr) ? 'checked' : '' ?>> B</label>
                                <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="c" <?= in_array('c', $jawaban_kompleks_arr) ? 'checked' : '' ?>> C</label>
                                <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="d" <?= in_array('d', $jawaban_kompleks_arr) ? 'checked' : '' ?>> D</label>
                                <label class="me-3"><input type="checkbox" name="jawaban_kompleks_checkbox[]" value="e" <?= in_array('e', $jawaban_kompleks_arr) ? 'checked' : '' ?>> E</label>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label>Skor per Jawaban Benar</label>
                            <input type="number" name="skor_per_jawaban" id="skor_per_jawaban" class="form-control" value="<?= $soal['skor_per_jawaban'] ?? 5 ?>" min="1">
                            <small class="text-muted">Total skor akan dihitung otomatis: skor per jawaban × jumlah jawaban benar</small>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="jawaban_benar" value="a">
                <input type="hidden" name="jawaban_kompleks" id="jawaban_kompleks_hidden" value="">
                <input type="hidden" name="pasangan_jodoh" value="">
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const skorInput = document.getElementById('skor_per_jawaban');
                    const totalSkor = document.getElementById('total_skor');
                    const checkboxes = document.querySelectorAll('input[name="jawaban_kompleks_checkbox[]"]');
                    function updateTotal() {
                        const checked = document.querySelectorAll('input[name="jawaban_kompleks_checkbox[]"]:checked').length;
                        const skor = parseInt(skorInput.value) || 0;
                        totalSkor.value = skor * checked;
                    }
                    skorInput.addEventListener('input', updateTotal);
                    checkboxes.forEach(cb => cb.addEventListener('change', updateTotal));
                    updateTotal();
                    
                    document.querySelector('form').addEventListener('submit', function() {
                        const checked = document.querySelectorAll('input[name="jawaban_kompleks_checkbox[]"]:checked');
                        const jawabanArray = Array.from(checked).map(cb => cb.value);
                        document.getElementById('jawaban_kompleks_hidden').value = JSON.stringify(jawabanArray);
                    });
                });
                </script>
                
                <!-- Essay -->
                <?php elseif ($jenis_soal == 'essay'): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light"><h5 class="mb-0">Soal Essay</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label>Petunjuk Jawaban (Opsional)</label><textarea name="opsi_a" class="form-control" rows="3"><?= htmlspecialchars(($soal['opsi_a'] != '-' ? $soal['opsi_a'] : '')) ?></textarea></div>
                        <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Soal essay akan dikoreksi manual oleh guru.</div>
                    </div>
                </div>
                <input type="hidden" name="opsi_b" value="-"><input type="hidden" name="opsi_c" value="-"><input type="hidden" name="opsi_d" value="-"><input type="hidden" name="opsi_e" value="-">
                <input type="hidden" name="jawaban_benar" value="a"><input type="hidden" name="jawaban_kompleks" value=""><input type="hidden" name="skor_per_jawaban" value="0"><input type="hidden" name="pasangan_jodoh" value="">
                
                <!-- Benar/Salah -->
                <?php elseif ($jenis_soal == 'benar_salah'): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light"><h5 class="mb-0">Soal Benar/Salah</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label>Jawaban Benar <span class="text-danger">*</span></label>
                            <select name="jawaban_benar" class="form-control" required>
                                <option value="a" <?= $soal['jawaban_benar'] == 'a' ? 'selected' : '' ?>>Benar</option>
                                <option value="b" <?= $soal['jawaban_benar'] == 'b' ? 'selected' : '' ?>>Salah</option>
                            </select>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="opsi_a" value="Benar"><input type="hidden" name="opsi_b" value="Salah">
                <input type="hidden" name="opsi_c" value="-"><input type="hidden" name="opsi_d" value="-"><input type="hidden" name="opsi_e" value="-">
                <input type="hidden" name="jawaban_kompleks" value=""><input type="hidden" name="skor_per_jawaban" value="0"><input type="hidden" name="pasangan_jodoh" value="">
                
                <!-- Menjodohkan -->
                <?php elseif ($jenis_soal == 'menjodohkan'): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light"><h5 class="mb-0">Soal Menjodohkan</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label>Pernyataan Kiri <span class="text-danger">*</span></label><textarea name="opsi_a" class="form-control" rows="4" required><?= htmlspecialchars(($soal['opsi_a'] != '-' ? $soal['opsi_a'] : '')) ?></textarea></div>
                        <div class="mb-3"><label>Pernyataan Kanan <span class="text-danger">*</span></label><textarea name="opsi_b" class="form-control" rows="4" required><?= htmlspecialchars(($soal['opsi_b'] != '-' ? $soal['opsi_b'] : '')) ?></textarea></div>
                        <div class="mb-3"><label>Pasangan Jawaban <span class="text-danger">*</span></label><textarea name="pasangan_jodoh" class="form-control" rows="3" required><?= htmlspecialchars($soal['pasangan_jodoh'] ?? '') ?></textarea><small>Format: 1-1,2-2,3-3</small></div>
                    </div>
                </div>
                <input type="hidden" name="opsi_c" value="-"><input type="hidden" name="opsi_d" value="-"><input type="hidden" name="opsi_e" value="-">
                <input type="hidden" name="jawaban_benar" value="a"><input type="hidden" name="jawaban_kompleks" value=""><input type="hidden" name="skor_per_jawaban" value="0">
                <?php endif; ?>
                
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Simpan Perubahan</button>
                    <a href="kelola_soal_guru.php" class="btn btn-secondary btn-lg"><i class="fas fa-times me-2"></i>Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>