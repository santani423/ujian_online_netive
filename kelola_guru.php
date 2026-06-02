<?php
// kelola_guru.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

$title = "Kelola Guru";
include 'templates/header.php';
include 'templates/sidebar.php';
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Kelola Guru</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahGuruModal">
            <i class="fas fa-plus me-2"></i>Tambah Guru
        </button>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="tabelGuru">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIP</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No. Telp</th>
                            <th>Mapel Diampu</th>
                            <th>Kelas Diampu</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Query untuk mengambil data guru beserta mapel dan kelas yang diampu
                        $stmt = $pdo->query("
                            SELECT g.*, u.username,
                            GROUP_CONCAT(DISTINCT mg.nama_mapel ORDER BY mg.nama_mapel SEPARATOR ', ') as mapel_diampu,
                            GROUP_CONCAT(DISTINCT kg.kelas ORDER BY kg.kelas SEPARATOR ', ') as kelas_diampu
                            FROM guru g 
                            JOIN users u ON g.user_id = u.id
                            LEFT JOIN guru_mapel gm ON g.id = gm.guru_id
                            LEFT JOIN mata_pelajaran mg ON gm.mapel_id = mg.id
                            LEFT JOIN guru_kelas gk ON g.id = gk.guru_id
                            LEFT JOIN kelas kg ON gk.kelas_id = kg.id
                            GROUP BY g.id
                        ");
                        $no = 1;
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nip']) ?></td>
                            <td><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['no_telp']) ?></td>
                            <td><?= htmlspecialchars($row['mapel_diampu'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['kelas_diampu'] ?? '-') ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editGuruModal" 
                                        data-id="<?= $row['id'] ?>" data-nip="<?= $row['nip'] ?>" 
                                        data-nama="<?= $row['nama'] ?>" data-email="<?= $row['email'] ?>" 
                                        data-notelp="<?= $row['no_telp'] ?>"
                                        data-mapel="<?= htmlspecialchars($row['mapel_ids'] ?? '') ?>"
                                        data-kelas="<?= htmlspecialchars($row['kelas_ids'] ?? '') ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="hapusGuru(<?= $row['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Guru -->
<div class="modal fade" id="tambahGuruModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="proses_guru.php?action=tambah" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Guru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">NIP</label>
                                <input type="text" class="form-control" name="nip" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" name="nama" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. Telepon</label>
                                <input type="text" class="form-control" name="no_telp">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pilihan Mapel yang Diampu -->
                    <div class="mb-3">
                        <label class="form-label">Mata Pelajaran yang Diampu (Bisa pilih banyak)</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                <?php
                                // Ambil semua mata pelajaran
                                $stmt_mapel = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel");
                                while ($mapel = $stmt_mapel->fetch()): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="mapel_id[]" 
                                           value="<?= $mapel['id'] ?>" id="mapel_<?= $mapel['id'] ?>">
                                    <label class="form-check-label" for="mapel_<?= $mapel['id'] ?>">
                                        <?= htmlspecialchars($mapel['kode_mapel']) ?> - <?= htmlspecialchars($mapel['nama_mapel']) ?>
                                    </label>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pilihan Kelas yang Diampu -->
                    <div class="mb-3">
                        <label class="form-label">Kelas yang Diampu (Bisa pilih banyak)</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                <?php
                                // Ambil semua kelas unik dari tabel siswa atau buat tabel kelas jika belum ada
                                $stmt_kelas = $pdo->query("
                                    SELECT DISTINCT kelas FROM siswa 
                                    WHERE kelas IS NOT NULL AND kelas != ''
                                    ORDER BY kelas
                                ");
                                $kelas_list = $stmt_kelas->fetchAll();
                                
                                if (count($kelas_list) > 0) {
                                    foreach ($kelas_list as $kelas): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="kelas[]" 
                                               value="<?= htmlspecialchars($kelas['kelas']) ?>" 
                                               id="kelas_<?= htmlspecialchars($kelas['kelas']) ?>">
                                        <label class="form-check-label" for="kelas_<?= htmlspecialchars($kelas['kelas']) ?>">
                                            Kelas <?= htmlspecialchars($kelas['kelas']) ?>
                                        </label>
                                    </div>
                                    <?php endforeach;
                                } else {
                                    // Jika tidak ada kelas di tabel siswa, tampilkan pilihan default
                                    $kelas_default = ['7', '8', '9', '10', '11', '12'];
                                    foreach ($kelas_default as $kelas): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="kelas[]" 
                                               value="<?= $kelas ?>" id="kelas_<?= $kelas ?>">
                                        <label class="form-check-label" for="kelas_<?= $kelas ?>">
                                            Kelas <?= $kelas ?>
                                        </label>
                                    </div>
                                    <?php endforeach;
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Guru -->
<div class="modal fade" id="editGuruModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="proses_guru.php?action=edit" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Guru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">NIP</label>
                                <input type="text" class="form-control" name="nip" id="edit_nip" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" name="nama" id="edit_nama" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. Telepon</label>
                                <input type="text" class="form-control" name="no_telp" id="edit_notelp">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password Baru (kosongkan jika tidak diubah)</label>
                        <input type="password" class="form-control" name="password">
                    </div>
                    
                    <!-- Pilihan Mapel yang Diampu untuk Edit -->
                    <div class="mb-3">
                        <label class="form-label">Mata Pelajaran yang Diampu (Bisa pilih banyak)</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;" id="edit_mapel_container">
                                <?php
                                // Ambil semua mata pelajaran
                                $stmt_mapel = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel");
                                while ($mapel = $stmt_mapel->fetch()): ?>
                                <div class="form-check">
                                    <input class="form-check-input edit-mapel-checkbox" type="checkbox" name="mapel_id[]" 
                                           value="<?= $mapel['id'] ?>" id="edit_mapel_<?= $mapel['id'] ?>">
                                    <label class="form-check-label" for="edit_mapel_<?= $mapel['id'] ?>">
                                        <?= htmlspecialchars($mapel['kode_mapel']) ?> - <?= htmlspecialchars($mapel['nama_mapel']) ?>
                                    </label>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pilihan Kelas yang Diampu untuk Edit -->
                    <div class="mb-3">
                        <label class="form-label">Kelas yang Diampu (Bisa pilih banyak)</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;" id="edit_kelas_container">
                                <?php
                                // Ambil semua kelas unik dari tabel siswa
                                $stmt_kelas = $pdo->query("
                                    SELECT DISTINCT kelas FROM siswa 
                                    WHERE kelas IS NOT NULL AND kelas != ''
                                    ORDER BY kelas
                                ");
                                $kelas_list = $stmt_kelas->fetchAll();
                                
                                if (count($kelas_list) > 0) {
                                    foreach ($kelas_list as $kelas): ?>
                                    <div class="form-check">
                                        <input class="form-check-input edit-kelas-checkbox" type="checkbox" name="kelas[]" 
                                               value="<?= htmlspecialchars($kelas['kelas']) ?>" 
                                               id="edit_kelas_<?= htmlspecialchars($kelas['kelas']) ?>">
                                        <label class="form-check-label" for="edit_kelas_<?= htmlspecialchars($kelas['kelas']) ?>">
                                            Kelas <?= htmlspecialchars($kelas['kelas']) ?>
                                        </label>
                                    </div>
                                    <?php endforeach;
                                } else {
                                    // Jika tidak ada kelas di tabel siswa, tampilkan pilihan default
                                    $kelas_default = ['7', '8', '9', '10', '11', '12'];
                                    foreach ($kelas_default as $kelas): ?>
                                    <div class="form-check">
                                        <input class="form-check-input edit-kelas-checkbox" type="checkbox" name="kelas[]" 
                                               value="<?= $kelas ?>" id="edit_kelas_<?= $kelas ?>">
                                        <label class="form-check-label" for="edit_kelas_<?= $kelas ?>">
                                            Kelas <?= $kelas ?>
                                        </label>
                                    </div>
                                    <?php endforeach;
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function hapusGuru(id) {
    if (confirm('Apakah Anda yakin ingin menghapus guru ini?')) {
        window.location.href = 'proses_guru.php?action=hapus&id=' + id;
    }
}

// Modal edit handler
var editGuruModal = document.getElementById('editGuruModal');
editGuruModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var nip = button.getAttribute('data-nip');
    var nama = button.getAttribute('data-nama');
    var email = button.getAttribute('data-email');
    var notelp = button.getAttribute('data-notelp');
    
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nip').value = nip;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_notelp').value = notelp;
    
    // Reset semua checkbox
    document.querySelectorAll('.edit-mapel-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('.edit-kelas-checkbox').forEach(cb => cb.checked = false);
    
    // Load data mapel dan kelas yang sudah dipilih sebelumnya
    fetch('proses_guru.php?action=get_detail&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Set checkbox mapel
                if (data.mapel_ids) {
                    data.mapel_ids.forEach(mapelId => {
                        const checkbox = document.getElementById('edit_mapel_' + mapelId);
                        if (checkbox) checkbox.checked = true;
                    });
                }
                
                // Set checkbox kelas
                if (data.kelas_list) {
                    data.kelas_list.forEach(kelas => {
                        const checkbox = document.getElementById('edit_kelas_' + kelas);
                        if (checkbox) checkbox.checked = true;
                    });
                }
            }
        })
        .catch(error => console.error('Error:', error));
});
</script>

<?php include 'templates/footer.php'; ?>