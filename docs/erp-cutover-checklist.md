# ERP Cutover Checklist

## 1. Pre-Cutover
- Freeze transaksi baru pada sistem lama di akhir hari kerja.
- Jalankan `php artisan erp:migration-dry-run` dan simpan output sebagai baseline.
- Backup penuh database produksi.
- Validasi akun COA, fiscal period aktif, dan master data inti (customer, vendor, item, employee).

## 2. Migration
- Jalankan migration ERP: `php artisan migrate`.
- Jalankan seed baseline: `php artisan db:seed`.
- Migrasikan data master dan saldo awal per modul menggunakan script ETL idempotent.
- Migrasikan transaksi berjalan yang belum settle.

## 3. Reconciliation
- Bandingkan total kas, AR, AP, dan profit project sebelum/sesudah migrasi.
- Verifikasi jurnal dari transaksi kas sudah terbentuk pada `journal_entries` dan `journal_lines`.
- Jalankan spot check minimal 10 dokumen per modul.

## 4. Go-Live
- Buka akses user bertahap berdasarkan role.
- Monitor error log, queue, dan performa query selama 24 jam pertama.
- Siapkan rollback plan jika mismatch finansial material ditemukan.
