# Quality Assurance Review — Chatbot / Chat Module

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Chatbot Security

### 1.1 Input sanitization — strip_tags()

**Lokasi:** `app/Http/Controllers/ErpChatbotController.php:45,49`
```php
$message = strip_tags($message);
$history = collect($history)->map(fn ($item) => [
    'text' => strip_tags((string) ($item['text'] ?? '')),
]);
```
**Masalah:** `strip_tags()` hanya menghapus HTML tags. Tidak melindungi dari:
- XSS via markdown injection (`[x](javascript:alert(1))`)
- HTML entity bypass (`&lt;script&gt;`)
- Unicode normalization attacks
**Risiko:** Tinggi — XSS via chatbot response (karena response di-render dengan `v-html`).

### 1.2 Message length validation

**Lokasi:** `ErpChatbotController.php:37-42`
```php
$validated = $request->validate([
    'message' => 'required|string|max:1000',
    'history.*.text' => 'required_with:history|string|max:2000',
]);
```
**Masalah:** History message max 2000 chars. Tapi history dikirim dari client, bisa dimanipulasi.
**Risiko:** Rendah — Input validation.
**Rekomendasi:** Tambah batas jumlah history (max 10 item — sudah ada).

---

## 2. Chatbot Response — XSS via v-html

**Lokasi:** `resources/js/Layouts/AppLayout.vue:851`
```html
<div v-else class="leading-relaxed" v-html="renderMarkdown(msg.text)" />
```

**Masalah:** Response chatbot di-render dengan `v-html`. Fungsi `renderMarkdown()` di line 164-189 melakukan sanitasi:
```javascript
let safe = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
```

Ini hanya cover HTML entities dasar. Tidak melindungi dari:
- `javascript:` URLs
- `data:` URLs
- `onerror=` attribute injection via markdown image syntax
- Encoded bypass (`&#106avascript:`)

**Risiko:** **Critical** — Stored XSS. Attacker bisa inject script via chatbot parser rules atau response.

**Rekomendasi:**
1. Gunakan DOMPurify: 
```javascript
import DOMPurify from 'dompurify';
safe = DOMPurify.sanitize(rendered);
```
2. Atau jangan gunakan `v-html` sama sekali — gunakan text rendering + regex markdown.
3. Atau gunakan library markdown mature (marked.js + DOMPurify).

---

## 3. Chatbot Intent Parsing

### 3.1 Rule-based parser — keyword matching

**Lokasi:** `app/ERP/Core/Services/RuleBasedErpChatParser.php`
**Masalah:** Parser berbasis keyword matching via database `erp_chat_parser_rules`. Tidak ada NLP/AI. Untuk query kompleks, kemungkinan false positive tinggi.
**Risiko:** Rendah — User experience.
**Rekomendasi:** Ini desain choice, OK untuk scope sistem.

### 3.2 Follow-up context — disimpan di localStorage

**Lokasi:** `AppLayout.vue:55,148`
```javascript
const CHAT_STORAGE_KEY = 'erp_chat_history';
localStorage.setItem(CHAT_STORAGE_KEY, JSON.stringify(toSave));
```
**Masalah:** Riwayat chat disimpan di localStorage. Data yang disimpan termasuk response dari server (yang mungkin contain data bisnis sensitif). Jika user logout dan orang lain akses browser yang sama, data terbaca.
**Risiko:** Sedang — Data exposure.

**Rekomendasi:**
- Clear chat history saat logout
- Atau gunakan sessionStorage (terhapus saat tab ditutup)

---

## 4. Chatbot Rate Limiting

**Lokasi:** `routes/web.php:87`
```php
Route::post('erp/chatbot/ask', ...)->middleware('throttle:30,1');
```
**Status:** ✅ OK — 30 request per menit sudah reasonable.

---

## Ringkasan Prioritas Chatbot

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | XSS via v-html + insufficient sanitasi | `AppLayout.vue:851`, `ErpChatbotController.php:45` |
| **Medium** | Chat history di localStorage → data exposure | `AppLayout.vue:148` |
| **Low** | No NLP, keyword-based only | `RuleBasedErpChatParser.php` |
| **Low** | Rate limiting OK | ✅ |
