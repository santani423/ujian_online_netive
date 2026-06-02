<?php
// proses_guru.php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    redirect('login.php');
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action == 'tambah') {
        // Tambah guru baru
        try {
            $pdo->beginTransaction();
            
            // 1. Cek apakah username sudah ada
            $username = sanitize($_POST['username']);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Username sudah digunakan");
            }
            
            // 2. Tambah user
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $nama_lengkap = sanitize($_POST['nama']);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'guru', ?)");
            $stmt->execute([$username, $password, $nama_lengkap]);
            $user_id = $pdo->lastInsertId();
            
            // 3. Upload foto jika ada
            $foto_path = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/assets/uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                        $foto_path = 'assets/uploads/profiles/' . $new_filename;
                    }
                }
            }
            
            // 4. Tambah data guru
            $nip = sanitize($_POST['nip']);
            $nama = sanitize($_POST['nama']);
            $email = sanitize($_POST['email']);
            $no_telp = sanitize($_POST['no_telp']);
            $alamat = sanitize($_POST['alamat']);
            
            $stmt = $pdo->prepare("INSERT INTO guru (user_id, nip, nama, email, no_telp, alamat, foto) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $nip, $nama, $email, $no_telp, $alamat, $foto_path]);
            $guru_id = $pdo->lastInsertId();
            
            // 5. Simpan mapel yang diampu (jika dipilih)
            if (isset($_POST['mapel_id']) && is_array($_POST['mapel_id'])) {
                foreach ($_POST['mapel_id'] as $mapel_id) {
                    $mapel_id = intval($mapel_id);
                    $stmt = $pdo->prepare("INSERT INTO guru_mapel (guru_id, mapel_id) VALUES (?, ?)");
                    $stmt->execute([$guru_id, $mapel_id]);
                }
            }
            
            // 6. Simpan kelas yang diampu (jika dipilih)
            if (isset($_POST['kelas']) && is_array($_POST['kelas'])) {
                foreach ($_POST['kelas'] as $kelas_nama) {
                    $kelas_nama = sanitize($kelas_nama);
                    
                    // Cek apakah kelas sudah ada di tabel kelas
                    $stmt = $pdo->prepare("SELECT id FROM kelas WHERE kelas = ?");
                    $stmt->execute([$kelas_nama]);
                    $kelas_row = $stmt->fetch();
                    
                    $kelas_id = null;
                    if ($kelas_row) {
                        $kelas_id = $kelas_row['id'];
                    } else {
                        // Tambah kelas baru
                        $stmt = $pdo->prepare("INSERT INTO kelas (kelas) VALUES (?)");
                        $stmt->execute([$kelas_nama]);
                        $kelas_id = $pdo->lastInsertId();
                    }
                    
                    // Simpan ke guru_kelas
                    $stmt = $pdo->prepare("INSERT INTO guru_kelas (guru_id, kelas_id, kelas) VALUES (?, ?, ?)");
                    $stmt->execute([$guru_id, $kelas_id, $kelas_nama]);
                }
            }
            
            $pdo->commit();
            flashMessage('success', 'Guru berhasil ditambahkan');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            flashMessage('danger', 'Gagal menambah guru: ' . $e->getMessage());
        }
        
        redirect('kelola_guru.php');
        
    } elseif ($action == 'edit') {
        // Edit guru
        try {
            $pdo->beginTransaction();
            
            $guru_id = intval($_POST['id']);
            $nip = sanitize($_POST['nip']);
            $nama = sanitize($_POST['nama']);
            $email = sanitize($_POST['email']);
            $no_telp = sanitize($_POST['no_telp']);
            $alamat = sanitize($_POST['alamat']);
            
            // 1. Update data guru
            $stmt = $pdo->prepare("UPDATE guru SET nip = ?, nama = ?, email = ?, no_telp = ?, alamat = ? WHERE id = ?");
            $stmt->execute([$nip, $nama, $email, $no_telp, $alamat, $guru_id]);
            
            // 2. Update password jika diisi
            if (!empty($_POST['password'])) {
                $user_id = $pdo->query("SELECT user_id FROM guru WHERE id = $guru_id")->fetchColumn();
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, nama_lengkap = ? WHERE id = ?");
                $stmt->execute([$password, $nama, $user_id]);
            } else {
                // Update nama lengkap saja
                $user_id = $pdo->query("SELECT user_id FROM guru WHERE id = $guru_id")->fetchColumn();
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ? WHERE id = ?");
                $stmt->execute([$nama, $user_id]);
            }
            
            // 3. Hapus mapel lama dan tambah yang baru
            $pdo->exec("DELETE FROM guru_mapel WHERE guru_id = $guru_id");
            
            if (isset($_POST['mapel_id']) && is_array($_POST['mapel_id'])) {
                foreach ($_POST['mapel_id'] as $mapel_id) {
                    $mapel_id = intval($mapel_id);
                    $stmt = $pdo->prepare("INSERT INTO guru_mapel (guru_id, mapel_id) VALUES (?, ?)");
                    $stmt->execute([$guru_id, $mapel_id]);
                }
            }
            
            // 4. Hapus kelas lama dan tambah yang baru
            $pdo->exec("DELETE FROM guru_kelas WHERE guru_id = $guru_id");
            
            if (isset($_POST['kelas']) && is_array($_POST['kelas'])) {
                foreach ($_POST['kelas'] as $kelas_nama) {
                    $kelas_nama = sanitize($kelas_nama);
                    
                    // Cek apakah kelas sudah ada di tabel kelas
                    $stmt = $pdo->prepare("SELECT id FROM kelas WHERE kelas = ?");
                    $stmt->execute([$kelas_nama]);
                    $kelas_row = $stmt->fetch();
                    
                    $kelas_id = null;
                    if ($kelas_row) {
                        $kelas_id = $kelas_row['id'];
                    } else {
                        // Tambah kelas baru
                        $stmt = $pdo->prepare("INSERT INTO kelas (kelas) VALUES (?)");
                        $stmt->execute([$kelas_nama]);
                        $kelas_id = $pdo->lastInsertId();
                    }
                    
                    // Simpan ke guru_kelas
                    $stmt = $pdo->prepare("INSERT INTO guru_kelas (guru_id, kelas_id, kelas) VALUES (?, ?, ?)");
                    $stmt->execute([$guru_id, $kelas_id, $kelas_nama]);
                }
            }
            
            $pdo->commit();
            flashMessage('success', 'Data guru berhasil diperbarui');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            flashMessage('danger', 'Gagal memperbarui data guru: ' . $e->getMessage());
        }
        
        redirect('kelola_guru.php');
        
    } elseif ($action == 'hapus') {
        // Hapus guru
        $id = intval($_GET['id']);
        
        try {
            // Hapus user akan otomatis menghapus guru karena foreign key constraint
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = (SELECT user_id FROM guru WHERE id = ?)");
            $stmt->execute([$id]);
            
            flashMessage('success', 'Guru berhasil dihapus');
        } catch (PDOException $e) {
            flashMessage('danger', 'Gagal menghapus guru: ' . $e->getMessage());
        }
        
        redirect('kelola_guru.php');
        
    } elseif ($action == 'get_detail') {
        // AJAX: Get detail guru untuk modal edit
        $id = intval($_GET['id']);
        
        try {
            // Ambil data guru
            $stmt = $pdo->prepare("SELECT * FROM guru WHERE id = ?");
            $stmt->execute([$id]);
            $guru = $stmt->fetch();
            
            if (!$guru) {
                throw new Exception("Guru tidak ditemukan");
            }
            
            echo json_encode([
                'success' => true,
                'guru' => $guru
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
        exit();
    }
}
?>