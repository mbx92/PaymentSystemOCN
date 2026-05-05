# 📋 Product Requirements Document (PRD)
## Sistem Pembukuan Usaha Jasa Software / Website

---

| | |
|---|---|
| **Versi** | 1.2.0 |
| **Status** | Draft |
| **Tanggal** | Mei 2026 |
| **Author** | Admin |
| **Stack** | Laravel 11 + Inertia.js + Vue 3 + Tailwind CSS + DaisyUI + PostgreSQL |

---

## Daftar Isi

1. [Latar Belakang](#1-latar-belakang)
2. [Tujuan Produk](#2-tujuan-produk)
3. [Ruang Lingkup](#3-ruang-lingkup)
4. [Pengguna & Role](#4-pengguna--role)
5. [Kebutuhan Fungsional](#5-kebutuhan-fungsional)
6. [Kebutuhan Non-Fungsional](#6-kebutuhan-non-fungsional)
7. [Skema Database](#7-skema-database)
8. [Alur Sistem](#8-alur-sistem)
9. [UI/UX Requirements](#9-uiux-requirements)
10. [Milestone & Timeline](#10-milestone--timeline)
11. [Kriteria Penerimaan](#11-kriteria-penerimaan)
12. [Asumsi & Batasan](#12-asumsi--batasan)

---

## 1. Latar Belakang

Usaha jasa pembuatan software/website yang beroperasi dengan model tim freelance menghadapi tantangan dalam mengelola keuangan project secara tertib. Selama ini pencatatan dilakukan secara manual melalui spreadsheet, yang rentan terhadap kesalahan, sulit diakses oleh beberapa anggota tim secara bersamaan, dan tidak memberikan visibilitas keuangan yang real-time.

### Permasalahan yang Ada

- Pembagian hasil tim dihitung manual setiap project selesai → rawan salah hitung
- Tidak ada riwayat pembayaran termin yang terpusat → sulit tracking pelunasan klien
- Anggota tim tidak bisa melihat porsi mereka secara transparan
- Tidak ada laporan laba per project yang mudah dibaca
- Data tersebar di WhatsApp, Excel, dan catatan pribadi

### Solusi

Membangun sistem pembukuan berbasis web yang dapat diakses multi-user, mencatat seluruh transaksi keuangan project, menghitung pembagian tim secara otomatis, dan menghasilkan laporan yang dapat di-export.

---

## 2. Tujuan Produk

### Tujuan Utama

> Menyediakan platform terpusat untuk mengelola keuangan usaha jasa software secara transparan, akurat, dan efisien — dari pencatatan project hingga pembagian hasil tim.

### Tujuan Spesifik

- ✅ Menggantikan pencatatan manual spreadsheet dengan sistem terintegrasi
- ✅ Memberikan visibilitas real-time terhadap laba per project
- ✅ Mengotomatisasi perhitungan pembagian hasil tim berdasarkan persentase
- ✅ Memberikan akses transparan kepada anggota tim tentang porsi mereka
- ✅ Menghasilkan laporan keuangan yang dapat di-export (Excel/PDF)

---

## 3. Ruang Lingkup

### In Scope (Versi 1.0)

| Modul | Deskripsi |
|---|---|
| Autentikasi & Role | Login, logout, manajemen user dengan 3 level akses |
| Dashboard | Statistik keuangan real-time, chart, project aktif |
| Manajemen Project | CRUD project + tracking termin pembayaran |
| Kas Masuk | Pencatatan pemasukan per project |
| Kas Keluar | Pencatatan pengeluaran (tim, komisi, operasional) |
| Pembagian Tim | Kalkulator otomatis + riwayat pembayaran per anggota |
| Laporan | Laporan per project, bulanan, dan per anggota tim |
| Export | Export Excel & PDF untuk semua laporan |

### Out of Scope (Versi 1.0)

- Integrasi payment gateway (Midtrans/Xendit)
- Notifikasi email/WhatsApp otomatis
- Aplikasi mobile native
- Multi-tenant (untuk beberapa usaha berbeda)
- Invoice generator ke klien
- Time tracking per task

---

## 4. Pengguna & Role

### 4.1 Deskripsi Role

#### 🔴 Admin
Pemilik usaha / lead yang bertanggung jawab penuh atas sistem.

**Akses:**
- Full CRUD semua modul
- Manajemen user & role
- Melihat semua laporan
- Menghapus data (soft delete)
- Export semua laporan

#### 🟡 Manajer
Anggota kepercayaan yang membantu operasional pembukuan.

**Akses:**
- CRUD project, kas masuk, kas keluar
- Input & edit pembagian tim
- Melihat semua laporan
- Export laporan
- Tidak bisa: kelola user, hapus permanen

#### 🟢 Anggota
Developer / desainer yang terlibat dalam project.

**Akses:**
- Melihat list project tempat mereka terlibat
- Melihat porsi/bayaran mereka sendiri per project
- Melihat total pendapatan mereka (dashboard personal)
- Tidak bisa: input/edit data apapun

### 4.2 User Stories

#### Admin
```
Sebagai Admin, saya ingin:
- Menambah project baru dengan detail klien dan nilai kontrak
- Melihat laba bersih per project secara real-time
- Menghitung dan menyimpan pembagian hasil ke anggota tim
- Mengekspor laporan bulanan ke Excel untuk arsip
- Mengelola akun user dan menetapkan role
```

#### Manajer
```
Sebagai Manajer, saya ingin:
- Mencatat pembayaran termin dari klien saat diterima
- Mencatat pengeluaran project (biaya tim, komisi, operasional)
- Melihat dashboard keuangan keseluruhan
- Mengekspor laporan untuk dilaporkan ke pemilik usaha
```

#### Anggota
```
Sebagai Anggota, saya ingin:
- Melihat project apa saja yang saya kerjakan
- Mengetahui berapa bayaran saya per project (base pay + bonus)
- Melihat total pendapatan saya dalam periode tertentu
- Memastikan pembayaran yang tercatat sesuai dengan yang saya terima
```

---

## 5. Kebutuhan Fungsional

### 5.1 Autentikasi & Manajemen User

| ID | Kebutuhan | Prioritas |
|---|---|---|
| AUTH-01 | Login dengan email & password | 🔴 Must |
| AUTH-02 | Logout dari semua device | 🔴 Must |
| AUTH-03 | Admin dapat CRUD user | 🔴 Must |
| AUTH-04 | Admin dapat assign role ke user | 🔴 Must |
| AUTH-05 | Middleware proteksi route berdasarkan role | 🔴 Must |
| AUTH-06 | Tampilkan nama & role user di navbar | 🟡 Should |
| AUTH-07 | Ganti password oleh user sendiri | 🟡 Should |
| AUTH-08 | Reset password via email | 🟢 Could |

### 5.2 Dashboard

| ID | Kebutuhan | Prioritas |
|---|---|---|
| DASH-01 | Card statistik: Total Pendapatan, Total Biaya, Laba Bersih, Project Aktif | 🔴 Must |
| DASH-02 | Tabel 5 project terbaru + status | 🔴 Must |
| DASH-03 | Bar chart pendapatan vs pengeluaran (6 bulan) | 🔴 Must |
| DASH-04 | Filter tahun untuk chart | 🟡 Should |
| DASH-05 | Dashboard personal untuk role Anggota (porsi mereka saja) | 🔴 Must |
| DASH-06 | Indikator project yang termin-nya sudah jatuh tempo | 🟡 Should |

### 5.3 Manajemen Project

| ID | Kebutuhan | Prioritas |
|---|---|---|
| PROJ-01 | Tambah project (nama, klien, kontak, nilai, status, tanggal) | 🔴 Must |
| PROJ-02 | Edit project | 🔴 Must |
| PROJ-03 | Soft delete project | 🔴 Must |
| PROJ-04 | List project dengan filter status & pencarian | 🔴 Must |
| PROJ-05 | Otomatis buat 3 termin (30%/40%/30%) saat project dibuat | 🔴 Must |
| PROJ-06 | Tandai termin sebagai "lunas" dengan tanggal | 🔴 Must |
| PROJ-07 | Progress bar termin di list project | 🟡 Should |
| PROJ-08 | Halaman detail project dengan tab Info, Kas, Pembagian Tim | 🔴 Must |
| PROJ-09 | Summary keuangan per project (masuk, keluar, laba) | 🔴 Must |
| PROJ-10 | Status project: Negosiasi, Berjalan, Selesai, Dibatalkan | 🔴 Must |

### 5.4 Kas Masuk

| ID | Kebutuhan | Prioritas |
|---|---|---|
| KM-01 | Tambah kas masuk (project, kategori, jumlah, tanggal, keterangan) | 🔴 Must |
| KM-02 | Edit & hapus kas masuk | 🔴 Must |
| KM-03 | List dengan filter project, kategori, rentang tanggal | 🔴 Must |
| KM-04 | Summary total periode di atas tabel | 🟡 Should |
| KM-05 | Format input angka Rupiah (thousand separator) | 🔴 Must |
| KM-06 | Export Excel dengan filter aktif | 🟡 Should |
| KM-07 | Kategori: Pendapatan Jasa, Lainnya | 🔴 Must |

### 5.5 Kas Keluar

| ID | Kebutuhan | Prioritas |
|---|---|---|
| KK-01 | Tambah kas keluar (project, kategori, jumlah, tanggal, keterangan, penerima) | 🔴 Must |
| KK-02 | Edit & hapus kas keluar | 🔴 Must |
| KK-03 | List dengan filter project, kategori, rentang tanggal | 🔴 Must |
| KK-04 | Breakdown pengeluaran per kategori | 🟡 Should |
| KK-05 | Export Excel | 🟡 Should |
| KK-06 | Kategori: Biaya Tim, Komisi Referral, Operasional, Lainnya | 🔴 Must |

### 5.6 Kalkulator Pembagian Tim

| ID | Kebutuhan | Prioritas |
|---|---|---|
| TIM-01 | Pilih project → tampilkan nilai bersih otomatis | 🔴 Must |
| TIM-02 | Input komisi referral (nama + jumlah, multi-entry) | 🔴 Must |
| TIM-03 | Input anggota tim + peran + persentase + base pay + bonus | 🔴 Must |
| TIM-04 | Kalkulasi total per anggota secara real-time (Vue `computed` / `watch`, client-side) | 🔴 Must |
| TIM-05 | Preset: "1 Lead + 2 Dev" → isi persentase 45%/27.5%/27.5% | 🟡 Should |
| TIM-06 | Warning jika total persentase ≠ 100% | 🔴 Must |
| TIM-07 | Warning jika total bayar melebihi nilai bersih tim | 🔴 Must |
| TIM-08 | Simpan hasil ke database | 🔴 Must |
| TIM-09 | Tampilkan riwayat pembagian sebelumnya | 🟡 Should |
| TIM-10 | Anggota dapat melihat porsi mereka sendiri | 🔴 Must |
| TIM-11 | Summary card: Nilai, Komisi, Operasional, Total Tim, Sisa Profit | 🔴 Must |

### 5.7 Laporan

| ID | Kebutuhan | Prioritas |
|---|---|---|
| LAP-01 | Laporan laba per project (nilai, masuk, komisi, tim, operasional, laba, margin%) | 🔴 Must |
| LAP-02 | Highlight laba rendah (< 20% = kuning, < 0 = merah) | 🟡 Should |
| LAP-03 | Laporan rekap bulanan + filter bulan & tahun | 🔴 Must |
| LAP-04 | Pie chart breakdown pengeluaran per kategori | 🟡 Should |
| LAP-05 | Laporan pembayaran per anggota tim | 🔴 Must |
| LAP-06 | Export laporan ke Excel | 🔴 Must |
| LAP-07 | Export laporan ke PDF | 🟡 Should |
| LAP-08 | Header export: nama sistem, filter aktif, tanggal generate, user | 🟡 Should |

---

## 6. Kebutuhan Non-Fungsional

### 6.1 Performa

| Kebutuhan | Target |
|---|---|
| Waktu load halaman (first paint) | < 2 detik pada koneksi normal |
| Waktu respon kalkulasi Vue reaktif (client-side) | < 100ms |
| Waktu respon API/Inertia request | < 500ms |
| Maksimal data per tabel (tanpa pagination) | 15 baris |
| Waktu export Excel (< 1000 baris) | < 5 detik |

### 6.2 Keamanan

- Semua route wajib authenticated (kecuali halaman login)
- Middleware role check di setiap route yang dibatasi
- CSRF protection aktif di semua form (Laravel default)
- Password di-hash dengan bcrypt
- Tidak ada data sensitif yang di-expose di URL
- Soft delete — data tidak terhapus permanen kecuali oleh admin

### 6.3 Kompatibilitas

| Item | Spesifikasi |
|---|---|
| Browser | Chrome 90+, Firefox 88+, Safari 14+, Edge 90+ |
| Resolusi minimum | 1280 × 720 (desktop) |
| Mobile support | Responsive hingga 375px (iPhone SE) |
| PHP | 8.2+ |
| PostgreSQL | 15+ |
| Node.js (build) | 20+ |
| Vue | 3.4+ (Composition API + `<script setup>`) |
| Vite | 5+ (sebagai asset bundler) |

### 6.4 Maintainability

- Kode PHP mengikuti PSR-12 coding standard
- Setiap Vue component memiliki satu tanggung jawab (SRP) — gunakan Composition API (`<script setup>`)
- Gunakan Laravel Form Request untuk validasi di sisi server
- Validasi form di sisi client menggunakan `@vee-validate/valibot` atau komposisi manual dengan `ref` & `computed`
- Database query menggunakan Eloquent ORM (tidak raw query)
- State management kompleks menggunakan Pinia store
- Komentar pada method/composable yang kompleks

---

## 7. Skema Database

### Diagram Relasi (ERD Sederhana)

```
users
  └──< team_distributions >──┐
                              │
projects ──< project_payments │
  │                           │
  ├──< cash_in               │
  ├──< cash_out              │
  ├──< referrals             │
  └──< team_distributions >──┘
```

### Definisi Tabel

> Tipe data menggunakan konvensi PostgreSQL. `ENUM` diganti dengan `VARCHAR` + check constraint agar lebih fleksibel dan mudah dimigrasikan. Laravel migration menggunakan `$table->string()` + `->default()`.

#### `users`
```
id                BIGSERIAL PRIMARY KEY
name              VARCHAR(255)
email             VARCHAR(255) UNIQUE NOT NULL
email_verified_at TIMESTAMPTZ NULL
password          VARCHAR(255)
remember_token    VARCHAR(100) NULL
created_at        TIMESTAMPTZ
updated_at        TIMESTAMPTZ
```

#### `projects`
```
id              BIGSERIAL PRIMARY KEY
name            VARCHAR(255)
client_name     VARCHAR(255)
client_contact  VARCHAR(255) NULL
total_value     NUMERIC(15,2)
status          VARCHAR(20) DEFAULT 'negosiasi'
                  CHECK (status IN ('negosiasi','berjalan','selesai','dibatalkan'))
started_at      DATE NULL
finished_at     DATE NULL
description     TEXT NULL
created_at      TIMESTAMPTZ
updated_at      TIMESTAMPTZ
deleted_at      TIMESTAMPTZ NULL  -- soft delete
```

#### `project_payments` (termin)
```
id              BIGSERIAL PRIMARY KEY
project_id      BIGINT REFERENCES projects(id) ON DELETE CASCADE
term_number     SMALLINT  -- 1, 2, 3
percentage      NUMERIC(5,2)
amount          NUMERIC(15,2)
paid_at         DATE NULL
note            TEXT NULL
created_at      TIMESTAMPTZ
updated_at      TIMESTAMPTZ
```

#### `cash_in` (kas masuk)
```
id              BIGSERIAL PRIMARY KEY
project_id      BIGINT REFERENCES projects(id) ON DELETE CASCADE
category        VARCHAR(30) DEFAULT 'pendapatan_jasa'
                  CHECK (category IN ('pendapatan_jasa','lainnya'))
amount          NUMERIC(15,2)
date            DATE
note            TEXT NULL
created_by      BIGINT REFERENCES users(id)
created_at      TIMESTAMPTZ
updated_at      TIMESTAMPTZ
```

#### `cash_out` (kas keluar)
```
id              BIGSERIAL PRIMARY KEY
project_id      BIGINT REFERENCES projects(id) ON DELETE CASCADE
category        VARCHAR(30)
                  CHECK (category IN ('biaya_tim','komisi_referral','operasional','lainnya'))
amount          NUMERIC(15,2)
date            DATE
note            TEXT NULL
recipient_name  VARCHAR(255) NULL
created_by      BIGINT REFERENCES users(id)
created_at      TIMESTAMPTZ
updated_at      TIMESTAMPTZ
```

#### `team_distributions` (pembagian tim)
```
id              BIGSERIAL PRIMARY KEY
project_id      BIGINT REFERENCES projects(id) ON DELETE CASCADE
user_id         BIGINT REFERENCES users(id)
role_in_project VARCHAR(20)
                  CHECK (role_in_project IN ('lead','developer','designer','qa'))
percentage      NUMERIC(5,2)
base_pay        NUMERIC(15,2)
bonus           NUMERIC(15,2) DEFAULT 0
total_pay       NUMERIC(15,2)  -- base_pay + bonus, bisa dihitung via generated column
created_at      TIMESTAMPTZ
updated_at      TIMESTAMPTZ
```

#### `referrals`
```
id                BIGSERIAL PRIMARY KEY
project_id        BIGINT REFERENCES projects(id) ON DELETE CASCADE
referrer_name     VARCHAR(255)
commission_amount NUMERIC(15,2)
paid_at           DATE NULL
note              TEXT NULL
created_at        TIMESTAMPTZ
updated_at        TIMESTAMPTZ
```

---

## 8. Alur Sistem

### 8.1 Alur Utama: Project Baru hingga Selesai

```
1. Admin/Manajer tambah project baru
       ↓
2. Sistem otomatis buat 3 termin pembayaran (30/40/30)
       ↓
3. Klien bayar termin → Admin/Manajer input di Kas Masuk
   + tandai termin sebagai "lunas"
       ↓
4. Admin/Manajer catat pengeluaran di Kas Keluar
   (biaya tim, komisi referral, operasional)
       ↓
5. Setelah project selesai → Admin input pembagian tim
   via Kalkulator Pembagian Tim
       ↓
6. Anggota tim bisa login → lihat porsi mereka
       ↓
7. Admin generate laporan → export Excel/PDF
```

### 8.2 Alur Kalkulator Pembagian Tim

```
Pilih Project
    ↓
Tampilkan: Nilai Project, Komisi Referral (dari DB), Operasional
    ↓
Hitung: Nilai Bersih = Nilai - Komisi - Operasional
    ↓
Input anggota tim + persentase + bonus (real-time)
    ↓
Validasi: total % = 100? Total bayar ≤ nilai bersih?
    ↓
Simpan → update tabel team_distributions
    ↓
Anggota dapat melihat porsi mereka di dashboard personal
```

### 8.3 Alur Role & Akses

```
Login
  ↓
Cek Role
  ├── Admin    → Full dashboard + semua menu
  ├── Manajer  → Dashboard + project + kas + laporan (tanpa user mgmt)
  └── Anggota  → Dashboard personal (porsi sendiri saja)
```

---

## 9. UI/UX Requirements

### 9.1 Design System

| Elemen | Spesifikasi |
|---|---|
| CSS Framework | Tailwind CSS v3 |
| Component Library | DaisyUI v4 |
| Theme | `corporate` atau `business` |
| Font | Inter / sistem default |
| Icon | `@heroicons/vue` (Vue 3 package) |
| Chart | Chart.js v4 via `vue-chartjs` wrapper |
| SPA Navigation | Inertia.js `<Link>` component (tanpa full page reload) |

### 9.2 Layout

- **Sidebar** — navigasi kiri menggunakan DaisyUI `drawer`, collapsible di mobile; diimplementasikan sebagai Vue component (`AppSidebar.vue`) yang di-share via Inertia shared layout
- **Topbar** — nama user + role badge + tombol logout; data user dikirim via Inertia `HandleInertiaRequests` middleware (shared props)
- **Breadcrumb** — di setiap halaman selain dashboard; dirender dinamis dari prop halaman
- **Content area** — max-width `7xl`, padding responsif
- **Layout** — satu file layout utama `AppLayout.vue` yang membungkus semua halaman via `<slot />`

### 9.3 Komponen UI yang Wajib

Semua komponen di bawah diimplementasikan sebagai Vue Single File Component (`.vue`) yang menggunakan kelas DaisyUI:

| Komponen DaisyUI | Vue Component | Penggunaan |
|---|---|---|
| `badge` | `StatusBadge.vue` | Status project, kategori kas, role user |
| `progress` | `TerminProgress.vue` | Progress termin per project |
| `stats` | `StatCard.vue` | Card ringkasan di dashboard |
| `table` + `table-zebra` | `DataTable.vue` | Semua tabel data |
| `modal` | `ConfirmModal.vue`, `FormModal.vue` | Konfirmasi hapus, form tambah/edit |
| `alert` | `ValidationAlert.vue` | Warning validasi (persentase, batas pembagian) |
| `tabs` | `ProjectDetailTabs.vue` | Detail project (Info, Kas, Tim) |
| `select` + `input` | `CurrencyInput.vue`, form fields | Semua form, format Rupiah |

### 9.4 Struktur Direktori Vue

```
resources/js/
├── app.js                   # Entry point Inertia + Vue
├── bootstrap.js
├── Layouts/
│   └── AppLayout.vue        # Layout utama (sidebar + topbar)
├── Pages/
│   ├── Auth/
│   │   └── Login.vue
│   ├── Dashboard/
│   │   └── Index.vue
│   ├── Projects/
│   │   ├── Index.vue
│   │   ├── Create.vue
│   │   ├── Edit.vue
│   │   └── Show.vue         # Tab Info, Kas, Tim
│   ├── CashIn/
│   ├── CashOut/
│   ├── TeamDistribution/
│   │   └── Calculator.vue   # Kalkulator reaktif (computed)
│   ├── Reports/
│   └── Users/
├── Components/
│   ├── StatCard.vue
│   ├── DataTable.vue
│   ├── CurrencyInput.vue
│   ├── StatusBadge.vue
│   ├── ConfirmModal.vue
│   └── Charts/
│       ├── RevenueBarChart.vue
│       └── ExpensePieChart.vue
└── composables/
    └── useCurrency.js       # Format & parse Rupiah
```

### 9.5 Responsif

- Desktop (≥ 1280px): sidebar selalu tampil, tabel penuh
- Tablet (768px–1279px): sidebar collapsible, tabel scroll horizontal
- Mobile (< 768px): hamburger menu, card view untuk tabel utama

---

## 10. Milestone & Timeline

### Estimasi Pengerjaan (Solo Developer)

| Fase | Modul | Estimasi |
|---|---|---|
| **Fase 1** | Setup project, database, auth, role | 1–2 hari |
| **Fase 2** | Dashboard + layout utama | 1–2 hari |
| **Fase 3** | Manajemen Project + Termin | 2–3 hari |
| **Fase 4** | Kas Masuk + Kas Keluar | 1–2 hari |
| **Fase 5** | Kalkulator Pembagian Tim | 2–3 hari |
| **Fase 6** | Laporan + Export | 2–3 hari |
| **Fase 7** | Testing, bug fix, polish | 1–2 hari |
| **Total** | | **~10–17 hari kerja** |

### Prioritas MVP

Jika waktu terbatas, urutan implementasi yang disarankan:

```
🔴 Must (MVP) → Fase 1, 2, 3, sebagian Fase 5
🟡 Should     → Fase 4, Fase 6 (Excel only)
🟢 Could      → PDF export, chart, filter lanjutan
```

---

## 11. Kriteria Penerimaan

Sistem dinyatakan **siap digunakan** jika semua kriteria berikut terpenuhi:

### Fungsional

- [ ] Admin dapat login dan logout
- [ ] Admin dapat membuat project dan 3 termin terbuat otomatis
- [ ] Admin dapat menandai termin sebagai lunas
- [ ] Admin dapat mencatat kas masuk dan kas keluar
- [ ] Kalkulator pembagian tim menghitung real-time dan menyimpan ke DB
- [ ] Anggota dapat login dan melihat porsi mereka (tidak lebih)
- [ ] Laporan laba per project tampil dengan benar
- [ ] Export Excel berhasil dengan data yang akurat

### Role & Keamanan

- [ ] Anggota tidak bisa akses halaman admin/manajer (redirect 403)
- [ ] URL tidak bisa diakses tanpa login
- [ ] Tidak ada route yang bypass middleware

### Kalkulasi

- [ ] Nilai bersih tim = nilai project - komisi - operasional ✓
- [ ] Total per anggota = base pay + bonus ✓
- [ ] Laba project = kas masuk - kas keluar ✓
- [ ] Warning muncul jika total persentase ≠ 100% ✓

---

## 12. Asumsi & Batasan

### Asumsi

- Sistem digunakan oleh satu usaha (single-tenant)
- Semua transaksi dalam mata uang Rupiah (IDR)
- Satu project memiliki tepat 3 termin pembayaran (30/40/30) — bisa diubah manual
- Anggota tim adalah pengguna terdaftar di sistem (bukan pihak eksternal)
- Server menggunakan VPS dengan PHP 8.2+ dan PostgreSQL 15+ (shared hosting umumnya tidak mendukung PostgreSQL)
- Koneksi database dikonfigurasi via `DB_CONNECTION=pgsql` di file `.env`

### Batasan Versi 1.0

- Tidak ada integrasi bank atau payment gateway
- Tidak ada notifikasi otomatis (email/WA)
- Tidak ada audit log / history perubahan data
- Tidak ada fitur backup otomatis
- Tidak ada multi-bahasa (hanya Bahasa Indonesia)
- Komisi referral hanya untuk orang di luar sistem (tidak punya akun)

---

## Lampiran

### Teknologi & Package

```bash
# Core (Composer)
laravel/laravel:^11.0
laravel/breeze:^2.0             # Auth scaffold (pilih stack: Inertia + Vue)
inertiajs/inertia-laravel:^1.0  # Server-side Inertia adapter
spatie/laravel-permission:^6.0  # Role & permission
tightenco/ziggy:^2.0            # Route helper untuk Vue (route())

# Export (Composer)
maatwebsite/excel:^3.1          # Export Excel
barryvdh/laravel-dompdf:^2.0    # Export PDF

# Frontend (npm)
vue:^3.0
@inertiajs/vue3:^1.0
@vitejs/plugin-vue:^5.0         # Vite plugin untuk Vue SFC
tailwindcss:^3.0
daisyui:^4.0
@heroicons/vue:^2.0             # Icon set Vue
vue-chartjs:^5.0                # Chart.js wrapper untuk Vue
chart.js:^4.0
pinia:^2.0                      # State management (opsional, untuk state kompleks)
```

### Arsitektur Inertia.js

Inertia.js berperan sebagai "jembatan" antara Laravel (server) dan Vue (client), sehingga:

- **Tidak ada REST API terpisah** — controller Laravel tetap me-return `Inertia::render('PageName', $props)` seperti layaknya Blade view
- **Navigasi SPA** — perpindahan halaman tanpa full page reload via `<Link>` component dari `@inertiajs/vue3`
- **Shared props** — data global (user, flash message, dll) dikirim sekali via `HandleInertiaRequests` middleware, tersedia di semua halaman
- **Form handling** — gunakan `useForm()` dari `@inertiajs/vue3` untuk submit form dengan handling error otomatis
- **Server-side validation** — error Laravel Form Request otomatis diteruskan ke Vue sebagai `form.errors`

```
Browser (Vue 3 SPA)
    ↕  Inertia XHR request (JSON)
Laravel Controller
    → Inertia::render('Projects/Index', ['projects' => $projects])
    ↓
resources/js/Pages/Projects/Index.vue
    → menerima $projects sebagai defineProps()
```

### Referensi Format Angka

Semua input nominal menggunakan format:
- Input: `15.000.000` atau `15000000` (keduanya diterima)
- Tampilan: `Rp 15.000.000` (dengan prefix Rp dan thousand separator titik)
- Database: `15000000.00` (decimal, tanpa separator)

---

*PRD ini adalah dokumen hidup dan dapat diperbarui seiring perkembangan proyek.*

*Versi 1.2 — Sistem Pembukuan Jasa Software (Stack: Laravel 11 + Inertia.js + Vue 3 + PostgreSQL)*
