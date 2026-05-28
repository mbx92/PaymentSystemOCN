# Quality Assurance Review — Invoice & PDF Generation

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Invoice Controller

### 1.1 PDF options — isRemoteEnabled = true

**Lokasi:** `app/Http/Controllers/InvoiceController.php:57-63`
```php
->setOptions([
    'dpi' => 150,
    'defaultFont' => 'DejaVu Sans',
    'isHtml5ParserEnabled' => true,
    'isRemoteEnabled' => true,  // <-- SECURITY RISK
    'chroot' => public_path(),
]);
```
**Masalah:** `isRemoteEnabled => true` memungkinkan DOMPDF mengakses remote resource (CSS, gambar via URL). Risk Server-Side Request Forgery (SSRF) jika attacker bisa inject `<link>` atau `<img>` dengan URL eksternal.
**Risiko:** **High** — SSRF, Remote file inclusion.

**Rekomendasi:**
- Set `isRemoteEnabled => false` jika tidak ada resource eksternal yang diperlukan
- Jika perlu remote images, whitelist domain

### 1.2 Invoice PDF — sync generation every request

**Lokasi:** `InvoiceController.php:17-33`
```php
public function show(string|int $id): Response
{
    $payload = $this->invoiceService->getInvoiceDocument($id);
    $invoice = $payload['invoice'];
    return $this->makePdf('pdf.invoice', $payload)->stream(...);
}
```
**Masalah:** PDF di-generate setiap request view/download. Invoice adalah dokumen yang tidak berubah setelah dibuat — idealnya di-cache.
**Risiko:** Sedang — Performa, repeated work.
**Rekomendasi:**
- Cache PDF setelah generate pertama
- Atau simpan PDF ke storage setelah pertama di-generate
- Gunakan ETag / Last-Modified header untuk caching

### 1.3 No authorization check

**Lokasi:** `InvoiceController.php:17,26,35,44`
**Masalah:** Method `show`, `download`, `showSalesNote`, `downloadSalesNote` tidak memiliki authorization check. Siapa pun yang tahu ID invoice bisa akses.
```php
public function show(string|int $id): Response
```
**Risiko:** **Critical** — Data exposure. User bisa akses invoice project yang bukan miliknya.

**Rekomendasi:**
- Implicit route binding dengan Policy
- Atau verifikasi user punya akses ke project terkait invoice
```php
public function show(ProjectInvoice $invoice): Response
{
    $this->authorize('view', $invoice);
    // ...
}
```

### 1.4 Type hint `string|int` — tidak konsisten

**Lokasi:** `InvoiceController.php:17`
```php
public function show(string|int $id): Response
```
**Masalah:** Union type `string|int` tanpa implicit binding. Invoice seharusnya menggunakan route model binding.
**Rekomendasi:** Gunakan route model binding dengan tipe yang tepat.

---

## 2. InvoiceService

### 2.1 getInvoiceDocument — potensi N+1 query

Perlu dicek apakah `getInvoiceDocument()` melakukan eager loading relasi. Jika tidak, bisa N+1 saat mapping.
**Rekomendasi:** Pastikan semua relasi di-load dengan `with()`.

---

## Ringkasan Prioritas Invoice

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | No authorization → invoice accessible by anyone | `InvoiceController.php:17,26,35,44` |
| **High** | SSRF risk via isRemoteEnabled=true | `InvoiceController.php:61` |
| **Medium** | PDF generated sync each request | `InvoiceController.php:22` |
| **Low** | No route model binding | `InvoiceController.php:17` |
