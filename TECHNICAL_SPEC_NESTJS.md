# Spesifikasi Teknis — UjianOnline Rebuild (NestJS)

> **Versi Dokumen:** 1.0.0  
> **Tanggal:** 2026-06-03  
> **Status:** Draft — Single Source of Truth  
> **Aplikasi Asal:** UjianOnline v3.10 (PHP Procedural + MySQL)  
> **Target Rebuild:** NestJS + TypeScript + PostgreSQL + Prisma

---

## Daftar Isi

1. [Gambaran Sistem](#1-gambaran-sistem)
2. [Arsitektur Sistem](#2-arsitektur-sistem)
3. [Struktur Project](#3-struktur-project)
4. [Database Design](#4-database-design)
5. [Prisma ORM Design](#5-prisma-orm-design)
6. [Authentication & Authorization](#6-authentication--authorization)
7. [Security Design](#7-security-design)
8. [Modul Aplikasi](#8-modul-aplikasi)
9. [API Documentation](#9-api-documentation)
10. [Background Jobs & Queue](#10-background-jobs--queue)
11. [Event Driven Architecture](#11-event-driven-architecture)
12. [Caching Strategy](#12-caching-strategy)
13. [Logging & Monitoring](#13-logging--monitoring)
14. [File Storage](#14-file-storage)
15. [Testing Strategy](#15-testing-strategy)
16. [Deployment Architecture](#16-deployment-architecture)
17. [CI/CD Pipeline](#17-cicd-pipeline)
18. [Non Functional Requirements](#18-non-functional-requirements)
19. [Future Development](#19-future-development)

---

# 1. Gambaran Sistem

## 1.1 Latar Belakang

UjianOnline adalah sistem manajemen ujian daring yang dibangun untuk kebutuhan sekolah di Indonesia. Versi awal (v3.10) dikembangkan dengan PHP prosedural murni dan MySQL yang berjalan di atas XAMPP. Meskipun fungsional, arsitektur lama memiliki keterbatasan serius: tidak ada pemisahan concerns yang jelas, logika bisnis tercampur dengan presentasi, tidak ada API terstandarisasi, sulit di-scale, dan tidak mendukung integrasi dengan sistem eksternal.

Rebuild ini bertujuan menghadirkan sistem baru berbasis **NestJS** sebagai pure REST API backend, memisahkan sepenuhnya lapisan data, logika bisnis, dan presentasi. Sistem baru dirancang untuk bisa melayani berbagai klien (web, mobile, third-party) melalui API yang terdokumentasi dengan baik.

## 1.2 Tujuan Sistem

- Menyediakan platform ujian daring yang aman, andal, dan dapat diskalakan untuk institusi pendidikan
- Mendukung berbagai jenis soal (pilihan ganda, essay, menjodohkan, benar/salah)
- Memastikan integritas ujian melalui sistem anti-kecurangan
- Menyediakan reporting dan analitik yang komprehensif
- Memungkinkan integrasi dengan sistem informasi sekolah (SIS) melalui API

## 1.3 Ruang Lingkup Aplikasi

**Dalam Cakupan:**
- Manajemen pengguna (Admin, Guru, Siswa) dengan RBAC
- Manajemen bank soal dengan media (gambar, video)
- Manajemen ujian (konfigurasi, penjadwalan, publikasi)
- Pelaksanaan ujian dengan pengawasan real-time
- Penilaian otomatis dan manual (essay)
- Laporan nilai, absensi, berita acara
- Export PDF dan Excel
- Upload media (gambar soal, foto profil)
- Pengaturan sistem dan profil sekolah
- Monitoring kecurangan

**Di Luar Cakupan (v1.0):**
- Multi-tenant / multi-sekolah
- Payment gateway
- Video proctoring (webcam)
- Mode olimpiade multi-babak

## 1.4 Aktor yang Terlibat

| Aktor | Deskripsi | Akses |
|-------|-----------|-------|
| **Admin** | Guru dengan flag `is_admin = true`. Mengelola seluruh sistem | Full access |
| **Guru** | Pengajar yang membuat soal dan ujian | Scope data milik sendiri |
| **Siswa** | Peserta ujian | Read-only + mengerjakan ujian |
| **System** | Proses otomatis (cron job, queue worker) | Internal only |

## 1.5 Use Case Diagram (Tekstual)

```
╔══════════════════════════════════════════════════════════════╗
║                     SISTEM UJIAN ONLINE                      ║
╠══════════════════════════════════════════════════════════════╣
║                                                              ║
║  ADMIN ──────────────────────────────────────────────────   ║
║    │  ├── Kelola Guru & Siswa                               ║
║    │  ├── Kelola Mata Pelajaran & Kelas                     ║
║    │  ├── Kelola Semua Soal & Ujian                         ║
║    │  ├── Lihat Semua Hasil Ujian                           ║
║    │  ├── Konfigurasi Sistem                                ║
║    │  └── Kelola Profil Sekolah                             ║
║                                                              ║
║  GURU ────────────────────────────────────────────────────  ║
║    │  ├── Kelola Bank Soal (CRUD + Import Excel)            ║
║    │  ├── Buat & Kelola Ujian                               ║
║    │  ├── Tambah Soal ke Ujian (Manual/Auto)                ║
║    │  ├── Monitoring Ujian Real-time                        ║
║    │  ├── Koreksi Essay                                     ║
║    │  ├── Lihat & Export Hasil Ujian                        ║
║    │  ├── Kelola Absensi                                    ║
║    │  └── Buat Berita Acara                                 ║
║                                                              ║
║  SISWA ───────────────────────────────────────────────────  ║
║    │  ├── Lihat Daftar Ujian Tersedia                       ║
║    │  ├── Kerjakan Ujian                                    ║
║    │  ├── Lanjutkan Ujian (Resume)                          ║
║    │  └── Lihat Hasil & Riwayat Ujian                       ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
```

## 1.6 Business Flow Diagram

```
ALUR UTAMA — SIKLUS UJIAN

[Admin/Guru] → Buat Mata Pelajaran
           → Daftarkan Siswa & Guru
           → Assign Guru ke Mapel/Kelas

[Guru] → Buat Bank Soal (input manual / import Excel)
       → Buat Ujian (judul, waktu, durasi, kelas target)
       → Tambah Soal ke Ujian (manual pilih / random otomatis)
       → Publish Ujian

[Sistem] → Ujian aktif otomatis berdasarkan waktu
         → Siswa hanya melihat ujian yang relevan (kelas + waktu)

[Siswa] → Login → Lihat Daftar Ujian → Klik Mulai Ujian
        → Sistem buat sesi ujian (hasil_ujian, sesi_ujian)
        → Kerjakan soal satu per satu (jawaban disimpan real-time)
        → Sistem monitor aktivitas (tab switch, focus loss)
        → Selesai / Waktu habis → Sistem hitung nilai otomatis

[Guru] → Koreksi jawaban essay (jika ada)
       → Lihat rekap nilai, export Excel/PDF
       → Buat berita acara & cetak absensi

[Admin] → Lihat laporan lintas kelas & mapel
        → Export data rekap
```

## 1.7 Activity Diagram — Mengerjakan Ujian

```
[Siswa Login]
      │
      ▼
[GET /exams/available] ─── Sistem filter: waktu aktif + kelas siswa
      │
      ▼
[POST /exams/{id}/start] ── Cek: sudah ada sesi? → resume
      │                  ── Buat hasil_ujian + sesi_ujian
      ▼
[GET /exam-sessions/{id}/question?order=N]
      │
      ▼
[POST /exam-sessions/{id}/answer] ─ Simpan jawaban (upsert)
      │                           ─ Update last_activity sesi
      │
      ▼
[Masih ada soal?] ─YES→ Kembali ke GET question
      │
     NO
      ▼
[POST /exam-sessions/{id}/finish]
      │
      ├── Hitung nilai otomatis (pilihan ganda)
      ├── Set status = 'selesai'
      ├── Update waktu_selesai
      └── Emit ExamFinishedEvent → queue: notify guru

[Return nilai kepada siswa]
```

## 1.8 Sequence Diagram — Login Flow

```
Client          AuthController      AuthService         PrismaService      Redis
  │                   │                  │                    │               │
  │ POST /auth/login  │                  │                    │               │
  │──────────────────►│                  │                    │               │
  │                   │ login(dto)       │                    │               │
  │                   │─────────────────►│                    │               │
  │                   │                  │ findByUsername()   │               │
  │                   │                  │───────────────────►│               │
  │                   │                  │◄───────────────────│               │
  │                   │                  │ bcrypt.compare()   │               │
  │                   │                  │ signAccessToken()  │               │
  │                   │                  │ signRefreshToken() │               │
  │                   │                  │ storeRefreshToken()│               │
  │                   │                  │────────────────────────────────────►│
  │                   │◄─────────────────│                    │               │
  │◄──────────────────│ {accessToken,    │                    │               │
  │  200 OK           │  refreshToken}   │                    │               │
```

---

# 2. Arsitektur Sistem

## 2.1 Clean Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     PRESENTATION LAYER                       │
│  Controllers · Guards · Interceptors · Pipes · Decorators   │
├─────────────────────────────────────────────────────────────┤
│                     APPLICATION LAYER                        │
│         Services · Use Cases · DTOs · Validators            │
├─────────────────────────────────────────────────────────────┤
│                       DOMAIN LAYER                           │
│     Entities · Domain Events · Business Rules · Interfaces  │
├─────────────────────────────────────────────────────────────┤
│                   INFRASTRUCTURE LAYER                       │
│  Repositories (Prisma) · Redis · S3 · Email · Queue Workers │
└─────────────────────────────────────────────────────────────┘
```

- **Presentation Layer** tidak boleh berisi logika bisnis
- **Application Layer** mengatur alur use case, tidak tahu detail implementasi infrastruktur
- **Domain Layer** murni TypeScript, tidak boleh import NestJS atau library eksternal
- **Infrastructure Layer** mengimplementasikan interfaces yang didefinisikan domain

## 2.2 Modular Architecture NestJS

Setiap domain bisnis dienkapsulasi dalam modul NestJS yang mandiri. Modul berkomunikasi melalui service injection, events, atau queue — tidak boleh import repository modul lain secara langsung.

```
AppModule
├── CoreModule (global)
│   ├── DatabaseModule (Prisma)
│   ├── RedisModule
│   ├── ConfigModule
│   └── LoggerModule
├── AuthModule
├── UserModule
├── GuruModule
├── SiswaModule
├── MataPelajaranModule
├── KelasModule
├── SoalModule
├── UjianModule
├── SesiUjianModule
├── HasilUjianModule
├── KoreksiEssayModule
├── AbsensiModule
├── BeritaAcaraModule
├── MonitoringModule
├── PengaturanModule
├── ProfilSekolahModule
├── ReportModule
└── StorageModule
```

## 2.3 Layer Architecture Detail

### Controller Layer
- Menerima HTTP request, validasi input via Pipes (class-validator)
- Meneruskan ke Service layer
- Tidak boleh berisi logika bisnis
- Dekorasi Swagger (@ApiTags, @ApiOperation, @ApiResponse)
- Guard untuk autentikasi & otorisasi

### Service Layer
- Implementasi use case bisnis
- Orkestrasi panggilan ke repository, event emitter, queue
- Transaction management
- Mengembalikan domain object atau DTO

### Repository Layer
- Abstraksi akses database via Prisma
- Setiap modul memiliki repository-nya sendiri
- Implementasi interface `IXxxRepository`
- Query builder, pagination, filtering

### Infrastructure Layer
- PrismaService (database connection pool)
- RedisService (caching, queue, session)
- StorageService (file upload local/S3)
- MailService (notifikasi email)
- BullMQ workers

### Shared Module
- Guards (JwtAuthGuard, RolesGuard)
- Interceptors (LoggingInterceptor, TransformInterceptor)
- Pipes (ValidationPipe, ParseIntPipe)
- Decorators (@CurrentUser, @Roles, @Public)
- Filters (HttpExceptionFilter, PrismaExceptionFilter)

### Common Module
- Constants (roles, status, error codes)
- Enums (Role, UjianStatus, JenisSoal, dll)
- Interfaces (IPaginatedResult, IQueryOptions)
- Utils (pagination, date, string helpers)
- Base classes (BaseRepository, BaseService)

## 2.4 Dependency Flow

```
Controller
    │ inject
    ▼
  Service ──inject──► Repository ──uses──► PrismaService
    │                                           │
    │ inject                                    └──► PostgreSQL
    ▼
EventEmitter2 ──emit──► EventListener ──► Queue
                                              │
                                              ▼
                                         BullMQ Worker
```

---

# 3. Struktur Project

```
ujianonline-api/
├── src/
│   ├── main.ts                    # Bootstrap NestJS app
│   ├── app.module.ts              # Root module
│   ├── app.controller.ts          # Health check endpoint
│   │
│   ├── modules/                   # Domain modules (business features)
│   │   ├── auth/
│   │   ├── users/
│   │   ├── guru/
│   │   ├── siswa/
│   │   ├── mata-pelajaran/
│   │   ├── kelas/
│   │   ├── soal/
│   │   ├── ujian/
│   │   ├── sesi-ujian/
│   │   ├── hasil-ujian/
│   │   ├── koreksi-essay/
│   │   ├── absensi/
│   │   ├── berita-acara/
│   │   ├── monitoring/
│   │   ├── pengaturan/
│   │   ├── profil-sekolah/
│   │   └── report/
│   │
│   ├── common/                    # Shared code tanpa dependency bisnis
│   │   ├── constants/
│   │   │   ├── roles.constant.ts
│   │   │   ├── error-codes.constant.ts
│   │   │   └── cache-keys.constant.ts
│   │   ├── decorators/
│   │   │   ├── current-user.decorator.ts
│   │   │   ├── roles.decorator.ts
│   │   │   └── public.decorator.ts
│   │   ├── enums/
│   │   │   ├── role.enum.ts
│   │   │   ├── ujian-status.enum.ts
│   │   │   ├── jenis-soal.enum.ts
│   │   │   └── status-koreksi.enum.ts
│   │   ├── filters/
│   │   │   ├── http-exception.filter.ts
│   │   │   └── prisma-exception.filter.ts
│   │   ├── guards/
│   │   │   ├── jwt-auth.guard.ts
│   │   │   └── roles.guard.ts
│   │   ├── interceptors/
│   │   │   ├── logging.interceptor.ts
│   │   │   ├── transform.interceptor.ts
│   │   │   └── audit.interceptor.ts
│   │   ├── interfaces/
│   │   │   ├── paginated-result.interface.ts
│   │   │   ├── query-options.interface.ts
│   │   │   └── jwt-payload.interface.ts
│   │   ├── pipes/
│   │   │   └── parse-sort.pipe.ts
│   │   └── utils/
│   │       ├── pagination.util.ts
│   │       ├── date.util.ts
│   │       ├── string.util.ts
│   │       └── score.util.ts
│   │
│   ├── config/                    # Konfigurasi aplikasi
│   │   ├── app.config.ts
│   │   ├── database.config.ts
│   │   ├── jwt.config.ts
│   │   ├── redis.config.ts
│   │   ├── storage.config.ts
│   │   └── mail.config.ts
│   │
│   ├── database/                  # Database infrastructure
│   │   ├── prisma.service.ts
│   │   ├── prisma.module.ts
│   │   └── base.repository.ts
│   │
│   ├── infrastructure/            # External services implementation
│   │   ├── redis/
│   │   │   ├── redis.service.ts
│   │   │   └── redis.module.ts
│   │   ├── storage/
│   │   │   ├── storage.service.ts
│   │   │   ├── local-storage.provider.ts
│   │   │   └── s3-storage.provider.ts
│   │   └── mail/
│   │       ├── mail.service.ts
│   │       └── mail.module.ts
│   │
│   ├── shared/                    # Modul NestJS yang di-export global
│   │   └── shared.module.ts       # Re-export guards, interceptors, pipes
│   │
│   ├── jobs/                      # Scheduled cron jobs
│   │   ├── exam-auto-close.job.ts
│   │   └── cleanup-sessions.job.ts
│   │
│   ├── events/                    # Domain event definitions & listeners
│   │   ├── exam-finished.event.ts
│   │   ├── exam-started.event.ts
│   │   └── exam-finished.listener.ts
│   │
│   ├── queues/                    # BullMQ queue definitions & processors
│   │   ├── queues.constant.ts
│   │   ├── score-calculation.processor.ts
│   │   └── notification.processor.ts
│   │
│   └── providers/                 # Custom NestJS providers
│       ├── prisma-exception.provider.ts
│       └── app-logger.provider.ts
│
├── prisma/
│   ├── schema.prisma
│   └── migrations/
│
├── test/
│   ├── unit/
│   ├── integration/
│   └── e2e/
│
├── docker/
│   ├── Dockerfile
│   ├── Dockerfile.prod
│   └── nginx.conf
│
├── .env
├── .env.example
├── docker-compose.yml
├── docker-compose.prod.yml
├── nest-cli.json
├── tsconfig.json
├── jest.config.ts
└── package.json
```

### Tanggung Jawab Masing-masing Folder

| Folder | Tanggung Jawab |
|--------|---------------|
| `src/modules/` | Satu subfolder per domain bisnis. Masing-masing berisi controller, service, repository, dto, dan entity. |
| `src/common/` | Kode yang digunakan lintas modul: guard, decorator, filter, interceptor, enum, constant, interface, util. Tidak boleh import dari `modules/`. |
| `src/config/` | Typed configuration menggunakan `@nestjs/config`. Setiap file config merupakan registered config namespace. |
| `src/database/` | PrismaService dan base repository abstraction. Diregistrasi sebagai global module. |
| `src/infrastructure/` | Implementasi konkret untuk layanan eksternal (Redis, Storage, Mail). Diekspos sebagai NestJS module. |
| `src/shared/` | Global NestJS module yang mengexport common providers agar tersedia tanpa import manual di setiap modul. |
| `src/jobs/` | `@Cron()` jobs menggunakan `@nestjs/schedule`. |
| `src/events/` | Event class definitions dan `@OnEvent()` listeners. |
| `src/queues/` | BullMQ queue names, job data interfaces, dan `@Processor()` classes. |
| `src/providers/` | Custom NestJS provider factories. |

---

# 4. Database Design

## 4.1 Prinsip Desain

- **Soft Delete** — semua tabel utama menggunakan kolom `deleted_at TIMESTAMP NULL`. Record tidak pernah dihapus fisik kecuali tabel log/audit.
- **Audit Columns** — semua tabel memiliki `created_at`, `updated_at`, `created_by UUID NULL`, `updated_by UUID NULL`.
- **UUID Primary Key** — menggunakan UUID v4 sebagai PK untuk seluruh tabel (mendukung distributed system dan mencegah enumeration attack).
- **Timezone** — semua timestamp disimpan sebagai UTC di database. Konversi ke WIB (Asia/Jakarta) dilakukan di application layer.
- **Normalisasi** — target minimal 3NF. Denormalisasi diizinkan hanya pada tabel snapshot ujian (`soal_ujian`) untuk menjaga integritas historis.

## 4.2 ERD (Entity Relationship Diagram)

```
users ──1──< guru >──< guru_kelas >──< kelas
  │         guru >──< guru_mapel >──< mata_pelajaran
  │
  └──1──< siswa
              │
              └──< absensi_ujian
              └──< hasil_ujian ──1──< sesi_ujian
                                └──< jawaban_siswa
                                └──< jawaban_essay
                                └──< log_kecurangan
                                └──< cheating_logs

ujian ──M──< soal_ujian >──M── soal ──M──< mata_pelajaran
ujian ──1──< hasil_ujian
ujian ──1──< absensi_ujian
ujian ──1──< berita_acara ──M── guru (pengawas)

mata_pelajaran ──1──< soal
mata_pelajaran ──1──< ujian

pengaturan (key-value singleton)
profil_sekolah (singleton)
```

## 4.3 Definisi Tabel

---

### Tabel: `users`
**Tujuan:** Menyimpan kredensial autentikasi semua pengguna sistem.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | Primary key |
| `username` | VARCHAR(50) | UNIQUE, NOT NULL | — | Username login |
| `password` | VARCHAR(255) | NOT NULL | — | bcrypt hash |
| `role` | ENUM | NOT NULL | — | `admin`, `guru`, `siswa` |
| `is_active` | BOOLEAN | NOT NULL | true | Soft-disable akun |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |
| `deleted_at` | TIMESTAMP | NULL | NULL | Soft delete |

**Index:** `UNIQUE(username)`, `INDEX(role)`, `INDEX(deleted_at)`

---

### Tabel: `guru`
**Tujuan:** Profil lengkap guru/pengajar.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `user_id` | UUID | FK → users.id, UNIQUE | — | |
| `nip` | VARCHAR(30) | UNIQUE, NULL | NULL | Nomor Induk Pegawai |
| `nama` | VARCHAR(150) | NOT NULL | — | Nama lengkap |
| `email` | VARCHAR(150) | NULL | NULL | |
| `no_telp` | VARCHAR(20) | NULL | NULL | |
| `alamat` | TEXT | NULL | NULL | |
| `foto` | VARCHAR(500) | NULL | NULL | Path/URL foto |
| `is_admin` | BOOLEAN | NOT NULL | false | Flag superuser |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |
| `deleted_at` | TIMESTAMP | NULL | NULL | |

**Index:** `UNIQUE(user_id)`, `UNIQUE(nip) WHERE nip IS NOT NULL`

---

### Tabel: `siswa`
**Tujuan:** Profil lengkap siswa.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `user_id` | UUID | FK → users.id, UNIQUE | — | |
| `nisn` | VARCHAR(20) | UNIQUE, NOT NULL | — | Nomor Induk Siswa Nasional |
| `nama` | VARCHAR(150) | NOT NULL | — | |
| `kelas_id` | UUID | FK → kelas.id | — | |
| `jenis_kelamin` | ENUM | NULL | NULL | `L`, `P` |
| `tanggal_lahir` | DATE | NULL | NULL | |
| `alamat` | TEXT | NULL | NULL | |
| `foto` | VARCHAR(500) | NULL | NULL | |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |
| `deleted_at` | TIMESTAMP | NULL | NULL | |

**Index:** `UNIQUE(user_id)`, `UNIQUE(nisn)`, `INDEX(kelas_id)`

---

### Tabel: `kelas`
**Tujuan:** Master data kelas/tingkat.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `nama` | VARCHAR(20) | UNIQUE, NOT NULL | — | Contoh: "7A", "8B", "9C" |
| `tingkat` | INTEGER | NULL | NULL | 7, 8, atau 9 |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |
| `deleted_at` | TIMESTAMP | NULL | NULL | |

---

### Tabel: `mata_pelajaran`
**Tujuan:** Master data mata pelajaran.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `kode` | VARCHAR(20) | UNIQUE, NOT NULL | — | Kode mapel unik |
| `nama` | VARCHAR(150) | NOT NULL | — | Nama mapel |
| `deskripsi` | TEXT | NULL | NULL | |
| `kkm` | INTEGER | NOT NULL | 70 | Kriteria Ketuntasan Minimal |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |
| `deleted_at` | TIMESTAMP | NULL | NULL | |

---

### Tabel: `guru_kelas`
**Tujuan:** Relasi many-to-many antara guru dan kelas (penugasan mengajar).

| Kolom | Tipe | Constraint | Default |
|-------|------|-----------|---------|
| `id` | UUID | PK | gen_random_uuid() |
| `guru_id` | UUID | FK → guru.id | — |
| `kelas_id` | UUID | FK → kelas.id | — |
| `created_at` | TIMESTAMP | NOT NULL | NOW() |

**Index:** `UNIQUE(guru_id, kelas_id)`

---

### Tabel: `guru_mapel`
**Tujuan:** Relasi many-to-many antara guru dan mata pelajaran.

| Kolom | Tipe | Constraint | Default |
|-------|------|-----------|---------|
| `id` | UUID | PK | gen_random_uuid() |
| `guru_id` | UUID | FK → guru.id | — |
| `mapel_id` | UUID | FK → mata_pelajaran.id | — |
| `created_at` | TIMESTAMP | NOT NULL | NOW() |

**Index:** `UNIQUE(guru_id, mapel_id)`

---

### Tabel: `soal`
**Tujuan:** Bank soal untuk semua mata pelajaran.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `mapel_id` | UUID | FK → mata_pelajaran.id | — | |
| `created_by` | UUID | FK → guru.id | — | |
| `jenis` | ENUM | NOT NULL | — | `pilihan_ganda`, `essay`, `menjodohkan`, `benar_salah`, `pilihan_ganda_kompleks` |
| `pertanyaan` | TEXT | NOT NULL | — | Teks soal |
| `gambar_soal` | VARCHAR(500) | NULL | NULL | Path/URL gambar |
| `video_soal` | VARCHAR(500) | NULL | NULL | Path/URL atau YouTube URL |
| `opsi_a` | TEXT | NULL | NULL | |
| `opsi_b` | TEXT | NULL | NULL | |
| `opsi_c` | TEXT | NULL | NULL | |
| `opsi_d` | TEXT | NULL | NULL | |
| `opsi_e` | TEXT | NULL | NULL | |
| `opsi_a_gambar` | VARCHAR(500) | NULL | NULL | |
| `opsi_b_gambar` | VARCHAR(500) | NULL | NULL | |
| `opsi_c_gambar` | VARCHAR(500) | NULL | NULL | |
| `opsi_d_gambar` | VARCHAR(500) | NULL | NULL | |
| `opsi_e_gambar` | VARCHAR(500) | NULL | NULL | |
| `jawaban_benar` | VARCHAR(5) | NULL | NULL | `a`, `b`, `c`, `d`, `e` |
| `jawaban_kompleks` | JSONB | NULL | NULL | Array jawaban benar untuk kompleks |
| `pasangan_jodoh` | JSONB | NULL | NULL | Array pasangan untuk menjodohkan |
| `skor` | DECIMAL(5,2) | NOT NULL | 10 | Poin soal |
| `kelas_target` | UUID[] | NULL | NULL | Array kelas_id yang bisa memakai soal ini |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |
| `deleted_at` | TIMESTAMP | NULL | NULL | |

**Index:** `INDEX(mapel_id)`, `INDEX(created_by)`, `INDEX(jenis)`, `GIN(kelas_target)`

---

### Tabel: `ujian`
**Tujuan:** Konfigurasi ujian yang dibuat guru.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `mapel_id` | UUID | FK → mata_pelajaran.id | — | |
| `created_by` | UUID | FK → guru.id | — | |
| `judul` | VARCHAR(300) | NOT NULL | — | |
| `deskripsi` | TEXT | NULL | NULL | |
| `waktu_mulai` | TIMESTAMP | NOT NULL | — | Waktu mulai (UTC) |
| `waktu_selesai` | TIMESTAMP | NOT NULL | — | Waktu selesai (UTC) |
| `durasi_menit` | INTEGER | NOT NULL | — | Durasi pengerjaan |
| `status` | ENUM | NOT NULL | `draft` | `draft`, `published`, `archived` |
| `kelas_target` | UUID[] | NOT NULL | — | Array kelas_id peserta |
| `random_soal` | BOOLEAN | NOT NULL | true | Acak urutan soal |
| `allow_restart` | BOOLEAN | NOT NULL | false | Boleh mengulang |
| `max_kecurangan` | INTEGER | NOT NULL | 3 | Maks pelanggaran sebelum submit paksa |
| `passing_grade` | INTEGER | NOT NULL | 60 | KKM khusus ujian ini |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |
| `deleted_at` | TIMESTAMP | NULL | NULL | |

**Index:** `INDEX(created_by)`, `INDEX(mapel_id)`, `INDEX(status)`, `INDEX(waktu_mulai, waktu_selesai)`, `GIN(kelas_target)`

---

### Tabel: `soal_ujian`
**Tujuan:** Junction table + snapshot soal pada saat ujian dibuat. Data soal disalin agar tidak berubah meski bank soal diupdate.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `ujian_id` | UUID | FK → ujian.id CASCADE | — | |
| `soal_id` | UUID | FK → soal.id | — | Referensi asli |
| `urutan` | INTEGER | NOT NULL | — | Urutan tampil |
| `snapshot_pertanyaan` | TEXT | NOT NULL | — | Salinan teks soal |
| `snapshot_opsi` | JSONB | NOT NULL | — | Salinan semua opsi |
| `snapshot_jawaban_benar` | VARCHAR(5) | NULL | NULL | Salinan kunci jawaban |
| `snapshot_jawaban_kompleks` | JSONB | NULL | NULL | |
| `snapshot_pasangan_jodoh` | JSONB | NULL | NULL | |
| `snapshot_gambar_soal` | VARCHAR(500) | NULL | NULL | |
| `snapshot_video_soal` | VARCHAR(500) | NULL | NULL | |
| `jenis` | ENUM | NOT NULL | — | Salinan jenis soal |
| `skor` | DECIMAL(5,2) | NOT NULL | — | Salinan skor |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |

**Index:** `UNIQUE(ujian_id, soal_id)`, `INDEX(ujian_id, urutan)`

---

### Tabel: `hasil_ujian`
**Tujuan:** Record satu sesi pengerjaan ujian oleh satu siswa.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `ujian_id` | UUID | FK → ujian.id | — | |
| `siswa_id` | UUID | FK → siswa.id | — | |
| `nilai` | DECIMAL(5,2) | NULL | NULL | 0.00–100.00 |
| `nilai_essay` | DECIMAL(5,2) | NULL | NULL | Nilai komponen essay |
| `waktu_mulai` | TIMESTAMP | NOT NULL | NOW() | Kapan siswa mulai |
| `waktu_selesai` | TIMESTAMP | NULL | NULL | Kapan siswa selesai |
| `status` | ENUM | NOT NULL | `sedang_ujian` | `sedang_ujian`, `selesai`, `expired` |
| `attempt_number` | INTEGER | NOT NULL | 1 | Ke berapa kali ujian |
| `ip_address` | VARCHAR(45) | NULL | NULL | IP saat ujian |
| `user_agent` | TEXT | NULL | NULL | Browser info |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |

**Index:** `INDEX(ujian_id, siswa_id)`, `INDEX(siswa_id)`, `INDEX(status)`  
**Constraint:** `UNIQUE(ujian_id, siswa_id, attempt_number)`

---

### Tabel: `sesi_ujian`
**Tujuan:** Tracking state aktif satu sesi ujian (untuk resume jika koneksi terputus).

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `hasil_ujian_id` | UUID | FK → hasil_ujian.id, UNIQUE, CASCADE | — | |
| `sisa_waktu_detik` | INTEGER | NOT NULL | — | Sisa waktu dalam detik |
| `soal_terakhir` | INTEGER | NOT NULL | 1 | Nomor soal terakhir dikunjungi |
| `last_activity` | TIMESTAMP | NOT NULL | NOW() | Heartbeat terakhir |

---

### Tabel: `jawaban_siswa`
**Tujuan:** Jawaban pilihan ganda per soal per sesi ujian.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `hasil_ujian_id` | UUID | FK → hasil_ujian.id CASCADE | — | |
| `soal_ujian_id` | UUID | FK → soal_ujian.id | — | Merujuk ke snapshot |
| `jawaban` | VARCHAR(10) | NULL | NULL | Pilihan siswa |
| `is_benar` | BOOLEAN | NULL | NULL | Dihitung saat finish |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |

**Index:** `UNIQUE(hasil_ujian_id, soal_ujian_id)`, `INDEX(hasil_ujian_id)`

---

### Tabel: `jawaban_essay`
**Tujuan:** Jawaban essay beserta penilaian guru.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `hasil_ujian_id` | UUID | FK → hasil_ujian.id CASCADE | — | |
| `soal_ujian_id` | UUID | FK → soal_ujian.id | — | |
| `jawaban_text` | TEXT | NULL | NULL | Jawaban siswa |
| `skor_essay` | DECIMAL(5,2) | NULL | NULL | Nilai dari guru |
| `komentar_guru` | TEXT | NULL | NULL | |
| `status_koreksi` | ENUM | NOT NULL | `belum` | `belum`, `sudah` |
| `dikoreksi_oleh` | UUID | NULL | NULL | FK → guru.id |
| `dikoreksi_at` | TIMESTAMP | NULL | NULL | |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |

**Index:** `UNIQUE(hasil_ujian_id, soal_ujian_id)`, `INDEX(status_koreksi)`

---

### Tabel: `log_kecurangan`
**Tujuan:** Log setiap pelanggaran integritas ujian oleh siswa.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `hasil_ujian_id` | UUID | FK → hasil_ujian.id CASCADE | — | |
| `siswa_id` | UUID | FK → siswa.id | — | Denormalized untuk query |
| `jenis` | ENUM | NOT NULL | — | `keluar_tab`, `keluar_focus`, `attempt_leave`, `kembali_fokus`, `penalty_activated` |
| `keterangan` | TEXT | NULL | NULL | |
| `terjadi_at` | TIMESTAMP | NOT NULL | NOW() | |

**Index:** `INDEX(hasil_ujian_id)`, `INDEX(siswa_id, terjadi_at)`

---

### Tabel: `absensi_ujian`
**Tujuan:** Rekap kehadiran siswa dalam sebuah ujian.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `ujian_id` | UUID | FK → ujian.id CASCADE | — | |
| `siswa_id` | UUID | FK → siswa.id | — | |
| `status` | ENUM | NOT NULL | `tidak_hadir` | `hadir`, `tidak_hadir`, `izin`, `sakit` |
| `waktu_hadir` | TIMESTAMP | NULL | NULL | |
| `waktu_pulang` | TIMESTAMP | NULL | NULL | |
| `keterangan` | TEXT | NULL | NULL | |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |

**Index:** `UNIQUE(ujian_id, siswa_id)`

---

### Tabel: `berita_acara`
**Tujuan:** Dokumen resmi pelaksanaan ujian.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `ujian_id` | UUID | FK → ujian.id CASCADE | — | |
| `guru_pengawas_id` | UUID | FK → guru.id | — | |
| `tanggal_ujian` | DATE | NOT NULL | — | |
| `waktu_mulai` | TIME | NOT NULL | — | |
| `waktu_selesai` | TIME | NOT NULL | — | |
| `ruangan` | VARCHAR(100) | NULL | NULL | |
| `jumlah_peserta` | INTEGER | NOT NULL | 0 | |
| `jumlah_hadir` | INTEGER | NOT NULL | 0 | |
| `jumlah_tidak_hadir` | INTEGER | NOT NULL | 0 | |
| `kejadian_penting` | TEXT | NULL | NULL | |
| `kendala_teknis` | TEXT | NULL | NULL | |
| `tindak_lanjut` | TEXT | NULL | NULL | |
| `ttd_guru` | VARCHAR(500) | NULL | NULL | Path gambar TTD |
| `status` | ENUM | NOT NULL | `draft` | `draft`, `selesai`, `diverifikasi` |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |

---

### Tabel: `pengaturan`
**Tujuan:** Key-value store konfigurasi sistem.

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `nama` | VARCHAR(100) | UNIQUE, NOT NULL | — | Kunci setting |
| `value` | TEXT | NOT NULL | — | Nilai setting |
| `deskripsi` | TEXT | NULL | NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() | |

**Default Rows:**

| nama | value | deskripsi |
|------|-------|-----------|
| `random_soal` | `true` | Acak urutan soal |
| `allow_restart` | `false` | Izinkan ulang ujian |
| `passing_grade` | `60` | KKM default sistem |
| `auto_calculate` | `true` | Hitung nilai otomatis |
| `site_name` | `Sistem Ujian Online` | Nama aplikasi |
| `site_description` | `Platform Ujian Online Sekolah` | Deskripsi |
| `timezone` | `Asia/Jakarta` | Zona waktu |
| `max_file_size` | `5242880` | Batas upload (bytes) |
| `allowed_extensions` | `jpg,jpeg,png,pdf` | Ekstensi file |
| `max_kecurangan` | `3` | Maks pelanggaran |

---

### Tabel: `profil_sekolah`
**Tujuan:** Data identitas sekolah (singleton — hanya satu baris).

| Kolom | Tipe | Constraint | Default |
|-------|------|-----------|---------|
| `id` | UUID | PK | gen_random_uuid() |
| `nama_sekolah` | VARCHAR(300) | NOT NULL | — |
| `npsn` | VARCHAR(20) | NULL | NULL |
| `alamat` | TEXT | NULL | NULL |
| `telepon` | VARCHAR(30) | NULL | NULL |
| `email` | VARCHAR(150) | NULL | NULL |
| `website` | VARCHAR(300) | NULL | NULL |
| `kepala_sekolah` | VARCHAR(200) | NULL | NULL |
| `nip_kepala` | VARCHAR(30) | NULL | NULL |
| `logo` | VARCHAR(500) | NULL | NULL |
| `visi` | TEXT | NULL | NULL |
| `misi` | TEXT | NULL | NULL |
| `updated_at` | TIMESTAMP | NOT NULL | NOW() |

---

### Tabel: `refresh_tokens`
**Tujuan:** Menyimpan refresh token aktif (untuk revocation dan multi-device).

| Kolom | Tipe | Constraint | Default | Keterangan |
|-------|------|-----------|---------|------------|
| `id` | UUID | PK | gen_random_uuid() | |
| `user_id` | UUID | FK → users.id CASCADE | — | |
| `token_hash` | VARCHAR(255) | UNIQUE, NOT NULL | — | SHA-256 dari token |
| `device_info` | TEXT | NULL | NULL | User-agent atau device name |
| `ip_address` | VARCHAR(45) | NULL | NULL | |
| `expires_at` | TIMESTAMP | NOT NULL | — | |
| `revoked_at` | TIMESTAMP | NULL | NULL | NULL = masih aktif |
| `created_at` | TIMESTAMP | NOT NULL | NOW() | |

**Index:** `INDEX(user_id)`, `INDEX(expires_at)`, `INDEX(revoked_at)`

---

## 4.4 Indexing Strategy

```sql
-- Performa query ujian aktif untuk siswa
CREATE INDEX idx_ujian_active ON ujian(status, waktu_mulai, waktu_selesai) 
  WHERE deleted_at IS NULL;

-- GIN index untuk array kelas_target
CREATE INDEX idx_ujian_kelas ON ujian USING GIN(kelas_target);
CREATE INDEX idx_soal_kelas ON soal USING GIN(kelas_target);

-- Hasil ujian per siswa
CREATE INDEX idx_hasil_siswa ON hasil_ujian(siswa_id, status);

-- Jawaban per sesi
CREATE INDEX idx_jawaban_sesi ON jawaban_siswa(hasil_ujian_id);
CREATE INDEX idx_essay_koreksi ON jawaban_essay(status_koreksi) 
  WHERE status_koreksi = 'belum';

-- Log kecurangan realtime
CREATE INDEX idx_kecurangan_sesi ON log_kecurangan(hasil_ujian_id, terjadi_at DESC);
```

## 4.5 Migration Strategy

- Semua perubahan skema melalui Prisma Migrate
- File migration dikompile dan di-commit ke repository
- Dijalankan otomatis saat deployment via `prisma migrate deploy`
- Rollback dilakukan dengan membuat migration baru (forward-only)
- Data migration (seed) menggunakan `prisma/seed.ts`
- Untuk production: migration dijalankan terpisah dari aplikasi start (init container pattern di Docker)

---

# 5. Prisma ORM Design

## 5.1 Schema Prisma Lengkap

```prisma
// prisma/schema.prisma
generator client {
  provider        = "prisma-client-js"
  previewFeatures = ["postgresqlExtensions"]
}
datasource db {
  provider   = "postgresql"
  url        = env("DATABASE_URL")
  extensions = [pgcrypto, pg_trgm]
}

enum Role                { admin guru siswa }
enum JenisSoal           { pilihan_ganda essay menjodohkan benar_salah pilihan_ganda_kompleks }
enum UjianStatus         { draft published archived }
enum HasilUjianStatus    { sedang_ujian selesai expired }
enum StatusKoreksi       { belum sudah }
enum StatusAbsensi       { hadir tidak_hadir izin sakit }
enum JenisKecurangan     { keluar_tab keluar_focus attempt_leave kembali_fokus penalty_activated }
enum BeritaAcaraStatus   { draft selesai diverifikasi }

model User {
  id        String    @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  username  String    @unique @db.VarChar(50)
  password  String    @db.VarChar(255)
  role      Role
  isActive  Boolean   @default(true)  @map("is_active")
  createdAt DateTime  @default(now()) @map("created_at")
  updatedAt DateTime  @updatedAt      @map("updated_at")
  deletedAt DateTime?                 @map("deleted_at")
  guru          Guru?
  siswa         Siswa?
  refreshTokens RefreshToken[]
  @@index([role])
  @@map("users")
}

model Guru {
  id        String    @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  userId    String    @unique @map("user_id")   @db.Uuid
  nip       String?   @unique                   @db.VarChar(30)
  nama      String    @db.VarChar(150)
  email     String?   @db.VarChar(150)
  noTelp    String?   @map("no_telp")           @db.VarChar(20)
  alamat    String?
  foto      String?   @db.VarChar(500)
  isAdmin   Boolean   @default(false)           @map("is_admin")
  createdAt DateTime  @default(now())           @map("created_at")
  updatedAt DateTime  @updatedAt                @map("updated_at")
  deletedAt DateTime?                           @map("deleted_at")
  user         User           @relation(fields: [userId], references: [id])
  guruKelas    GuruKelas[]
  guruMapel    GuruMapel[]
  soal         Soal[]
  ujian        Ujian[]
  beritaAcara  BeritaAcara[]
  koreksiEssay JawabanEssay[] @relation("DikoreksOleh")
  @@map("guru")
}

model Siswa {
  id           String    @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  userId       String    @unique @map("user_id")    @db.Uuid
  nisn         String    @unique                    @db.VarChar(20)
  nama         String    @db.VarChar(150)
  kelasId      String    @map("kelas_id")           @db.Uuid
  jenisKelamin String?   @map("jenis_kelamin")      @db.VarChar(1)
  tanggalLahir DateTime? @map("tanggal_lahir")      @db.Date
  alamat       String?
  foto         String?   @db.VarChar(500)
  createdAt    DateTime  @default(now())            @map("created_at")
  updatedAt    DateTime  @updatedAt                 @map("updated_at")
  deletedAt    DateTime?                            @map("deleted_at")
  user       User           @relation(fields: [userId],  references: [id])
  kelas      Kelas          @relation(fields: [kelasId], references: [id])
  hasilUjian HasilUjian[]
  absensi    AbsensiUjian[]
  kecurangan LogKecurangan[]
  @@index([kelasId])
  @@map("siswa")
}

model Kelas {
  id        String    @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  nama      String    @unique @db.VarChar(20)
  tingkat   Int?
  createdAt DateTime  @default(now()) @map("created_at")
  updatedAt DateTime  @updatedAt      @map("updated_at")
  deletedAt DateTime?                 @map("deleted_at")
  siswa     Siswa[]
  guruKelas GuruKelas[]
  @@map("kelas")
}

model MataPelajaran {
  id        String    @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  kode      String    @unique @db.VarChar(20)
  nama      String    @db.VarChar(150)
  deskripsi String?
  kkm       Int       @default(70)
  createdAt DateTime  @default(now()) @map("created_at")
  updatedAt DateTime  @updatedAt      @map("updated_at")
  deletedAt DateTime?                 @map("deleted_at")
  guruMapel GuruMapel[]
  soal      Soal[]
  ujian     Ujian[]
  @@map("mata_pelajaran")
}

model GuruKelas {
  id        String   @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  guruId    String   @map("guru_id")   @db.Uuid
  kelasId   String   @map("kelas_id")  @db.Uuid
  createdAt DateTime @default(now())   @map("created_at")
  guru  Guru  @relation(fields: [guruId],  references: [id])
  kelas Kelas @relation(fields: [kelasId], references: [id])
  @@unique([guruId, kelasId])
  @@map("guru_kelas")
}

model GuruMapel {
  id        String   @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  guruId    String   @map("guru_id")  @db.Uuid
  mapelId   String   @map("mapel_id") @db.Uuid
  createdAt DateTime @default(now())  @map("created_at")
  guru  Guru          @relation(fields: [guruId],  references: [id])
  mapel MataPelajaran @relation(fields: [mapelId], references: [id])
  @@unique([guruId, mapelId])
  @@map("guru_mapel")
}

model Soal {
  id              String    @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  mapelId         String    @map("mapel_id")    @db.Uuid
  createdBy       String    @map("created_by")  @db.Uuid
  jenis           JenisSoal
  pertanyaan      String
  gambarSoal      String?   @map("gambar_soal") @db.VarChar(500)
  videoSoal       String?   @map("video_soal")  @db.VarChar(500)
  opsiA           String?   @map("opsi_a")
  opsiB           String?   @map("opsi_b")
  opsiC           String?   @map("opsi_c")
  opsiD           String?   @map("opsi_d")
  opsiE           String?   @map("opsi_e")
  opsiAGambar     String?   @map("opsi_a_gambar") @db.VarChar(500)
  opsiBGambar     String?   @map("opsi_b_gambar") @db.VarChar(500)
  opsiCGambar     String?   @map("opsi_c_gambar") @db.VarChar(500)
  opsiDGambar     String?   @map("opsi_d_gambar") @db.VarChar(500)
  opsiEGambar     String?   @map("opsi_e_gambar") @db.VarChar(500)
  jawabanBenar    String?   @map("jawaban_benar")    @db.VarChar(5)
  jawabanKompleks Json?     @map("jawaban_kompleks")
  pasanganJodoh   Json?     @map("pasangan_jodoh")
  skor            Decimal   @default(10)             @db.Decimal(5, 2)
  kelasTarget     String[]  @map("kelas_target")     @db.Uuid
  createdAt       DateTime  @default(now())           @map("created_at")
  updatedAt       DateTime  @updatedAt                @map("updated_at")
  deletedAt       DateTime?                           @map("deleted_at")
  mapel     MataPelajaran @relation(fields: [mapelId],   references: [id])
  guru      Guru          @relation(fields: [createdBy], references: [id])
  soalUjian SoalUjian[]
  @@index([mapelId])
  @@index([createdBy])
  @@map("soal")
}

model Ujian {
  id            String      @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  mapelId       String      @map("mapel_id")     @db.Uuid
  createdBy     String      @map("created_by")   @db.Uuid
  judul         String      @db.VarChar(300)
  deskripsi     String?
  waktuMulai    DateTime    @map("waktu_mulai")
  waktuSelesai  DateTime    @map("waktu_selesai")
  durasiMenit   Int         @map("durasi_menit")
  status        UjianStatus @default(draft)
  kelasTarget   String[]    @map("kelas_target") @db.Uuid
  randomSoal    Boolean     @default(true)       @map("random_soal")
  allowRestart  Boolean     @default(false)      @map("allow_restart")
  maxKecurangan Int         @default(3)          @map("max_kecurangan")
  passingGrade  Int         @default(60)         @map("passing_grade")
  createdAt     DateTime    @default(now())      @map("created_at")
  updatedAt     DateTime    @updatedAt           @map("updated_at")
  deletedAt     DateTime?                        @map("deleted_at")
  mapel       MataPelajaran  @relation(fields: [mapelId],  references: [id])
  guru        Guru           @relation(fields: [createdBy], references: [id])
  soalUjian   SoalUjian[]
  hasilUjian  HasilUjian[]
  absensi     AbsensiUjian[]
  beritaAcara BeritaAcara[]
  @@index([createdBy])
  @@index([status])
  @@map("ujian")
}

model SoalUjian {
  id                      String    @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  ujianId                 String    @map("ujian_id")   @db.Uuid
  soalId                  String    @map("soal_id")    @db.Uuid
  urutan                  Int
  jenis                   JenisSoal
  skor                    Decimal   @db.Decimal(5, 2)
  snapshotPertanyaan      String    @map("snapshot_pertanyaan")
  snapshotOpsi            Json      @map("snapshot_opsi")
  snapshotJawabanBenar    String?   @map("snapshot_jawaban_benar")    @db.VarChar(5)
  snapshotJawabanKompleks Json?     @map("snapshot_jawaban_kompleks")
  snapshotPasanganJodoh   Json?     @map("snapshot_pasangan_jodoh")
  snapshotGambarSoal      String?   @map("snapshot_gambar_soal")      @db.VarChar(500)
  snapshotVideoSoal       String?   @map("snapshot_video_soal")       @db.VarChar(500)
  createdAt               DateTime  @default(now())                   @map("created_at")
  ujian        Ujian          @relation(fields: [ujianId], references: [id])
  soal         Soal           @relation(fields: [soalId],  references: [id])
  jawabanSiswa JawabanSiswa[]
  jawabanEssay JawabanEssay[]
  @@unique([ujianId, soalId])
  @@index([ujianId, urutan])
  @@map("soal_ujian")
}

model HasilUjian {
  id            String           @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  ujianId       String           @map("ujian_id")   @db.Uuid
  siswaId       String           @map("siswa_id")   @db.Uuid
  nilai         Decimal?         @db.Decimal(5, 2)
  nilaiEssay    Decimal?         @map("nilai_essay") @db.Decimal(5, 2)
  waktuMulai    DateTime         @default(now())    @map("waktu_mulai")
  waktuSelesai  DateTime?                           @map("waktu_selesai")
  status        HasilUjianStatus @default(sedang_ujian)
  attemptNumber Int              @default(1)        @map("attempt_number")
  ipAddress     String?          @map("ip_address") @db.VarChar(45)
  userAgent     String?          @map("user_agent")
  createdAt     DateTime         @default(now())    @map("created_at")
  updatedAt     DateTime         @updatedAt         @map("updated_at")
  ujian        Ujian          @relation(fields: [ujianId], references: [id])
  siswa        Siswa          @relation(fields: [siswaId], references: [id])
  sesi         SesiUjian?
  jawabanSiswa JawabanSiswa[]
  jawabanEssay JawabanEssay[]
  kecurangan   LogKecurangan[]
  @@unique([ujianId, siswaId, attemptNumber])
  @@index([siswaId])
  @@index([status])
  @@map("hasil_ujian")
}

model SesiUjian {
  id             String   @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  hasilUjianId   String   @unique @map("hasil_ujian_id") @db.Uuid
  sisaWaktuDetik Int      @map("sisa_waktu_detik")
  soalTerakhir   Int      @default(1) @map("soal_terakhir")
  lastActivity   DateTime @default(now()) @map("last_activity")
  hasilUjian HasilUjian @relation(fields: [hasilUjianId], references: [id], onDelete: Cascade)
  @@map("sesi_ujian")
}

model JawabanSiswa {
  id           String   @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  hasilUjianId String   @map("hasil_ujian_id") @db.Uuid
  soalUjianId  String   @map("soal_ujian_id")  @db.Uuid
  jawaban      String?  @db.VarChar(10)
  isBenar      Boolean? @map("is_benar")
  createdAt    DateTime @default(now()) @map("created_at")
  updatedAt    DateTime @updatedAt      @map("updated_at")
  hasilUjian HasilUjian @relation(fields: [hasilUjianId], references: [id], onDelete: Cascade)
  soalUjian  SoalUjian  @relation(fields: [soalUjianId],  references: [id])
  @@unique([hasilUjianId, soalUjianId])
  @@index([hasilUjianId])
  @@map("jawaban_siswa")
}

model JawabanEssay {
  id             String        @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  hasilUjianId   String        @map("hasil_ujian_id") @db.Uuid
  soalUjianId    String        @map("soal_ujian_id")  @db.Uuid
  jawabanText    String?       @map("jawaban_text")
  skorEssay      Decimal?      @map("skor_essay")     @db.Decimal(5, 2)
  komentarGuru   String?       @map("komentar_guru")
  statusKoreksi  StatusKoreksi @default(belum)        @map("status_koreksi")
  dikoreksOlehId String?       @map("dikoreksi_oleh") @db.Uuid
  dikoreksAt     DateTime?     @map("dikoreksi_at")
  createdAt      DateTime      @default(now())        @map("created_at")
  updatedAt      DateTime      @updatedAt             @map("updated_at")
  hasilUjian   HasilUjian @relation(fields: [hasilUjianId],   references: [id], onDelete: Cascade)
  soalUjian    SoalUjian  @relation(fields: [soalUjianId],    references: [id])
  dikoreksOleh Guru?      @relation("DikoreksOleh", fields: [dikoreksOlehId], references: [id])
  @@unique([hasilUjianId, soalUjianId])
  @@index([statusKoreksi])
  @@map("jawaban_essay")
}

model LogKecurangan {
  id           String          @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  hasilUjianId String          @map("hasil_ujian_id") @db.Uuid
  siswaId      String          @map("siswa_id")       @db.Uuid
  jenis        JenisKecurangan
  keterangan   String?
  terjadiAt    DateTime        @default(now()) @map("terjadi_at")
  hasilUjian HasilUjian @relation(fields: [hasilUjianId], references: [id], onDelete: Cascade)
  siswa      Siswa      @relation(fields: [siswaId],      references: [id])
  @@index([hasilUjianId])
  @@map("log_kecurangan")
}

model AbsensiUjian {
  id          String        @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  ujianId     String        @map("ujian_id") @db.Uuid
  siswaId     String        @map("siswa_id") @db.Uuid
  status      StatusAbsensi @default(tidak_hadir)
  waktuHadir  DateTime?     @map("waktu_hadir")
  waktuPulang DateTime?     @map("waktu_pulang")
  keterangan  String?
  createdAt   DateTime      @default(now()) @map("created_at")
  updatedAt   DateTime      @updatedAt      @map("updated_at")
  ujian Ujian @relation(fields: [ujianId], references: [id], onDelete: Cascade)
  siswa Siswa @relation(fields: [siswaId], references: [id])
  @@unique([ujianId, siswaId])
  @@map("absensi_ujian")
}

model BeritaAcara {
  id               String            @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  ujianId          String            @map("ujian_id")          @db.Uuid
  guruPengawasId   String            @map("guru_pengawas_id")  @db.Uuid
  tanggalUjian     DateTime          @map("tanggal_ujian")     @db.Date
  waktuMulai       DateTime          @map("waktu_mulai")       @db.Time()
  waktuSelesai     DateTime          @map("waktu_selesai")     @db.Time()
  ruangan          String?           @db.VarChar(100)
  jumlahPeserta    Int               @default(0) @map("jumlah_peserta")
  jumlahHadir      Int               @default(0) @map("jumlah_hadir")
  jumlahTidakHadir Int               @default(0) @map("jumlah_tidak_hadir")
  kejadianPenting  String?           @map("kejadian_penting")
  kendalaTeknis    String?           @map("kendala_teknis")
  tindakLanjut     String?           @map("tindak_lanjut")
  ttdGuru          String?           @map("ttd_guru")          @db.VarChar(500)
  status           BeritaAcaraStatus @default(draft)
  createdAt        DateTime          @default(now())           @map("created_at")
  updatedAt        DateTime          @updatedAt                @map("updated_at")
  ujian        Ujian @relation(fields: [ujianId],        references: [id], onDelete: Cascade)
  guruPengawas Guru  @relation(fields: [guruPengawasId], references: [id])
  @@map("berita_acara")
}

model Pengaturan {
  id        String   @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  nama      String   @unique @db.VarChar(100)
  value     String
  deskripsi String?
  updatedAt DateTime @updatedAt @map("updated_at")
  @@map("pengaturan")
}

model ProfilSekolah {
  id            String   @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  namaSekolah   String   @map("nama_sekolah")  @db.VarChar(300)
  npsn          String?  @db.VarChar(20)
  alamat        String?
  telepon       String?  @db.VarChar(30)
  email         String?  @db.VarChar(150)
  website       String?  @db.VarChar(300)
  kepalaSekolah String?  @map("kepala_sekolah") @db.VarChar(200)
  nipKepala     String?  @map("nip_kepala")     @db.VarChar(30)
  logo          String?  @db.VarChar(500)
  visi          String?
  misi          String?
  updatedAt     DateTime @updatedAt @map("updated_at")
  @@map("profil_sekolah")
}

model RefreshToken {
  id         String    @id @default(dbgenerated("gen_random_uuid()")) @db.Uuid
  userId     String    @map("user_id")    @db.Uuid
  tokenHash  String    @unique @map("token_hash") @db.VarChar(255)
  deviceInfo String?   @map("device_info")
  ipAddress  String?   @map("ip_address") @db.VarChar(45)
  expiresAt  DateTime  @map("expires_at")
  revokedAt  DateTime? @map("revoked_at")
  createdAt  DateTime  @default(now())   @map("created_at")
  user User @relation(fields: [userId], references: [id], onDelete: Cascade)
  @@index([userId])
  @@index([expiresAt])
  @@map("refresh_tokens")
}
```

## 5.2 Base Repository Pattern

```typescript
// src/database/base.repository.ts
export abstract class BaseRepository<T> {
  constructor(protected readonly prisma: PrismaService) {}

  protected abstract getModel(): any;

  async findAll(opts: QueryOptions): Promise<PaginatedResult<T>> {
    const { page = 1, limit = 20, where = {}, orderBy } = opts;
    const [data, total] = await this.prisma.$transaction([
      this.getModel().findMany({
        where: { ...where, deletedAt: null },
        skip: (page - 1) * limit,
        take: limit,
        orderBy: orderBy ?? { createdAt: 'desc' },
      }),
      this.getModel().count({ where: { ...where, deletedAt: null } }),
    ]);
    return { data, total, page, limit, totalPages: Math.ceil(total / limit) };
  }

  async softDelete(id: string): Promise<T> {
    return this.getModel().update({ where: { id }, data: { deletedAt: new Date() } });
  }
}
```

## 5.3 Pagination Response Format

```json
{
  "data": [],
  "meta": {
    "total": 150,
    "page": 2,
    "limit": 20,
    "totalPages": 8,
    "hasNextPage": true,
    "hasPrevPage": true
  }
}
```

---

# 6. Authentication & Authorization

## 6.1 Login Flow

```
POST /auth/login { username, password }
  1. findByUsername() — cari di tabel users (case-insensitive)
  2. Tidak ada → 401 Unauthorized
  3. bcrypt.compare(password, hash) — tidak cocok → 401
  4. isActive = false → 403 Forbidden
  5. generateAccessToken(JwtPayload) → JWT HS256, TTL 15 menit
  6. generateRefreshToken() → 64 bytes hex random
  7. SHA-256(token) → simpan ke tabel refresh_tokens
  8. Return { accessToken, refreshToken, user: { id, username, role } }
```

## 6.2 Refresh Token Flow

```
POST /auth/refresh (httpOnly Secure Cookie: refreshToken)
  1. SHA-256(cookie) → hash
  2. Query: token_hash=hash AND revoked_at IS NULL AND expires_at > NOW()
  3. Tidak valid → 401
  4. Generate accessToken baru
  5. Rotate: SET revoked_at=NOW() pada token lama
  6. Generate + simpan refresh token baru
  7. Return { accessToken } + Set-Cookie refreshToken baru
```

## 6.3 JWT Payload

```typescript
interface JwtPayload {
  sub: string;       // user.id (UUID)
  username: string;
  role: 'admin' | 'guru' | 'siswa';
  isAdmin: boolean;  // true jika guru dengan is_admin=true
  profileId: string; // guru.id atau siswa.id
  iat: number;
  exp: number;
}
```

## 6.4 RBAC Permission Matrix

| Resource | Admin | Guru (Owner) | Guru (Other) | Siswa |
|----------|:-----:|:------------:|:------------:|:-----:|
| User CRUD | YES | NO | NO | NO |
| Soal CRUD | YES | YES | NO | NO |
| Ujian CRUD | YES | YES | NO | NO |
| Ujian baca (available) | YES | YES | NO | YES |
| Hasil Ujian baca | YES | YES (own exam) | NO | YES (own) |
| Koreksi Essay | YES | YES (own exam) | NO | NO |
| Pengaturan | YES | NO | NO | NO |
| Monitoring | YES | YES (own exam) | NO | NO |

## 6.5 Guard Stack

```
Request → JwtAuthGuard → RolesGuard → Controller → Service (ownership check)
```

- `@Public()` — bypass JwtAuthGuard untuk endpoint publik
- `@Roles('admin', 'guru')` — RolesGuard membaca roles dari reflector
- Ownership check (apakah resource milik user) dilakukan di service layer, bukan guard

## 6.6 Token TTL & Storage

| Token | TTL | Storage Client |
|-------|-----|---------------|
| Access Token | 15 menit | Memory JS (tidak di localStorage) |
| Refresh Token | 7 hari (rolling) | httpOnly Secure SameSite=Strict Cookie |

---

# 7. Security Design

## 7.1 Password Hashing

```typescript
const SALT_ROUNDS = 12;
const hash = await bcrypt.hash(plaintext, SALT_ROUNDS);
const ok   = await bcrypt.compare(plaintext, hash);
```

Siswa diimport dengan password default `siswa123` (langsung di-hash). Flag `mustChangePassword` memaksa ganti saat login pertama.

## 7.2 Rate Limiting

```
Endpoint umum       : 100 req/menit per IP
POST /auth/login    : 5 gagal per 15 menit per IP → lockout 15 menit
Upload file         : 10 req/menit per user
Generate report/PDF : 5 req/menit per user
```

Implementasi: `@nestjs/throttler` + Redis store.

## 7.3 CORS

```typescript
app.enableCors({
  origin: process.env.ALLOWED_ORIGINS?.split(','),
  methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization'],
  credentials: true,   // untuk httpOnly cookie refresh token
  maxAge: 86400,
});
```

## 7.4 HTTP Security Headers (Helmet)

```typescript
app.use(helmet({
  contentSecurityPolicy: { directives: { defaultSrc: ["'self'"] } },
  hsts: { maxAge: 31_536_000, includeSubDomains: true },
  noSniff: true,
  frameguard: { action: 'deny' },
}));
```

## 7.5 Input Validation & SQL Injection Prevention

- Global `ValidationPipe({ whitelist: true, forbidNonWhitelisted: true, transform: true })`
- Prisma menggunakan prepared statements — SQL injection tidak mungkin terjadi
- File upload: validasi MIME via magic bytes (`file-type` library), bukan hanya ekstensi
- HTML sanitasi pada field teks bebas (pertanyaan, jawaban essay) via `sanitize-html`

## 7.6 CSRF

API-only backend menggunakan token-based auth (Bearer JWT). Tidak memerlukan CSRF token tradisional. Refresh token via httpOnly cookie dilindungi dengan `SameSite=Strict`.

## 7.7 Secret Management

```
Semua secret dari environment variable
Development : .env (tidak di-commit ke git)
Production  : AWS Secrets Manager / Kubernetes Secrets
Secret rotation : JWT secret dapat dirotasi tanpa downtime
                  menggunakan dual-key strategy (old + new key)
```

## 7.8 Environment Variables

```dotenv
# .env.example
NODE_ENV=production
PORT=3000
DATABASE_URL=postgresql://user:pass@host:5432/ujianonline
REDIS_URL=redis://host:6379
JWT_SECRET=<min-64-chars>
JWT_REFRESH_SECRET=<min-64-chars-berbeda>
JWT_EXPIRES_IN=15m
JWT_REFRESH_EXPIRES_IN=7d
ALLOWED_ORIGINS=https://app.sekolah.id
STORAGE_DRIVER=local          # local | s3
STORAGE_LOCAL_PATH=./uploads
AWS_REGION=ap-southeast-1
AWS_BUCKET=ujianonline-files
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USER=...
MAIL_PASS=...
```

---

# 8. Modul Aplikasi

## 8.1 AuthModule

**Tujuan:** Autentikasi dan manajemen sesi token.

**Endpoints:**
| Method | URL | Auth | Keterangan |
|--------|-----|------|-----------|
| POST | `/auth/login` | Public | Login, return access + refresh token |
| POST | `/auth/logout` | Bearer | Revoke refresh token |
| POST | `/auth/refresh` | Cookie | Rotate refresh token |
| GET | `/auth/me` | Bearer | Data user aktif |
| PATCH | `/auth/change-password` | Bearer | Ganti password |

**Business Rules:**
- Guru dapat login dengan `username` atau `nip`
- Siswa login dengan `username` (= NISN)
- Refresh token dirotasi setiap kali digunakan
- Login ke perangkat baru tidak merevoke perangkat lama (multi-device)

**Dependencies:** PrismaModule, JwtModule, RedisModule

---

## 8.2 UsersModule

**Tujuan:** CRUD user — khusus Admin.

**Endpoints:**
| Method | URL | Permission | Keterangan |
|--------|-----|-----------|-----------|
| GET | `/users` | Admin | List paginated + filter |
| POST | `/users` | Admin | Buat user + profil |
| GET | `/users/:id` | Admin | Detail |
| PATCH | `/users/:id` | Admin | Update |
| DELETE | `/users/:id` | Admin | Soft delete |
| PATCH | `/users/:id/activate` | Admin | Toggle aktif |
| POST | `/users/:id/reset-password` | Admin | Reset ke password baru |

**Business Rules:**
- Saat buat user guru: otomatis insert ke tabel `guru`
- Saat buat user siswa: butuh `kelas_id`, otomatis insert ke `siswa`
- Admin tidak bisa hapus atau nonaktifkan dirinya sendiri
- Soft delete user cascade soft-delete ke profil terkait

---

## 8.3 GuruModule

**Tujuan:** Profil dan penugasan guru.

**Endpoints:**
| Method | URL | Permission | Keterangan |
|--------|-----|-----------|-----------|
| GET | `/guru` | Admin | List guru |
| GET | `/guru/:id` | Admin / Self | Detail |
| PATCH | `/guru/:id` | Admin / Self | Update profil |
| POST | `/guru/:id/photo` | Admin / Self | Upload foto |
| GET | `/guru/:id/kelas` | Admin / Self | Kelas yang diampu |
| GET | `/guru/:id/mapel` | Admin / Self | Mapel yang diampu |
| POST | `/guru/assign-kelas` | Admin | Assign guru ke kelas |
| DELETE | `/guru/assign-kelas` | Admin | Unassign |
| POST | `/guru/assign-mapel` | Admin | Assign guru ke mapel |
| DELETE | `/guru/assign-mapel` | Admin | Unassign |

---

## 8.4 SiswaModule

**Tujuan:** Profil siswa dan import massal.

**Endpoints:**
| Method | URL | Permission | Keterangan |
|--------|-----|-----------|-----------|
| GET | `/siswa` | Admin / Guru | List (Guru: hanya kelas diampu) |
| GET | `/siswa/:id` | Admin / Guru / Self | Detail |
| PATCH | `/siswa/:id` | Admin / Self | Update |
| POST | `/siswa/:id/photo` | Admin / Self | Upload foto |
| POST | `/siswa/import` | Admin | Import Excel (async queue) |
| GET | `/siswa/import/template` | Admin | Download template |
| GET | `/siswa/kelas/:kelasId` | Admin / Guru | Siswa per kelas |

**Business Rules:**
- NISN unik, 10 digit angka
- Import: upsert by NISN, max 500 baris/file
- Guru hanya bisa lihat siswa di kelas yang diampunya

**Queue:** `ImportSiswaJob` — async processing, response berupa `jobId` untuk polling status

---

## 8.5 MataPelajaranModule

**Endpoints:**
| Method | URL | Permission |
|--------|-----|-----------|
| GET | `/mata-pelajaran` | Authenticated |
| POST | `/mata-pelajaran` | Admin |
| GET | `/mata-pelajaran/:id` | Authenticated |
| PATCH | `/mata-pelajaran/:id` | Admin |
| DELETE | `/mata-pelajaran/:id` | Admin |

**Business Rules:**
- Tidak bisa dihapus jika ada soal atau ujian aktif yang mereferensinya

---

## 8.6 KelasModule

**Endpoints:**
| Method | URL | Permission |
|--------|-----|-----------|
| GET | `/kelas` | Authenticated |
| POST | `/kelas` | Admin |
| PATCH | `/kelas/:id` | Admin |
| DELETE | `/kelas/:id` | Admin |

**Business Rules:**
- Tidak bisa dihapus jika masih ada siswa aktif di kelas tersebut

---

## 8.7 SoalModule

**Tujuan:** Bank soal lengkap dengan media.

**Endpoints:**
| Method | URL | Permission | Keterangan |
|--------|-----|-----------|-----------|
| GET | `/soal` | Admin / Guru | List + filter (mapel, jenis, kelas) |
| POST | `/soal` | Admin / Guru | Buat soal baru |
| GET | `/soal/:id` | Admin / Guru (owner) | Detail |
| PATCH | `/soal/:id` | Admin / Guru (owner) | Update |
| DELETE | `/soal/:id` | Admin / Guru (owner) | Soft delete |
| POST | `/soal/import` | Admin / Guru | Import Excel (async) |
| GET | `/soal/import/template` | Admin / Guru | Download template |
| POST | `/soal/:id/media` | Admin / Guru (owner) | Upload gambar soal/opsi |
| DELETE | `/soal/:id/media/:field` | Admin / Guru (owner) | Hapus media |

**Validation per jenis soal:**

| Jenis | Aturan |
|-------|--------|
| `pilihan_ganda` | Min 2 opsi + `jawaban_benar` (a/b/c/d/e) wajib diisi |
| `essay` | Tidak perlu opsi/jawaban_benar |
| `benar_salah` | `jawaban_benar` harus `benar` atau `salah` |
| `menjodohkan` | `pasangan_jodoh` JSON array wajib: `[{kiri:"...", kanan:"..."}]` |
| `pilihan_ganda_kompleks` | `jawaban_kompleks` JSON array string: `["a","c"]` |

---

## 8.8 UjianModule

**Tujuan:** Siklus hidup konfigurasi ujian.

**Endpoints:**
| Method | URL | Permission | Keterangan |
|--------|-----|-----------|-----------|
| GET | `/ujian` | Admin / Guru | List dengan filter |
| POST | `/ujian` | Admin / Guru | Buat ujian (status: draft) |
| GET | `/ujian/:id` | Admin / Guru | Detail + list soal |
| PATCH | `/ujian/:id` | Admin / Guru (owner) | Update (hanya draft) |
| DELETE | `/ujian/:id` | Admin / Guru (owner) | Soft delete (hanya draft) |
| PATCH | `/ujian/:id/publish` | Admin / Guru (owner) | Publish ujian |
| PATCH | `/ujian/:id/archive` | Admin / Guru (owner) | Archive |
| POST | `/ujian/:id/soal` | Admin / Guru (owner) | Tambah soal manual |
| POST | `/ujian/:id/soal/auto` | Admin / Guru (owner) | Random auto-add soal |
| DELETE | `/ujian/:id/soal/:soalUjianId` | Admin / Guru (owner) | Hapus soal dari ujian |
| PUT | `/ujian/:id/soal/reorder` | Admin / Guru (owner) | Ubah urutan soal |
| GET | `/ujian/available` | Siswa | Daftar ujian tersedia |

**Business Rules:**
- Ujian published tidak bisa diubah konfigurasi maupun daftar soal
- Min 1 soal sebelum dapat dipublish
- `waktu_selesai > waktu_mulai + durasi_menit` (validasi server-side)
- Saat publish: semua soal di-snapshot ke tabel `soal_ujian`
- Saat publish: generate record `absensi_ujian` untuk semua siswa kelas target
- `GET /ujian/available` untuk siswa: filter `status=published` + `NOW() BETWEEN waktu_mulai AND waktu_selesai` + `kelas siswa ∈ kelas_target`

**Events emitted:**
- `UjianPublishedEvent` → trigger GenerateAbsensiListener, NotifyGuruListener
- `UjianArchivedEvent`

---

## 8.9 SesiUjianModule

**Tujuan:** Engine pengerjaan ujian real-time.

**Endpoints:**
| Method | URL | Permission | Keterangan |
|--------|-----|-----------|-----------|
| POST | `/ujian/:ujianId/start` | Siswa | Mulai atau resume sesi |
| GET | `/exam-sessions/:id` | Siswa | State sesi aktif |
| GET | `/exam-sessions/:id/question` | Siswa | Ambil soal (`?order=N`) |
| POST | `/exam-sessions/:id/answer` | Siswa | Simpan jawaban (upsert) |
| POST | `/exam-sessions/:id/finish` | Siswa | Selesaikan ujian |
| POST | `/exam-sessions/:id/heartbeat` | Siswa | Update waktu + last_activity |
| POST | `/exam-sessions/:id/report-cheat` | Siswa | Log kecurangan dari client |

**Business Rules:**
- Satu siswa hanya boleh punya satu sesi `sedang_ujian` per ujian (kecuali `allow_restart=true`)
- `POST /start` — jika ada sesi aktif: resume; jika tidak: buat baru
- Jawaban disimpan real-time via upsert per soal (tidak tunggu finish)
- Urutan soal random dikunci saat start dan disimpan di Redis sebagai ordered list
- Heartbeat dari client setiap 30 detik; jika tidak ada heartbeat > (durasi + 10 menit) → cron set status = `expired`
- Jumlah kecurangan > `max_kecurangan` → auto-finish saat heartbeat berikutnya
- Saat finish: hitung nilai otomatis untuk non-essay, emit `ExamFinishedEvent`

**Caching:** State sesi (`sisaWaktuDetik`, `soalTerakhir`, `urutanSoal[]`) disimpan di Redis. Sync ke DB setiap heartbeat.

---

## 8.10 HasilUjianModule

**Endpoints:**
| Method | URL | Permission | Keterangan |
|--------|-----|-----------|-----------|
| GET | `/hasil-ujian` | Admin / Guru | Rekap dengan filter |
| GET | `/hasil-ujian/my` | Siswa | Hasil milik siswa aktif |
| GET | `/hasil-ujian/:id` | Admin / Guru / Owner | Detail lengkap |
| GET | `/ujian/:ujianId/hasil` | Admin / Guru (owner) | Semua hasil satu ujian |
| GET | `/ujian/:ujianId/statistik` | Admin / Guru (owner) | Rata-rata, distribusi nilai |
| POST | `/hasil-ujian/:id/recalculate` | Admin | Hitung ulang nilai |
| GET | `/hasil-ujian/:id/export` | Admin / Guru | PDF satu hasil |
| GET | `/ujian/:ujianId/export` | Admin / Guru | Excel rekap semua hasil |

---

## 8.11 KoreksiEssayModule

**Endpoints:**
| Method | URL | Permission | Keterangan |
|--------|-----|-----------|-----------|
| GET | `/koreksi-essay` | Admin / Guru | List belum dikoreksi |
| GET | `/koreksi-essay/:id` | Admin / Guru (owner) | Detail |
| PATCH | `/koreksi-essay/:id` | Admin / Guru (owner) | Submit nilai + komentar |
| POST | `/koreksi-essay/bulk` | Admin / Guru (owner) | Koreksi bulk |

**Business Rules:**
- Skor tidak boleh melebihi skor max soal
- Setelah semua essay dikoreksi → otomatis hitung ulang nilai akhir

---

## 8.12 AbsensiModule

**Endpoints:**
| Method | URL | Permission |
|--------|-----|-----------|
| GET | `/absensi/:ujianId` | Admin / Guru (owner) |
| PATCH | `/absensi/:ujianId/:siswaId` | Admin / Guru (owner) |
| POST | `/absensi/:ujianId/generate` | Admin / Guru (owner) |
| GET | `/absensi/:ujianId/export` | Admin / Guru (owner) |

---

## 8.13 BeritaAcaraModule

**Endpoints:**
| Method | URL | Permission |
|--------|-----|-----------|
| GET | `/berita-acara` | Admin / Guru |
| POST | `/berita-acara` | Guru |
| GET | `/berita-acara/:id` | Admin / Guru |
| PATCH | `/berita-acara/:id` | Guru (owner, hanya draft) |
| PATCH | `/berita-acara/:id/selesai` | Guru (owner) |
| PATCH | `/berita-acara/:id/verifikasi` | Admin |
| GET | `/berita-acara/:id/pdf` | Admin / Guru |

---

## 8.14 MonitoringModule

**Endpoints:**
| Method | URL | Permission |
|--------|-----|-----------|
| GET | `/monitoring/:ujianId` | Admin / Guru (owner) |
| GET | `/monitoring/:ujianId/kecurangan` | Admin / Guru (owner) |
| GET | `/monitoring/:ujianId/statistik` | Admin / Guru (owner) |
| POST | `/monitoring/:ujianId/force-finish/:siswaId` | Admin / Guru (owner) |

**Implementasi:** Data diambil dari Redis (session state) untuk performa. Polling setiap 30 detik atau via SSE.

---

## 8.15 PengaturanModule

**Endpoints:**
| Method | URL | Permission |
|--------|-----|-----------|
| GET | `/pengaturan` | Admin |
| PATCH | `/pengaturan` | Admin |
| GET | `/pengaturan/public` | Public |

---

## 8.16 ProfilSekolahModule

**Endpoints:**
| Method | URL | Permission |
|--------|-----|-----------|
| GET | `/profil-sekolah` | Authenticated |
| PATCH | `/profil-sekolah` | Admin |
| POST | `/profil-sekolah/logo` | Admin |

---

## 8.17 ReportModule

**Endpoints:**
| Method | URL | Permission |
|--------|-----|-----------|
| GET | `/reports/nilai/:ujianId` | Admin / Guru |
| GET | `/reports/rekap-nilai` | Admin / Guru |
| GET | `/reports/kartu-ujian/:ujianId/:siswaId` | Admin / Guru / Siswa (own) |

**Implementasi:** PDF via Puppeteer (render HTML → PDF), Excel via ExcelJS. File PDF di-cache Redis TTL 1 jam.


---

# 9. API Documentation

Semua endpoint mengikuti konvensi berikut:

## 9.1 Konvensi Umum

**Base URL:** `https://api.ujianonline.sekolah.id/v1`

**Request Headers:**
```
Content-Type: application/json
Authorization: Bearer <accessToken>
```

**Standard Success Response:**
```json
{
  "statusCode": 200,
  "message": "OK",
  "data": { ... }
}
```

**Standard Error Response:**
```json
{
  "statusCode": 400,
  "error": "Bad Request",
  "message": "Validation failed",
  "errors": [
    { "field": "username", "message": "username tidak boleh kosong" }
  ],
  "timestamp": "2026-06-03T08:00:00.000Z",
  "path": "/auth/login"
}
```

**HTTP Status Codes:**
| Code | Kondisi |
|------|---------|
| 200 | OK — request berhasil |
| 201 | Created — resource baru berhasil dibuat |
| 204 | No Content — berhasil, tidak ada response body (DELETE) |
| 400 | Bad Request — validasi gagal |
| 401 | Unauthorized — token tidak ada atau expired |
| 403 | Forbidden — tidak punya permission |
| 404 | Not Found — resource tidak ditemukan |
| 409 | Conflict — duplicate data (username sudah ada, dll) |
| 422 | Unprocessable Entity — business rule violation |
| 429 | Too Many Requests — rate limit |
| 500 | Internal Server Error |

---

## 9.2 Endpoint Detail

### POST /auth/login

**Deskripsi:** Login dan mendapatkan access token + refresh token.

**Authentication:** Public

**Request Body:**
```json
{
  "username": "string | required | min:3 max:50",
  "password": "string | required | min:6 max:100"
}
```

**Validation:**
- `username`: required, string, tidak boleh kosong. Untuk guru bisa berupa NIP.
- `password`: required, string, min 6 karakter.

**Success Response (200):**
```json
{
  "statusCode": 200,
  "message": "Login berhasil",
  "data": {
    "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": "uuid",
      "username": "admin",
      "role": "admin",
      "isAdmin": true,
      "nama": "Administrator"
    }
  }
}
```
Set-Cookie: `refreshToken=<token>; HttpOnly; Secure; SameSite=Strict; Path=/auth; Max-Age=604800`

**Error Responses:**
- `401`: Username atau password salah
- `403`: Akun tidak aktif
- `429`: Terlalu banyak percobaan login

**Swagger DTOs:**
```typescript
class LoginDto {
  @ApiProperty({ example: 'admin' })
  @IsString() @IsNotEmpty() @MaxLength(50)
  username: string;

  @ApiProperty({ example: 'Admin@123' })
  @IsString() @IsNotEmpty() @MinLength(6)
  password: string;
}
```

---

### POST /auth/refresh

**Deskripsi:** Rotate refresh token dan mendapatkan access token baru.

**Authentication:** httpOnly Cookie `refreshToken`

**Request:** Tidak ada body. Token diambil dari cookie.

**Success Response (200):**
```json
{
  "statusCode": 200,
  "data": {
    "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }
}
```

Set-Cookie dengan refresh token baru (rotasi).

---

### GET /ujian/available

**Deskripsi:** Daftar ujian yang tersedia untuk siswa yang sedang login.

**Authentication:** Bearer (Siswa)

**Query Parameters:**
| Parameter | Type | Default | Keterangan |
|-----------|------|---------|-----------|
| `page` | number | 1 | Halaman |
| `limit` | number | 20 | Per halaman (max 50) |

**Business Logic:**
```sql
WHERE status = 'published'
  AND NOW() BETWEEN waktu_mulai AND waktu_selesai
  AND kelas_siswa = ANY(kelas_target)
  AND deleted_at IS NULL
```

**Success Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "judul": "UTS Matematika Kelas 7A",
      "mapel": { "id": "uuid", "nama": "Matematika" },
      "waktuMulai": "2026-06-03T07:00:00.000Z",
      "waktuSelesai": "2026-06-03T09:00:00.000Z",
      "durasiMenit": 90,
      "jumlahSoal": 40,
      "sudahDikerjakan": false,
      "status": "published"
    }
  ],
  "meta": { "total": 3, "page": 1, "limit": 20, "totalPages": 1 }
}
```

---

### POST /ujian/:ujianId/start

**Deskripsi:** Mulai atau resume sesi pengerjaan ujian.

**Authentication:** Bearer (Siswa)

**Path Parameter:** `ujianId` (UUID)

**Business Logic:**
1. Cek ujian `published` + waktu aktif + kelas siswa sesuai
2. Cek apakah ada sesi `sedang_ujian` yang ada → resume
3. Jika tidak ada → buat `hasil_ujian` + `sesi_ujian` baru
4. Jika `random_soal = true`: generate urutan acak, simpan di Redis
5. Return state sesi

**Success Response (201 / 200):**
```json
{
  "statusCode": 201,
  "data": {
    "sesiId": "uuid",
    "hasilUjianId": "uuid",
    "isResume": false,
    "sisaWaktuDetik": 5400,
    "soalTerakhir": 1,
    "jumlahSoal": 40,
    "ujian": {
      "id": "uuid",
      "judul": "UTS Matematika",
      "durasiMenit": 90
    }
  }
}
```

**Error Responses:**
- `404`: Ujian tidak ditemukan
- `403`: Ujian belum/sudah berakhir, atau kelas tidak sesuai
- `409`: Ujian sudah selesai dikerjakan (jika `allow_restart=false`)

---

### GET /exam-sessions/:id/question

**Deskripsi:** Ambil data soal pada urutan tertentu.

**Authentication:** Bearer (Siswa)

**Path Parameter:** `id` (sesi UUID)

**Query Parameter:**
| Parameter | Type | Keterangan |
|-----------|------|-----------|
| `order` | number | Urutan soal (1-based) |

**Response (200):**
```json
{
  "data": {
    "soalUjianId": "uuid",
    "urutan": 5,
    "jenis": "pilihan_ganda",
    "pertanyaan": "Berapa hasil dari 2 + 2 × 3?",
    "gambarSoal": null,
    "videoSoal": null,
    "opsi": {
      "a": "8",
      "b": "10",
      "c": "12",
      "d": "6"
    },
    "opsiGambar": { "a": null, "b": null, "c": null, "d": null },
    "skor": 2.5,
    "jawabanTersimpan": "a",
    "totalSoal": 40
  }
}
```

**Catatan:** `jawaban_benar` TIDAK disertakan dalam response ini.

---

### POST /exam-sessions/:id/answer

**Deskripsi:** Simpan atau update jawaban untuk satu soal (upsert).

**Authentication:** Bearer (Siswa)

**Request Body:**
```json
{
  "soalUjianId": "uuid",
  "jawaban": "b"
}
```

**Validation:**
- `soalUjianId`: required, UUID, harus milik sesi ini
- `jawaban`: required untuk non-essay, nullable untuk essay
- Untuk essay: `{ "soalUjianId": "uuid", "jawabanText": "Jawaban panjang..." }`

**Success Response (200):**
```json
{
  "statusCode": 200,
  "message": "Jawaban disimpan",
  "data": { "soalUjianId": "uuid", "jawaban": "b" }
}
```

---

### POST /exam-sessions/:id/finish

**Deskripsi:** Selesaikan ujian dan hitung nilai.

**Authentication:** Bearer (Siswa)

**Request Body:** Kosong (tidak diperlukan)

**Business Logic:**
1. Set `waktu_selesai = NOW()`
2. Set `status = 'selesai'`
3. Hitung nilai pilihan ganda: bandingkan `jawaban_siswa.jawaban` vs `soal_ujian.snapshot_jawaban_benar`
4. Untuk soal dengan `jawaban_benar` yang terisi: set `is_benar`
5. Hitung `nilai = (total_benar × skor_per_soal) / total_skor_max × 100`
6. Update `hasil_ujian.nilai`
7. Hapus state sesi dari Redis
8. Emit `ExamFinishedEvent`

**Success Response (200):**
```json
{
  "data": {
    "hasilUjianId": "uuid",
    "nilai": 85.5,
    "jumlahBenar": 34,
    "jumlahSalah": 6,
    "jumlahSoal": 40,
    "passingGrade": 60,
    "lulus": true,
    "adaEssay": false,
    "pesanEssay": null
  }
}
```

---

### POST /exam-sessions/:id/heartbeat

**Deskripsi:** Update sisa waktu dan last_activity. Harus dipanggil setiap 30 detik.

**Request Body:**
```json
{
  "sisaWaktuDetik": 4800,
  "soalTerakhir": 12
}
```

**Response (200):**
```json
{
  "data": {
    "sisaWaktuDetik": 4800,
    "forceFinish": false,
    "kecuranganCount": 1
  }
}
```

Jika `forceFinish: true` → client harus memanggil `/finish` segera.

---

### GET /ujian/:ujianId/hasil

**Deskripsi:** Rekap semua hasil ujian untuk satu ujian (Guru/Admin).

**Authentication:** Bearer (Admin / Guru owner)

**Query Parameters:**
| Parameter | Type | Keterangan |
|-----------|------|-----------|
| `page` | number | Halaman |
| `limit` | number | Per halaman |
| `search` | string | Cari nama siswa |
| `status` | string | `selesai`, `sedang_ujian`, `expired` |
| `sort` | string | `nilai_asc`, `nilai_desc`, `nama_asc` |

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "siswa": { "id": "uuid", "nama": "Budi Santoso", "nisn": "1234567890" },
      "nilai": 87.5,
      "status": "selesai",
      "waktuMulai": "2026-06-03T07:10:00.000Z",
      "waktuSelesai": "2026-06-03T08:45:00.000Z",
      "durasi": "95 menit",
      "lulus": true,
      "adaEssayBelumDikoreksi": false
    }
  ],
  "meta": { "total": 32, "page": 1, "limit": 20, "totalPages": 2 },
  "summary": {
    "rataRata": 78.3,
    "nilaiMin": 42.0,
    "nilaiMax": 97.5,
    "jumlahLulus": 28,
    "jumlahTidakLulus": 4
  }
}
```

---

### PATCH /koreksi-essay/:id

**Deskripsi:** Submit nilai dan komentar untuk jawaban essay.

**Authentication:** Bearer (Guru owner ujian / Admin)

**Request Body:**
```json
{
  "skorEssay": 8.5,
  "komentarGuru": "Jawaban sudah benar namun kurang lengkap penjelasannya."
}
```

**Validation:**
- `skorEssay`: required, number, min 0, max = skor soal
- `komentarGuru`: optional, string, max 1000 karakter

**Response (200):**
```json
{
  "data": {
    "id": "uuid",
    "statusKoreksi": "sudah",
    "skorEssay": 8.5,
    "komentarGuru": "...",
    "dikoreksAt": "2026-06-03T10:00:00.000Z"
  }
}
```

---

### POST /soal/import

**Deskripsi:** Import soal dari file Excel (async).

**Authentication:** Bearer (Admin / Guru)

**Request:** `multipart/form-data`
```
file: <xlsx file, max 10MB>
mapelId: UUID
kelasTarget: ["uuid1","uuid2"]
```

**Response (202 Accepted):**
```json
{
  "statusCode": 202,
  "message": "Import sedang diproses",
  "data": {
    "jobId": "import-soal-uuid",
    "statusUrl": "/jobs/import-soal-uuid"
  }
}
```

**Template Excel kolom:**
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| Jenis Soal | string | `pilihan_ganda`, `essay`, dll |
| Pertanyaan | string | Teks soal |
| Opsi A | string | |
| Opsi B | string | |
| Opsi C | string | |
| Opsi D | string | |
| Opsi E | string | Opsional |
| Jawaban Benar | string | `a`, `b`, `c`, `d`, `e` |
| Skor | number | Default 10 |

---

### GET /reports/nilai/:ujianId

**Deskripsi:** Generate laporan nilai PDF untuk satu ujian.

**Authentication:** Bearer (Admin / Guru)

**Response:** `application/pdf` stream

PDF mencakup:
- Header sekolah (nama, logo, alamat)
- Judul ujian, mapel, tanggal, kelas
- Tabel nilai: No | Nama | NISN | Nilai | Status Lulus
- Statistik: rata-rata, tertinggi, terendah
- Tanda tangan guru

---

## 9.3 Swagger Setup

```typescript
// main.ts
const config = new DocumentBuilder()
  .setTitle('UjianOnline API')
  .setDescription('REST API untuk Sistem Ujian Online')
  .setVersion('1.0')
  .addBearerAuth({ type: 'http', scheme: 'bearer', bearerFormat: 'JWT' }, 'access-token')
  .addCookieAuth('refreshToken')
  .addTag('auth', 'Autentikasi')
  .addTag('ujian', 'Manajemen Ujian')
  .addTag('exam-sessions', 'Sesi Pengerjaan Ujian')
  .addTag('soal', 'Bank Soal')
  .addTag('hasil-ujian', 'Hasil & Nilai')
  .build();

const document = SwaggerModule.createDocument(app, config);
SwaggerModule.setup('api/docs', app, document, {
  swaggerOptions: { persistAuthorization: true },
});
```

Swagger UI tersedia di `/api/docs`. JSON spec di `/api/docs-json`.

---

# 10. Background Jobs & Queue

## 10.1 Arsitektur Queue (BullMQ + Redis)

```
Producer (Service)
    │
    ▼
Redis Queue (BullMQ)
    │
    ▼
Worker/Processor (NestJS)
    │
    ├── Success → update status job, emit event
    └── Failure → retry (max 3x), dead letter queue
```

## 10.2 Queue Definitions

```typescript
// src/queues/queues.constant.ts
export const QUEUES = {
  SCORE_CALCULATION: 'score-calculation',
  IMPORT_SISWA: 'import-siswa',
  IMPORT_SOAL: 'import-soal',
  NOTIFICATION: 'notification',
  REPORT_GENERATION: 'report-generation',
  CLEANUP: 'cleanup',
} as const;
```

## 10.3 Job: ScoreCalculationJob

**Queue:** `score-calculation`  
**Trigger:** `ExamFinishedEvent`  
**Purpose:** Hitung nilai akhir setelah ujian selesai (untuk reliabilitas, meskipun kalkulasi awal dilakukan sync).

```typescript
interface ScoreCalculationJobData {
  hasilUjianId: string;
  recalculate: boolean;
}
```

**Flow:**
1. Load `hasil_ujian` + semua `jawaban_siswa` + `soal_ujian` snapshot
2. Bandingkan jawaban dengan kunci
3. Hitung total skor
4. Update `hasil_ujian.nilai`
5. Jika semua essay sudah dikoreksi: hitung nilai final termasuk essay

**Retry:** max 3x, exponential backoff (1s, 5s, 30s)

---

## 10.4 Job: ImportSiswaJob

**Queue:** `import-siswa`  
**Trigger:** `POST /siswa/import`  
**Purpose:** Proses import Excel siswa secara async.

```typescript
interface ImportSiswaJobData {
  filePath: string;
  uploadedBy: string;
  jobId: string;
}
```

**Flow:**
1. Baca file Excel dari storage
2. Parse baris per baris
3. Validate setiap baris
4. Upsert ke database (batch per 50 baris dalam transaction)
5. Update job progress (percentage)
6. Setelah selesai: update status, simpan summary (berhasil/gagal per baris)
7. Hapus file temp dari storage

**Progress Tracking:** Status dapat di-poll via `GET /jobs/:jobId`

---

## 10.5 Job: ImportSoalJob

Sama seperti ImportSiswaJob tapi untuk soal. Max 200 baris per file.

---

## 10.6 Job: NotificationJob

**Queue:** `notification`  
**Purpose:** Kirim notifikasi (email, atau webhook ke frontend).

```typescript
interface NotificationJobData {
  type: 'exam_published' | 'exam_finished' | 'essay_graded';
  recipientId: string;
  payload: Record<string, any>;
}
```

---

## 10.7 Job: ReportGenerationJob

**Queue:** `report-generation`  
**Purpose:** Generate PDF/Excel besar secara async.

```typescript
interface ReportJobData {
  type: 'nilai_pdf' | 'rekap_excel' | 'berita_acara_pdf';
  ujianId: string;
  requestedBy: string;
}
```

Response: download URL ke file yang di-generate (disimpan sementara di storage, TTL 1 jam).

---

## 10.8 Scheduled Jobs (Cron)

```typescript
// src/jobs/exam-auto-close.job.ts
@Injectable()
export class ExamAutoCloseJob {
  @Cron(CronExpression.EVERY_MINUTE)
  async closeExpiredSessions() {
    // Set hasil_ujian.status = 'expired' untuk sesi yang:
    // - status = 'sedang_ujian'
    // - last_activity < NOW() - (durasi + 10 menit)
    // Kemudian hitung nilai otomatis untuk sesi expired
  }
}

// src/jobs/cleanup-sessions.job.ts
@Injectable()
export class CleanupSessionsJob {
  @Cron(CronExpression.EVERY_DAY_AT_MIDNIGHT)
  async cleanupOldSessions() {
    // Hapus Redis keys sesi ujian yang sudah expired
    // Hapus refresh_tokens yang sudah expired atau revoked > 30 hari
    // Hapus file temp upload yang tidak diproses > 24 jam
  }
}
```

## 10.9 Dead Letter Queue

Job yang gagal setelah max retry dipindahkan ke queue `failed-jobs`:
- Disimpan di Redis dengan TTL 7 hari
- Bisa di-retry manual via `POST /admin/jobs/:id/retry`
- Alert dikirim ke monitoring (jika configured)

---

# 11. Event Driven Architecture

## 11.1 Event Definitions

```typescript
// src/events/exam-started.event.ts
export class ExamStartedEvent {
  constructor(
    public readonly hasilUjianId: string,
    public readonly ujianId: string,
    public readonly siswaId: string,
    public readonly isResume: boolean,
  ) {}
}

// src/events/exam-finished.event.ts
export class ExamFinishedEvent {
  constructor(
    public readonly hasilUjianId: string,
    public readonly ujianId: string,
    public readonly siswaId: string,
    public readonly nilai: number,
    public readonly status: 'selesai' | 'expired',
  ) {}
}

// src/events/ujian-published.event.ts
export class UjianPublishedEvent {
  constructor(
    public readonly ujianId: string,
    public readonly guruId: string,
    public readonly kelasTarget: string[],
  ) {}
}

// src/events/essay-graded.event.ts
export class EssayGradedEvent {
  constructor(
    public readonly hasilUjianId: string,
    public readonly allEssayGraded: boolean,
  ) {}
}
```

## 11.2 Event Listeners

```typescript
// src/events/exam-finished.listener.ts
@Injectable()
export class ExamFinishedListener {
  constructor(
    private readonly scoreQueue: Queue,
    private readonly absensiService: AbsensiService,
  ) {}

  @OnEvent(ExamFinishedEvent.name)
  async handle(event: ExamFinishedEvent) {
    // 1. Enqueue score calculation job (untuk reliabilitas)
    await this.scoreQueue.add(QUEUES.SCORE_CALCULATION, {
      hasilUjianId: event.hasilUjianId,
    });

    // 2. Update absensi siswa menjadi 'hadir'
    await this.absensiService.markHadir(event.ujianId, event.siswaId);
  }
}

// src/events/ujian-published.listener.ts
@Injectable()
export class UjianPublishedListener {
  @OnEvent(UjianPublishedEvent.name)
  async handle(event: UjianPublishedEvent) {
    // Generate absensi records untuk semua siswa kelas target
    // Enqueue notification ke guru bahwa ujian sudah aktif
  }
}

// src/events/essay-graded.listener.ts
@Injectable()
export class EssayGradedListener {
  @OnEvent(EssayGradedEvent.name)
  async handle(event: EssayGradedEvent) {
    if (event.allEssayGraded) {
      // Recalculate nilai akhir termasuk komponen essay
      // Kirim notifikasi ke siswa
    }
  }
}
```

## 11.3 Event Flow Diagram

```
SesiUjianService.finish()
    │
    ├──► Update DB (status=selesai, nilai sementara)
    │
    └──► EventEmitter2.emit(ExamFinishedEvent)
              │
              ├──► ExamFinishedListener
              │         ├──► Enqueue ScoreCalculationJob
              │         └──► AbsensiService.markHadir()
              │
              └──► (Future) ProctorListener, AnalyticsListener

UjianService.publish()
    │
    └──► EventEmitter2.emit(UjianPublishedEvent)
              │
              └──► UjianPublishedListener
                        └──► AbsensiService.generateForUjian()
```

---

# 12. Caching Strategy

## 12.1 Redis Cache Layers

| Layer | Konten | TTL | Invalidasi |
|-------|--------|-----|-----------|
| **Sesi Ujian** | State aktif ujian (sisa waktu, urutan soal) | Durasi ujian + 30 menit | Saat finish/expired |
| **Pengaturan** | Key-value setting sistem | 5 menit | Saat PATCH /pengaturan |
| **Profil Sekolah** | Data profil | 10 menit | Saat PATCH /profil-sekolah |
| **Soal Snapshot** | Snapshot soal ujian yang aktif | 1 jam | Saat ujian diarchive |
| **Report PDF** | File PDF yang di-generate | 1 jam | Setelah TTL |
| **Monitoring Live** | State pengerjaan siswa untuk monitoring guru | 1 menit | Setiap heartbeat |
| **Rate Limit** | Counter per IP/user | Sesuai window | — |
| **Refresh Token Blacklist** | Token yang sudah di-revoke | Sisa TTL token | — |

## 12.2 Cache Keys Naming Convention

```
ujianonline:{env}:{resource}:{identifier}:{sub-resource}

Contoh:
ujianonline:prod:sesi:{hasilUjianId}:state
ujianonline:prod:sesi:{hasilUjianId}:soal-order
ujianonline:prod:ujian:{ujianId}:monitoring
ujianonline:prod:setting:all
ujianonline:prod:report:{ujianId}:nilai-pdf
ujianonline:prod:blacklist:token:{tokenHash}
ujianonline:prod:ratelimit:login:{ip}
```

## 12.3 Implementasi Cache Service

```typescript
// src/infrastructure/redis/redis.service.ts
@Injectable()
export class RedisService {
  constructor(@InjectRedis() private readonly redis: Redis) {}

  async get<T>(key: string): Promise<T | null> {
    const value = await this.redis.get(key);
    return value ? JSON.parse(value) : null;
  }

  async set(key: string, value: any, ttlSeconds?: number): Promise<void> {
    const serialized = JSON.stringify(value);
    if (ttlSeconds) {
      await this.redis.set(key, serialized, 'EX', ttlSeconds);
    } else {
      await this.redis.set(key, serialized);
    }
  }

  async del(key: string): Promise<void> {
    await this.redis.del(key);
  }

  async delPattern(pattern: string): Promise<void> {
    const keys = await this.redis.keys(pattern);
    if (keys.length > 0) await this.redis.del(...keys);
  }

  async incr(key: string, ttlSeconds?: number): Promise<number> {
    const count = await this.redis.incr(key);
    if (ttlSeconds && count === 1) await this.redis.expire(key, ttlSeconds);
    return count;
  }
}
```

## 12.4 Cache-Aside Pattern

```typescript
// Contoh di SesiUjianService
async getSesiState(hasilUjianId: string): Promise<SesiState> {
  const cacheKey = `ujianonline:prod:sesi:${hasilUjianId}:state`;
  const cached = await this.redis.get<SesiState>(cacheKey);
  if (cached) return cached;

  const sesi = await this.prisma.sesiUjian.findUnique({
    where: { hasilUjianId },
  });

  if (sesi) {
    await this.redis.set(cacheKey, sesi, 3600);
  }
  return sesi;
}
```

## 12.5 Cache Invalidation Strategy

- **Write-through pada update:** Setiap update setting langsung update cache
- **Delete on mutation:** Resource yang berubah → hapus cache, biarkan re-populate saat request berikutnya
- **TTL-based expiry:** Untuk data semi-statis (profil sekolah, setting)
- **Event-based invalidation:** `UjianArchivedEvent` → hapus cache snapshot soal ujian tersebut


---

# 13. Logging & Monitoring

## 13.1 Logger Setup (Winston)

```typescript
// src/providers/app-logger.provider.ts
import { WinstonModule } from 'nest-winston';
import * as winston from 'winston';

export const loggerConfig = WinstonModule.forRoot({
  transports: [
    new winston.transports.Console({
      format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.colorize(),
        winston.format.printf(({ timestamp, level, message, context, ...meta }) =>
          `${timestamp} [${context ?? 'App'}] ${level}: ${message} ${
            Object.keys(meta).length ? JSON.stringify(meta) : ''
          }`,
        ),
      ),
    }),
    new winston.transports.File({
      filename: 'logs/error.log',
      level: 'error',
      format: winston.format.combine(winston.format.timestamp(), winston.format.json()),
    }),
    new winston.transports.File({
      filename: 'logs/combined.log',
      format: winston.format.combine(winston.format.timestamp(), winston.format.json()),
    }),
  ],
});
```

## 13.2 Log Categories

| Kategori | Level | Konten | Storage |
|----------|-------|--------|---------|
| **Request Log** | info | Method, URL, status code, duration, userId | Console + file |
| **Error Log** | error | Stack trace, request context, userId | file + alert |
| **Auth Log** | info | Login attempt (success/fail), IP, userId | file |
| **Audit Log** | info | Siapa mengubah apa, kapan (CRUD penting) | Database (tabel `audit_logs`) |
| **Business Log** | debug | Kalkulasi nilai, state machine ujian | Console (dev only) |
| **Security Log** | warn | Rate limit hit, suspicious activity | file + alert |

## 13.3 Logging Interceptor

```typescript
// src/common/interceptors/logging.interceptor.ts
@Injectable()
export class LoggingInterceptor implements NestInterceptor {
  intercept(context: ExecutionContext, next: CallHandler): Observable<any> {
    const request  = context.switchToHttp().getRequest();
    const { method, url, user } = request;
    const start = Date.now();

    return next.handle().pipe(
      tap((response) => {
        const duration = Date.now() - start;
        this.logger.log({
          method, url, duration,
          userId: user?.sub ?? 'anonymous',
          statusCode: context.switchToHttp().getResponse().statusCode,
        });
      }),
      catchError((error) => {
        this.logger.error({ method, url, error: error.message, stack: error.stack });
        throw error;
      }),
    );
  }
}
```

## 13.4 Audit Log

Tabel `audit_logs` menyimpan history perubahan data penting:

```sql
CREATE TABLE audit_logs (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id    UUID NOT NULL,
  action     VARCHAR(50) NOT NULL,  -- CREATE, UPDATE, DELETE, LOGIN, PUBLISH
  resource   VARCHAR(100) NOT NULL, -- 'ujian', 'soal', 'user', dll
  resource_id UUID,
  old_value  JSONB,
  new_value  JSONB,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_audit_user ON audit_logs(user_id, created_at DESC);
CREATE INDEX idx_audit_resource ON audit_logs(resource, resource_id);
```

Audit log dicatat untuk: Login/Logout, CRUD User, Publish/Archive Ujian, Reset Password, Update Pengaturan, Koreksi Essay.

## 13.5 Health Check Endpoint

```typescript
// GET /health
// Menggunakan @nestjs/terminus
@Controller('health')
export class HealthController {
  @Get()
  @HealthCheck()
  check() {
    return this.health.check([
      () => this.db.pingCheck('database'),
      () => this.redis.checkHealth('redis'),
      () => this.http.pingCheck('storage', process.env.STORAGE_HEALTH_URL),
    ]);
  }
}
```

**Response (200):**
```json
{
  "status": "ok",
  "info": {
    "database": { "status": "up" },
    "redis": { "status": "up" },
    "storage": { "status": "up" }
  }
}
```

## 13.6 Monitoring Strategy

- **Application Metrics:** `@willsoto/nestjs-prometheus` — request count, latency histogram, error rate
- **Metrics endpoint:** `GET /metrics` (Prometheus format, hanya akses internal)
- **Alerting:** Prometheus Alertmanager atau Grafana alerts untuk: error rate > 5%, p99 latency > 2s, DB connection pool exhausted
- **Uptime monitoring:** External ping setiap 1 menit ke `/health`

---

# 14. File Storage

## 14.1 Upload Flow

```
Client → POST /soal/:id/media (multipart/form-data)
    │
    ▼
MulterModule (buffer in memory, max 10MB)
    │
    ▼
FileValidationPipe
    ├── Cek MIME type (magic bytes via file-type library)
    ├── Cek ukuran (max per setting pengaturan)
    └── Cek ekstensi whitelist
    │
    ▼
StorageService.upload(file, destination)
    ├── LocalStorageProvider.save() → /uploads/{year}/{month}/{uuid}.{ext}
    └── S3StorageProvider.upload() → s3://{bucket}/{path}
    │
    ▼
Update DB dengan path/URL file
Return URL file
```

## 14.2 Storage Abstraction

```typescript
// src/infrastructure/storage/storage.service.ts
export interface IStorageProvider {
  upload(file: Express.Multer.File, path: string): Promise<string>;
  delete(path: string): Promise<void>;
  getUrl(path: string): string;
}

@Injectable()
export class StorageService {
  constructor(
    @Inject('STORAGE_PROVIDER')
    private readonly provider: IStorageProvider,
  ) {}

  async uploadSoalMedia(file: Express.Multer.File, soalId: string): Promise<string> {
    const ext = path.extname(file.originalname).toLowerCase();
    const filename = `${soalId}-${Date.now()}${ext}`;
    return this.provider.upload(file, `soal/${filename}`);
  }

  async uploadProfilePhoto(file: Express.Multer.File, userId: string): Promise<string> {
    const ext = path.extname(file.originalname).toLowerCase();
    const filename = `${userId}${ext}`;
    return this.provider.upload(file, `profiles/${filename}`);
  }
}
```

## 14.3 Storage Drivers

**Local Storage (development/single-server):**
- Path: `./uploads/{category}/{year}/{month}/`
- Diakses via static file serving: `GET /static/{path}`
- Backup: rsync ke backup server

**S3 Compatible (production):**
- AWS S3 atau MinIO self-hosted
- Upload langsung dari NestJS menggunakan `@aws-sdk/client-s3`
- URL: signed URL (private) atau public URL tergantung jenis file
- CDN: CloudFront atau Nginx proxy di depan MinIO

## 14.4 File Validation

```typescript
const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const ALLOWED_DOCUMENT_MIMES = ['application/pdf'];
const MAX_IMAGE_SIZE = 5 * 1024 * 1024;   // 5 MB
const MAX_DOCUMENT_SIZE = 10 * 1024 * 1024; // 10 MB
```

Validasi menggunakan `file-type` library untuk deteksi MIME dari magic bytes — mencegah bypass ekstensi rename.

## 14.5 File Lifecycle

- **Soal media:** Soft-delete soal tidak menghapus file fisik. File dihapus via cron job cleanup mingguan untuk soal yang `deleted_at > 30 hari`
- **Profile photo:** Upload baru otomatis hapus foto lama
- **Import temp files:** Dihapus segera setelah job selesai atau gagal
- **Report PDF:** TTL 1 jam di Redis + 1 jam di storage, lalu dihapus

---

# 15. Testing Strategy

## 15.1 Unit Test

**Scope:** Service layer dan utility functions.

**Tools:** Jest + `@nestjs/testing`

**Contoh:**
```typescript
// src/modules/sesi-ujian/sesi-ujian.service.spec.ts
describe('SesiUjianService', () => {
  let service: SesiUjianService;
  let prisma: DeepMockProxy<PrismaService>;
  let redis: jest.Mocked<RedisService>;

  beforeEach(async () => {
    const module = await Test.createTestingModule({
      providers: [
        SesiUjianService,
        { provide: PrismaService, useValue: mockDeep<PrismaService>() },
        { provide: RedisService, useValue: createMock<RedisService>() },
        { provide: EventEmitter2, useValue: createMock<EventEmitter2>() },
      ],
    }).compile();

    service = module.get(SesiUjianService);
    prisma  = module.get(PrismaService);
    redis   = module.get(RedisService);
  });

  describe('finish()', () => {
    it('should calculate score correctly for pilihan_ganda', async () => {
      // Arrange: mock 4 soal dengan 3 jawaban benar
      // Act: service.finish(hasilUjianId, siswaId)
      // Assert: nilai = 75.00
    });

    it('should emit ExamFinishedEvent', async () => {
      // Assert: eventEmitter.emit called with ExamFinishedEvent
    });

    it('should throw if session already finished', async () => {
      // Assert: throws ConflictException
    });
  });
});
```

**Target coverage:** min 80% untuk service layer.

## 15.2 Integration Test

**Scope:** Controller → Service → Repository → Database (test DB).

**Tools:** Jest + `@nestjs/testing` + Prisma test DB + `supertest`

**Setup:**
```typescript
// test/integration/setup.ts
beforeAll(async () => {
  app = await createTestApp(); // NestJS app dengan DATABASE_URL test
  await prisma.$executeRaw`TRUNCATE TABLE ... CASCADE`;
  await seedTestData();
});

afterAll(async () => {
  await app.close();
});
```

**Contoh:**
```typescript
describe('POST /auth/login', () => {
  it('should return token on valid credentials', async () => {
    const res = await request(app.getHttpServer())
      .post('/auth/login')
      .send({ username: 'admin', password: 'Admin@123' });

    expect(res.status).toBe(200);
    expect(res.body.data).toHaveProperty('accessToken');
    expect(res.headers['set-cookie']).toBeDefined();
  });

  it('should return 401 on wrong password', async () => {
    const res = await request(app.getHttpServer())
      .post('/auth/login')
      .send({ username: 'admin', password: 'wrong' });

    expect(res.status).toBe(401);
  });
});
```

## 15.3 E2E Test

**Scope:** Flow lengkap dari login → kerjakan ujian → lihat hasil.

**Tools:** Jest + supertest + test database

**Contoh skenario:**
```typescript
describe('Exam Taking Flow (E2E)', () => {
  it('should complete full exam cycle', async () => {
    // 1. Login sebagai guru → publish ujian
    // 2. Login sebagai siswa → GET /ujian/available → verify ujian ada
    // 3. POST /ujian/:id/start → dapat sesiId
    // 4. GET /exam-sessions/:id/question?order=1 → dapat soal
    // 5. POST /exam-sessions/:id/answer → simpan jawaban
    // 6. (Ulangi untuk semua soal)
    // 7. POST /exam-sessions/:id/finish → dapat nilai
    // 8. GET /hasil-ujian/:id → verify data lengkap
    // 9. Login sebagai guru → GET /ujian/:id/hasil → verify nilai muncul
  });
});
```

## 15.4 Test Coverage Target

| Layer | Target |
|-------|--------|
| Utils & Helpers | 95% |
| Service Layer | 80% |
| Repository Layer | 70% |
| Controller Layer | 60% (integration) |
| E2E | Happy path + 3 edge cases per modul utama |

## 15.5 Mocking Strategy

- **Database:** `jest-mock-extended` untuk mock Prisma (unit test)
- **Redis:** `jest.fn()` / `createMock()` (unit test)
- **Queue:** Mock BullMQ producer, tidak test worker di unit test
- **Storage:** Mock `StorageService` (unit test), test dengan file nyata (integration)
- **EventEmitter:** Mock untuk verifikasi event emitted

## 15.6 Jest Config

```typescript
// jest.config.ts
export default {
  moduleFileExtensions: ['js', 'json', 'ts'],
  rootDir: 'src',
  testRegex: '.*\\.spec\\.ts$',
  transform: { '^.+\\.(t|j)s$': 'ts-jest' },
  collectCoverageFrom: ['**/*.(t|j)s', '!**/*.module.ts', '!**/main.ts'],
  coverageDirectory: '../coverage',
  testEnvironment: 'node',
  moduleNameMapper: { '^@/(.*)$': '<rootDir>/$1' },
};
```

---

# 16. Deployment Architecture

## 16.1 Dockerfile

```dockerfile
# docker/Dockerfile
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npx prisma generate
RUN npm run build

FROM node:20-alpine AS production
WORKDIR /app
ENV NODE_ENV=production
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/prisma ./prisma
EXPOSE 3000
CMD ["node", "dist/main.js"]
```

## 16.2 Docker Compose (Development)

```yaml
# docker-compose.yml
version: '3.9'
services:
  api:
    build: { context: ., dockerfile: docker/Dockerfile }
    ports: ["3000:3000"]
    environment:
      DATABASE_URL: postgresql://postgres:postgres@db:5432/ujianonline
      REDIS_URL: redis://redis:6379
    volumes: ["./src:/app/src"]    # hot reload di dev
    depends_on: [db, redis]
    command: npm run start:dev

  db:
    image: postgres:16-alpine
    environment: { POSTGRES_DB: ujianonline, POSTGRES_PASSWORD: postgres }
    ports: ["5432:5432"]
    volumes: ["postgres_data:/var/lib/postgresql/data"]

  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]
    volumes: ["redis_data:/data"]

  nginx:
    image: nginx:alpine
    ports: ["80:80", "443:443"]
    volumes:
      - ./docker/nginx.conf:/etc/nginx/nginx.conf
      - ./uploads:/var/www/uploads    # static file serving
    depends_on: [api]

volumes:
  postgres_data:
  redis_data:
```

## 16.3 Nginx Config (Reverse Proxy)

```nginx
# docker/nginx.conf
upstream api {
  server api:3000;
}

server {
  listen 80;
  server_name api.ujianonline.sekolah.id;
  return 301 https://$host$request_uri;
}

server {
  listen 443 ssl http2;
  server_name api.ujianonline.sekolah.id;

  ssl_certificate     /etc/nginx/certs/fullchain.pem;
  ssl_certificate_key /etc/nginx/certs/privkey.pem;

  client_max_body_size 20M;

  location /static/ {
    root /var/www;
    expires 30d;
    add_header Cache-Control "public, immutable";
  }

  location / {
    proxy_pass http://api;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 120s;
  }
}
```

## 16.4 Environment Variables (Lengkap)

```dotenv
# .env.example
NODE_ENV=production
PORT=3000
API_VERSION=v1

# Database
DATABASE_URL=postgresql://user:password@host:5432/ujianonline?schema=public&connection_limit=10

# Redis
REDIS_URL=redis://:password@host:6379
REDIS_PREFIX=ujianonline:prod

# JWT
JWT_SECRET=<min-64-chars-random-string>
JWT_REFRESH_SECRET=<min-64-chars-different-string>
JWT_EXPIRES_IN=15m
JWT_REFRESH_EXPIRES_IN=7d

# CORS
ALLOWED_ORIGINS=https://app.sekolah.id,https://admin.sekolah.id

# Storage
STORAGE_DRIVER=s3
STORAGE_LOCAL_PATH=./uploads
AWS_REGION=ap-southeast-1
AWS_BUCKET=ujianonline-prod
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_ENDPOINT=                   # Untuk MinIO self-hosted

# Email
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SECURE=false
MAIL_USER=
MAIL_PASS=
MAIL_FROM=noreply@sekolah.id

# App
TIMEZONE=Asia/Jakarta
MAX_UPLOAD_SIZE_MB=10
LOG_LEVEL=info
LOG_DIR=./logs
```

## 16.5 Production Init Container Pattern

```yaml
# docker-compose.prod.yml (excerpt)
services:
  migrate:
    image: ujianonline-api:latest
    command: npx prisma migrate deploy
    environment:
      DATABASE_URL: ${DATABASE_URL}
    restart: "no"

  seed:
    image: ujianonline-api:latest
    command: npx ts-node prisma/seed.ts
    depends_on:
      migrate: { condition: service_completed_successfully }
    restart: "no"

  api:
    image: ujianonline-api:latest
    depends_on:
      migrate: { condition: service_completed_successfully }
    restart: unless-stopped
    replicas: 2
```

## 16.6 Scaling Strategy

- **Horizontal scaling:** Multiple API container instances di belakang Nginx load balancer
- **Database:** PostgreSQL read replica untuk query berat (laporan, monitoring)
- **Redis:** Redis Sentinel untuk HA, atau Redis Cluster untuk scale-out
- **Session state:** Karena disimpan di Redis (bukan memory), scaling horizontal API container tidak bermasalah
- **File storage:** S3/MinIO untuk shared storage antar instance
- **Queue workers:** Scale BullMQ workers secara terpisah dari API

---

# 17. CI/CD Pipeline

## 17.1 Git Flow & Branching Strategy

```
main          ← Production branch (protected, no direct push)
staging       ← Pre-production (auto-deploy ke staging server)
develop       ← Integration branch (auto-test)
feature/*     ← Fitur baru (merge ke develop via PR)
fix/*         ← Bug fix (merge ke develop atau hotfix ke main)
hotfix/*      ← Critical fix langsung ke main + cherry-pick ke develop
```

**Aturan:**
- `main` dan `develop` protected: require PR + 1 reviewer approval + CI pass
- Setiap PR wajib lolos: lint, type-check, unit test, integration test
- Merge ke `main` trigger deploy ke production
- Merge ke `staging` trigger deploy ke staging

## 17.2 GitHub Actions Pipeline

```yaml
# .github/workflows/ci.yml
name: CI

on:
  push:
    branches: [main, staging, develop]
  pull_request:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env: { POSTGRES_DB: ujianonline_test, POSTGRES_PASSWORD: test }
        ports: ["5432:5432"]
      redis:
        image: redis:7
        ports: ["6379:6379"]

    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: 'npm' }

      - run: npm ci
      - run: npm run lint
      - run: npm run type-check
      - run: npx prisma migrate deploy
        env: { DATABASE_URL: postgresql://postgres:test@localhost:5432/ujianonline_test }
      - run: npm run test:unit -- --coverage
      - run: npm run test:integration
        env:
          DATABASE_URL: postgresql://postgres:test@localhost:5432/ujianonline_test
          REDIS_URL: redis://localhost:6379
      - uses: codecov/codecov-action@v4

  build:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main' || github.ref == 'refs/heads/staging'
    steps:
      - uses: actions/checkout@v4
      - name: Build & push Docker image
        run: |
          docker build -t ${{ secrets.REGISTRY }}/ujianonline-api:${{ github.sha }} .
          docker push ${{ secrets.REGISTRY }}/ujianonline-api:${{ github.sha }}

  deploy-staging:
    needs: build
    if: github.ref == 'refs/heads/staging'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to staging
        run: |
          ssh ${{ secrets.STAGING_HOST }} "
            docker pull ${{ secrets.REGISTRY }}/ujianonline-api:${{ github.sha }}
            docker-compose -f docker-compose.prod.yml up -d --no-deps api
          "

  deploy-production:
    needs: build
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: production   # Requires manual approval
    steps:
      - name: Deploy to production
        run: |
          ssh ${{ secrets.PROD_HOST }} "
            docker pull ${{ secrets.REGISTRY }}/ujianonline-api:${{ github.sha }}
            docker-compose -f docker-compose.prod.yml up -d --no-deps api
          "
```

## 17.3 Rollback Strategy

```bash
# Rollback ke image sebelumnya
docker-compose -f docker-compose.prod.yml \
  up -d --no-deps \
  -e IMAGE_TAG=<previous-sha> api

# Rollback database migration (jika ada breaking change)
npx prisma migrate resolve --rolled-back <migration_name>
# Kemudian buat migration baru yang memundurkan perubahan
```

**Prinsip:** Semua migration bersifat forward-only. Rollback database dilakukan dengan migration baru, bukan `migrate rollback`. Pastikan setiap deploy kompatibel backward dengan versi sebelumnya selama 1 release cycle.

---

# 18. Non Functional Requirements

## 18.1 Performance

| Metrik | Target |
|--------|--------|
| API response time (p50) | < 100ms |
| API response time (p99) | < 500ms |
| Throughput | 500 req/detik per instance |
| DB query time (p99) | < 200ms |
| File upload (5MB) | < 3 detik |
| PDF generation | < 5 detik |
| Exam answer save | < 50ms (Redis cache) |

**Strategi:**
- Database connection pooling (PgBouncer atau Prisma pool)
- Redis caching untuk data yang sering dibaca
- Pagination wajib di semua endpoint list
- N+1 query dihindari dengan Prisma `include` yang tepat
- Heavy computation (PDF, Excel) dijalankan via queue

## 18.2 Scalability

- Stateless API (session di Redis) → horizontal scale bebas
- Database read replica untuk query analitik
- Queue workers dapat di-scale terpisah
- File storage S3 (infinite scale)
- Auto-scaling via Docker Swarm atau Kubernetes HPA

## 18.3 Availability

- Target uptime: **99.9%** (SLA: max 8.7 jam downtime/tahun)
- Multi-instance API (min 2 di production)
- PostgreSQL HA (primary + standby dengan automatic failover)
- Redis Sentinel (HA) atau Redis Cluster
- Health check endpoint `/health` untuk load balancer
- Zero-downtime deployment via rolling update

## 18.4 Reliability

- Graceful shutdown: NestJS `enableShutdownHooks()` — tunggu request aktif selesai sebelum shutdown
- Database transaction untuk operasi multi-tabel (publish ujian, finish ujian)
- Idempotent API: `POST /exam-sessions/:id/answer` adalah upsert — aman di-retry
- Job retry dengan exponential backoff untuk operasi yang bisa gagal
- Circuit breaker untuk external service (email, S3)

## 18.5 Security

- JWT access token short-lived (15 menit)
- Refresh token rotation (stolen token terdeteksi jika digunakan lagi)
- Semua password bcrypt cost 12
- Rate limiting per IP dan per user
- Input validation di setiap endpoint
- SQL injection tidak mungkin (Prisma prepared statements)
- HTTPS wajib di production
- Security headers via Helmet
- Dependency vulnerability scan via `npm audit` di CI

## 18.6 Maintainability

- Modular architecture: satu modul = satu domain bisnis
- Prisma schema sebagai single source of truth untuk database
- TypeScript strict mode: `strict: true`
- ESLint + Prettier enforced via CI
- Semua endpoint terdokumentasi di Swagger
- Environment variable configuration (tidak ada hardcoded config)
- README developer guide untuk setup lokal

---

# 19. Future Development

## 19.1 Microservice Readiness

Arsitektur modular NestJS saat ini memudahkan ekstraksi modul menjadi microservice:

```
Kandidat pemisahan pertama (jika traffic tinggi):
- ExamService → Exam Microservice (paling critical path)
- ReportService → Report Microservice (CPU-intensive)
- NotificationService → Notification Microservice

Communication pattern:
- Sync: gRPC atau REST antar service
- Async: Apache Kafka atau RabbitMQ menggantikan in-process EventEmitter2
```

**Langkah migrasi:**
1. Ganti `EventEmitter2` dengan `@nestjs/microservices` (transport: Redis / Kafka)
2. Pisahkan module menjadi package tersendiri dalam monorepo (Nx atau Turborepo)
3. Extract satu per satu module yang paling independen terlebih dahulu

## 19.2 Multi-Tenant Readiness

Untuk mendukung banyak sekolah dalam satu deployment:

- Tambah kolom `tenant_id UUID` ke semua tabel utama
- Row-level security di PostgreSQL via `SET app.current_tenant`
- Subdomain routing: `sekolah-a.ujianonline.id` → tenant_id lookup
- Prisma middleware untuk auto-filter semua query berdasarkan tenant context
- Separate storage bucket per tenant
- Billing dan quota management per tenant

## 19.3 Horizontal Scaling Readiness

Sudah dipersiapkan sejak awal:
- ✅ Stateless API (Redis untuk session, tidak ada in-memory state)
- ✅ Distributed lock via Redis (untuk operasi publish ujian, avoid race condition)
- ✅ Shared file storage (S3)
- ✅ Health check endpoint untuk load balancer
- ✅ BullMQ workers dapat di-scale independent
- ✅ Database connection pooling

## 19.4 Cloud Native Readiness

**Kubernetes Migration Path:**
```yaml
# Minimal Kubernetes manifests (future):
- Deployment (API replicas)
- Service (internal load balancer)
- Ingress (Nginx/Traefik)
- HorizontalPodAutoscaler (scale berdasarkan CPU/request rate)
- CronJob (menggantikan @Cron() di NestJS untuk distributed env)
- ConfigMap (non-secret config)
- Secret (JWT secret, DB password)
- PersistentVolumeClaim (untuk local storage fallback)
```

**Observability Stack (Cloud Native):**
- Metrics: Prometheus + Grafana
- Logging: Loki + Grafana (atau ELK Stack)
- Tracing: OpenTelemetry + Jaeger / Tempo
- Alerting: Alertmanager + PagerDuty/Opsgenie

## 19.5 Feature Roadmap (Pasca v1.0)

| Prioritas | Fitur | Keterangan |
|-----------|-------|-----------|
| High | WebSocket / SSE | Real-time monitoring tanpa polling |
| High | Mobile API optimization | Endpoint khusus untuk mobile app |
| Medium | Soal bank sharing | Guru berbagi soal antar sekolah |
| Medium | Analytics dashboard | Analitik kesulitan soal, performa kelas |
| Medium | Webcam proctoring | Anti-kecurangan via kamera |
| Low | Multi-round competition | Mode olimpiade |
| Low | Payment gateway | Ujian berbayar (ujian sertifikasi) |
| Low | Multi-language | Antarmuka bahasa Inggris |

---

# Appendix

## A. Package.json Dependencies

```json
{
  "dependencies": {
    "@nestjs/common": "^10.x",
    "@nestjs/core": "^10.x",
    "@nestjs/platform-express": "^10.x",
    "@nestjs/jwt": "^10.x",
    "@nestjs/passport": "^10.x",
    "@nestjs/config": "^3.x",
    "@nestjs/schedule": "^4.x",
    "@nestjs/terminus": "^10.x",
    "@nestjs/throttler": "^5.x",
    "@nestjs/event-emitter": "^2.x",
    "@nestjs/swagger": "^7.x",
    "@nestjs/bull": "^10.x",
    "bull": "^4.x",
    "@prisma/client": "^5.x",
    "passport": "^0.7.x",
    "passport-jwt": "^4.x",
    "bcrypt": "^5.x",
    "class-validator": "^0.14.x",
    "class-transformer": "^0.5.x",
    "helmet": "^7.x",
    "compression": "^1.x",
    "ioredis": "^5.x",
    "winston": "^3.x",
    "nest-winston": "^1.x",
    "multer": "^1.x",
    "file-type": "^18.x",
    "sanitize-html": "^2.x",
    "exceljs": "^4.x",
    "puppeteer": "^21.x",
    "@aws-sdk/client-s3": "^3.x",
    "crypto": "built-in"
  },
  "devDependencies": {
    "prisma": "^5.x",
    "@types/bcrypt": "^5.x",
    "@types/multer": "^1.x",
    "@types/passport-jwt": "^3.x",
    "jest": "^29.x",
    "@nestjs/testing": "^10.x",
    "supertest": "^6.x",
    "jest-mock-extended": "^3.x",
    "@golevelup/ts-jest": "^0.4.x",
    "ts-jest": "^29.x"
  }
}
```

## B. Prisma Seed Data

```typescript
// prisma/seed.ts
async function main() {
  // 1. Buat user admin
  const adminUser = await prisma.user.upsert({
    where: { username: 'admin' },
    update: {},
    create: {
      username: 'admin',
      password: await bcrypt.hash('Admin@123', 12),
      role: 'admin',
      guru: { create: { nama: 'Administrator', isAdmin: true } },
    },
  });

  // 2. Default pengaturan
  const settings = [
    { nama: 'site_name', value: 'Sistem Ujian Online', deskripsi: 'Nama aplikasi' },
    { nama: 'passing_grade', value: '60', deskripsi: 'KKM default' },
    { nama: 'random_soal', value: 'true', deskripsi: 'Acak urutan soal' },
    { nama: 'allow_restart', value: 'false', deskripsi: 'Izinkan ulang ujian' },
    { nama: 'max_kecurangan', value: '3', deskripsi: 'Maks pelanggaran' },
    { nama: 'max_file_size', value: '5242880', deskripsi: 'Max upload (bytes)' },
  ];
  for (const setting of settings) {
    await prisma.pengaturan.upsert({
      where: { nama: setting.nama },
      update: {},
      create: setting,
    });
  }

  // 3. Sample kelas
  const kelasList = ['7A','7B','7C','8A','8B','8C','9A','9B','9C'];
  for (const nama of kelasList) {
    await prisma.kelas.upsert({
      where: { nama },
      update: {},
      create: { nama, tingkat: parseInt(nama[0]) },
    });
  }
}
```

## C. Error Code Reference

| Code | HTTP | Keterangan |
|------|------|-----------|
| `AUTH_001` | 401 | Token tidak ada atau format salah |
| `AUTH_002` | 401 | Token expired |
| `AUTH_003` | 401 | Username atau password salah |
| `AUTH_004` | 403 | Akun tidak aktif |
| `AUTH_005` | 403 | Tidak punya permission |
| `AUTH_006` | 429 | Terlalu banyak percobaan login |
| `UJIAN_001` | 404 | Ujian tidak ditemukan |
| `UJIAN_002` | 422 | Ujian tidak dalam status yang valid untuk operasi ini |
| `UJIAN_003` | 422 | Ujian belum memiliki soal |
| `UJIAN_004` | 403 | Waktu ujian belum/sudah berakhir |
| `SESI_001` | 409 | Sudah ada sesi aktif untuk ujian ini |
| `SESI_002` | 409 | Ujian sudah selesai dikerjakan |
| `SESI_003` | 404 | Sesi tidak ditemukan |
| `SESI_004` | 422 | Jawaban tidak valid |
| `SOAL_001` | 422 | Validasi soal gagal (kurang opsi, dll) |
| `IMPORT_001` | 422 | Format file tidak valid |
| `IMPORT_002` | 422 | Baris ke-N tidak valid: {detail} |
| `FILE_001` | 422 | Tipe file tidak diizinkan |
| `FILE_002` | 422 | Ukuran file melebihi batas |
| `VALIDATION_001` | 400 | Validasi input gagal |
| `DUPLICATE_001` | 409 | Data duplikat (username, NISN, kode mapel) |

