-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 22 Apr 2026 pada 16.19
-- Versi server: 10.1.38-MariaDB
-- Versi PHP: 7.3.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ujianonline`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_ujian`
--

CREATE TABLE `absensi_ujian` (
  `id` int(11) NOT NULL,
  `ujian_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `waktu_hadir` datetime DEFAULT NULL,
  `waktu_pulang` datetime DEFAULT NULL,
  `status_hadir` enum('hadir','tidak_hadir','izin','sakit') DEFAULT 'tidak_hadir',
  `keterangan` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `berita_acara`
--

CREATE TABLE `berita_acara` (
  `id` int(11) NOT NULL,
  `ujian_id` int(11) NOT NULL,
  `guru_pengawas` int(11) NOT NULL,
  `tanggal_ujian` date NOT NULL,
  `waktu_mulai` time NOT NULL,
  `waktu_selesai` time NOT NULL,
  `jumlah_peserta` int(11) DEFAULT NULL,
  `jumlah_hadir` int(11) DEFAULT NULL,
  `jumlah_tidak_hadir` int(11) DEFAULT NULL,
  `kelas` varchar(50) DEFAULT NULL,
  `ruangan` varchar(50) DEFAULT NULL,
  `kejadian_penting` text,
  `kendala_teknis` text,
  `tindak_lanjut` text,
  `ttd_guru` varchar(255) DEFAULT NULL,
  `status` enum('draft','selesai','diverifikasi') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `cheating_logs`
--

CREATE TABLE `cheating_logs` (
  `id` int(11) NOT NULL,
  `hasil_ujian_id` int(11) NOT NULL,
  `log_type` varchar(50) NOT NULL,
  `log_details` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nip` varchar(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_telp` varchar(15) DEFAULT NULL,
  `alamat` text,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `guru`
--

INSERT INTO `guru` (`id`, `user_id`, `nip`, `nama`, `email`, `no_telp`, `alamat`, `foto`) VALUES
(1, 1, '1234567890', 'admin', 'jangandihapusloginadmin@gmail.com', '085298777055', NULL, 'assets/uploads/profiles/profile_1_1765362135.png'),
(5, 170, NULL, 'lutfi', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru_kelas`
--

CREATE TABLE `guru_kelas` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `kelas_id` int(11) DEFAULT NULL,
  `kelas` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `guru_kelas`
--

INSERT INTO `guru_kelas` (`id`, `guru_id`, `kelas_id`, `kelas`, `created_at`) VALUES
(48, 5, 8, '7A', '2026-04-20 18:22:48'),
(49, 5, 9, '7B', '2026-04-20 18:22:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru_mapel`
--

CREATE TABLE `guru_mapel` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `mapel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `hasil_ujian`
--

CREATE TABLE `hasil_ujian` (
  `id` int(11) NOT NULL,
  `ujian_id` int(11) DEFAULT NULL,
  `siswa_id` int(11) DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `waktu_mulai_pengerjaan` datetime DEFAULT NULL,
  `status` enum('selesai','sedang_ujian') DEFAULT 'sedang_ujian'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jawaban_essay`
--

CREATE TABLE `jawaban_essay` (
  `id` int(11) NOT NULL,
  `hasil_ujian_id` int(11) NOT NULL,
  `soal_id` int(11) NOT NULL,
  `jawaban_text` text,
  `skor_essay` decimal(5,2) DEFAULT NULL,
  `komentar_guru` text,
  `status_koreksi` enum('belum','sudah') DEFAULT 'belum',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Tabel untuk menyimpan jawaban essay siswa';

-- --------------------------------------------------------

--
-- Struktur dari tabel `jawaban_siswa`
--

CREATE TABLE `jawaban_siswa` (
  `id` int(11) NOT NULL,
  `hasil_ujian_id` int(11) DEFAULT NULL,
  `soal_id` int(11) DEFAULT NULL,
  `jawaban_siswa` varchar(255) DEFAULT NULL,
  `jawaban_essay` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id` int(11) NOT NULL,
  `kelas` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `kelas`
--

INSERT INTO `kelas` (`id`, `kelas`, `created_at`) VALUES
(8, '7A', '2026-04-20 18:22:48'),
(9, '7B', '2026-04-20 18:22:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_kecurangan`
--

CREATE TABLE `log_kecurangan` (
  `id` int(11) NOT NULL,
  `hasil_ujian_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `jenis_pelanggaran` varchar(50) NOT NULL,
  `keterangan` text,
  `waktu_pelanggaran` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `log_kecurangan`
--

INSERT INTO `log_kecurangan` (`id`, `hasil_ujian_id`, `siswa_id`, `jenis_pelanggaran`, `keterangan`, `waktu_pelanggaran`) VALUES
(1, 7, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-08 12:54:10'),
(2, 7, 5, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-08 12:54:10'),
(3, 7, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-08 12:54:21'),
(4, 7, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-08 12:54:21'),
(5, 7, 5, 'keluar_focus', 'Keluar fokus selama 12 detik', '2026-02-08 12:54:29'),
(6, 7, 5, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-02-08 12:59:37'),
(7, 7, 5, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-02-08 12:59:54'),
(8, 7, 5, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-02-08 12:59:56'),
(9, 7, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-08 13:00:13'),
(10, 7, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-08 13:00:13'),
(11, 7, 5, 'keluar_focus', 'Keluar fokus selama 6 detik', '2026-02-08 13:00:15'),
(12, 7, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-08 13:00:25'),
(13, 7, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-08 13:00:25'),
(14, 7, 5, 'keluar_focus', 'Keluar fokus selama 30 detik', '2026-02-08 13:00:52'),
(15, 11, 6, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-02-08 13:01:39'),
(16, 11, 6, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-02-08 13:01:46'),
(17, 11, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-08 13:01:48'),
(18, 12, 7, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-08 13:17:34'),
(19, 12, 7, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-08 13:17:34'),
(20, 12, 7, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-02-08 13:17:39'),
(21, 12, 7, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-08 13:17:47'),
(22, 12, 7, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-08 13:17:47'),
(23, 12, 7, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-02-08 13:17:48'),
(24, 12, 7, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-08 13:17:57'),
(25, 12, 7, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-08 13:17:57'),
(26, 1, 1, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-02-09 07:04:13'),
(27, 1, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-09 07:04:20'),
(28, 1, 1, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-02-09 07:04:21'),
(29, 1, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-09 07:04:32'),
(30, 1, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-09 07:04:32'),
(31, 1, 1, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-02-09 07:04:33'),
(32, 1, 1, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-02-09 07:05:37'),
(33, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-09 07:37:08'),
(34, 2, 2, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-02-09 07:37:09'),
(35, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-09 07:37:17'),
(36, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-09 07:37:17'),
(37, 2, 2, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-02-09 07:37:18'),
(38, 2, 2, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-09 07:42:53'),
(39, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-09 07:42:58'),
(40, 2, 2, 'keluar_focus', 'Keluar fokus selama 8 detik', '2026-02-09 07:43:02'),
(41, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-09 07:43:11'),
(42, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-09 07:43:11'),
(43, 2, 2, 'keluar_focus', 'Keluar fokus selama 6 detik', '2026-02-09 07:43:13'),
(44, 1, 1, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-02-09 07:45:14'),
(45, 1, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-09 16:39:51'),
(46, 1, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-09 16:39:51'),
(47, 1, 1, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-09 16:39:51'),
(48, 1, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-09 16:42:08'),
(49, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-09 16:48:22'),
(50, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-09 16:48:22'),
(51, 2, 2, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-09 16:48:22'),
(52, 2, 2, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-02-09 16:53:36'),
(53, 3, 2, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-02-21 17:46:38'),
(54, 3, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 17:46:41'),
(55, 3, 2, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-02-21 17:46:42'),
(56, 3, 2, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-02-21 17:46:44'),
(57, 3, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 17:46:47'),
(58, 3, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 17:46:47'),
(59, 3, 2, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-21 17:46:48'),
(60, 3, 2, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-02-21 17:46:53'),
(61, 1, 3, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 19:23:21'),
(62, 1, 3, 'keluar_focus', 'Keluar fokus selama 8 detik', '2026-02-21 19:23:26'),
(63, 1, 3, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-02-21 19:23:29'),
(64, 1, 3, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 19:23:31'),
(65, 1, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:23:31'),
(66, 1, 3, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 19:23:34'),
(67, 1, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:23:34'),
(68, 1, 3, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-21 19:23:34'),
(69, 1, 3, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 19:23:40'),
(70, 1, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:23:40'),
(71, 1, 3, 'keluar_focus', 'Keluar fokus selama 96 detik', '2026-02-21 19:25:12'),
(72, 1, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:25:13'),
(73, 1, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:25:17'),
(74, 1, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:25:44'),
(75, 2, 4, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 19:42:25'),
(76, 2, 4, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-21 19:42:25'),
(77, 2, 4, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 19:42:30'),
(78, 2, 4, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:42:30'),
(79, 2, 4, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-02-21 19:42:30'),
(80, 2, 4, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:42:36'),
(81, 2, 4, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 19:44:01'),
(82, 2, 4, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-02-21 19:49:33'),
(83, 3, 4, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 20:05:00'),
(84, 3, 4, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 20:05:00'),
(85, 3, 4, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 20:05:02'),
(86, 3, 4, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 20:05:02'),
(87, 3, 4, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 20:05:20'),
(88, 4, 3, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-02-21 20:15:40'),
(89, 4, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 20:19:01'),
(90, 4, 3, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-02-21 20:28:18'),
(91, 4, 3, 'penalty_activated', 'Penalti 5 menit', '2026-02-21 20:28:18'),
(92, 4, 3, 'keluar_focus', 'Keluar fokus 6 detik', '2026-02-21 20:28:20'),
(93, 4, 3, 'penalty_activated', 'Penalti 5 menit', '2026-02-21 20:28:28'),
(94, 4, 3, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-02-21 20:28:28'),
(95, 4, 3, 'keluar_focus', 'Keluar fokus 21 detik', '2026-02-21 20:28:45'),
(96, 4, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 20:28:55'),
(97, 4, 3, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-02-21 20:33:35'),
(98, 4, 3, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-02-21 20:35:46'),
(99, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:44:53'),
(100, 5, 5, 'keluar_focus', 'Keluar fokus selama 9 detik', '2026-04-12 06:45:00'),
(101, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:45:10'),
(102, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:45:11'),
(103, 5, 5, 'keluar_focus', 'Keluar fokus selama 164 detik', '2026-04-12 06:47:52'),
(104, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:47:57'),
(105, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:47:57'),
(106, 5, 5, 'keluar_focus', 'Keluar fokus selama 223 detik', '2026-04-12 06:51:36'),
(107, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:51:43'),
(108, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:51:44'),
(109, 5, 5, 'keluar_focus', 'Keluar fokus selama 10 detik', '2026-04-12 06:51:49'),
(110, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:51:56'),
(111, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:51:56'),
(112, 5, 5, 'keluar_focus', 'Keluar fokus selama 52 detik', '2026-04-12 06:52:47'),
(113, 5, 5, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-12 06:52:51'),
(114, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:52:51'),
(115, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:52:51'),
(116, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:52:55'),
(117, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:52:55'),
(118, 5, 5, 'keluar_focus', 'Keluar fokus selama 7 detik', '2026-04-12 06:52:59'),
(119, 5, 5, 'attempt_leave', 'Mencoba meninggalkan halaman ujian', '2026-04-12 06:54:09'),
(120, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:54:11'),
(121, 5, 5, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-12 06:54:23'),
(122, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:54:25'),
(123, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:54:26'),
(124, 5, 5, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-12 06:54:26'),
(125, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-12 06:54:31'),
(126, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:54:31'),
(127, 5, 5, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-12 06:54:31'),
(128, 5, 5, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-12 06:54:33'),
(129, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-12 06:54:40'),
(130, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:00:00'),
(131, 2, 2, 'keluar_focus', 'Keluar fokus selama 60 detik', '2026-04-18 05:00:56'),
(132, 2, 2, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-18 05:01:00'),
(133, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:01:09'),
(134, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:01:09'),
(135, 2, 2, 'keluar_focus', 'Keluar fokus selama 18 detik', '2026-04-18 05:01:23'),
(136, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:01:28'),
(137, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:01:28'),
(138, 2, 2, 'keluar_focus', 'Keluar fokus selama 11 detik', '2026-04-18 05:01:35'),
(139, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:01:40'),
(140, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:01:40'),
(141, 2, 2, 'keluar_focus', 'Keluar fokus selama 55 detik', '2026-04-18 05:02:32'),
(142, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:03:06'),
(143, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:03:06'),
(144, 2, 2, 'keluar_focus', 'Keluar fokus selama 15 detik', '2026-04-18 05:03:17'),
(145, 2, 2, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 05:03:25'),
(146, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:03:28'),
(147, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:03:28'),
(148, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:03:30'),
(149, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:03:30'),
(150, 2, 2, 'keluar_focus', 'Keluar fokus selama 276 detik', '2026-04-18 05:08:03'),
(151, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:08:08'),
(152, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:08:08'),
(153, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:08:09'),
(154, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:08:09'),
(155, 2, 2, 'keluar_focus', 'Keluar fokus selama 8 detik', '2026-04-18 05:08:13'),
(156, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:08:19'),
(157, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 05:08:19'),
(158, 2, 2, 'keluar_focus', 'Keluar fokus selama 174 detik', '2026-04-18 05:11:09'),
(159, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:11:15'),
(160, 2, 2, 'keluar_focus', 'Keluar fokus selama 6 detik', '2026-04-18 05:11:18'),
(161, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:11:25'),
(162, 2, 2, 'keluar_focus', 'Keluar fokus selama 7 detik', '2026-04-18 05:11:28'),
(163, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:11:33'),
(164, 2, 2, 'keluar_focus', 'Keluar fokus selama 243 detik', '2026-04-18 05:15:32'),
(165, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:15:52'),
(166, 2, 2, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-04-18 05:15:53'),
(167, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:15:57'),
(168, 2, 2, 'keluar_focus', 'Keluar fokus selama 207 detik', '2026-04-18 05:19:20'),
(169, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:19:35'),
(170, 2, 2, 'keluar_focus', 'Keluar fokus selama 32 detik', '2026-04-18 05:19:58'),
(171, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 05:20:13'),
(172, 2, 2, 'keluar_focus', 'Keluar fokus selama 74 detik', '2026-04-18 05:21:14'),
(173, 2, 2, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-18 05:23:06'),
(174, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:23:08'),
(175, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:25:27'),
(176, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:25:29'),
(177, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:25:33'),
(178, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:25:34'),
(179, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:25:34'),
(180, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:25:34'),
(181, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:34:21'),
(182, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:34:22'),
(183, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:34:58'),
(184, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:35:00'),
(185, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:35:00'),
(186, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:35:01'),
(187, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:36:00'),
(188, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:36:02'),
(189, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:42:54'),
(190, 2, 2, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 05:43:16'),
(191, 2, 2, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 05:43:30'),
(192, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:19:08'),
(193, 5, 5, 'keluar_focus', 'Keluar fokus selama 8 detik', '2026-04-18 06:19:12'),
(194, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:19:19'),
(195, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:19:19'),
(196, 5, 5, 'keluar_focus', 'Keluar fokus selama 6 detik', '2026-04-18 06:19:21'),
(197, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:19:26'),
(198, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:19:26'),
(199, 5, 5, 'keluar_focus', 'Keluar fokus selama 35 detik', '2026-04-18 06:19:56'),
(200, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:20:02'),
(201, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:20:02'),
(202, 5, 5, 'keluar_focus', 'Keluar fokus selama 204 detik', '2026-04-18 06:23:22'),
(203, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:23:43'),
(204, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:23:43'),
(205, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:23:45'),
(206, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:23:45'),
(207, 5, 5, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-18 06:23:45'),
(208, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:23:50'),
(209, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:23:50'),
(210, 5, 5, 'keluar_focus', 'Keluar fokus selama 9 detik', '2026-04-18 06:23:55'),
(211, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:24:59'),
(212, 5, 5, 'keluar_focus', 'Keluar fokus selama 7 detik', '2026-04-18 06:25:02'),
(213, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:25:11'),
(214, 5, 5, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-18 06:25:11'),
(215, 5, 5, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 06:25:20'),
(216, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:25:41'),
(217, 5, 5, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-18 06:25:46'),
(218, 5, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:25:51'),
(219, 5, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:25:51'),
(220, 5, 5, 'keluar_focus', 'Keluar fokus selama 22 detik', '2026-04-18 06:26:09'),
(221, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:26:26'),
(222, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:26:27'),
(223, 6, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:26:27'),
(224, 6, 6, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-18 06:26:27'),
(225, 6, 6, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 06:26:28'),
(226, 6, 6, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 06:26:32'),
(227, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:26:44'),
(228, 6, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:26:44'),
(229, 6, 6, 'keluar_focus', 'Keluar fokus selama 8 detik', '2026-04-18 06:26:48'),
(230, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:26:53'),
(231, 6, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:26:53'),
(232, 6, 6, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-18 06:26:54'),
(233, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:27:15'),
(234, 6, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:27:15'),
(235, 6, 6, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-18 06:27:16'),
(236, 6, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-18 06:27:20'),
(237, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:27:23'),
(238, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:27:31'),
(239, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:27:33'),
(240, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:27:53'),
(241, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:27:55'),
(242, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:27:56'),
(243, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:27:58'),
(244, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:28:15'),
(245, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:28:15'),
(246, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:28:16'),
(247, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:28:16'),
(248, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:28:16'),
(249, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:28:19'),
(250, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:34:44'),
(251, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:34:45'),
(252, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:34:54'),
(253, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:34:55'),
(254, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:34:55'),
(255, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:34:59'),
(256, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:34:59'),
(257, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:35:00'),
(258, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:35:04'),
(259, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:35:04'),
(260, 6, 6, 'kembali_fokus', 'Kembali ke halaman ujian', '2026-04-18 06:35:05'),
(261, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian', '2026-04-18 06:35:09'),
(262, 6, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:35:10'),
(263, 6, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 06:35:15'),
(264, 6, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 06:35:15'),
(265, 6, 6, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-18 06:35:15'),
(266, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:12:27'),
(267, 7, 6, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-04-18 11:12:28'),
(268, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:12:34'),
(269, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:12:34'),
(270, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:12:35'),
(271, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:12:35'),
(272, 7, 6, 'keluar_focus', 'Keluar fokus selama 20 detik', '2026-04-18 11:12:52'),
(273, 7, 6, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 11:12:55'),
(274, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:00'),
(275, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:00'),
(276, 7, 6, 'keluar_focus', 'Keluar fokus selama 21 detik', '2026-04-18 11:13:18'),
(277, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:24'),
(278, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:24'),
(279, 7, 6, 'keluar_focus', 'Keluar fokus selama 8 detik', '2026-04-18 11:13:28'),
(280, 7, 6, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-18 11:13:31'),
(281, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:33'),
(282, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:33'),
(283, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:35'),
(284, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:35'),
(285, 7, 6, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-18 11:13:36'),
(286, 7, 6, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:13:38'),
(287, 7, 6, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 11:13:40'),
(288, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:41'),
(289, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:41'),
(290, 7, 6, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:13:42'),
(291, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:45'),
(292, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:45'),
(293, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:47'),
(294, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:47'),
(295, 7, 6, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-04-18 11:13:48'),
(296, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:53'),
(297, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:53'),
(298, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:53'),
(299, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:53'),
(300, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:54'),
(301, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:54'),
(302, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:13:55'),
(303, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:13:55'),
(304, 7, 6, 'keluar_focus', 'Keluar fokus selama 10 detik', '2026-04-18 11:14:02'),
(305, 7, 6, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-18 11:14:05'),
(306, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:14:10'),
(307, 7, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:14:10'),
(308, 7, 6, 'keluar_focus', 'Keluar fokus selama 40 detik', '2026-04-18 11:14:46'),
(309, 7, 6, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:14:51'),
(310, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:14:54'),
(311, 7, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:14:56'),
(312, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:23:13'),
(313, 8, 6, 'keluar_focus', 'Keluar fokus selama 10 detik', '2026-04-18 11:23:19'),
(314, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:23:24'),
(315, 8, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:23:24'),
(316, 8, 6, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-18 11:23:24'),
(317, 8, 6, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 11:23:27'),
(318, 8, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:23:33'),
(319, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:23:33'),
(320, 8, 6, 'keluar_focus', 'Keluar fokus selama 28 detik', '2026-04-18 11:23:56'),
(321, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:24:01'),
(322, 8, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:24:01'),
(323, 8, 6, 'keluar_focus', 'Keluar fokus selama 39 detik', '2026-04-18 11:24:36'),
(324, 8, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:24:42'),
(325, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:24:42'),
(326, 8, 6, 'keluar_focus', 'Keluar fokus selama 25 detik', '2026-04-18 11:25:04'),
(327, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:25:14'),
(328, 8, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:25:14'),
(329, 8, 6, 'keluar_focus', 'Keluar fokus selama 10 detik', '2026-04-18 11:25:20'),
(330, 8, 6, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 11:25:23'),
(331, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:25:25'),
(332, 8, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:25:25'),
(333, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:25:27'),
(334, 8, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:25:27'),
(335, 8, 6, 'keluar_focus', 'Keluar fokus selama 14 detik', '2026-04-18 11:25:38'),
(336, 8, 6, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:25:43'),
(337, 8, 6, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:25:43'),
(338, 8, 6, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-18 11:25:43'),
(339, 9, 1, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:31:42'),
(340, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:32:00'),
(341, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:32:02'),
(342, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:32:02'),
(343, 9, 1, 'keluar_focus', 'Keluar fokus selama 25 detik', '2026-04-18 11:32:24'),
(344, 9, 1, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:32:28'),
(345, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:32:32'),
(346, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:32:32'),
(347, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:32:33'),
(348, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:32:33'),
(349, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:32:33'),
(350, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:32:33'),
(351, 9, 1, 'keluar_focus', 'Keluar fokus selama 28 detik', '2026-04-18 11:32:57'),
(352, 9, 1, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:32:59'),
(353, 9, 1, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:33:01'),
(354, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:33:02'),
(355, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:33:02'),
(356, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:33:03'),
(357, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:33:03'),
(358, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:33:05'),
(359, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:33:05'),
(360, 9, 1, 'keluar_focus', 'Keluar fokus selama 18 detik', '2026-04-18 11:33:19'),
(361, 9, 1, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:33:21'),
(362, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:33:25'),
(363, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:33:25'),
(364, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:33:25'),
(365, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:33:25'),
(366, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:33:26'),
(367, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:33:26'),
(368, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:33:26'),
(369, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:33:26'),
(370, 9, 1, 'keluar_focus', 'Keluar fokus selama 17 detik', '2026-04-18 11:33:39'),
(371, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:33:43'),
(372, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:33:43'),
(373, 9, 1, 'keluar_focus', 'Keluar fokus selama 19 detik', '2026-04-18 11:33:58'),
(374, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:34:03'),
(375, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:34:03'),
(376, 9, 1, 'keluar_focus', 'Keluar fokus selama 21 detik', '2026-04-18 11:34:20'),
(377, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:34:26'),
(378, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:34:37'),
(379, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:34:37'),
(380, 9, 1, 'keluar_focus', 'Keluar fokus selama 46 detik', '2026-04-18 11:35:19'),
(381, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:36:09'),
(382, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:38:10'),
(383, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:38:12'),
(384, 10, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:38:12'),
(385, 10, 5, 'keluar_focus', 'Keluar fokus selama 11 detik', '2026-04-18 11:38:19'),
(386, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:38:24'),
(387, 10, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:38:24'),
(388, 10, 5, 'keluar_focus', 'Keluar fokus selama 26 detik', '2026-04-18 11:38:47'),
(389, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:38:52'),
(390, 10, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:38:52'),
(391, 10, 5, 'keluar_focus', 'Keluar fokus selama 22 detik', '2026-04-18 11:39:09'),
(392, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:39:15'),
(393, 10, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:39:15'),
(394, 10, 5, 'keluar_focus', 'Keluar fokus selama 54 detik', '2026-04-18 11:40:05'),
(395, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:40:10'),
(396, 10, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:40:10'),
(397, 10, 5, 'keluar_focus', 'Keluar fokus selama 6 detik', '2026-04-18 11:40:12'),
(398, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:40:16'),
(399, 10, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:40:16'),
(400, 10, 5, 'keluar_focus', 'Keluar fokus selama 29 detik', '2026-04-18 11:40:41'),
(401, 10, 5, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:40:44'),
(402, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:40:51'),
(403, 10, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:40:51'),
(404, 10, 5, 'keluar_focus', 'Keluar fokus selama 20 detik', '2026-04-18 11:41:08'),
(405, 10, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:41:16'),
(406, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:41:16'),
(407, 10, 5, 'keluar_focus', 'Keluar fokus selama 135 detik', '2026-04-18 11:43:26'),
(408, 10, 5, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 11:43:42'),
(409, 10, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:43:56'),
(410, 10, 5, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-04-18 11:43:57'),
(411, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:45:25'),
(412, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:45:25'),
(413, 9, 1, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-04-18 11:45:26'),
(414, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:45:31'),
(415, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:45:31'),
(416, 9, 1, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-04-18 11:45:32'),
(417, 9, 1, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-18 11:45:34'),
(418, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:45:45'),
(419, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:45:45'),
(420, 9, 1, 'keluar_focus', 'Keluar fokus selama 9 detik', '2026-04-18 11:45:50'),
(421, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:45:55'),
(422, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:45:55'),
(423, 9, 1, 'keluar_focus', 'Keluar fokus selama 17 detik', '2026-04-18 11:46:07'),
(424, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:46:12'),
(425, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:46:12'),
(426, 9, 1, 'keluar_focus', 'Keluar fokus selama 15 detik', '2026-04-18 11:46:23'),
(427, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:46:28'),
(428, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:46:28'),
(429, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:46:29'),
(430, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:46:29'),
(431, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:46:29'),
(432, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:46:29'),
(433, 9, 1, 'keluar_focus', 'Keluar fokus selama 7 detik', '2026-04-18 11:46:33'),
(434, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:46:39'),
(435, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:46:39'),
(436, 9, 1, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-18 11:46:39'),
(437, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:46:48'),
(438, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:46:48'),
(439, 9, 1, 'keluar_focus', 'Keluar fokus selama 21 detik', '2026-04-18 11:47:04'),
(440, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:47:10'),
(441, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:47:10'),
(442, 9, 1, 'keluar_focus', 'Keluar fokus selama 24 detik', '2026-04-18 11:47:30'),
(443, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:47:37'),
(444, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:47:37'),
(445, 9, 1, 'keluar_focus', 'Keluar fokus selama 114 detik', '2026-04-18 11:49:27'),
(446, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:49:40'),
(447, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:55:21'),
(448, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:55:21'),
(449, 9, 1, 'keluar_focus', 'Keluar fokus selama 66 detik', '2026-04-18 11:56:23'),
(450, 9, 1, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 11:56:35'),
(451, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:56:40'),
(452, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:56:40'),
(453, 9, 1, 'keluar_focus', 'Keluar fokus selama 18 detik', '2026-04-18 11:56:54'),
(454, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:56:59'),
(455, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:56:59'),
(456, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:57:00'),
(457, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:57:00'),
(458, 9, 1, 'keluar_focus', 'Keluar fokus selama 79 detik', '2026-04-18 11:58:15'),
(459, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:58:20'),
(460, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:58:20'),
(461, 9, 1, 'keluar_focus', 'Keluar fokus selama 6 detik', '2026-04-18 11:58:22'),
(462, 9, 1, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 11:58:26'),
(463, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:58:28'),
(464, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:58:28'),
(465, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:58:31'),
(466, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:58:31'),
(467, 9, 1, 'keluar_focus', 'Keluar fokus selama 25 detik', '2026-04-18 11:58:52'),
(468, 9, 1, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-18 11:58:58'),
(469, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:59:02'),
(470, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:59:02'),
(471, 9, 1, 'keluar_focus', 'Keluar fokus selama 32 detik', '2026-04-18 11:59:30'),
(472, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:59:35'),
(473, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:59:35'),
(474, 9, 1, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-18 11:59:35'),
(475, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:59:38'),
(476, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:59:41'),
(477, 9, 1, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-18 11:59:55'),
(478, 9, 1, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-18 11:59:55'),
(479, 1, 4, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-20 12:19:31'),
(480, 1, 4, 'keluar_focus', 'Keluar fokus selama 3 detik', '2026-04-20 12:19:37'),
(481, 2, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 13:20:29'),
(482, 2, 5, 'keluar_focus', 'Keluar fokus selama 5 detik', '2026-04-20 13:20:30'),
(483, 2, 5, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-20 13:21:37'),
(484, 2, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 13:21:52'),
(485, 2, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 13:21:52'),
(486, 2, 5, 'keluar_focus', 'Keluar fokus selama 31 detik', '2026-04-20 13:22:20'),
(487, 2, 5, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-20 13:22:25'),
(488, 2, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 13:22:30'),
(489, 2, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 13:22:31'),
(490, 2, 5, 'keluar_focus', 'Keluar fokus selama 80 detik', '2026-04-20 13:23:46'),
(491, 2, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 13:23:59'),
(492, 2, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 13:23:59'),
(493, 2, 5, 'keluar_focus', 'Keluar fokus selama 6 detik', '2026-04-20 13:23:59'),
(494, 2, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 13:25:56'),
(495, 2, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 13:26:48'),
(496, 2, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 13:27:09'),
(497, 2, 5, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 13:27:10'),
(498, 3, 4, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 13:58:46'),
(499, 3, 4, 'keluar_focus', 'Keluar fokus selama 4 detik', '2026-04-20 13:58:46'),
(500, 3, 4, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-20 13:59:05'),
(501, 4, 5, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 14:43:01'),
(502, 4, 5, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-20 14:43:01'),
(503, 4, 5, 'keluar_focus', 'Keluar fokus selama 2 detik', '2026-04-20 14:43:05'),
(504, 4, 5, 'keluar_focus', 'Keluar fokus selama 1 detik', '2026-04-20 14:43:29'),
(505, 5, 4, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 14:56:59'),
(506, 5, 4, 'keluar_focus', 'Keluar fokus selama 265 detik', '2026-04-20 15:01:20'),
(507, 5, 4, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 15:01:26'),
(508, 5, 4, 'keluar_tab', 'Meninggalkan halaman ujian lebih dari 3 detik', '2026-04-20 15:01:26'),
(509, 8, 8, 'keluar_tab', 'Meninggalkan halaman ujian selama 5 detik', '2026-04-20 15:47:31'),
(510, 8, 8, 'keluar_tab', 'Meninggalkan halaman ujian selama 3 detik', '2026-04-20 15:47:40'),
(511, 8, 8, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 15:47:40'),
(512, 8, 8, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 15:47:57'),
(513, 8, 8, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 15:48:00'),
(514, 9, 9, 'keluar_tab', 'Meninggalkan halaman ujian selama 4 detik', '2026-04-20 15:56:15'),
(515, 10, 6, 'keluar_tab', 'Meninggalkan halaman ujian selama 3 detik', '2026-04-20 16:08:15'),
(516, 12, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 4 detik', '2026-04-20 18:51:46'),
(517, 12, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 6 detik', '2026-04-20 18:51:53'),
(518, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 18:51:53'),
(519, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 18:52:04'),
(520, 12, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 2 detik', '2026-04-20 18:56:42'),
(521, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 18:56:42'),
(522, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-20 18:57:43'),
(523, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 18:57:47'),
(524, 12, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 5 detik', '2026-04-20 18:58:13'),
(525, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 18:58:13'),
(526, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 18:58:39'),
(527, 12, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 2 detik', '2026-04-20 18:58:39');
INSERT INTO `log_kecurangan` (`id`, `hasil_ujian_id`, `siswa_id`, `jenis_pelanggaran`, `keterangan`, `waktu_pelanggaran`) VALUES
(528, 12, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 4 detik', '2026-04-20 18:58:44'),
(529, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 18:58:44'),
(530, 12, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 2 detik', '2026-04-20 18:59:13'),
(531, 12, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 18:59:13'),
(532, 13, 12, 'keluar_tab', 'Meninggalkan halaman ujian selama 8 detik', '2026-04-20 19:12:24'),
(533, 13, 12, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 19:12:30'),
(534, 13, 12, 'keluar_tab', 'Meninggalkan halaman ujian selama 4 detik', '2026-04-20 19:12:30'),
(535, 13, 12, 'keluar_tab', 'Meninggalkan halaman ujian selama 6 detik', '2026-04-20 19:12:37'),
(536, 13, 12, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 19:12:37'),
(537, 13, 12, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 19:13:03'),
(538, 13, 12, 'keluar_tab', 'Meninggalkan halaman ujian selama 8 detik', '2026-04-20 19:13:03'),
(539, 13, 12, 'keluar_tab', 'Meninggalkan halaman ujian selama 5 detik', '2026-04-20 19:13:45'),
(540, 13, 12, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 19:13:45'),
(541, 13, 12, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 19:13:47'),
(542, 13, 12, 'keluar_tab', 'Meninggalkan halaman ujian selama 1 detik', '2026-04-20 19:13:47'),
(543, 13, 12, 'keluar_tab', 'Meninggalkan halaman ujian selama 6 detik', '2026-04-20 19:13:54'),
(544, 13, 12, 'penalty_activated', 'Penalti 5 menit diaktifkan', '2026-04-20 19:13:54'),
(545, 16, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 5 detik', '2026-04-22 13:57:52'),
(546, 16, 13, 'penalty_activated', 'Penalti 5 menit diaktifkan karena pelanggaran berulang', '2026-04-22 13:58:34'),
(547, 16, 13, 'keluar_tab', 'Meninggalkan halaman ujian selama 8 detik', '2026-04-22 13:58:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_pelajaran`
--

CREATE TABLE `mata_pelajaran` (
  `id` int(11) NOT NULL,
  `kode_mapel` varchar(10) NOT NULL,
  `nama_mapel` varchar(100) NOT NULL,
  `deskripsi` text,
  `kkm` int(11) DEFAULT '70',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `pengaturan`
--

INSERT INTO `pengaturan` (`id`, `nama`, `value`, `created_at`, `updated_at`) VALUES
(1, 'random_soal', '1', '2025-10-11 11:53:38', '2026-04-21 04:52:32'),
(2, 'allow_restart', '1', '2025-10-11 11:53:38', '2026-04-21 04:52:32'),
(7, 'site_name', 'Sistem Ujian Online', '2025-12-06 12:31:40', '2026-04-21 04:52:32'),
(8, 'site_description', 'Platform Ujian Online Sekolah', '2025-12-06 12:31:40', '2026-04-21 04:52:32'),
(9, 'timezone', 'Asia/Jakarta', '2025-12-06 05:51:54', '2026-04-21 04:52:32'),
(92, 'max_file_size', '5242880', '2026-04-22 14:19:04', '2026-04-22 14:19:04'),
(93, 'allowed_extensions', 'jpg,jpeg,png,pdf', '2026-04-22 14:19:04', '2026-04-22 14:19:04'),
(94, 'passing_grade', '60', '2026-04-22 14:19:04', '2026-04-22 14:19:04'),
(95, 'auto_calculate', '1', '2026-04-22 14:19:04', '2026-04-22 14:19:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `profil_sekolah`
--

CREATE TABLE `profil_sekolah` (
  `id` int(11) NOT NULL,
  `nama_sekolah` varchar(200) NOT NULL,
  `npsn` varchar(20) DEFAULT NULL,
  `alamat` text,
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `kepala_sekolah` varchar(100) DEFAULT NULL,
  `nip_kepala` varchar(20) DEFAULT NULL,
  `logo` varchar(255) DEFAULT 'assets/images/logo.png',
  `visi` text,
  `misi` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `profil_sekolah`
--

INSERT INTO `profil_sekolah` (`id`, `nama_sekolah`, `npsn`, `alamat`, `telepon`, `email`, `website`, `kepala_sekolah`, `nip_kepala`, `logo`, `visi`, `misi`, `created_at`, `updated_at`) VALUES
(1, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765013128_6933f6884795b.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-06 09:25:28', '2025-12-06 09:25:28'),
(2, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'https://www.mtsnurulhidayah.sch.idas', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765013128_6933f6884795b.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-09 17:38:43', '2025-12-09 17:38:43'),
(3, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'https://www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765301931_69385eab32fe7.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-09 17:38:51', '2025-12-09 17:38:51'),
(4, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'https://www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765301950_69385ebec268d.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-09 17:39:10', '2025-12-09 17:39:10'),
(5, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'https://www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765352108_693922ac07516.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 07:35:08', '2025-12-10 07:35:08'),
(6, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765361589_693947b52f078.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 10:13:09', '2025-12-10 10:13:09'),
(7, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765361904_693948f035ae2.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 10:18:24', '2025-12-10 10:18:24'),
(8, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765362984_69394d2840f58.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 10:36:24', '2025-12-10 10:36:24'),
(9, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_20251210_175358_693951460f0a4.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 10:53:58', '2025-12-10 10:53:58'),
(10, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_20251210_180208_693953300bd3c.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 11:02:08', '2025-12-10 11:02:08'),
(11, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765365435_693956bb54cd5.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 11:17:15', '2025-12-10 11:17:15'),
(12, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765366188_693959ac07c60.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 11:29:48', '2025-12-10 11:29:48'),
(13, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765366507_69395aeb698cc.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 11:35:07', '2025-12-10 11:35:07'),
(14, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765368360_6939622833e6a.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 12:06:00', '2025-12-10 12:06:00'),
(15, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765368377_693962395c3af.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 12:06:17', '2025-12-10 12:06:17'),
(16, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765369119_6939651f2c53c.png', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 12:18:39', '2025-12-10 12:18:39'),
(17, 'Viralytics', '456755643', 'Grintingan Desa Bladokulon kecamatan TegalSiwalan', '082131290542', 'babbaimar@gmail.com', 'www.mtsnurulhidayah.sch.id', 'lutfillah', '196512312345678901', 'assets/uploads/logos/logo_1765369143_693965380105f.jpg', 'santai tapi pasti', 'yang penting sebat dulu', '2025-12-10 12:19:03', '2025-12-10 12:19:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sesi_ujian`
--

CREATE TABLE `sesi_ujian` (
  `id` int(11) NOT NULL,
  `hasil_ujian_id` int(11) DEFAULT NULL,
  `sisa_waktu` int(11) DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nisn` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kelas` varchar(10) NOT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `soal`
--

CREATE TABLE `soal` (
  `id` int(11) NOT NULL,
  `mapel_id` int(11) DEFAULT NULL,
  `kelas` varchar(10) DEFAULT NULL,
  `pertanyaan` text NOT NULL,
  `gambar_soal` varchar(255) DEFAULT NULL,
  `video_soal` text,
  `opsi_a` text NOT NULL,
  `opsi_a_gambar` varchar(255) DEFAULT NULL,
  `opsi_b` text NOT NULL,
  `opsi_b_gambar` varchar(255) DEFAULT NULL,
  `opsi_c` text NOT NULL,
  `opsi_c_gambar` varchar(255) DEFAULT NULL,
  `opsi_d` text NOT NULL,
  `opsi_e` text,
  `opsi_d_gambar` varchar(255) DEFAULT NULL,
  `jawaban_benar` enum('a','b','c','d','e') NOT NULL,
  `jawaban_kompleks` text COMMENT 'JSON untuk menyimpan multiple jawaban benar (contoh: ["a","c","e"])',
  `skor_per_jawaban` int(11) DEFAULT '0' COMMENT 'Skor per jawaban benar untuk pilihan ganda kompleks',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `skor` int(11) DEFAULT '10',
  `jenis_soal` enum('pilihan_ganda','pilihan_ganda_kompleks','essay','menjodohkan','benar_salah') DEFAULT 'pilihan_ganda',
  `pasangan_jodoh` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `soal_ujian`
--

CREATE TABLE `soal_ujian` (
  `id` int(11) NOT NULL,
  `ujian_id` int(11) DEFAULT NULL,
  `soal_id` int(11) DEFAULT NULL,
  `urutan` int(11) DEFAULT NULL,
  `soal_text` text,
  `gambar_soal` varchar(255) DEFAULT NULL,
  `video_soal` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ujian`
--

CREATE TABLE `ujian` (
  `id` int(11) NOT NULL,
  `mapel_id` int(11) DEFAULT NULL,
  `judul_ujian` varchar(200) NOT NULL,
  `deskripsi` text,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi` int(11) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `kelas_target` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ujian_backup_wib`
--

CREATE TABLE `ujian_backup_wib` (
  `id` int(11) NOT NULL DEFAULT '0',
  `mapel_id` int(11) DEFAULT NULL,
  `judul_ujian` varchar(200) NOT NULL,
  `deskripsi` text,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi` int(11) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `kelas_target` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `ujian_backup_wib`
--

INSERT INTO `ujian_backup_wib` (`id`, `mapel_id`, `judul_ujian`, `deskripsi`, `waktu_mulai`, `waktu_selesai`, `durasi`, `status`, `created_by`, `created_at`, `kelas_target`) VALUES
(14, 10, 'qdfg', NULL, '2025-12-06 12:30:00', '2025-12-06 15:43:00', 193, 'published', 1, '2025-12-06 05:36:24', '4'),
(14, 10, 'qdfg', NULL, '2025-12-06 12:30:00', '2025-12-06 15:43:00', 193, 'published', 1, '2025-12-06 05:36:24', '4');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('guru','siswa') NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `nama_lengkap`, `created_at`) VALUES
(1, 'admin', '$2y$10$g794l35rs8uHtlnDT6VU3.JyuxfwT1eNUoovhb99ackwHauMtCuHC', 'guru', 'admin', '2025-10-11 11:30:22'),
(170, 'Lutfiarega', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guru', 'Guru', '2026-02-07 05:00:48');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi_ujian`
--
ALTER TABLE `absensi_ujian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ujian_siswa` (`ujian_id`,`siswa_id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indeks untuk tabel `berita_acara`
--
ALTER TABLE `berita_acara`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ujian_kelas` (`ujian_id`,`kelas`),
  ADD KEY `guru_pengawas` (`guru_pengawas`);

--
-- Indeks untuk tabel `cheating_logs`
--
ALTER TABLE `cheating_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hasil_ujian_id` (`hasil_ujian_id`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip` (`nip`),
  ADD KEY `idx_guru_user_id` (`user_id`);

--
-- Indeks untuk tabel `guru_kelas`
--
ALTER TABLE `guru_kelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guru_id` (`guru_id`),
  ADD KEY `kelas_id` (`kelas_id`);

--
-- Indeks untuk tabel `guru_mapel`
--
ALTER TABLE `guru_mapel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guru_id` (`guru_id`),
  ADD KEY `mapel_id` (`mapel_id`);

--
-- Indeks untuk tabel `hasil_ujian`
--
ALTER TABLE `hasil_ujian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hasil_ujian_siswa_id` (`siswa_id`),
  ADD KEY `idx_hasil_ujian_ujian_id` (`ujian_id`);

--
-- Indeks untuk tabel `jawaban_essay`
--
ALTER TABLE `jawaban_essay`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_essay_answer` (`hasil_ujian_id`,`soal_id`),
  ADD KEY `idx_essay_hasil_id` (`hasil_ujian_id`),
  ADD KEY `idx_essay_soal_id` (`soal_id`),
  ADD KEY `idx_essay_status` (`status_koreksi`);

--
-- Indeks untuk tabel `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `soal_id` (`soal_id`),
  ADD KEY `idx_jawaban_siswa_hasil_id` (`hasil_ujian_id`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kelas` (`kelas`);

--
-- Indeks untuk tabel `log_kecurangan`
--
ALTER TABLE `log_kecurangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_hasil_id` (`hasil_ujian_id`),
  ADD KEY `idx_log_siswa_id` (`siswa_id`),
  ADD KEY `idx_log_waktu` (`waktu_pelanggaran`);

--
-- Indeks untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_mapel` (`kode_mapel`);

--
-- Indeks untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`),
  ADD KEY `idx_pengaturan_nama` (`nama`);

--
-- Indeks untuk tabel `profil_sekolah`
--
ALTER TABLE `profil_sekolah`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `sesi_ujian`
--
ALTER TABLE `sesi_ujian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hasil_ujian_id` (`hasil_ujian_id`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nisn` (`nisn`),
  ADD KEY `idx_siswa_user_id` (`user_id`),
  ADD KEY `idx_siswa_nisn` (`nisn`);

--
-- Indeks untuk tabel `soal`
--
ALTER TABLE `soal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_soal_mapel_id` (`mapel_id`);

--
-- Indeks untuk tabel `soal_ujian`
--
ALTER TABLE `soal_ujian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ujian_id` (`ujian_id`),
  ADD KEY `soal_id` (`soal_id`);

--
-- Indeks untuk tabel `ujian`
--
ALTER TABLE `ujian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_ujian_mapel_id` (`mapel_id`),
  ADD KEY `idx_ujian_status` (`status`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi_ujian`
--
ALTER TABLE `absensi_ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `berita_acara`
--
ALTER TABLE `berita_acara`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `cheating_logs`
--
ALTER TABLE `cheating_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `guru_kelas`
--
ALTER TABLE `guru_kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT untuk tabel `guru_mapel`
--
ALTER TABLE `guru_mapel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `hasil_ujian`
--
ALTER TABLE `hasil_ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `jawaban_essay`
--
ALTER TABLE `jawaban_essay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `log_kecurangan`
--
ALTER TABLE `log_kecurangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=548;

--
-- AUTO_INCREMENT untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT untuk tabel `profil_sekolah`
--
ALTER TABLE `profil_sekolah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `sesi_ujian`
--
ALTER TABLE `sesi_ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `soal`
--
ALTER TABLE `soal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT untuk tabel `soal_ujian`
--
ALTER TABLE `soal_ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT untuk tabel `ujian`
--
ALTER TABLE `ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi_ujian`
--
ALTER TABLE `absensi_ujian`
  ADD CONSTRAINT `absensi_ujian_ibfk_1` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_ujian_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `berita_acara`
--
ALTER TABLE `berita_acara`
  ADD CONSTRAINT `berita_acara_ibfk_1` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `berita_acara_ibfk_2` FOREIGN KEY (`guru_pengawas`) REFERENCES `guru` (`id`);

--
-- Ketidakleluasaan untuk tabel `cheating_logs`
--
ALTER TABLE `cheating_logs`
  ADD CONSTRAINT `cheating_logs_ibfk_1` FOREIGN KEY (`hasil_ujian_id`) REFERENCES `hasil_ujian` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD CONSTRAINT `guru_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `guru_kelas`
--
ALTER TABLE `guru_kelas`
  ADD CONSTRAINT `guru_kelas_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `guru_mapel`
--
ALTER TABLE `guru_mapel`
  ADD CONSTRAINT `guru_mapel_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guru_mapel_ibfk_2` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `hasil_ujian`
--
ALTER TABLE `hasil_ujian`
  ADD CONSTRAINT `hasil_ujian_ibfk_1` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`),
  ADD CONSTRAINT `hasil_ujian_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`);

--
-- Ketidakleluasaan untuk tabel `jawaban_essay`
--
ALTER TABLE `jawaban_essay`
  ADD CONSTRAINT `jawaban_essay_ibfk_1` FOREIGN KEY (`hasil_ujian_id`) REFERENCES `hasil_ujian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jawaban_essay_ibfk_2` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`);

--
-- Ketidakleluasaan untuk tabel `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  ADD CONSTRAINT `jawaban_siswa_ibfk_1` FOREIGN KEY (`hasil_ujian_id`) REFERENCES `hasil_ujian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jawaban_siswa_ibfk_2` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`);

--
-- Ketidakleluasaan untuk tabel `sesi_ujian`
--
ALTER TABLE `sesi_ujian`
  ADD CONSTRAINT `sesi_ujian_ibfk_1` FOREIGN KEY (`hasil_ujian_id`) REFERENCES `hasil_ujian` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `soal`
--
ALTER TABLE `soal`
  ADD CONSTRAINT `soal_ibfk_1` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`),
  ADD CONSTRAINT `soal_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `guru` (`id`);

--
-- Ketidakleluasaan untuk tabel `soal_ujian`
--
ALTER TABLE `soal_ujian`
  ADD CONSTRAINT `soal_ujian_ibfk_1` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `soal_ujian_ibfk_2` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ujian`
--
ALTER TABLE `ujian`
  ADD CONSTRAINT `ujian_ibfk_1` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`),
  ADD CONSTRAINT `ujian_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `guru` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
