<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Penjualan {{ $invoice['number'] }}</title>
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
            color: #243447;
            background: #ffffff;
            line-height: 1.45;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .title {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 3px;
            color: #1E3A5F;
            margin: 0 0 5px;
        }

        .subtitle {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.3px;
            color: #6b7280;
            margin-bottom: 14px;
        }

        .header-card,
        .info-card,
        .notes-card,
        .total-card {
            border: 1px solid #dbe4ee;
            background: #fcfdff;
        }

        .header-card {
            padding: 10px 12px;
        }

        .info-card,
        .notes-card {
            padding: 12px 14px;
        }

        .total-card {
            padding: 0;
        }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.3px;
            color: #1E3A5F;
            padding-bottom: 7px;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 700;
            color: #1E3A5F;
        }

        .small-text {
            font-size: 10.5px;
            color: #4b5563;
            line-height: 1.6;
        }

        .logo-box {
            width: 60px;
            height: 60px;
            border: 1px solid #dbe4ee;
            text-align: center;
            background: #f8fafc;
        }

        .logo-box img {
            width: 48px;
            height: 48px;
            margin-top: 5px;
        }

        .logo-text {
            line-height: 60px;
            font-size: 14px;
            color: #1E3A5F;
            font-weight: 700;
        }

        .meta td {
            padding: 3px 0;
            font-size: 10.5px;
        }

        .meta td:first-child {
            width: 36%;
            color: #6b7280;
        }

        .meta td:last-child {
            text-align: right;
            font-weight: 700;
        }

        .items {
            margin-top: 16px;
            border: 1px solid #dbe4ee;
        }

        .items th {
            background: #1E3A5F;
            color: #ffffff;
            padding: 9px 7px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid #1E3A5F;
        }

        .items td {
            padding: 9px 7px;
            border: 1px solid #e5e7eb;
            font-size: 10.5px;
        }

        .items tbody tr:nth-child(even) td {
            background: #F9F9F9;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .total-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10.5px;
        }

        .total-table tr:last-child td {
            background: #1E3A5F;
            color: #ffffff;
            border-bottom: 0;
            font-weight: 700;
            font-size: 11.5px;
        }

        .signature {
            margin-top: 18px;
            border-top: 1px solid #cbd5e1;
            padding-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #4b5563;
        }
    </style>
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
@endphp

<table>
    <tr>
        <td style="padding-right: 8mm;">
            <table>
                <tr>
                    <td style="width: 70px;">
                        <div class="logo-box">
                            @if ($companyLogoPath)
                                <img src="{{ $companyLogoPath }}" alt="Logo">
                            @else
                                <div class="logo-text">LOGO</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="title">NOTA PENJUALAN</div>
                        <div class="subtitle">Ringkasan Penjualan dan Serah Terima</div>
                        <div class="brand-name">{{ $invoice['company']['name'] }}</div>
                        <div class="small-text">
                            {{ $invoice['company']['address'] }}<br>
                            {{ $invoice['company']['phone'] }} | {{ $invoice['company']['email'] }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 72mm;">
            <div class="header-card">
                <div class="section-title">Data Dokumen</div>
                <table class="meta">
                    <tr>
                        <td>No. Nota</td>
                        <td>NOTA/{{ $invoice['number'] }}</td>
                    </tr>
                    <tr>
                        <td>Tanggal</td>
                        <td>{{ $invoice['formatted_date'] }}</td>
                    </tr>
                    <tr>
                        <td>Relasi Invoice</td>
                        <td>{{ $invoice['number'] }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table style="margin-top: 14px;">
    <tr>
        <td style="width: 56%; padding-right: 6mm;">
            <div class="info-card">
                <div class="section-title">Pembeli</div>
                <div class="small-text">
                    <strong style="font-size: 14px; color: #243447;">{{ $invoice['client']['name'] }}</strong><br>
                    {{ $invoice['client']['address'] }}<br>
                    Telp: {{ $invoice['client']['phone'] }}<br>
                    Email: {{ $invoice['client']['email'] }}
                </div>
            </div>
        </td>
        <td style="width: 44%;">
            <div class="info-card">
                <div class="section-title">Keterangan</div>
                <div class="small-text">
                    Nota penjualan ini merangkum item yang ditagihkan kepada pelanggan dan dapat digunakan sebagai lampiran operasional bersama invoice utama.
                </div>
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width: 7%;">No</th>
            <th style="width: 39%;">Deskripsi</th>
            <th style="width: 10%;">Qty</th>
            <th style="width: 12%;">Satuan</th>
            <th style="width: 15%;">Harga</th>
            <th style="width: 17%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice['items'] as $item)
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td>{{ $item['description'] }}</td>
                <td class="text-center">{{ number_format((float) $item['qty'], 0, ',', '.') }}</td>
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">{{ $rupiah($item['price']) }}</td>
                <td class="text-right">{{ $rupiah($item['subtotal']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 14px;">
    <tr>
        <td style="width: 58%; padding-right: 8mm;">
            <div class="notes-card">
                <div class="section-title">Catatan</div>
                <div class="small-text">
                    {{ $invoice['notes'] }}<br><br>
                    Status invoice terkait saat ini: <strong>{{ $invoice['status_label'] }}</strong>.
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="total-card">
                <table class="total-table">
                    <tr>
                        <td>Subtotal</td>
                        <td class="text-right">{{ $rupiah($invoice['subtotal']) }}</td>
                    </tr>
                    <tr>
                        <td>PPN</td>
                        <td class="text-right">{{ $rupiah(0) }}</td>
                    </tr>
                    <tr>
                        <td>Total</td>
                        <td class="text-right">{{ $rupiah($invoice['subtotal']) }}</td>
                    </tr>
                </table>
            </div>
            <div class="signature">
                Disiapkan oleh,<br><br>
                {{ $invoice['company']['name'] }}
            </div>
        </td>
    </tr>
</table>
</body>
</html>
