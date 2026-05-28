# Quality Assurance Review — Notification & Email

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Notification System

### 1.1 Mark all read — N+1 query

**Lokasi:** `app/Http/Controllers/NotificationController.php:62-77`
```php
public function markAllRead(Request $request, AppNotificationCenter $notificationCenter): RedirectResponse
{
    $items = $notificationCenter->buildFor($request->user())['items'] ?? [];
    foreach ($items as $item) {
        UserNotificationRead::query()->updateOrCreate([...]); // <-- query per item
    }
}
```
**Masalah:** Untuk setiap item notifikasi, ada query `updateOrCreate` terpisah. Jika ada 100 notifikasi unread, ada 100 query.
**Risiko:** Sedang — Performa.

**Rekomendasi:**
```php
$notificationIds = collect($items)->pluck('notification_id');
$inserts = $notificationIds->map(fn ($id) => [
    'user_id' => $request->user()->id,
    'notification_id' => $id,
    'read_at' => now(),
])->all();
UserNotificationRead::query()->upsert($inserts, ['user_id', 'notification_id'], ['read_at']);
```

### 1.2 Notification poll — every 60 seconds

**Lokasi:** `resources/js/Layouts/AppLayout.vue:482-484`
```javascript
notificationPollTimer = window.setInterval(() => {
    pollNotifications();
}, 60000);
```
**Masalah:** Polling setiap 60 detik tanpa exponential backoff. Jika server lambat, akan ada request bertubi-tubi.
**Risiko:** Rendah — Network overhead.
**Rekomendasi:** Tambah exponential backoff: jika gagal, double interval hingga max 5 menit.

### 1.3 Notification center buildFor — query overhead

**Lokasi:** `app/Support/AppNotificationCenter.php`
**Masalah:** Method `buildFor()` di-call setiap request (via shared props dan notificationController). Mungkin menjalankan multiple queries.
**Risiko:** Sedang — Overhead.
**Rekomendasi:** Cache hasil `buildFor()` per user dengan TTL 30 detik.

### 1.4 Notification markRead menggunakan route PATCH + DELETE

**Lokasi:** `routes/web.php:94-95`
```php
Route::patch('notifications/mark-read', ...)->name('notifications.mark-read');
Route::delete('notifications/mark-read', ...)->name('notifications.mark-unread');
```
**Masalah:** Dua endpoint dengan URL sama tapi method berbeda. Secara teknis OK, tapi confusing untuk API client.
**Risiko:** Rendah — API design.
**Rekomendasi:** Gunakan URL berbeda: `POST notifications/{id}/read` dan `POST notifications/{id}/unread`.

---

## 2. Email (Project Invoice)

### 2.1 PDF binary in memory — large attachment

**Lokasi:** `app/Mail/ProjectInvoiceMail.php:19`
```php
public readonly string $pdfBinary,
```
**Masalah:** PDF binary disimpan di property object dan di-attach via `fromData()`. Untuk invoice dengan banyak halaman, binary bisa >10MB. Queue mail dengan attachment besar risk memory.
**Risiko:** Sedang — Memory.

**Rekomendasi:**
- Simpan PDF ke storage sementara, attach via path
- Atau gunakan temporary URL untuk download

### 2.2 No email queue

**Lokasi:** Pemanggilan `Mail::send()` dari chatbot controller
**Masalah:** Email dikirim synchronously. User nunggu sampai email terkirim.
**Risiko:** Tinggi — Slow response.
**Rekomendasi:**
```php
Mail::to($recipient)->queue(new ProjectInvoiceMail(...));
```

### 2.3 Mail template — no fallback

**Lokasi:** `emails.project-invoice`
**Masalah:** Jika template view tidak ditemukan, error 500. Tidak ada fallback plain text.
**Rekomendasi:** Buat fallback text version atau validasi template exists.

---

## Ringkasan Prioritas Notification & Email

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Medium** | N+1 query markAllRead | `NotificationController.php:66` |
| **High** | Email sent synchronously | Panggilan Mail::send() |
| **Medium** | Large PDF attachment in memory | `ProjectInvoiceMail.php:19` |
| **Low** | Notification poll without backoff | `AppLayout.vue:482` |
| **Low** | GET/POST/DELETE URL confusion | `routes/web.php:94` |
