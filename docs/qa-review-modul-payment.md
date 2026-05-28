# Quality Assurance Review — Payment Integration

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Supplier Payment

### 1.1 Payable status update — race condition

**Lokasi:** `app/Http/Controllers/ERPAccountingPaymentController.php:149-199`
**Masalah:** `lockForUpdate()` sudah digunakan, baik. Tapi update `paid_amount` menggunakan increment dari PHP:
```php
$newPaidAmount = (float) $lockedPayable->paid_amount + $amount;
```
Jika ada dua payment concurrent untuk payable yang sama, `lockForUpdate()` mencegah race condition. OK.
**Status:** ✅ OK — Race condition handled.

### 1.2 Payable outstanding — komparasi float

**Lokasi:** `ERPAccountingPaymentController.php:161`
```php
if ($amount > $outstanding) {
    throw ValidationException::withMessages([...]);
}
```
**Masalah:** Komparasi float `$amount > $outstanding`. Float precision bisa menyebabkan false positive/negative.
**Risiko:** Rendah — Precision error.
**Rekomendasi:** Gunakan `bccomp()` atau integer cents:
```php
if (bccomp((string) $amount, (string) $outstanding, 2) > 0) { ... }
```

---

## 2. Project Invoice Payment

### 2.1 Payment method validation

**Lokasi:** `ERPSalesController::updatePosTransactionPaymentMethod`
```php
'payment_method_id' => 'required|exists:payment_methods,id',
```
**Masalah:** Tidak ada validasi bahwa payment method active.
**Risiko:** Rendah — User bisa pilih payment method nonaktif.
**Rekomendasi:** `Rule::exists('payment_methods', 'id')->where('is_active', true)`

### 2.2 Receipt download — URL structure

**Masalah:** Receipt didownload via Inertia route. Perlu dicek apakah receipt di-generate on-the-fly atau dari storage.
**Rekomendasi:** Generate receipt via queue + cache.

---

## 3. Member Payment

### 3.1 TeamDistribution → member payment flow

**Lokasi:** `ERPAccountingPaymentController::storeMemberPayment`
**Masalah:** Payment member menggunakan `TeamDistribution` sebagai parameter route. Perlu dicek apakah TeamDistribution sudah di-lock saat payment.
**Rekomendasi:** `lockForUpdate()` pada TeamDistribution saat proses payment.

---

## 4. Payment Methods — Tidak ada integrasi payment gateway

**Catatan:** Sistem hanya mencatat metode pembayaran (cash, transfer, dll) sebagai master data. Tidak ada integrasi dengan payment gateway eksternal (Midtrans, Xendit, dll).

**Rekomendasi:** Jika membutuhkan online payment, implementasi Payment Gateway pattern dengan:
- Interface PaymentGateway (authorize, capture, refund, void)
- Class konkret per gateway
- Webhook handler untuk callback
- Transaction log table

---

## Ringkasan Prioritas Payment

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **High** | Payment method validation — active check | `ERPSalesController.php:206` |
| **Medium** | Float comparison payable amount | `ERPAccountingPaymentController.php:161` |
| **Medium** | TeamDistribution lock saat payment | `ERPAccountingPaymentController.php` |
| **Low** | No payment gateway integration | - |
