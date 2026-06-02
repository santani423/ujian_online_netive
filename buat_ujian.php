<?php
// buat_ujian.php - MEMILIH KELAS TARGET LEBIH DARI SATU (MULTIPLE SELECT)
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: login.php");
    exit();
}

$title = "Buat Ujian Baru";
include 'templates/header.php';

// Ambil data mata pelajaran
$mapel_stmt = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel");
$mapel_list = $mapel_stmt->fetchAll();

// Ambil daftar kelas unik dari siswa
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mapel_id = $_POST['mapel_id'];
    $judul_ujian = $_POST['judul_ujian'];
    $waktu_mulai = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];
    
    // Ambil kelas target (bisa multiple select)
    $kelas_target = isset($_POST['kelas_target']) ? $_POST['kelas_target'] : [];
    
    // Jika multiple select kosong, set ke array kosong
    if (empty($kelas_target)) {
        $kelas_target = [];
    }
    
    // Simpan sebagai JSON atau comma separated
    $kelas_target_simpan = !empty($kelas_target) ? implode(',', $kelas_target) : '';
    
    // Hitung durasi otomatis dari selisih waktu
    $waktu_mulai_timestamp = strtotime($waktu_mulai);
    $waktu_selesai_timestamp = strtotime($waktu_selesai);
    $durasi = round(($waktu_selesai_timestamp - $waktu_mulai_timestamp) / 60); // dalam menit
    
    if ($durasi <= 0) {
        flashMessage('danger', 'Waktu selesai harus setelah waktu mulai!');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO ujian (mapel_id, judul_ujian, waktu_mulai, waktu_selesai, durasi, kelas_target, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$mapel_id, $judul_ujian, $waktu_mulai, $waktu_selesai, $durasi, $kelas_target_simpan, $_SESSION['user_id']]);
            
            $ujian_id = $pdo->lastInsertId();
            flashMessage('success', 'Ujian berhasil dibuat! Durasi: ' . $durasi . ' menit. Silakan tambahkan soal.');
            redirect("kelola_soal_ujian_auto.php?id=$ujian_id");
        } catch (Exception $e) {
            flashMessage('danger', 'Gagal membuat ujian: ' . $e->getMessage());
        }
    }
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Buat Ujian Baru</h2>
        <a href="kelola_ujian.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" id="formUjian">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            <select name="mapel_id" class="form-select" required>
                                <option value="">Pilih Mata Pelajaran</option>
                                <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?= $mapel['id'] ?>">
                                    <?= htmlspecialchars($mapel['kode_mapel']) ?> - <?= htmlspecialchars($mapel['nama_mapel']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kelas Target <span class="text-info">(Bisa pilih lebih dari satu)</span></label>
                            <select name="kelas_target[]" class="form-select" multiple size="5" style="height: auto; min-height: 150px;">
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= $kelas['kelas'] ?>">
                                    Kelas <?= htmlspecialchars($kelas['kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Petunjuk:</strong> Tekan <kbd>Ctrl</kbd> (Windows) atau <kbd>Cmd</kbd> (Mac) untuk memilih lebih dari satu kelas.<br>
                                Kosongkan untuk semua kelas (tidak disarankan jika banyak kelas).
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Judul Ujian <span class="text-danger">*</span></label>
                            <input type="text" name="judul_ujian" class="form-control" required 
                                   placeholder="Contoh: Ujian Matematika Kelas X - Semester 1">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="waktu_mulai" class="form-control" id="waktuMulai" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="waktu_selesai" class="form-control" id="waktuSelesai" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Durasi Otomatis</label>
                            <div class="form-control bg-light" id="durasiDisplay">
                                <span class="text-muted">Durasi akan dihitung otomatis</span>
                            </div>
                            <div class="form-text">
                                Durasi dihitung otomatis dari selisih waktu mulai dan selesai
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Informasi:</h6>
                            <small>
                                - Pilih satu atau lebih kelas untuk membatasi ujian hanya untuk kelas tersebut<br>
                                - Gunakan <kbd>Ctrl</kbd> + Klik untuk memilih beberapa kelas<br>
                                - Ujian akan tersedia untuk semua siswa di kelas yang dipilih<br>
                                - Durasi dihitung otomatis dari waktu mulai dan selesai
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Ujian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Styling untuk multiple select */
select[multiple] {
    border-radius: 8px;
    padding: 8px;
}
select[multiple] option {
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 2px;
}
select[multiple] option:hover {
    background-color: #e7f3ff;
}
select[multiple] option:checked {
    background-color: #0d6efd;
    color: white;
}
kbd {
    background-color: #f8f9fa;
    border: 1px solid #ccc;
    border-radius: 3px;
    padding: 1px 4px;
    font-size: 0.85em;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const waktuMulai = document.getElementById('waktuMulai');
    const waktuSelesai = document.getElementById('waktuSelesai');
    const durasiDisplay = document.getElementById('durasiDisplay');
    
    function hitungDurasi() {
        if (waktuMulai.value && waktuSelesai.value) {
            const mulai = new Date(waktuMulai.value);
            const selesai = new Date(waktuSelesai.value);
            
            if (selesai > mulai) {
                const selisihMenit = Math.round((selesai - mulai) / (1000 * 60));
                const jam = Math.floor(selisihMenit / 60);
                const menit = selisihMenit % 60;
                
                let durasiText = '';
                if (jam > 0) {
                    durasiText += jam + ' jam ';
                }
                durasiText += menit + ' menit';
                durasiText += ' (' + selisihMenit + ' menit)';
                
                durasiDisplay.innerHTML = '<strong class="text-success">' + durasiText + '</strong>';
            } else {
                durasiDisplay.innerHTML = '<span class="text-danger">Waktu selesai harus setelah waktu mulai</span>';
            }
        } else {
            durasiDisplay.innerHTML = '<span class="text-muted">Durasi akan dihitung otomatis</span>';
        }
    }
    
    waktuMulai.addEventListener('change', hitungDurasi);
    waktuSelesai.addEventListener('change', hitungDurasi);
    
    // Set waktu default (2 jam dari sekarang)
    const now = new Date();
    const defaultMulai = new Date(now.getTime() + 60 * 60 * 1000); // 1 jam dari sekarang
    const defaultSelesai = new Date(now.getTime() + 3 * 60 * 60 * 1000); // 3 jam dari sekarang
    
    waktuMulai.value = formatDateTimeLocal(defaultMulai);
    waktuSelesai.value = formatDateTimeLocal(defaultSelesai);
    
    // Hitung durasi awal
    hitungDurasi();
    
    function formatDateTimeLocal(date) {
        return date.toISOString().slice(0, 16);
    }
});
</script>

<?php include 'templates/footer.php'; ?>