<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['number'] }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm 15mm 15mm 15mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #243447;
            background: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            vertical-align: top;
        }

        .page-title {
            font-size: 30px;
            font-weight: 700;
            letter-spacing: 4px;
            color: #1E3A5F;
            margin: 0 0 4px;
        }

        .page-subtitle {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .header-table td {
            padding: 0;
        }

        .company-block {
            padding-right: 12mm;
        }

        .logo-box {
            width: 66px;
            height: 66px;
            border: 1px solid #dbe4ee;
            text-align: center;
            vertical-align: middle;
            background: #f8fafc;
        }

        .logo-box img {
            width: 54px;
            height: 54px;
            margin-top: 5px;
        }

        .logo-text {
            font-size: 16px;
            font-weight: 700;
            color: #1E3A5F;
            line-height: 66px;
            letter-spacing: 2px;
        }

        .company-name {
            font-size: 20px;
            font-weight: 700;
            color: #1E3A5F;
            padding-top: 2px;
        }

        .company-meta {
            font-size: 10.5px;
            color: #4b5563;
            line-height: 1.55;
            padding-top: 6px;
        }

        .invoice-box {
            border: 1px solid #dbe4ee;
            background: #f8fafc;
            padding: 10px 12px;
        }

        .invoice-box-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #1E3A5F;
            padding-bottom: 8px;
        }

        .meta-table td {
            padding: 4px 0;
            font-size: 10.5px;
        }

        .meta-label {
            width: 42%;
            color: #6b7280;
        }

        .meta-value {
            font-weight: 700;
            color: #243447;
            text-align: right;
        }

        .section {
            margin-top: 14px;
        }

        .bill-card {
            border: 1px solid #dbe4ee;
            border-left: 4px solid #1E3A5F;
            background: #fcfdff;
            padding: 12px 14px;
        }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.3px;
            color: #1E3A5F;
            padding-bottom: 6px;
        }

        .client-name {
            font-size: 14px;
            font-weight: 700;
            color: #243447;
            padding-bottom: 4px;
        }

        .client-meta {
            font-size: 10.5px;
            color: #4b5563;
            line-height: 1.6;
        }

        .items-table {
            margin-top: 16px;
            border: 1px solid #dbe4ee;
        }

        .items-table thead th {
            background: #1E3A5F;
            color: #ffffff;
            padding: 9px 7px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid #1E3A5F;
        }

        .items-table tbody td {
            padding: 9px 7px;
            font-size: 10.5px;
            border: 1px solid #e5e7eb;
        }

        .items-table tbody tr:nth-child(even) td {
            background: #F9F9F9;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .desc-title {
            font-weight: 700;
            color: #243447;
            padding-bottom: 2px;
        }

        .desc-sub {
            font-size: 9.5px;
            color: #6b7280;
        }

        .summary-wrap {
            margin-top: 14px;
        }

        .summary-layout td {
            padding: 0;
        }

        .summary-box {
            width: 82mm;
            margin-left: auto;
            border: 1px solid #dbe4ee;
        }

        .summary-box td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10.5px;
        }

        .summary-label {
            color: #4b5563;
        }

        .summary-value {
            text-align: right;
            font-weight: 700;
            color: #243447;
        }

        .summary-total td {
            background: #1E3A5F;
            color: #ffffff;
            border-bottom: 0;
            font-size: 11.5px;
            font-weight: 700;
        }

        .footer-section {
            margin-top: 18px;
        }

        .footer-table td {
            padding: 0;
        }

        .notes-box {
            border: 1px solid #dbe4ee;
            background: #fcfdff;
            padding: 12px 14px;
            min-height: 88px;
        }

        .status-box {
            border: 1px solid #dbe4ee;
            padding: 12px 14px;
            min-height: 88px;
        }

        .status-badge {
            display: inline-block;
            border: 1px solid {{ $invoice['status_color'] }};
            color: {{ $invoice['status_color'] }};
            padding: 4px 10px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .signature-box {
            margin-top: 18px;
            padding-top: 42px;
            border-top: 1px solid #cbd5e1;
            font-size: 10px;
            color: #4b5563;
            text-align: center;
        }

        .footer-note {
            margin-top: 16px;
            font-size: 10px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
@endphp

<table class="header-table">
    <tr>
        <td class="company-block">
            <table>
                <tr>
                    <td style="width: 76px;">
                        <div class="logo-box">
                            @if ($companyLogoPath)
                                <img src="{{ $companyLogoPath }}" alt="Logo">
                            @else
                                <div class="logo-text">LOGO</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="page-title">INVOICE</div>
                        <div class="page-subtitle">Dokumen Tagihan Resmi</div>
                        <div class="company-name">{{ $invoice['company']['name'] }}</div>
                        <div class="company-meta">
                            {{ $invoice['company']['address'] }}<br>
                            Telp: {{ $invoice['company']['phone'] }} | Email: {{ $invoice['company']['email'] }}<br>
                            NPWP: {{ $invoice['company']['npwp'] }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 70mm;">
            <div class="invoice-box">
                <div class="invoice-box-title">Informasi Invoice</div>
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Nomor</td>
                        <td class="meta-value">{{ $invoice['number'] }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Tanggal</td>
                        <td class="meta-value">{{ $invoice['formatted_date'] }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Jatuh Tempo</td>
                        <td class="meta-value">{{ $invoice['formatted_due_date'] }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Status</td>
                        <td class="meta-value">{{ $invoice['status_label'] }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="bill-card">
        <div class="section-title">Tagihan Kepada</div>
        <div class="client-name">{{ $invoice['client']['name'] }}</div>
        <div class="client-meta">
            {{ $invoice['client']['address'] }}<br>
            Telp: {{ $invoice['client']['phone'] }}<br>
            Email: {{ $invoice['client']['email'] }}
        </div>
    </div>
</div>

<table class="items-table">
    <thead>
        <tr>
            <th style="width: 7%;">No</th>
            <th style="width: 39%;">Deskripsi</th>
            <th style="width: 9%;">Qty</th>
            <th style="width: 12%;">Satuan</th>
            <th style="width: 16%;">Harga</th>
            <th style="width: 17%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice['items'] as $item)
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td>
                    <div class="desc-title">{{ $item['description'] }}</div>
                    <div class="desc-sub">Item tagihan {{ $invoice['number'] }}</div>
                </td>
                <td class="text-center">{{ number_format((float) $item['qty'], 0, ',', '.') }}</td>
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">{{ $rupiah($item['price']) }}</td>
                <td class="text-right">{{ $rupiah($item['subtotal']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="summary-wrap">
    <table class="summary-layout">
        <tr>
            <td></td>
            <td style="width: 84mm;">
                <table class="summary-box">
                    <tr>
                        <td class="summary-label">Subtotal</td>
                        <td class="summary-value">{{ $rupiah($invoice['subtotal']) }}</td>
                    </tr>
                    <tr>
                        <td class="summary-label">PPN</td>
                        <td class="summary-value">{{ $rupiah(0) }}</td>
                    </tr>
                    <tr class="summary-total">
                        <td>Total</td>
                        <td class="text-right">{{ $rupiah($invoice['subtotal']) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="footer-section">
    <table class="footer-table">
        <tr>
            <td style="width: 58%; padding-right: 8mm;">
                <div class="notes-box">
                    <div class="section-title">Catatan Pembayaran</div>
                    <div class="client-meta">{{ $invoice['notes'] }}</div>
                </div>
            </td>
            <td style="width: 42%;">
                <div class="status-box">
                    <div class="section-title">Status & Otorisasi</div>
                    <div class="status-badge">{{ $invoice['status_label'] }}</div>
                    <div class="client-meta">Dokumen ini diterbitkan secara internal dan siap digunakan sebagai invoice preview maupun file unduhan PDF.</div>
                    <div class="signature-box">
                        Hormat kami,<br><br>
                        {{ $invoice['company']['name'] }}
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="footer-note">
    Dicetak pada {{ $generatedAt->locale('id')->translatedFormat('d F Y H:i') }} WITA
</div>
</body>
</html>
