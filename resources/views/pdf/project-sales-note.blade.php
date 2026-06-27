<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota {{ $invoice['number'] }}</title>
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

        table { width: 100%; border-collapse: collapse; }
        .mt-sm { margin-top: 10px; }

        .title {
            font-size: 23px;
            font-weight: 700;
            letter-spacing: 2.5px;
            color: #1E3A5F;
            margin: 0 0 3px;
        }

        .subtitle {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .logo-box {
            width: 52px;
            height: 52px;
            border: 1px solid #dbe4ee;
            text-align: center;
            background: #f8fafc;
        }

        .logo-box img {
            width: 40px;
            height: 40px;
            margin-top: 5px;
        }

        .logo-text {
            line-height: 52px;
            font-size: 12px;
            color: #1E3A5F;
            font-weight: 700;
        }

        .brand-name {
            font-size: 15px;
            font-weight: 700;
            color: #1E3A5F;
        }

        .small-text {
            font-size: 9px;
            color: #4b5563;
            line-height: 1.35;
        }

        .card {
            border: 1px solid #dbe4ee;
            background: #fcfdff;
            padding: 8px 10px;
        }

        .section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #1E3A5F;
            padding-bottom: 4px;
        }

        .meta td {
            padding: 2px 0;
            font-size: 9px;
        }

        .meta td:first-child {
            width: 40%;
            color: #6b7280;
        }

        .meta td:last-child {
            text-align: right;
            font-weight: 700;
        }

        .items {
            margin-top: 12px;
            border: 1px solid #dbe4ee;
        }

        .items th {
            background: #1E3A5F;
            color: #ffffff;
            padding: 6px 5px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border: 1px solid #1E3A5F;
        }

        .items td {
            padding: 5px 5px;
            border: 1px solid #e5e7eb;
            font-size: 9px;
        }

        .items tbody tr:nth-child(even) td {
            background: #F9F9F9;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .total-table {
            border: 1px solid #dbe4ee;
        }

        .total-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }

        .total-table tr:last-child td {
            background: #1E3A5F;
            color: #ffffff;
            border-bottom: 0;
            font-size: 10px;
            font-weight: 700;
        }

        .signature {
            padding-top: 22px;
            text-align: center;
            font-size: 9px;
            color: #4b5563;
        }
    </style>
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => 'Integrated Business Platform', 'logo_data_uri' => null];
    $taxAmount = 0;
@endphp

<table>
    <tr>
        <td style="padding-right: 6mm;">
            <table>
                <tr>
                    <td style="width: 60px;">
                        <div class="logo-box">
                            @if (!empty($brand['logo_data_uri']))
                                <img src="{{ $brand['logo_data_uri'] }}" alt="Logo">
                            @else
                                <div class="logo-text">LOGO</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="title">NOTA PENJUALAN</div>
                        <div class="subtitle">Lampiran Item Penjualan</div>
                        <div class="brand-name">{{ $brand['name'] }}</div>
                        <div class="small-text">
                            {{ $brand['tagline'] }}<br>
                            Project: {{ $project->name }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 72mm;">
            <div class="card">
                <div class="section-title">Data Dokumen</div>
                <table class="meta">
                    <tr><td>No. Nota</td><td>NOTA/{{ $invoice['number'] }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $generatedAt->locale('id')->translatedFormat('d F Y') }}</td></tr>
                    <tr><td>Invoice</td><td>{{ $invoice['number'] }}</td></tr>
                    <tr><td>Status</td><td>{{ strtoupper($invoice['status']) }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="mt-sm">
    <tr>
        <td>
            <div class="card">
                <div class="section-title">Customer</div>
                <div class="small-text">
                    <strong style="font-size: 11px; color: #243447;">{{ $project->client_name }}</strong><br>
                    {{ $project->client_contact ?: '-' }}
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
        @forelse ($items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item['name'] }}</td>
                <td class="text-center">{{ number_format((float) $item['qty'], 0, ',', '.') }}</td>
                <td class="text-center">{{ $item['uom'] }}</td>
                <td class="text-right">{{ $rupiah($item['unit_price']) }}</td>
                <td class="text-right">{{ $rupiah($item['subtotal']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center">Belum ada item penjualan.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="mt-sm">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Catatan</div>
                <div class="small-text">
                    Nilai resmi mengikuti invoice <strong>{{ $invoice['number'] }}</strong>.<br>
                    Dicetak {{ $generatedAt->locale('id')->translatedFormat('d F Y H:i') }} WITA.
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <table class="total-table">
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right">{{ $rupiah($itemsSubtotal) }}</td>
                </tr>
                <tr>
                    <td>PPN</td>
                    <td class="text-right">{{ $rupiah($taxAmount) }}</td>
                </tr>
                @if (($totalDiscount ?? 0) > 0)
                    <tr>
                        <td>Total Tagihan</td>
                        <td class="text-right">{{ $rupiah($invoice['amount']) }}</td>
                    </tr>
                    <tr>
                        <td>Diskon (Potongan)</td>
                        <td class="text-right">− {{ $rupiah($totalDiscount) }}</td>
                    </tr>
                    <tr>
                        <td>Jumlah Bayar (Kas)</td>
                        <td class="text-right">{{ $rupiah($cashReceived ?? 0) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>Total Dokumen</td>
                        <td class="text-right">{{ $rupiah($invoice['amount']) }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table style="width:100%;margin-top:10px;border-collapse:collapse;">
    <tr>
        <td colspan="2" style="border-top:1px solid #cbd5e1;padding:0;line-height:0;font-size:0;">&nbsp;</td>
    </tr>
    <tr>
        <td style="width:58%;"></td>
        <td style="width:42%;" class="signature">
            Disiapkan oleh,<br><br>
            {{ $brand['name'] }}
        </td>
    </tr>
</table>
</body>
</html>
