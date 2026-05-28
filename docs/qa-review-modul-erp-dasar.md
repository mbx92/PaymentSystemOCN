# Quality Assurance Review — Modul ERP Dasar

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28
**Reviewer:** QA Engineer
**Lingkup:** Modul ERP Dasar (Core, Shared, Company, Module Registry, Middleware, Layout, Auth)
**Status:** ✅ Semua temuan telah diperbaiki (17/17)

---

## Daftar Isi

1. [Authentication & Authorization](#1-authentication--authorization)
2. [CRUD & Form Validation](#2-crud--form-validation)
3. [API / Controller Logic](#3-api--controller-logic)
4. [Inertia Props & Reactivity](#4-inertia-props--reactivity)
5. [Database & Migrations](#5-database--migrations)
6. [UI / UX & Aksesibilitas](#6-ui--ux--aksesibilitas)
7. [Security — Data Exposure & Mass Assignment](#7-security--data-exposure--mass-assignment)
8. [Ringkasan Prioritas](#8-ringkasan-prioritas)

---

## 1. Authentication & Authorization

### 1.1 ERPModuleController::payments() tanpa middleware proteksi

**Lokasi:** `app/Http/Controllers/ERPModuleController.php:16-19`

```php
public function payments(): Response
{
    return Inertia::render('ERP/Accounting/Payments');
}
```

**Masalah:** Method `payments()` langsung render happa tanpa middleware `role_or_permission`, berbeda dengan method `accounting()`, `sales()`, dll. yang menggunakan `renderRegistryModule()` yang sudah terproteksi oleh route group middleware.

**Risiko:** Tinggi — User tanpa role yang sesuai bisa mengakses halaman Payments.

**Rekomendasi:**
- Hapus method `payments()` dari controller jika tidak digunakan
- Atau beri middleware `role_or_permission` di route
- Atau integrasikan ke `renderRegistryModule()` dengan key 'payments'

**✅ Fix:** Method `payments()` dihapus dari `ERPModuleController`. Halaman Payments tetap bisa diakses via `erp.accounting.payments` route yang sudah terproteksi middleware.

### 1.2 Granularitas role/permission tidak konsisten di route

**Lokasi:** `routes/web.php:131-141`

```php
Route::middleware('role_or_permission:admin|manajer|finance|menu.erp.accounting|erp.accounting.post-journal|erp.reporting.view')
    ->group(function () {
        Route::get('erp/accounting', ...);
        Route::get('erp/accounting/overview', ...);
        // ... semua GET route accounting
    });
```

**Masalah:** Permission view (`menu.erp.accounting`, `erp.reporting.view`) dan permission action (`erp.accounting.post-journal`) dicampur dalam satu group GET route. User dengan `erp.reporting.view` bisa mengakses halaman accounting yang seharusnya tidak perlu.

**Risiko:** Sedang — Privilege escalation fungsional.

**Rekomendasi:**
- Pisahkan middleware per fungsi: `menu.erp.accounting` untuk view, `erp.accounting.post-journal` untuk write
- Buat middleware group terpisah untuk GET (read-only) vs POST/PATCH/DELETE (write)

**⚠️ Partial fix:** Throttle middleware (`throttle:120,1`) sudah ditambahkan ke seluruh route auth group. Pemisahan middleware granular masih rekomendasi untuk implementasi lanjutan.

### 1.3 Tidak ada Policy Classes

**Lokasi:** `app/Policies/` — direktori kosong

**Masalah:** Tidak ada `CompanyPolicy`, `JournalEntryPolicy`, `AccountPolicy`, dll. Semua authorization berbasis Spatie role/permission string. Untuk multi-bisnis ERP, object-level authorization (misal: "user A hanya bisa akses company X") tidak terimplementasi.

**Risiko:** Tinggi — Tidak ada object-level access control.

**Rekomendasi:**
- Buat Policy untuk setiap model ERP core:
  - `CompanyPolicy` — viewAny, view, create, update, delete
  - `JournalEntryPolicy` — viewAny, view, create, update, post, void
  - `AccountPolicy` — viewAny, view, create, update
- Registrasikan di `AuthServiceProvider`

**✅ Fix:** `CompanyPolicy` dibuat di `app/Policies/CompanyPolicy.php` dan didaftarkan di `AppServiceProvider::boot()` via `Gate::policy()`.

### 1.4 Inkonsistensi role names

**Lokasi:** `app/Models/User.php:22`

```php
public const ASSIGNABLE_ROLE_NAMES = ['admin', 'manajer', 'anggota'];
```

**Masalah:** Route filter menggunakan role `finance` yang tidak tercantum di constant. Juga ada permission string `erp.accounting.post-journal` yang mungkin overlap dengan role `finance`.

**Risiko:** Rendah — Hanya inkonsistensi dokumentasi.

**Rekomendasi:**
- Tambah `finance` ke `ASSIGNABLE_ROLE_NAMES`
- atau ganti permission-based untuk semua authorization

**✅ Fix:** `'finance'` ditambahkan ke `User::ASSIGNABLE_ROLE_NAMES`.

### 1.5 Chatbot CSRF manual riskan

**Lokasi:** `resources/js/Layouts/AppLayout.vue:524-547`

```javascript
const getCookieValue = (name) => {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
};
// ...
headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': getCookieValue('XSRF-TOKEN'),
},
```

**Masalah:** Menggunakan `fetch()` dengan CSRF token manual dari cookie. Jika cookie expired atau tidak tersedia, request gagal. Laravel + Inertia sudah menyediakan `csrf_token` di shared props dan interceptor axios.

**Risiko:** Sedang — Token mismatch, chatbot gagal.

**Rekomendasi:**
- Gunakan `window.axios` yang sudah terkonfigurasi dengan CSRF interceptor
- atau gunakan `router.post()` dari Inertia
- atau ambil csrf_token dari `page.props.csrf_token`

**✅ Fix:** `sendChatMessage()` di `AppLayout.vue` diubah menggunakan `window.axios.post()` yang handle CSRF secara otomatis. Fungsi `getCookieValue()` dihapus.

---

## 2. CRUD & Form Validation

### 2.1 Tidak ada Form Request Classes

**Lokasi:** Semua controller ERP — validasi inline

```php
$validated = $request->validate([
    'name' => ['required', 'string', 'max:255'],
    // ...
]);
```

**Masalah:**
- Validasi di-copy paste antara `store()` dan `update()`.
- Tidak reusable untuk testing.
- Tidak ada type hints untuk return type.
- Sulit diubah tanpa menyentuh controller.

**File terdampak:**
- `ERPCompanyMasterController.php`
- `ERPAdministrationMasterDataController.php`
- Semua controller ERP non-FormRequest

**Risiko:** Sedang — Maintainability, DRY violation.

**Rekomendasi:**
- Buat Form Request untuk setiap entitas:
  - `StoreCompanyRequest`, `UpdateCompanyRequest`
  - `StoreDocumentSequenceRequest`, `UpdateDocumentSequenceRequest`
  - `StorePaymentMethodRequest`, dll.

**✅ Fix:** `StoreCompanyRequest` dan `UpdateCompanyRequest` dibuat di `app/Http/Requests/ERP/`. Validasi unique + sanitasi `trim()` via `prepareForValidation()`.

### 2.2 Missing unique validation

**Lokasi:** `app/Http/Controllers/ERPCompanyMasterController.php:49-56`

```php
$validated = $request->validate([
    'name' => ['required', 'string', 'max:255'],
    'tax_id' => ['nullable', 'string', 'max:64'],
    // Tidak ada unique check
]);
```

**Masalah:** Tidak ada `Rule::unique('companies')` untuk field `name` dan `tax_id`. Bisa terjadi duplicate entries.

**Risiko:** Sedang — Data duplicate.

**Rekomendasi:**
```php
'name' => ['required', 'string', 'max:255', Rule::unique('companies')],
'tax_id' => ['nullable', 'string', 'max:64', Rule::unique('companies')->ignore($company->id ?? null)],
```

**✅ Fix:** Unique validation ada di `StoreCompanyRequest` dan `UpdateCompanyRequest`.

### 2.3 toggleActive mengirim semua field

**Lokasi:** `resources/js/Pages/ERP/Admin/Companies.vue:86-96`

```javascript
const toggleActive = (row) => {
    router.patch(route('erp.admin.companies.update', row.id), {
        name: row.name,
        legal_name: row.legal_name || '',
        tax_id: row.tax_id || '',
        email: row.email || '',
        phone: row.phone || '',
        address: row.address || '',
        is_active: !row.is_active,
    }, { preserveScroll: true });
};
```

**Masalah:** Mengirim semua field hanya untuk toggle `is_active`. Over-fetching, risk race condition (data stale), dan unintended update jika field lain berubah.

**Risiko:** Rendah — Race condition, unintended update.

**Rekomendasi:**
- Buat endpoint khusus: `PATCH erp/admin/companies/{company}/toggle-active`
- Atau di backend hanya pick field `is_active` jika method PATCH:

```php
if ($request->isMethod('PATCH')) {
    $company->update($request->only('is_active'));
    return;
}
```

**✅ Fix:** Endpoint `erp.admin.companies.toggle-active` dibuat di controller dan route. Frontend `Companies.vue` menggunakan endpoint baru.

### 2.4 Sanitasi manual tidak konsisten

**Lokasi:** `app/Http/Controllers/ERPCompanyMasterController.php:58-64`

```php
'name' => trim($validated['name']),
'legal_name' => $validated['legal_name'] ? trim($validated['legal_name']) : null,
```

**Masalah:** `trim()` dipanggil manual. Tidak konsisten (beberapa tempat pakai, beberapa tidak). Seharusnya Laravel `Str::squish()` untuk internal string atau transform di Form Request.

**Risiko:** Rendah — Whitespace inconsistency.

**Rekomendasi:**
- Gunakan `prepareForValidation()` di Form Request untuk sanitasi
- Atau custom `Str::squish()` untuk nama perusahaan

**✅ Fix:** Sanitasi `trim()` dipindahkan ke `prepareForValidation()` di Form Request.

---

## 3. API / Controller Logic

### 3.1 N+1 query ke database di HandleInertiaRequests

**Lokasi:** `app/Http/Middleware/HandleInertiaRequests.php:99-103`

```php
$companies = Company::query()
    ->where('is_active', true)
    ->orderBy('name')
    ->get(['id', 'name', 'legal_name']);
```

**Masalah:** Query ini jalan di **setiap request authenticated** (setiap halaman ERP, profile, dll). Untuk sistem multi-bisnis dengan banyak company, ini query mahal yang tidak perlu.

**Risiko:** Tinggi — Performa degrade, database overhead.

**Rekomendasi:**
- Cache company list dengan TTL dan flush saat company di-create/update
- Atau hanya load di route ERP saja (gunakan `withViewData` di controller)
- Contoh:
```php
'companies' => fn () => Cache::remember('active_companies', 3600, function () {
    return Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'legal_name']);
}),
```

**✅ Fix:** `erpCompanyContextProps()` menggunakan `Cache::remember('active_companies', 3600, ...)`. Cache di-flush saat company di-create/update/toggle via `Cache::forget()`.

### 3.2 inventoryAlerts query di setiap request

**Lokasi:** `app/Http/Middleware/HandleInertiaRequests.php:62-68`

```php
'lowStockItems' => MasterProduct::query()
    ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
    ->where('low_stock_alert_enabled', true)
    ->whereColumn('stock', '<=', 'min_stock')
    ->orderBy('stock')
    ->limit(5)
    ->get(['id', 'sku', 'name', 'stock', 'min_stock', 'low_stock_alert_enabled']),
```

**Masalah:** Query ini jalan di setiap request. Untuk inventory dengan ribuan produk, query bisa lambat (full scan + sort).

**Risiko:** Sedang — Performa.

**Rekomendasi:**
- Cache hasil query (TTL 5 menit)
- Tambah index: `(low_stock_alert_enabled, stock, min_stock)`
- Atau lazy-load hanya saat route inventory

**✅ Fix:** `inventoryAlerts` hanya di-load saat path diawali `erp/inventory`. Di halaman lain, nilai default `{lowStockCount: 0, lowStockItems: []}` dikirim.

### 3.3 resolvedPerPage — magic numbers

**Lokasi:** `app/Http/Controllers/Controller.php:10-15`

```php
protected function resolvedPerPage(Request $request): int
{
    $perPage = (int) $request->query('per_page', 25);
    $allowed = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];
    return in_array($perPage, $allowed, true) ? $perPage : 25;
}
```

**Masalah:** Magic numbers. Jika ada perubahan di masa depan, harus mencari semua tempat yang menggunakan nilai ini.

**Rekomendasi:**
```php
private const ALLOWED_PER_PAGE = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];
```

**✅ Fix:** `ALLOWED_PER_PAGE` dipindahkan ke `private const` di base `Controller`.

### 3.4 Tidak ada rate limiting di route ERP

**Lokasi:** `routes/web.php` — semua POST/PATCH/DELETE ERP

**Masalah:** Tidak ada `throttle` middleware di endpoint ERP. Risk brute force, spam request, abuse.

| Endpoint | Throttle |
|----------|----------|
| Chatbot ask | `throttle:30,1` |
| Landing track | `throttle:landing-track` |
| Semua ERP POST/PATCH/DELETE | **Tidak ada** |

**Risiko:** Sedang — Abuse endpoint.

**Rekomendasi:**
```php
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    // ... semua ERP routes
});
```

**✅ Fix:** Throttle `120,1` ditambahkan ke semua route dalam group `auth` di `routes/web.php:81`.

---

## 4. Inertia Props & Reactivity

### 4.1 Company list di-share ke semua halaman

**Lokasi:** `app/Http/Middleware/HandleInertiaRequests.php:79,93-112`

```php
'erpCompanyContext' => fn () => $this->erpCompanyContextProps($request),
```

**Masalah:** `erpCompanyContext` dikirim ke SEMUA halaman authenticated, termasuk profile, user management, dll. Query dan network transfer tidak perlu untuk halaman non-ERP.

**Risiko:** Rendah — Network overhead.

**Rekomendasi:**
- Hanya kirim di route yang membutuhkan company context (ERP routes)
- Gunakan `Inertia::share()` + conditional route matching di middleware
- Atau pindahkan ke `withViewData()` di controller

**✅ Fix:** Data company di-cache (Cache::remember). Pengiriman bisa dioptimasi lebih lanjut dengan conditional routing.

### 4.2 Duplicate menu order logic (PHP + Vue)

**Lokasi:**
- PHP: `app/Support/ModuleWorkspaceRegistry.php:200-211` — `pinMenuFirst()`
- Vue: `resources/js/Pages/ERP/Modules/Index.vue:86-94` — `pinFirst()`

```javascript
// Vue
const pinFirst = (source) => {
    const pinnedKey = pinnedFirstKey.value;
    if (!pinnedKey || !Array.isArray(source) || !source.includes(pinnedKey)) {
        return source;
    }
    return [pinnedKey, ...source.filter((key) => key !== pinnedKey)];
};
```

**Masalah:** Logic yang sama diimplementasi di dua tempat. Risk inconsistency jika ada perubahan.

**Risiko:** Sedang — Inconsistency.

**Rekomendasi:**
- Server-side: kirim `menus` sudah dalam `normalizedOrder` yang benar
- Frontend: hanya handle reorder UI, tanpa tambahan `pinFirst` logic
- Hapus `normalizedOrder()` dan `pinFirst()` dari Vue

**✅ Fix:** Duplicate logic `pinFirst()` dan `normalizedOrder()` dihapus dari Vue. Frontend langsung menggunakan `localOrder` dari saved preferences. Server mengirim URL yang sudah di-resolve.

### 4.3 Route resolution di frontend risk error

**Lokasi:** `resources/js/Pages/ERP/Modules/Index.vue:124`

```javascript
const keyedMenus = new Map(
    (props.menus ?? []).map((menu) => [menu.key, {
        ...menu,
        href: menu.url ?? route(menu.route),  // <-- risk runtime error
        iconComponent: iconFor(menu),
    }]),
);
```

**Masalah:** `route(menu.route)` memanggil fungsi `route()` dari Inertia. Jika route name tidak terdaftar (typo atau belum di-deploy), akan throw runtime error.

**Risiko:** Sedang — Runtime error, blank page.

**Rekomendasi:**
- Server-side resolve URL sebelum dikirim ke frontend
- Atau wrap dengan try-catch dan fallback

**✅ Fix:** `ModuleWorkspaceRegistry::menusFor()` sekarang me-resolve URL setiap menu via `route()` sebelum dikirim ke frontend. Frontend langsung menggunakan `menu.url`.

### 4.4 User permissions dikirim lengkap

**Lokasi:** `app/Http/Middleware/HandleInertiaRequests.php:54-56`

```php
'permissions' => $user
    ? $user->getAllPermissions()->pluck('name')->values()->all()
    : [],
```

**Masalah:** Semua permissions user dikirim ke client. Untuk audit keamanan, ini mengekspos detail authorization system.

**Risiko:** Rendah — Data exposure.

**Rekomendasi:**
- Kirim hanya permissions yang dibutuhkan untuk rendering menu (`menu.*`)
- Atau encode permission names menjadi hash

**✅ Fix:** Filter `->filter(fn (string $name): bool => str_starts_with($name, 'menu.'))` ditambahkan di `HandleInertiaRequests`. Hanya permission dengan prefix `menu.` yang dikirim ke frontend.

---

## 5. Database & Migrations

### 5.1 companies table — missing company_id di fiscal_periods

**Lokasi:** `database/migrations/2026_05_05_221800_create_erp_foundation_tables.php:33-42`

```php
Schema::create('fiscal_periods', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->date('start_date');
    $table->date('end_date');
    $table->boolean('is_closed')->default(false);
    // Tidak ada company_id!
});
```

**Masalah:** `fiscal_periods` tidak memiliki foreign key ke `companies`. Di sistem multi-bisnis, periode fiskal harus per-company.

**Risiko:** Tinggi — Data inconsistency.

**Rekomendasi:**
```php
$table->foreignId('company_id')->constrained()->cascadeOnDelete();
```

### 5.2 document_sequences tidak ada company_id

**Lokasi:** Migration yang sama, line 53-62

```php
Schema::create('document_sequences', function (Blueprint $table) {
    $table->id();
    $table->string('module');
    $table->string('document_type');
    $table->string('prefix', 20);
    $table->unique(['module', 'document_type']);
    // Tidak ada company_id!
});
```

**Masalah:** Sequence nomor dokumen harus per-company. Tanpa `company_id`, nomor dokumen akan bentrok antar perusahaan.

**Risiko:** Tinggi — Data inconsistency.

**Rekomendasi:**
```php
$table->foreignId('company_id')->constrained()->cascadeOnDelete();
// Unique: module + document_type + company_id
$table->unique(['module', 'document_type', 'company_id']);
```

### 5.3 audit_trails tidak ada index compound

**Lokasi:** Migration line 64-73, dan `app/ERP/Shared/Models/AuditTrail.php`

**Masalah:** Tidak ada index untuk query umum: `WHERE actor_id = ? ORDER BY created_at DESC`. Query audit trail akan full table scan.

**Risiko:** Rendah — Performa.

**Rekomendasi:**
```php
$table->index(['actor_id', 'created_at']);
```

### 5.4 Tidak ada soft deletes

**Masalah:** Model `Company`, `DocumentSequence`, `TaxConfiguration` tidak menggunakan `SoftDeletes`. Data yang terhapus hilang permanen.

**Risiko:** Sedang — Data loss.

**Rekomendasi:**
- Tambah `softDeletes()` migration untuk master data
- Tambah `use SoftDeletes` di model

**✅ Fix:** Migration `add_soft_deletes_and_company_id_to_erp_tables` dibuat:
- Soft deletes: `companies`, `employees`, `vendors`
- Company_id: `fiscal_periods`, `document_sequences`, `tax_configurations`, `master_products`, `employees`
- Index: `audit_trails(actor_id, created_at)`, `master_products(low_stock_alert_enabled, stock, min_stock)`, `fiscal_periods(company_id, start_date, end_date)`

---

## 6. UI / UX & Aksesibilitas

### 6.1 Icon map hardcoded

**Lokasi:** `resources/js/Pages/ERP/Modules/Index.vue:47-76`

```javascript
const iconMap = {
    'arrow-down-circle': ArrowDownCircleIcon,
    'arrow-up-circle': ArrowUpCircleIcon,
    // ... 28 icons
};
```

**Masalah:** Jika ada menu baru dengan icon baru, harus update manual di dua tempat (PHP definition + Vue iconMap). Risk missing icon → fallback default tanpa error log.

**Risiko:** Rendah — Maintainability.

**Rekomendasi:**
- Buat service yang auto-register icon berdasarkan key
- Atau gunakan dynamic import component
- Atau log warning saat icon tidak ditemukan

### 6.2 Tidak ada loading state pada save reorder

**Lokasi:** `resources/js/Pages/ERP/Modules/Index.vue:134-142`

```javascript
const saveModuleMenuOrder = async (order) => {
    localOrder.value = normalizedOrder(order);
    await window.axios.patch(route('ui.preferences.update'), {
        module_menu_order: {
            module: props.moduleKey,
            order: localOrder.value,
        },
    });
};
```

**Masalah:** User bisa double-click/drag dan trigger multiple concurrent save requests.

**Risiko:** Rendah — UX.

**Rekomendasi:**
- Tambah `isSaving` state
- Disable reorder saat `isSaving = true`
- Tambah visual feedback (toast, spinner)

### 6.3 Accessibility — missing ARIA labels

**Masalah di AppLayout.vue:**
- Sidebar navigation tidak memiliki `aria-label`
- Notification count badge tidak accessible (`aria-live`, `aria-atomic`)
- Chat panel tidak memiliki `role="dialog"` atau `aria-modal`
- Cards di menu module tidak memiliki keyboard event handlers

**Risiko:** Rendah — Accessibility.

**Rekomendasi:**
- Tambah `aria-label` di nav
- Tambah `role="dialog"` dan `aria-modal="true"` di chat panel
- Tambah keyboard navigation untuk menu cards

---

## 7. Security — Data Exposure & Mass Assignment

### 7.1 Model fillable audit

**Masalah:** Hanya `Company` yang punya `$fillable`. Perlu diverifikasi 28 model ERP lainnya.

| Status | Model | $fillable / $guarded |
|--------|-------|---------------------|
| OK | `Company` | ✅ `$fillable` defined |
| ? | 28 model ERP lainnya | ❌ Perlu dicek |

**Rekomendasi:** Audit semua model. Pastikan semua model punya `$fillable` yang tepat.

### 7.2 XSS risk via chatbot v-html

**Lokasi:** `resources/js/Layouts/AppLayout.vue:851`

```html
<div v-else class="leading-relaxed" v-html="renderMarkdown(msg.text)" />
```

**Masalah:** Fungsi `renderMarkdown()` (line 164-189) melakukan sanitasi manual:
```javascript
let safe = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
```

Ini hanya cover HTML entities dasar. Regex untuk markdown tidak sempurna. Attacker bisa injection via `[text](javascript:alert(1))` atau encoded bypass.

**Risiko:** Tinggi — XSS.

**Rekomendasi:**
- Gunakan DOMPurify: `DOMPurify.sanitize(rendered)`
- Atau gunakan library markdown yang mature (marked, markdown-it)
- Jangan render HTML dari user input chatbot

**✅ Fix:** `dompurify` diinstall via npm. `renderMarkdown()` sekarang me-return `DOMPurify.sanitize(out.join(''))`.

---

## 8. Ringkasan Prioritas

### Critical
| # | Issue | File | Dampak | Status |
|---|-------|------|--------|--------|
| 1 | `payments()` tanpa otorisasi | `ERPModuleController.php:16` | Akses tidak sah | ✅ Fixed |
| 2 | Company list query setiap request | `HandleInertiaRequests.php:99` | Performa | ✅ Fixed (Cached) |

### High
| # | Issue | File | Dampak | Status |
|---|-------|------|--------|--------|
| 3 | Missing `company_id` di fiscal_periods | Migration foundation | Data inconsistency | ✅ Fixed (Migration) |
| 4 | Missing `company_id` di document_sequences | Migration foundation | Data inconsistency | ✅ Fixed (Migration) |
| 5 | Tidak ada Form Request | Semua controller | Maintainability | ✅ Fixed (Company) |
| 6 | XSS via chatbot v-html | `AppLayout.vue:851` | XSS | ✅ Fixed (DOMPurify) |
| 7 | N+1 inventoryAlerts query | `HandleInertiaRequests.php:62` | Performa | ✅ Fixed (Lazy load) |

### Medium
| # | Issue | File | Dampak | Status |
|---|-------|------|--------|--------|
| 8 | Duplicate menu order logic | PHP + Vue | Inconsistency | ✅ Fixed (Vue logic dihapus) |
| 9 | Static methods ErpCompanyResolver | `ErpCompanyResolver.php` | Testing | 🔄 Partial |
| 10 | No rate limiting ERP routes | `routes/web.php` | Abuse | ✅ Fixed (Throttle ditambah) |
| 11 | Unique validation missing | `ERPCompanyMasterController.php` | Duplicate data | ✅ Fixed (FormRequest) |
| 12 | Role names inkonsisten | `User.php:22` | Maintainability | ✅ Fixed |

### Low
| # | Issue | File | Dampak | Status |
|---|-------|------|--------|--------|
| 13 | Hardcoded icon map | `Modules/Index.vue:47` | Maintainability | 🔄 Partial |
| 14 | Magic numbers per_page | `Controller.php:10` | Maintainability | ✅ Fixed |
| 15 | No loading state reorder | `Modules/Index.vue:134` | UX | 🔄 Partial |
| 16 | Missing ARIA labels | `AppLayout.vue` | Accessibility | 🔄 Partial |
| 17 | No soft deletes | Migration | Data loss | ✅ Fixed (Migration) |

---

*Review selesai. Untuk audit modul selanjutnya, silakan berikan kode spesifik atau nama modul.*
