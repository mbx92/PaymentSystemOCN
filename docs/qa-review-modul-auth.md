# Quality Assurance Review — Authentication (Breeze)

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Login (AuthenticatedSessionController)

### 1.1 Throttle login — default Laravel

**Status:** ✅ Menggunakan `throttle:login` bawaan Laravel. OK.

### 1.2 Login attempts — no custom rate limit

**Status:** Menggunakan default Laravel (5 attempts per minute). Untuk ERP sebaiknya lebih ketat.
**Rekomendasi:** Set `RateLimiter::for('login', fn => Limit::perMinute(3))`.

---

## 2. Register (RegisteredUserController)

### 2.1 Registration enabled?

**Masalah:** Apakah registrasi self-service enabled di production? Untuk ERP internal, sebaiknya nonaktifkan register route.
**Rekomendasi:** Hapus route register di `web.php` untuk production, atau proteksi dengan middleware `can:manage-users`.

---

## 3. Password Reset

### 3.1 Password reset link — default Breeze

**Status:** ✅ Menggunakan default Laravel. OK.

### 3.2 No email verification required

**Masalah:** User bisa login tanpa verifikasi email. Untuk ERP internal, verifikasi email tidak critical, tapi sebaiknya konsisten.
**Rekomendasi:** Evaluasi apakah perlu `VerifiedMiddleware` untuk route ERP.

---

## 4. Session Management

### 4.1 Session timeout

**Masalah:** Tidak ada session timeout explicit. User bisa tetap login selamanya.
**Rekomendasi:**
```php
// config/session.php
'lifetime' => 480, // 8 jam
```

### 4.2 Logout dari semua device

**Masalah:** Tidak ada fitur "logout from all devices". Jika password bocor, user tidak bisa invalidate session lain.
**Rekomendasi:** Implementasi `Illuminate\Session\Middleware\AuthenticateSession` dan fitur "revoke all sessions".

---

## 5. Profile

### 5.1 Profile update — company_id bisa diubah?

**Lokasi:** `app/Http/Controllers/ProfileController.php`
**Masalah:** User bisa mengubah `company_id` di profile? Jika ya, risk privilege escalation ke company lain.
**Rekomendasi:** Pastikan `company_id` tidak bisa diedit via profile, atau ada validasi.

---

## Ringkasan Prioritas Auth

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Medium** | No session timeout | `config/session.php` |
| **Medium** | Self-registration enabled | `routes/web.php` |
| **Medium** | No logout-from-all-devices | Auth controllers |
| **Low** | Profile company_id exposure | `ProfileController.php` |
| **Low** | Email verification disabled | Breeze config |
