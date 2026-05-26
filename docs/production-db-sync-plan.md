# Production DB Sync Plan

## Tujuan
- Source data: database kerja `ocn_erp` saat ini.
- Target data: database production terpisah (`production_ocn_erp`).
- Metode: import bertahap per modul atau per tabel, selalu mulai dari `dry-run`, lalu `--execute` setelah validasi.

## Konfigurasi
Isi environment berikut pada server/tool yang menjalankan cutover:

```env
PRODUCTION_DB_HOST=
PRODUCTION_DB_PORT=5432
PRODUCTION_DB_DATABASE=
PRODUCTION_DB_USERNAME=
PRODUCTION_DB_PASSWORD=
PRODUCTION_DB_SCHEMA=public
PRODUCTION_DB_SSLMODE=prefer
```

Opsional:

```env
PRODUCTION_SYNC_SOURCE_CONNECTION=pgsql
PRODUCTION_SYNC_TARGET_CONNECTION=production_ocn_erp
```

## Command
Preview semua modul:

```bash
php artisan erp:production-sync
```

Eksekusi modul tertentu:

```bash
php artisan erp:production-sync --module=core --execute
php artisan erp:production-sync --module=inventory_master --execute
php artisan erp:production-sync --module=crm --execute
```

Eksekusi tabel tertentu:

```bash
php artisan erp:production-sync --table=projects --table=project_payments --execute
```

## Urutan yang disarankan
1. `core`
2. `inventory_master`
3. `crm`
4. `projects`
5. `purchasing`
6. `sales`
7. `cashflow_accounting`
8. `inventory_movements`
9. `hr`
10. `rnd`

## Aturan Operasional
- Selalu jalankan `dry-run` lebih dulu untuk modul yang akan dipindah.
- Setelah `--execute`, lakukan rekonsiliasi row count dan spot check dokumen.
- Jangan jalankan semua modul sekaligus di awal cutover. Naikkan bertahap.
- Command ini memakai `upsert` berbasis `id`, jadi aman untuk rerun selama source adalah data final yang memang ingin dipromosikan ke production.

## Catatan
- Flow normal aplikasi tidak berubah. Ini khusus untuk proses promosi data dari DB kerja ke DB production.
- Daftar tabel per modul ada di `config/production_sync.php` dan bisa disesuaikan jika scope bisnis OCN berubah.
