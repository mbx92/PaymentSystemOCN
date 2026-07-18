<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kwitansi {{ $invoice['number'] }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm 15mm 15mm 15mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #243447;
            background: #ffffff;
            line-height: 1.3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            vertical-align: top;
        }

        .title {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 3px;
            color: #1E3A5F;
            margin: 0 0 2px;
        }

        .subtitle {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1.1px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .logo-box {
            width: 56px;
            height: 56px;
            border: 1px solid #dbe4ee;
            background: #f8fafc;
            text-align: center;
        }

        .logo-box img {
            width: 44px;
            height: 44px;
            margin-top: 5px;
        }

        .logo-text {
            font-size: 13px;
            font-weight: 700;
            color: #1E3A5F;
            line-height: 56px;
            letter-spacing: 1px;
        }

        .brand-name {
            font-size: 16px;
            font-weight: 700;
            color: #1E3A5F;
            padding-top: 1px;
        }

        .muted {
            font-size: 9.25px;
            color: #4b5563;
            line-height: 1.35;
        }

        .info-box,
        .card {
            border: 1px solid #dbe4ee;
            background: #fcfdff;
            padding: 9px 11px;
        }

        .info-box {
            background: #f8fafc;
        }

        .box-title,
        .section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.05px;
            color: #1E3A5F;
            padding-bottom: 4px;
        }

        .meta-table td {
            padding: 2px 0;
            font-size: 9.25px;
        }

        .meta-table td:first-child {
            width: 42%;
            color: #6b7280;
        }

        .meta-table td:last-child {
            text-align: right;
            font-weight: 700;
        }

        .amount-card {
            border: 1px solid #1E3A5F;
            background: #1E3A5F;
            color: #ffffff;
            padding: 10px 12px;
        }

        .amount-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #dbeafe;
            padding-bottom: 4px;
        }

        .amount-value {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
        }

        .detail-table {
            margin-top: 12px;
            border: 1px solid #dbe4ee;
        }

        .detail-table th {
            background: #1E3A5F;
            color: #ffffff;
            padding: 6px 5px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border: 1px solid #1E3A5F;
            text-align: left;
        }

        .detail-table td {
            padding: 6px 6px;
            font-size: 9px;
            border: 1px solid #e5e7eb;
        }

        .detail-table td:first-child {
            width: 28%;
            color: #4b5563;
            font-weight: 700;
        }

        .highlight {
            font-weight: 700;
            color: #243447;
        }

        .status-badge {
            display: inline-block;
            border: 1px solid #166534;
            color: #166534;
            padding: 3px 8px;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .signature {
            margin-top: 12px;
            padding-top: 24px;
            border-top: 1px solid #cbd5e1;
            font-size: 9px;
            color: #4b5563;
            text-align: center;
        }

        .footer-note {
            margin-top: 10px;
            font-size: 8.5px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => 'Integrated Business Platform', 'logo_data_uri' => null];
@endphp

<table>
    <tr>
        <td style="padding-right: 7mm;">
            <table>
                <tr>
                    <td style="width: 62px;">
                        <div class="logo-box">
                            @if (!empty($brand['logo_data_uri']))
                                <img src="{{ $brand['logo_data_uri'] }}" alt="Logo">
                            @else
                                <div class="logo-text">LOGO</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="title">KWITANSI</div>
                        <div class="subtitle">Bukti Penerimaan Pembayaran</div>
                        <div class="brand-name">{{ $brand['name'] }}</div>
                        <div class="muted">
                            {{ $brand['tagline'] }}<br>
                            Project: {{ $project->name }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 70mm;">
            <div class="info-box">
                <div class="box-title">Informasi Dokumen</div>
                <table class="meta-table">
                    <tr><td>No. Invoice</td><td>{{ $invoice['number'] }}</td></tr>
                    <tr><td>Tgl Bayar</td><td>{{ $cashIn->date?->locale('id')->translatedFormat('d F Y') ?: '-' }}</td></tr>
                    <tr><td>Tgl Cetak</td><td>{{ $generatedAt->locale('id')->translatedFormat('d F Y') }}</td></tr>
                    <tr><td>Status</td><td>LUNAS / DITERIMA</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table style="margin-top: 10px;">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Diterima Dari</div>
                <div class="highlight" style="font-size: 12px; padding-bottom: 2px;">{{ $project->client_name }}</div>
                <div class="muted">
                    {{ $project->client_contact ?: 'Kontak klien belum diisi' }}<br>
                    Untuk pembayaran invoice project <strong>{{ $project->name }}</strong>
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="amount-card">
                <div class="amount-label">Jumlah Diterima</div>
                <div class="amount-value">{{ $rupiah($cashIn->amount) }}</div>
                @if (($discountAmount ?? 0) > 0)
                    <div class="muted" style="margin-top: 6px; color: #dbeafe; font-size: 8.5px; line-height: 1.45;">
                        Tagihan dilunasi {{ $rupiah($settledAmount) }}<br>
                        Diskon (potongan) − {{ $rupiah($discountAmount) }}
                    </div>
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="detail-table">
    <thead>
        <tr>
            <th colspan="2">Detail Pembayaran</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Keperluan</td>
            <td>Pembayaran invoice project {{ $project->name }}</td>
        </tr>
        <tr>
            <td>Metode Bayar</td>
            <td>{{ $cashIn->paymentMethod?->name ?: '-' }}</td>
        </tr>
        @if (($discountAmount ?? 0) > 0)
            <tr>
                <td>Tagihan Dilunasi</td>
                <td>{{ $rupiah($settledAmount) }}</td>
            </tr>
            <tr>
                <td>Diskon (Potongan)</td>
                <td>− {{ $rupiah($discountAmount) }}</td>
            </tr>
        @endif
        <tr>
            <td>Keterangan</td>
            <td>{{ $cashIn->note ?: '-' }}</td>
        </tr>
        <tr>
            <td>No. Referensi</td>
            <td>{{ $cashIn->id ? 'CASHIN-'.$cashIn->id : '-' }}</td>
        </tr>
        <tr>
            <td>Sisa Invoice</td>
            <td>{{ $rupiah($invoice['remaining_amount'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Catatan</div>
                <div class="muted">
                    Kwitansi ini diterbitkan sebagai bukti penerimaan pembayaran dari klien untuk invoice <strong>{{ $invoice['number'] }}</strong>.
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="card">
                <div class="section-title">Otorisasi</div>
                <div class="status-badge">Pembayaran Diterima</div>
                <div class="muted">
                    Dokumen dibuat otomatis oleh sistem.
                </div>
                <div class="signature">
                    Hormat kami,<br><br>
                    Finance<br>
                    {{ $brand['name'] }}
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="footer-note">
    Dicetak pada {{ $generatedAt->locale('id')->translatedFormat('d F Y H:i') }} WITA
</div>
</body>
</html>
