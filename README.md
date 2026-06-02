# 📚 UjianOnline — Sistem Manajemen Ujian Online

Sistem ujian online berbasis web untuk sekolah di Indonesia. Dibangun dengan PHP murni (tanpa framework) dan MySQL, berjalan di lingkungan XAMPP.

---

## 📋 Daftar Isi

- [Gambaran Umum](#gambaran-umum)
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi](#instalasi)
- [Struktur Direktori](#struktur-direktori)
- [Struktur Database](#struktur-database)
  - [Diagram Relasi Antar Tabel](#diagram-relasi-antar-tabel)
  - [Detail Setiap Tabel](#detail-setiap-tabel)
- [Fitur Utama](#fitur-utama)
- [Alur Sistem](#alur-sistem)
- [Modul & File Utama](#modul--file-utama)
- [Konfigurasi](#konfigurasi)
- [Akun Default](#akun-default)

---

## Gambaran Umum

UjianOnline adalah platform ujian digital yang dirancang untuk mendukung proses evaluasi pembelajaran di sekolah. Sistem ini mendukung tiga peran pengguna:

| Peran | Deskripsi |
|-------|-----------|
| **Admin** | Superuser, mengelola seluruh sistem termasuk guru, siswa, soal, dan ujian |
| **Guru** | Membuat soal, mengelola ujian, memantau pelaksanaan, dan mengoreksi jawaban |
| **Siswa** | Mengikuti ujian sesuai jadwal dan kelas yang ditentukan |

---

## Teknologi yang Digunakan

### Backend
- **PHP 7.3+** — Bahasa pemrograman utama (procedural, tanpa framework)
- **PDO MySQL** — Abstraksi koneksi database
- **MySQL / MariaDB 10.1+** — Database server
- **Charset:** `latin1` dengan collation `latin1_swedish_ci`
- **Timezone:** Asia/Jakarta (WIB, UTC+7)

### Frontend
- **Bootstrap 5.3.0** — Framework CSS responsif
- **jQuery** — Manipulasi DOM dan AJAX
- **DataTables 1.13.6** — Tabel interaktif dengan pencarian & sorting
- **FontAwesome 6.0.0** — Ikon
- **Custom CSS & JavaScript** — Styling dan logika tambahan

### Library Pihak Ketiga
- **PhpSpreadsheet** — Import/export data ke format Excel (.xlsx)
- **TCPDF** — Generasi laporan PDF

---

## Persyaratan Sistem

- XAMPP / WAMP / LAMP (PHP >= 7.3, MySQL >= 5.7)
- PHP Extensions: `pdo_mysql`, `gd`, `fileinfo`, `mbstring`
- Browser modern (Chrome, Firefox, Edge)
- Koneksi internet (untuk CDN Bootstrap & FontAwesome)

---

## Instalasi

1. **Clone / salin** folder proyek ke direktori `htdocs` XAMPP:
   ```
   C:\xampp\htdocs\ujianonline\
   ```

2. **Import database** — Buka phpMyAdmin, buat database bernama `ujianonline`, lalu import file:
   ```
   ujianonline.sql
   ```

3. **Konfigurasi koneksi** — Buka `config.php` dan sesuaikan jika perlu:
   ```php
   $host     = 'localhost';
   $username = 'root';
   $password = '';
   $database = 'ujianonline';
   ```

4. **Akses aplikasi** di browser:
   ```
   http://localhost/ujianonline/
   ```

5. **Login** menggunakan akun default (lihat bagian [Akun Default](#akun-default)).

---

## Struktur Direktori

```
ujianonline/
│
├── assets/
│   ├── css/                    # File stylesheet kustom
│   │   ├── custom.css
│   │   └── style.css
│   ├── js/                     # File JavaScript kustom
│   │   ├── app.js
│   │   ├── chart.js
│   │   └── script.js
│   ├── uploads/                # File yang diupload pengguna
│   │   ├── profiles/           # Foto profil guru & siswa
│   │   ├── logos/              # Logo sekolah
│   │   └── soal_images/        # Gambar untuk soal
│   └── cache/                  # Cache sementara
│
├── templates/                  # Komponen tampilan yang digunakan ulang
│   ├── header.php              # Header HTML utama (Admin/Guru)
│   ├── header_guru.php         # Header khusus guru
│   ├── header_siswa.php        # Header khusus siswa
│   ├── sidebar.php             # Navigasi sidebar
│   ├── footer.php              # Footer utama
│   └── footer_siswa.php        # Footer khusus siswa
│
├── vendor/                     # Library pihak ketiga
│   ├── phpoffice/              # PhpSpreadsheet (Excel)
│   └── autoload.php            # Autoloader manual
│
├── tcpdf/                      # Library generasi PDF
├── tmp/                        # File sementara
│
│── [File PHP Halaman Utama]    # ±60 file PHP di root
├── config.php                  # KONFIGURASI UTAMA (~2000 baris)
├── index.php                   # Router utama (redirect by role)
├── login.php                   # Halaman login
├── logout.php                  # Proses logout
│
└── ujianonline.sql             # Dump skema & data database
```

---

## Struktur Database

- **Database:** `ujianonline`
- **Engine:** InnoDB
- **Charset:** latin1
- **Server:** MariaDB 10.1.38+ / MySQL 5.7+
- **Total Tabel:** 16 tabel aktif + 1 tabel backup

---

### Diagram Relasi Antar Tabel

```
users (1) ──────────── (1) guru
  │                          │
  │                    ┌─────┴──────┐
  │               guru_kelas    guru_mapel
  │                    │              │
  │                  kelas      mata_pelajaran (1) ──── (N) soal
  │                                  │                        │
users (1) ──────────── (1) siswa     │              soal_ujian (N)
                            │        │                        │
                            └────────┴──── ujian (1) ─────────┘
                                              │
                                      hasil_ujian
                                         │    │
                              ┌──────────┘    └────────────┐
                       jawaban_siswa           jawaban_essay
                       log_kecurangan          cheating_logs
                       absensi_ujian           sesi_ujian
                       berita_acara
```

---

### Detail Setiap Tabel

---

#### 1. `users` — Akun Pengguna (Autentikasi)

Tabel inti untuk login. Setiap guru dan siswa memiliki satu akun di sini.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID unik akun |
| `username` | varchar(50) UNIQUE | Username untuk login |
| `password` | varchar(255) | Password ter-hash (bcrypt untuk guru, plaintext untuk siswa) |
| `role` | enum('guru','siswa') | Peran pengguna |
| `nama_lengkap` | varchar(100) | Nama lengkap |
| `created_at` | timestamp | Waktu pembuatan akun |

**Index:** `username` (UNIQUE), `idx_users_username`

---

#### 2. `guru` — Profil Guru / Admin

Data lengkap guru, terhubung ke tabel `users`.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID guru |
| `user_id` | int(11) FK | Referensi ke `users.id` (ON DELETE CASCADE) |
| `nip` | varchar(20) UNIQUE | Nomor Induk Pegawai (opsional) |
| `nama` | varchar(100) | Nama guru |
| `email` | varchar(100) | Email guru |
| `no_telp` | varchar(15) | Nomor telepon |
| `alamat` | text | Alamat lengkap |
| `foto` | varchar(255) | Path foto profil |

**Index:** `nip` (UNIQUE), `idx_guru_user_id`
**FK:** `user_id` → `users.id`

---

#### 3. `siswa` — Profil Siswa

Data lengkap siswa, terhubung ke tabel `users`.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID siswa |
| `user_id` | int(11) FK | Referensi ke `users.id` (ON DELETE CASCADE) |
| `nisn` | varchar(20) UNIQUE | Nomor Induk Siswa Nasional |
| `nama` | varchar(100) | Nama siswa |
| `kelas` | varchar(10) | Kelas siswa (contoh: 7A, 8B) |
| `jenis_kelamin` | enum('L','P') | Jenis kelamin |
| `tanggal_lahir` | date | Tanggal lahir |
| `alamat` | text | Alamat lengkap |
| `foto` | varchar(255) | Path foto profil |

**Index:** `nisn` (UNIQUE), `idx_siswa_user_id`, `idx_siswa_nisn`
**FK:** `user_id` → `users.id`

---

#### 4. `kelas` — Data Kelas

Daftar kelas yang tersedia di sekolah.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID kelas |
| `kelas` | varchar(10) UNIQUE | Nama kelas (contoh: 7A, 7B, 8A) |
| `created_at` | timestamp | Waktu dibuat |

**Index:** `kelas` (UNIQUE)

---

#### 5. `mata_pelajaran` — Mata Pelajaran

Daftar mata pelajaran yang diajarkan di sekolah.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID mata pelajaran |
| `kode_mapel` | varchar(10) UNIQUE | Kode singkat (contoh: MTK, IPA) |
| `nama_mapel` | varchar(100) | Nama lengkap mata pelajaran |
| `deskripsi` | text | Deskripsi mata pelajaran |
| `kkm` | int(11) | Kriteria Ketuntasan Minimal (default: 70) |
| `created_at` | timestamp | Waktu dibuat |

**Index:** `kode_mapel` (UNIQUE)

---

#### 6. `guru_kelas` — Penugasan Guru ke Kelas

Relasi many-to-many antara guru dan kelas yang diampunya.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID penugasan |
| `guru_id` | int(11) FK | Referensi ke `guru.id` (ON DELETE CASCADE) |
| `kelas_id` | int(11) FK | Referensi ke `kelas.id` |
| `kelas` | varchar(10) | Nama kelas (redundan, untuk performa) |
| `created_at` | timestamp | Waktu ditugaskan |

**FK:** `guru_id` → `guru.id`, `kelas_id` → `kelas.id`

---

#### 7. `guru_mapel` — Penugasan Guru ke Mata Pelajaran

Relasi many-to-many antara guru dan mata pelajaran yang diajarkan.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID penugasan |
| `guru_id` | int(11) FK | Referensi ke `guru.id` (ON DELETE CASCADE) |
| `mapel_id` | int(11) FK | Referensi ke `mata_pelajaran.id` (ON DELETE CASCADE) |
| `created_at` | timestamp | Waktu ditugaskan |

**FK:** `guru_id` → `guru.id`, `mapel_id` → `mata_pelajaran.id`

---

#### 8. `soal` — Bank Soal

Tabel penyimpanan semua soal. Mendukung berbagai jenis soal dan media.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID soal |
| `mapel_id` | int(11) FK | Mata pelajaran soal (→ `mata_pelajaran.id`) |
| `kelas` | varchar(10) | Kelas target soal |
| `pertanyaan` | text | Teks pertanyaan |
| `gambar_soal` | varchar(255) | Path gambar pertanyaan (opsional) |
| `video_soal` | text | URL video YouTube atau path video lokal |
| `opsi_a` | text | Teks pilihan jawaban A |
| `opsi_a_gambar` | varchar(255) | Gambar pilihan A (opsional) |
| `opsi_b` | text | Teks pilihan jawaban B |
| `opsi_b_gambar` | varchar(255) | Gambar pilihan B (opsional) |
| `opsi_c` | text | Teks pilihan jawaban C |
| `opsi_c_gambar` | varchar(255) | Gambar pilihan C (opsional) |
| `opsi_d` | text | Teks pilihan jawaban D |
| `opsi_d_gambar` | varchar(255) | Gambar pilihan D (opsional) |
| `opsi_e` | text | Teks pilihan jawaban E (opsional) |
| `jawaban_benar` | enum('a','b','c','d','e') | Kunci jawaban untuk pilihan ganda |
| `jawaban_kompleks` | text | JSON array jawaban benar untuk soal kompleks (contoh: `["a","c"]`) |
| `skor_per_jawaban` | int(11) | Skor per jawaban benar (untuk pilihan ganda kompleks) |
| `jenis_soal` | enum | Jenis soal: `pilihan_ganda`, `pilihan_ganda_kompleks`, `essay`, `menjodohkan`, `benar_salah` |
| `pasangan_jodoh` | text | Data pasangan untuk soal menjodohkan (format JSON) |
| `skor` | int(11) | Bobot nilai soal (default: 10) |
| `created_by` | int(11) FK | Guru pembuat soal (→ `guru.id`) |
| `created_at` | timestamp | Waktu dibuat |

**Index:** `created_by`, `idx_soal_mapel_id`
**FK:** `mapel_id` → `mata_pelajaran.id`, `created_by` → `guru.id`

---

#### 9. `ujian` — Data Ujian

Konfigurasi setiap ujian yang dibuat oleh guru atau admin.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID ujian |
| `mapel_id` | int(11) FK | Mata pelajaran ujian (→ `mata_pelajaran.id`) |
| `judul_ujian` | varchar(200) | Judul/nama ujian |
| `deskripsi` | text | Deskripsi atau petunjuk ujian |
| `waktu_mulai` | datetime | Waktu ujian dibuka (WIB) |
| `waktu_selesai` | datetime | Waktu ujian ditutup (WIB) |
| `durasi` | int(11) | Durasi pengerjaan dalam menit |
| `status` | enum('draft','published') | Status ujian (draft = belum aktif) |
| `kelas_target` | varchar(10) | Kelas yang dapat mengikuti ujian |
| `created_by` | int(11) FK | Guru pembuat ujian (→ `guru.id`) |
| `created_at` | timestamp | Waktu dibuat |

**Index:** `created_by`, `idx_ujian_mapel_id`, `idx_ujian_status`
**FK:** `mapel_id` → `mata_pelajaran.id`, `created_by` → `guru.id`

---

#### 10. `soal_ujian` — Daftar Soal dalam Ujian

Tabel junction yang menghubungkan soal dengan ujian (many-to-many).

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID relasi |
| `ujian_id` | int(11) FK | Referensi ke `ujian.id` (ON DELETE CASCADE) |
| `soal_id` | int(11) FK | Referensi ke `soal.id` (ON DELETE CASCADE) |
| `urutan` | int(11) | Nomor urut soal dalam ujian |
| `soal_text` | text | Snapshot teks soal saat ditambahkan |
| `gambar_soal` | varchar(255) | Snapshot gambar soal |
| `video_soal` | text | Snapshot video soal |

**Index:** `ujian_id`, `soal_id`
**FK:** `ujian_id` → `ujian.id`, `soal_id` → `soal.id`

---

#### 11. `hasil_ujian` — Rekap Hasil Ujian Siswa

Satu baris per siswa per ujian. Menyimpan status dan nilai akhir.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID hasil ujian |
| `ujian_id` | int(11) FK | Referensi ke `ujian.id` |
| `siswa_id` | int(11) FK | Referensi ke `siswa.id` |
| `nilai` | decimal(5,2) | Nilai akhir (0.00 - 100.00) |
| `waktu_mulai` | datetime | Waktu ujian dibuka oleh sistem |
| `waktu_selesai` | datetime | Waktu ujian selesai/dikumpulkan |
| `waktu_mulai_pengerjaan` | datetime | Waktu siswa mulai mengerjakan |
| `status` | enum('selesai','sedang_ujian') | Status pengerjaan |

**Index:** `idx_hasil_ujian_siswa_id`, `idx_hasil_ujian_ujian_id`
**FK:** `ujian_id` → `ujian.id`, `siswa_id` → `siswa.id`

---

#### 12. `jawaban_siswa` — Jawaban Pilihan Ganda Siswa

Menyimpan jawaban siswa untuk setiap soal pilihan ganda.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID jawaban |
| `hasil_ujian_id` | int(11) FK | Referensi ke `hasil_ujian.id` (ON DELETE CASCADE) |
| `soal_id` | int(11) FK | Referensi ke `soal.id` |
| `jawaban_siswa` | varchar(255) | Pilihan jawaban siswa (a/b/c/d/e) |
| `jawaban_essay` | text | Teks jawaban untuk soal essay (opsional) |

**Index:** `soal_id`, `idx_jawaban_siswa_hasil_id`
**FK:** `hasil_ujian_id` → `hasil_ujian.id`, `soal_id` → `soal.id`

---

#### 13. `jawaban_essay` — Jawaban Essay Siswa

Menyimpan jawaban essay beserta hasil koreksi guru.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID jawaban essay |
| `hasil_ujian_id` | int(11) FK | Referensi ke `hasil_ujian.id` (ON DELETE CASCADE) |
| `soal_id` | int(11) FK | Referensi ke `soal.id` |
| `jawaban_text` | text | Teks jawaban siswa |
| `skor_essay` | decimal(5,2) | Skor yang diberikan guru |
| `komentar_guru` | text | Komentar / feedback dari guru |
| `status_koreksi` | enum('belum','sudah') | Status koreksi (default: belum) |
| `created_at` | timestamp | Waktu jawaban masuk |
| `updated_at` | timestamp | Waktu terakhir diperbarui |

**Index:** `unique_essay_answer` (hasil_ujian_id, soal_id), `idx_essay_hasil_id`, `idx_essay_soal_id`, `idx_essay_status`
**FK:** `hasil_ujian_id` → `hasil_ujian.id`, `soal_id` → `soal.id`

---

#### 14. `sesi_ujian` — Sesi & Sisa Waktu Ujian

Melacak sisa waktu ujian siswa secara real-time untuk mendukung resume ujian.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID sesi |
| `hasil_ujian_id` | int(11) FK | Referensi ke `hasil_ujian.id` (ON DELETE CASCADE) |
| `sisa_waktu` | int(11) | Sisa waktu dalam detik |
| `last_activity` | timestamp | Waktu aktivitas terakhir tercatat |

**FK:** `hasil_ujian_id` → `hasil_ujian.id`

---

#### 15. `log_kecurangan` — Log Deteksi Kecurangan

Mencatat setiap indikasi kecurangan selama ujian berlangsung.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID log |
| `hasil_ujian_id` | int(11) FK | Referensi ke `hasil_ujian.id` |
| `siswa_id` | int(11) FK | Referensi ke `siswa.id` |
| `jenis_pelanggaran` | varchar(50) | Jenis pelanggaran (lihat keterangan di bawah) |
| `keterangan` | text | Deskripsi detail kejadian |
| `waktu_pelanggaran` | timestamp | Waktu pelanggaran terjadi |

**Jenis pelanggaran yang dicatat:**
- `keluar_tab` — Siswa berpindah/menutup tab browser
- `keluar_focus` — Siswa keluar dari fokus halaman ujian
- `attempt_leave` — Siswa mencoba meninggalkan halaman
- `kembali_fokus` — Siswa kembali ke halaman ujian
- `penalty_activated` — Penalti waktu diaktifkan akibat pelanggaran berulang

**Index:** `idx_log_hasil_id`, `idx_log_siswa_id`, `idx_log_waktu`

---

#### 16. `cheating_logs` — Log Kecurangan Alternatif

Tabel sekunder untuk log kecurangan (format berbeda dengan `log_kecurangan`).

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID log |
| `hasil_ujian_id` | int(11) FK | Referensi ke `hasil_ujian.id` (ON DELETE CASCADE) |
| `log_type` | varchar(50) | Tipe log kecurangan |
| `log_details` | text | Detail kejadian |
| `created_at` | timestamp | Waktu kejadian |

**FK:** `hasil_ujian_id` → `hasil_ujian.id`

---

#### 17. `absensi_ujian` — Absensi Peserta Ujian

Mencatat kehadiran siswa dalam setiap ujian.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID absensi |
| `ujian_id` | int(11) FK | Referensi ke `ujian.id` (ON DELETE CASCADE) |
| `siswa_id` | int(11) FK | Referensi ke `siswa.id` (ON DELETE CASCADE) |
| `waktu_hadir` | datetime | Waktu siswa hadir |
| `waktu_pulang` | datetime | Waktu siswa selesai dan keluar |
| `status_hadir` | enum('hadir','tidak_hadir','izin','sakit') | Status kehadiran (default: tidak_hadir) |
| `keterangan` | text | Keterangan tambahan |
| `created_at` | timestamp | Waktu data dibuat |
| `updated_at` | timestamp | Waktu terakhir diperbarui |

**Index:** `unique_ujian_siswa` (ujian_id, siswa_id), `siswa_id`
**FK:** `ujian_id` → `ujian.id`, `siswa_id` → `siswa.id`

---

#### 18. `berita_acara` — Berita Acara Ujian

Dokumentasi resmi pelaksanaan ujian oleh guru pengawas.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID berita acara |
| `ujian_id` | int(11) FK | Referensi ke `ujian.id` (ON DELETE CASCADE) |
| `guru_pengawas` | int(11) FK | Guru pengawas (→ `guru.id`) |
| `tanggal_ujian` | date | Tanggal pelaksanaan ujian |
| `waktu_mulai` | time | Jam mulai ujian |
| `waktu_selesai` | time | Jam selesai ujian |
| `jumlah_peserta` | int(11) | Total peserta terdaftar |
| `jumlah_hadir` | int(11) | Jumlah yang hadir |
| `jumlah_tidak_hadir` | int(11) | Jumlah yang tidak hadir |
| `kelas` | varchar(50) | Kelas peserta ujian |
| `ruangan` | varchar(50) | Nama/nomor ruangan |
| `kejadian_penting` | text | Catatan kejadian selama ujian |
| `kendala_teknis` | text | Catatan masalah teknis |
| `tindak_lanjut` | text | Tindak lanjut yang diambil |
| `ttd_guru` | varchar(255) | Path gambar tanda tangan guru |
| `status` | enum('draft','selesai','diverifikasi') | Status dokumen |
| `created_at` | timestamp | Waktu dibuat |
| `updated_at` | timestamp | Waktu terakhir diperbarui |

**Index:** `unique_ujian_kelas` (ujian_id, kelas), `guru_pengawas`
**FK:** `ujian_id` → `ujian.id`, `guru_pengawas` → `guru.id`

---

#### 19. `pengaturan` — Konfigurasi Sistem

Penyimpanan konfigurasi sistem dalam format key-value.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID pengaturan |
| `nama` | varchar(100) UNIQUE | Nama kunci konfigurasi |
| `value` | text | Nilai konfigurasi |
| `created_at` | timestamp | Waktu dibuat |
| `updated_at` | timestamp | Waktu terakhir diperbarui |

**Konfigurasi Default:**

| nama | value | Keterangan |
|------|-------|------------|
| `random_soal` | 1 | Aktifkan pengacakan urutan soal |
| `allow_restart` | 1 | Izinkan siswa restart ujian |
| `site_name` | Sistem Ujian Online | Nama aplikasi |
| `site_description` | Platform Ujian Online Sekolah | Deskripsi |
| `timezone` | Asia/Jakarta | Zona waktu sistem |
| `max_file_size` | 5242880 | Ukuran file maksimum (5MB) |
| `allowed_extensions` | jpg,jpeg,png,pdf | Ekstensi file yang diizinkan |
| `passing_grade` | 60 | Nilai kelulusan minimum |
| `auto_calculate` | 1 | Hitung nilai otomatis |

---

#### 20. `profil_sekolah` — Profil Sekolah

Data identitas sekolah yang ditampilkan di laporan dan header sistem.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | int(11) PK AI | ID profil |
| `nama_sekolah` | varchar(200) | Nama sekolah |
| `npsn` | varchar(20) | Nomor Pokok Sekolah Nasional |
| `alamat` | text | Alamat lengkap sekolah |
| `telepon` | varchar(20) | Nomor telepon sekolah |
| `email` | varchar(100) | Email resmi sekolah |
| `website` | varchar(100) | Website sekolah |
| `kepala_sekolah` | varchar(100) | Nama kepala sekolah |
| `nip_kepala` | varchar(20) | NIP kepala sekolah |
| `logo` | varchar(255) | Path logo sekolah |
| `visi` | text | Visi sekolah |
| `misi` | text | Misi sekolah |
| `created_at` | timestamp | Waktu dibuat |
| `updated_at` | timestamp | Waktu terakhir diperbarui |

---

#### 21. `ujian_backup_wib` — Backup Tabel Ujian (WIB)

Tabel cadangan ujian yang dibuat saat migrasi timezone ke WIB. Struktur identik dengan tabel `ujian`.

---

### Ringkasan Relasi Foreign Key

```
users ◄──── guru (user_id)
users ◄──── siswa (user_id)
guru  ◄──── guru_kelas (guru_id)
guru  ◄──── guru_mapel (guru_id)
guru  ◄──── soal (created_by)
guru  ◄──── ujian (created_by)
guru  ◄──── berita_acara (guru_pengawas)
kelas ◄──── guru_kelas (kelas_id)
mata_pelajaran ◄──── guru_mapel (mapel_id)
mata_pelajaran ◄──── soal (mapel_id)
mata_pelajaran ◄──── ujian (mapel_id)
soal  ◄──── soal_ujian (soal_id)
soal  ◄──── jawaban_siswa (soal_id)
soal  ◄──── jawaban_essay (soal_id)
ujian ◄──── soal_ujian (ujian_id)
ujian ◄──── hasil_ujian (ujian_id)
ujian ◄──── absensi_ujian (ujian_id)
ujian ◄──── berita_acara (ujian_id)
siswa ◄──── hasil_ujian (siswa_id)
siswa ◄──── absensi_ujian (siswa_id)
siswa ◄──── log_kecurangan (siswa_id)
hasil_ujian ◄──── jawaban_siswa (hasil_ujian_id)
hasil_ujian ◄──── jawaban_essay (hasil_ujian_id)
hasil_ujian ◄──── sesi_ujian (hasil_ujian_id)
hasil_ujian ◄──── log_kecurangan (hasil_ujian_id)
hasil_ujian ◄──── cheating_logs (hasil_ujian_id)
```

---

## Fitur Utama

### Manajemen Pengguna
- Registrasi & manajemen guru dan siswa
- Import data siswa massal via template Excel
- Reset password dan manajemen profil
- Role-based access control (Admin, Guru, Siswa)

### Manajemen Soal
- Bank soal per mata pelajaran dan kelas
- Jenis soal: Pilihan Ganda, Pilihan Ganda Kompleks, Essay, Menjodohkan, Benar/Salah
- Dukungan media: gambar (JPG, PNG, GIF, WebP maks 5MB), video YouTube, video lokal (MP4)
- Import soal massal via Excel
- Soal bisa digunakan di banyak ujian

### Manajemen Ujian
- Penjadwalan ujian dengan batas waktu mulai dan selesai (berbasis WIB)
- Konfigurasi durasi pengerjaan per ujian
- Penentuan kelas target peserta
- Pilih soal manual atau acak otomatis
- Status draft/published

### Pelaksanaan Ujian
- Antarmuka ujian berbasis AJAX (tidak perlu reload halaman)
- Auto-save jawaban secara berkala
- Penghitung waktu mundur real-time
- Resume ujian jika koneksi terputus (via `sesi_ujian`)
- Mode fullscreen (opsional)

### Sistem Pengawasan (Anti-Kecurangan)
- Deteksi perpindahan tab browser
- Deteksi kehilangan fokus halaman
- Deteksi percobaan meninggalkan halaman
- Sistem penalti: waktu dikurangi otomatis jika pelanggaran berulang
- Semua kejadian dicatat di `log_kecurangan`
- Guru dapat memantau siswa secara real-time

### Penilaian & Hasil
- Penilaian otomatis untuk soal pilihan ganda
- Koreksi manual oleh guru untuk soal essay
- Nilai akhir dihitung otomatis berdasarkan bobot soal
- Passing grade yang dapat dikonfigurasi (default: 60)
- Laporan nilai per siswa, per kelas, per ujian

### Laporan & Export
- Export hasil ujian ke Excel (.xlsx)
- Cetak laporan nilai (PDF via TCPDF)
- Cetak daftar absensi (PDF)
- Cetak berita acara ujian (PDF)
- Cetak kartu ujian siswa

### Pengaturan Sistem
- Konfigurasi nama sekolah, logo, visi, misi
- Atur passing grade global
- Toggle pengacakan soal
- Sinkronisasi timezone WIB
- Reset / restart sistem

---

## Alur Sistem

### Alur Guru Membuat Ujian

```
Login (Guru)
    │
    ▼
Buat Soal → Bank Soal (soal)
    │
    ▼
Buat Ujian → Konfigurasi jadwal & kelas (ujian)
    │
    ▼
Tambahkan Soal ke Ujian → (soal_ujian)
    │
    ▼
Publish Ujian (status: published)
    │
    ▼
Monitor Pelaksanaan → Lihat log kecurangan
    │
    ▼
Koreksi Essay (jika ada)
    │
    ▼
Lihat & Export Hasil
```

### Alur Siswa Mengikuti Ujian

```
Login (Siswa)
    │
    ▼
Dashboard → Daftar Ujian Tersedia
    │
    ▼
Klik "Mulai Ujian" → Validasi waktu & kelas
    │         (mulai_ujian.php)
    │
    ▼
Buat record hasil_ujian (status: sedang_ujian)
    │
    ▼
Kerjakan Ujian (AJAX) → Auto-save jawaban (jawaban_siswa)
    │    (kerjakan_ujian.php)
    │
    ▼
Sistem Catat Pelanggaran → (log_kecurangan)
    │
    ▼
Kumpulkan / Waktu Habis → Hitung nilai otomatis
    │    (proses_selesai_ujian.php)
    │
    ▼
Update hasil_ujian (status: selesai, nilai: X)
    │
    ▼
Lihat Hasil Ujian
```

---

## Modul & File Utama

### File Konfigurasi

| File | Fungsi |
|------|--------|
| `config.php` | Koneksi DB, timezone WIB, upload media, CSRF, utility functions |
| `index.php` | Router — redirect berdasarkan role & status login |

### Autentikasi

| File | Fungsi |
|------|--------|
| `login.php` | Form login (username/NIP + password) |
| `logout.php` | Hapus session & redirect ke login |
| `reset_password.php` | Reset password pengguna |

### Dashboard

| File | Fungsi |
|------|--------|
| `dashboard.php` | Dashboard Admin — statistik keseluruhan |
| `dashboard_guru.php` | Dashboard Guru — ujian & statistik kelas |
| `dashboard_siswa.php` | Dashboard Siswa — daftar ujian & nilai |

### Manajemen Soal

| File | Fungsi |
|------|--------|
| `kelola_soal.php` | Bank soal (Admin) |
| `kelola_soal_guru.php` | Bank soal (Guru) |
| `proses_soal.php` | Simpan/update soal (Admin) |
| `proses_soal_guru.php` | Simpan/update soal (Guru) |
| `edit_soal_guru.php` | Form edit soal guru |
| `proses_import_soal.php` | Import soal dari Excel |
| `download_template_soal.php` | Unduh template Excel soal |

### Manajemen Ujian

| File | Fungsi |
|------|--------|
| `buat_ujian.php` | Form pembuatan ujian baru |
| `kelola_ujian.php` | Daftar & manajemen ujian |
| `detail_ujian.php` | Detail konfigurasi ujian |
| `kelola_soal_ujian.php` | Pilih soal untuk ujian (manual) |
| `kelola_soal_ujian_auto.php` | Pilih soal otomatis/acak |
| `daftar_ujian.php` | Daftar ujian (sudut pandang siswa) |

### Pelaksanaan Ujian

| File | Fungsi |
|------|--------|
| `mulai_ujian.php` | Inisialisasi sesi ujian siswa |
| `kerjakan_ujian.php` | Antarmuka mengerjakan soal (AJAX) |
| `lanjutkan_ujian.php` | Resume ujian yang terputus |
| `proses_jawaban.php` | Endpoint AJAX simpan jawaban |
| `proses_selesai_ujian.php` | Finalisasi & hitung nilai |
| `get_soal_data.php` | AJAX: ambil data soal |
| `ajax_detail_soal.php` | AJAX: detail soal tertentu |

### Penilaian & Hasil

| File | Fungsi |
|------|--------|
| `hasil_ujian.php` | Rekap hasil ujian (Admin) |
| `hasil_ujian_guru.php` | Rekap hasil ujian (Guru) |
| `detail_hasil_siswa.php` | Detail hasil per siswa |
| `ajax_get_detail_hasil.php` | AJAX: detail hasil ujian |
| `input_nilai.php` | Input nilai manual |
| `koreksi_essay.php` | Koreksi jawaban essay |
| `proses_koreksi_essay.php` | Simpan hasil koreksi essay |
| `export_hasil.php` | Export hasil ke Excel/PDF |

### Absensi & Laporan

| File | Fungsi |
|------|--------|
| `absensi_ujian.php` | Rekap absensi (Admin) |
| `absensi_ujian_guru.php` | Rekap absensi (Guru) |
| `berita_acara.php` | Buat/lihat berita acara (Admin) |
| `berita_acara_guru.php` | Buat/lihat berita acara (Guru) |
| `cetak_absensi_pdf.php` | Cetak daftar absensi (PDF) |
| `cetak_berita_acara.php` | Cetak berita acara (PDF) |
| `laporan_nilai_pdf.php` | Cetak laporan nilai (PDF) |
| `cetak_kartu_ujian.php` | Cetak kartu ujian siswa |
| `monitoring_ujian_guru.php` | Monitor ujian real-time |
| `export_monitoring.php` | Export data monitoring |

### Manajemen Data

| File | Fungsi |
|------|--------|
| `kelola_siswa.php` | CRUD data siswa |
| `kelola_guru.php` | CRUD data guru |
| `kelola_mapel.php` | CRUD mata pelajaran |
| `proses_import_siswa.php` | Import siswa dari Excel |
| `download_template_siswa.php` | Unduh template Excel siswa |
| `profile.php` | Profil Admin |
| `profile_guru.php` | Profil Guru |
| `profile_siswa.php` | Profil Siswa |

### Pengaturan Sistem

| File | Fungsi |
|------|--------|
| `pengaturan.php` | Konfigurasi sistem (passing grade, dsb) |
| `setup_demo.php` | Isi data demo untuk testing |

---

## Konfigurasi

File utama konfigurasi adalah `config.php`. Variabel yang perlu disesuaikan:

```php
// Koneksi Database
$host     = 'localhost';
$username = 'root';
$password = '';
$database = 'ujianonline';

// URL Dasar Aplikasi
define('BASE_URL', 'http://localhost/ujianonline');

// Batas Ukuran Upload
define('MAX_FILE_SIZE_SOAL', 5 * 1024 * 1024); // 5MB
```

Konfigurasi lain (passing grade, pengacakan soal, dll) dikelola melalui halaman **Pengaturan** di dalam aplikasi dan disimpan di tabel `pengaturan`.

---

## Akun Default

Setelah import database, tersedia akun berikut:

| Username | Password | Role | Keterangan |
|----------|----------|------|------------|
| `admin` | `admin123` | Guru/Admin | Akun superuser utama |

> **Catatan Keamanan:** Segera ganti password default setelah instalasi pertama.

---

## Catatan Teknis

- **Charset:** Sistem menggunakan `latin1` karena menyesuaikan database lama. Untuk karakter Unicode penuh, perlu migrasi ke `utf8mb4`.
- **Password Siswa:** Saat ini masih menggunakan plaintext untuk siswa. Disarankan migrasi ke bcrypt untuk keamanan produksi.
- **Timezone:** Seluruh waktu ujian mengacu pada **WIB (Asia/Jakarta, UTC+7)**. Timezone diset di level PHP dan MySQL secara bersamaan.
- **CSRF Protection:** Semua form menggunakan token CSRF yang divalidasi di setiap proses.
- **Session:** Autentikasi berbasis PHP session (`$_SESSION`).

---

*Dokumentasi ini dibuat berdasarkan analisis kode sumber dan skema database program UjianOnline versi 3.10.*

---

## Pengembangan untuk Mode Kompetisi / Olimpiade

Daftar fitur yang dapat dikembangkan untuk menjadikan UjianOnline sebagai platform **Olimpiade / Kompetisi** resmi.

---

### 1. Sistem Kompetisi & Babak
- **Multi-babak** — Penyisihan → Semifinal → Final, dengan promosi otomatis berdasarkan peringkat
- **Bracket/Jadwal turnamen** — Visualisasi bracket kompetisi
- **Kuota peserta per babak** — Top-N siswa lolos ke babak berikutnya secara otomatis

### 2. Leaderboard & Peringkat
- **Papan peringkat real-time** — Skor live saat ujian berlangsung (seperti ICPC/OSN)
- **Peringkat per kelas, sekolah, dan wilayah**
- **Riwayat peringkat** — Grafik naik/turun posisi peserta selama kompetisi

### 3. Sistem Penilaian Kompetisi
- **Poin per soal berbeda** — Soal mudah/sedang/sulit dengan bobot berbeda
- **Bonus waktu** — Poin tambahan jika selesai lebih cepat dari durasi yang ditentukan
- **Penalty sistem** — Pengurangan poin tiap jawaban salah (seperti ICPC)
- **Partial score** — Nilai parsial untuk soal essay dan pilihan ganda kompleks

### 4. Keamanan & Integritas Kompetisi
- **Proctoring foto berkala** — Ambil foto via webcam tiap X menit dan upload ke server
- **Lock browser mode** — Fullscreen wajib + blokir keyboard shortcut berbahaya
- **Fingerprint sesi** — Deteksi login ganda atau akses dari perangkat berbeda
- **Watermark soal** — Identitas peserta dicetak di soal untuk mencegah kebocoran

### 5. Manajemen Peserta Kompetisi
- **Registrasi mandiri peserta** — Siswa mendaftar sendiri menggunakan kode kompetisi
- **Verifikasi identitas** — Upload foto kartu pelajar/identitas saat registrasi
- **Kompetisi beregu/tim** — Dukung mode kompetisi kelompok
- **Data asal sekolah/instansi** — Peserta dari sekolah berbeda dalam satu kompetisi

### 6. Analitik & Statistik Lanjutan
- **Distribusi nilai** — Histogram, rata-rata, median, dan standar deviasi
- **Analisis butir soal** — Tingkat kesukaran dan daya beda tiap soal
- **Heatmap jawaban** — Visualisasi soal yang paling banyak salah dijawab
- **Waktu rata-rata per soal** — Statistik durasi pengerjaan tiap nomor soal

### 7. Sertifikat & Penghargaan Otomatis
- **Sertifikat digital otomatis** — Generate PDF dengan nama, nilai, peringkat, dan QR code verifikasi
- **Badge/medali** — Emas/Perak/Perunggu berdasarkan threshold nilai yang dapat dikonfigurasi
- **Download sertifikat oleh peserta** — Langsung dari dashboard siswa setelah kompetisi selesai

### 8. Bank Soal Kompetisi
- **Kategori tingkat kesulitan** — Mudah / Sedang / Sulit / Olimpiade
- **Tag topik soal** — Filter soal berdasarkan topik (Aljabar, Geometri, Biologi, dll)
- **Soal dengan solusi/pembahasan** — Tampil otomatis setelah kompetisi selesai
- **Dukungan LaTeX/rumus matematika** — Render formula ilmiah di soal dan pilihan jawaban

### 9. Multi-Sekolah / Multi-Instansi
- **Manajemen instansi/sekolah** — Setiap sekolah memiliki admin sendiri
- **Kompetisi lintas sekolah** — Satu ujian dapat diikuti siswa dari banyak sekolah
- **Laporan per instansi** — Rekap nilai dan peringkat per sekolah peserta

### 10. Sistem Pembayaran Biaya Pendaftaran
- **Biaya pendaftaran per event** — Admin dapat menetapkan biaya pendaftaran untuk setiap kompetisi
- **Upload bukti pembayaran** — Peserta upload foto/screenshot bukti transfer saat registrasi
- **Verifikasi manual oleh admin** — Admin mengonfirmasi pembayaran sebelum peserta diizinkan mengikuti ujian
- **Status pembayaran** — Peserta dapat memantau status: Menunggu Verifikasi / Terverifikasi / Ditolak
- **Integrasi payment gateway** — Dukungan pembayaran otomatis via Midtrans, Xendit, atau Duitku (QRIS, transfer bank, e-wallet)
- **Kode unik transfer** — Generate nominal unik tiap peserta (misal: Rp 50.003) untuk identifikasi otomatis
- **Laporan keuangan** — Rekap total pemasukan, daftar yang sudah/belum bayar, dan export ke Excel/PDF
- **Refund/pembatalan** — Pengelolaan pengembalian dana jika event dibatalkan
- **Kode voucher/diskon** — Kode promo untuk peserta tertentu atau early bird

### 11. Aksesibilitas & UX
- **Mode offline/PWA** — Jawaban tersimpan lokal saat koneksi putus, sinkronisasi saat online kembali
- **Tampilan mobile-friendly** — Antarmuka ujian yang optimal di perangkat smartphone
- **Notifikasi jadwal** — Pengingat kompetisi via email atau integrasi WhatsApp

---

### Prioritas Pengembangan

| Prioritas | Fitur | Alasan |
|-----------|-------|--------|
| Tinggi | Leaderboard real-time | Elemen khas dan motivasi utama kompetisi |
| Tinggi | Multi-babak & kuota lolos | Alur standar olimpiade berjenjang |
| Tinggi | Sertifikat otomatis | Output yang diharapkan peserta dan panitia |
| Sedang | Penilaian berbasis waktu/penalty | Diferensiasi dari ujian biasa |
| Sedang | Proctoring foto berkala | Menjaga integritas kompetisi online |
| Sedang | Sistem pembayaran (upload bukti) | Penting untuk event berbayar, implementasi sederhana |
| Rendah | Integrasi payment gateway otomatis | Memerlukan akun merchant & integrasi API pihak ketiga |
| Rendah | Multi-instansi/sekolah | Memerlukan redesign struktur database |
