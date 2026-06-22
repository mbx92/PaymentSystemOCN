<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $budgetNumber }}</title>
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
        td, th { vertical-align: top; }
        .mt-sm { margin-top: 10px; }
        .mt-md { margin-top: 12px; }

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

        .info-box {
            border: 1px solid #dbe4ee;
            background: #f8fafc;
            padding: 8px 10px;
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

        .card {
            border: 1px solid #dbe4ee;
            background: #fcfdff;
            padding: 9px 11px;
        }

        .bill-card {
            border-left: 3px solid #1E3A5F;
        }

        .client-name {
            font-size: 12px;
            font-weight: 700;
            color: #243447;
            padding-bottom: 2px;
        }

        .items {
            margin-top: 12px;
            border: 1px solid #dbe4ee;
            page-break-inside: auto;
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
            font-size: 9px;
            border: 1px solid #e5e7eb;
            page-break-inside: avoid;
        }

        .items tbody tr:nth-child(even) td {
            background: #F9F9F9;
        }

        .desc-title {
            font-weight: 700;
            color: #243447;
            padding-bottom: 1px;
        }

        .desc-sub {
            font-size: 8px;
            color: #6b7280;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .summary-box {
            width: 78mm;
            margin-left: auto;
            border: 1px solid #dbe4ee;
        }

        .summary-box td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }

        .summary-box .label {
            color: #4b5563;
        }

        .summary-box .value {
            text-align: right;
            font-weight: 700;
            color: #243447;
        }

        .summary-total td {
            background: #1E3A5F;
            color: #ffffff;
            border-bottom: 0;
            font-size: 10px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            border: 1px solid #1E3A5F;
            color: #1E3A5F;
            padding: 3px 8px;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .status-deal { border-color: #166534; color: #166534; }
        .status-converted { border-color: #92400e; color: #92400e; }
        .status-draft { border-color: #1E3A5F; color: #1E3A5F; }

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
    $lineItems = collect($lineItems ?? []);
    $subtotal = (float) ($lineItemsSubtotal ?? $lineItems->sum('subtotal'));
    $grandTotal = (float) ($grandTotal ?? $subtotal);
    if ($grandTotal <= 0) {
        $grandTotal = $subtotal;
    }
    $statusLabel = match($budget->status) {
        'deal' => 'DEAL / SETUJU',
        'converted' => 'SUDAH CONVERT',
        default => 'DRAFT / PENAWARAN',
    };
    $statusClass = match($budget->status) {
        'deal' => 'status-deal',
        'converted' => 'status-converted',
        default => 'status-draft',
    };
    $validUntil = $generatedAt->copy()->addDays(14);
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
                        <div class="title">BUDGET</div>
                        <div class="subtitle">Dokumen Penawaran Project</div>
                        <div class="brand-name">{{ $brand['name'] }}</div>
                        <div class="muted">
                            {{ $brand['tagline'] }}<br>
                            @if (!empty($brand['address']))
                                {{ $brand['address'] }}<br>
                            @endif
                            @if (!empty($brand['phone']))
                                {{ $brand['phone'] }}<br>
                            @endif
                            Project: {{ $budget->name }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 70mm;">
            <div class="info-box">
                <div class="box-title">Informasi Budget</div>
                <table class="meta-table">
                    <tr><td>Nomor</td><td>{{ $budgetNumber }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $generatedAt->locale('id')->translatedFormat('d F Y') }}</td></tr>
                    <tr><td>Berlaku Hingga</td><td>{{ $validUntil->locale('id')->translatedFormat('d F Y') }}</td></tr>
                    <tr><td>Status</td><td>{{ $statusLabel }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="mt-sm">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card bill-card">
                <div class="section-title">Penawaran Kepada</div>
                <div class="client-name">{{ $budget->client_name }}</div>
                <div class="muted">
                    {{ $budget->client_contact ?: 'Kontak klien belum diisi' }}<br>
                    {{ $budget->projectTypeLabel() ?: 'Jenis project belum diisi' }}
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="card">
                <div class="section-title">Ringkasan Penawaran</div>
                <table class="meta-table">
                    <tr><td>Total Item</td><td>{{ $lineItems->count() }}</td></tr>
                    <tr><td>Tipe Project</td><td>{{ $budget->projectTypeLabel() ?: '-' }}</td></tr>
                    <tr><td>Deal</td><td>{{ $budget->deal_at?->locale('id')->translatedFormat('d M Y') ?: '-' }}</td></tr>
                    <tr><td>Total Estimasi</td><td>{{ $rupiah($grandTotal) }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width: 7%;">No</th>
            <th style="width: 38%;">Deskripsi</th>
            <th style="width: 10%;">Qty</th>
            <th style="width: 11%;">Satuan</th>
            <th style="width: 16%;">Harga</th>
            <th style="width: 18%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lineItems as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <div class="desc-title">{{ $item['name'] }}</div>
                    @if (!empty($item['description']))
                        <div class="desc-sub">{{ $item['description'] }}</div>
                    @endif
                </td>
                <td class="text-center">{{ number_format((float) $item['qty'], 0, ',', '.') }}</td>
                <td class="text-center">{{ $item['uom'] ?? 'unit' }}</td>
                <td class="text-right">{{ $rupiah($item['unit_price']) }}</td>
                <td class="text-right">{{ $rupiah($item['subtotal']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center">Belum ada item penawaran.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="mt-sm">
    <tr>
        <td></td>
        <td style="width: 84mm;">
            <table class="summary-box">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="value">{{ $rupiah($subtotal) }}</td>
                </tr>
                <tr>
                    <td class="label">PPN</td>
                    <td class="value">{{ $rupiah(0) }}</td>
                </tr>
                <tr class="summary-total">
                    <td>Total Estimasi</td>
                    <td class="text-right">{{ $rupiah($grandTotal) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="mt-md">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Catatan Penawaran</div>
                <div class="muted">
                    @if ($budget->description)
                        {{ $budget->description }}<br><br>
                    @endif
                    Penawaran ini berlaku hingga <strong>{{ $validUntil->locale('id')->translatedFormat('d F Y') }}</strong>.
                    Cantumkan nomor budget <strong>{{ $budgetNumber }}</strong> pada korespondensi terkait.
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="card">
                <div class="section-title">Status & Otorisasi</div>
                <div class="status-badge {{ $statusClass }}">{{ $statusLabel }}</div>
                <div class="muted">
                    Dokumen dibuat otomatis oleh sistem.
                </div>
                <div class="signature">
                    Hormat kami,<br><br>
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
