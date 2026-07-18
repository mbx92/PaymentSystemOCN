<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $budgetNumber }}</title>
    <style>
        @page {
            size: A4;
            margin: 16mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            color: #1f2937;
            background: #ffffff;
        }

        .container {
            width: 100%;
        }

        .header-table,
        .info-table,
        .items-table,
        .total-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td,
        .info-table td,
        .items-table td,
        .items-table th,
        .total-table td {
            vertical-align: top;
        }

        .brand-logo {
            width: 58px;
        }

        .brand-logo img {
            max-width: 56px;
            max-height: 56px;
            display: block;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.2;
        }

        .brand-tagline {
            margin-top: 2px;
            font-size: 10px;
            color: #6b7280;
        }

        .doc-meta {
            text-align: right;
        }

        .doc-kicker {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #2563eb;
        }

        .doc-title {
            margin-top: 4px;
            font-size: 14px;
            font-weight: 700;
        }

        .doc-date {
            margin-top: 2px;
            font-size: 10px;
            color: #6b7280;
        }

        .spacer-lg {
            height: 18px;
        }

        .info-box {
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
        }

        .info-label {
            font-size: 9px;
            color: #6b7280;
            padding-bottom: 4px;
        }

        .info-value {
            font-size: 11px;
            font-weight: 600;
            color: #111827;
        }

        .items-wrap {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .items-table th {
            background: #e5e7eb;
            color: #374151;
            padding: 9px 8px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            text-align: left;
        }

        .items-table td {
            padding: 9px 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
        }

        .items-table .center {
            text-align: center;
        }

        .items-table .right {
            text-align: right;
        }

        .items-table .muted {
            color: #6b7280;
        }

        .items-table .strong {
            font-weight: 600;
        }

        .empty-row td {
            padding: 24px 8px;
            text-align: center;
            color: #6b7280;
        }

        .total-box {
            margin-top: 12px;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
            border-radius: 12px;
            padding: 12px 14px;
        }

        .total-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
        }

        .total-value {
            margin-top: 4px;
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .footer-note {
            margin-top: 16px;
            font-size: 9px;
            color: #6b7280;
        }
    </style>
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => '', 'logo_data_uri' => null];
    $lineItems = collect($lineItems ?? []);
    $grandTotal = (float) ($grandTotal ?? $lineItems->sum('subtotal'));
@endphp

<div class="container">
    <table class="header-table">
        <tr>
            <td>
                <table>
                    <tr>
                        @if (! empty($brand['logo_data_uri']))
                            <td class="brand-logo">
                                <img src="{{ $brand['logo_data_uri'] }}" alt="{{ $brand['name'] }}">
                            </td>
                        @endif
                        <td>
                            <div class="brand-name">{{ $brand['name'] }}</div>
                            @if (! empty($brand['tagline']))
                                <div class="brand-tagline">{{ $brand['tagline'] }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="doc-meta" style="width: 34%;">
                <div class="doc-kicker">Rencana Anggaran Biaya</div>
                <div class="doc-title">{{ $budget->name }}</div>
                <div class="doc-date">{{ $generatedAt->locale('id')->translatedFormat('d F Y H:i') }} WITA</div>
            </td>
        </tr>
    </table>

    <div class="spacer-lg"></div>

    <table class="info-table">
        <tr>
            <td style="width: 50%; padding-right: 6px;">
                <div class="info-box">
                    <div class="info-label">Customer</div>
                    <div class="info-value">{{ $budget->client_name }}</div>
                </div>
            </td>
            <td style="width: 50%; padding-left: 6px;">
                <div class="info-box">
                    <div class="info-label">Tipe Project</div>
                    <div class="info-value">{{ $budget->projectTypeLabel() ?: '-' }}</div>
                </div>
            </td>
        </tr>
        @if ($budget->description)
            <tr>
                <td colspan="2" style="padding-top: 12px;">
                    <div class="info-box">
                        <div class="info-label">Catatan</div>
                        <div class="info-value" style="font-weight: 500;">{{ $budget->description }}</div>
                    </div>
                </td>
            </tr>
        @endif
    </table>

    <div class="spacer-lg"></div>

    <div class="items-wrap">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 7%;" class="center">#</th>
                    <th>Item</th>
                    <th style="width: 12%;" class="center">Qty</th>
                    <th style="width: 12%;" class="center">Satuan</th>
                    <th style="width: 20%;" class="right">Harga Satuan</th>
                    <th style="width: 20%;" class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lineItems as $index => $item)
                    <tr>
                        <td class="center muted">{{ $index + 1 }}</td>
                        <td class="strong">{{ $item['name'] }}</td>
                        <td class="center">{{ rtrim(rtrim(number_format((float) $item['qty'], 2, ',', '.'), '0'), ',') }}</td>
                        <td class="center muted">{{ $item['uom'] ?? 'unit' }}</td>
                        <td class="right">{{ $rupiah($item['unit_price'] ?? 0) }}</td>
                        <td class="right strong">{{ $rupiah($item['subtotal'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="6">Belum ada item pada RAB ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($lineItems->isNotEmpty())
        <table style="width: 100%; margin-top: 12px;">
            <tr>
                <td></td>
                <td style="width: 36%;">
                    <div class="total-box">
                        <div class="total-label">Total Penawaran</div>
                        <div class="total-value">{{ $rupiah($grandTotal) }}</div>
                    </div>
                </td>
            </tr>
        </table>
    @endif

    <div class="footer-note">
        Penawaran ini disusun berdasarkan kebutuhan project. Harga belum termasuk revisi di luar scope tanpa persetujuan tertulis.
    </div>
</div>
</body>
</html>
