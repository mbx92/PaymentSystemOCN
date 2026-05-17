<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $budgetNumber }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: {{ $theme['base_content'] ?? '#111827' }};
            line-height: 1.45;
        }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .head td { border: 0; padding: 0; vertical-align: top; }
        .logo-img { width: 52px; height: 52px; object-fit: contain; display: block; }
        .logo-placeholder {
            width: 52px;
            height: 52px;
            border: 1px dashed {{ $theme['base_300'] ?? '#dbe3ef' }};
            border-radius: 8px;
            background: {{ $theme['base_200'] ?? '#f3f6fb' }};
            color: {{ $theme['muted'] ?? '#6b7280' }};
            text-align: center;
            font-size: 8px;
            font-weight: 700;
            line-height: 52px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .doc-label {
            margin: 0 0 2px;
            color: {{ $theme['primary'] ?? '#1d4ed8' }};
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }
        .brand-title {
            margin: 0;
            color: {{ $theme['primary'] ?? '#1d4ed8' }};
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .brand-title.is-placeholder {
            color: {{ $theme['muted'] ?? '#6b7280' }};
            font-size: 18px;
            font-style: italic;
            font-weight: 600;
        }
        .brand-sub {
            margin: 4px 0 0;
            color: {{ $theme['primary'] ?? '#1d4ed8' }};
            font-size: 12px;
            font-weight: 700;
        }
        .brand-sub.is-placeholder {
            color: {{ $theme['muted'] ?? '#6b7280' }};
            font-style: italic;
            font-weight: 500;
        }
        .brand-contact {
            margin-top: 6px;
            font-size: 10px;
            color: {{ $theme['base_content'] ?? '#111827' }};
            line-height: 1.5;
        }
        .brand-contact .is-placeholder {
            color: {{ $theme['muted'] ?? '#6b7280' }};
            font-style: italic;
        }
        .meta { width: 220px; margin-left: auto; border-collapse: collapse; }
        .meta td { border: 0; padding: 2px 0; font-size: 11px; }
        .meta td:first-child { width: 88px; color: {{ $theme['muted'] ?? '#6b7280' }}; }
        .meta td:last-child { font-weight: 700; color: {{ $theme['base_content'] ?? '#111827' }}; text-align: right; }
        .subject {
            margin: 0 0 14px;
            font-size: 12px;
            font-weight: 700;
            color: {{ $theme['base_content'] ?? '#111827' }};
        }
        .items { width: 100%; border-collapse: collapse; }
        .items th {
            background: {{ $theme['primary'] ?? '#1d4ed8' }};
            color: {{ $theme['primary_content'] ?? '#ffffff' }};
            font-size: 11px;
            font-weight: 700;
            padding: 9px 8px;
            text-align: left;
        }
        .items th.text-center { text-align: center; }
        .items th.text-right { text-align: right; }
        .items td {
            padding: 8px;
            border-bottom: 1px solid {{ $theme['base_300'] ?? '#dbe3ef' }};
            vertical-align: top;
        }
        .items tr.row-alt td { background: {{ $theme['base_200'] ?? '#f3f6fb' }}; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-wrap { width: 100%; margin-top: 16px; border-collapse: collapse; }
        .total-wrap td { border: 0; padding: 0; }
        .total-box {
            width: 260px;
            margin-left: auto;
            background: {{ $theme['base_200'] ?? '#f3f6fb' }};
            border-radius: 10px;
            padding: 12px 16px;
        }
        .total-box table { width: 100%; border-collapse: collapse; }
        .total-box td { border: 0; padding: 0; }
        .total-label { font-size: 13px; font-weight: 800; color: {{ $theme['base_content'] ?? '#111827' }}; }
        .total-value { font-size: 16px; font-weight: 800; color: {{ $theme['primary'] ?? '#1d4ed8' }}; text-align: right; }
        .empty { padding: 24px 8px; text-align: center; color: {{ $theme['muted'] ?? '#6b7280' }}; }
    </style>
</head>
<body>
@php
    $statusLabels = [
        'draft' => 'Draft',
        'deal' => 'Deal',
        'converted' => 'Converted',
    ];
    $printedDate = $generatedAt->copy()->locale('id')->translatedFormat('j F Y');
    $formatQty = static function (float $qty): string {
        if (abs($qty - round($qty)) < 0.0001) {
            return number_format($qty, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
    };
    $grandTotal = (float) ($grandTotal ?? 0);
@endphp

<table class="head">
    <tr>
        <td style="width: 62%;">
            <table style="border-collapse: collapse;">
                <tr>
                    <td style="width: 58px; padding-right: 10px; vertical-align: top;">
                        @if($brand['has_logo'])
                            <img class="logo-img" src="{{ $brand['logo_data_uri'] }}" alt="Logo">
                        @else
                            <div class="logo-placeholder">{{ $brand['logo_placeholder'] }}</div>
                        @endif
                    </td>
                    <td>
                        <p class="doc-label">Budget</p>
                        <h1 class="brand-title {{ $brand['use_title_placeholder'] ? 'is-placeholder' : '' }}">
                            {{ $brand['use_title_placeholder'] ? $brand['title_placeholder'] : $brand['title'] }}
                        </h1>
                        <p class="brand-sub {{ $brand['use_tagline_placeholder'] ? 'is-placeholder' : '' }}">
                            {{ $brand['use_tagline_placeholder'] ? $brand['tagline_placeholder'] : $brand['tagline'] }}
                        </p>
                        <div class="brand-contact">
                            <span class="{{ $company['use_address_placeholder'] ? 'is-placeholder' : '' }}">
                                {{ $company['use_address_placeholder'] ? $company['address_placeholder'] : $company['address'] }}
                            </span><br>
                            <span class="{{ $company['use_phone_placeholder'] ? 'is-placeholder' : '' }}">
                                {{ $company['use_phone_placeholder'] ? $company['phone_placeholder'] : $company['phone'] }}
                            </span>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 38%;">
            <table class="meta">
                <tr>
                    <td>No. Budget</td>
                    <td>{{ $budgetNumber }}</td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>{{ $printedDate }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>{{ $statusLabels[$budget->status] ?? ucfirst((string) $budget->status) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p class="subject">Perihal: {{ $budget->name }}</p>

<table class="items">
    <thead>
        <tr>
            <th style="width: 6%;" class="text-center">No.</th>
            <th style="width: 40%;">Nama Item</th>
            <th style="width: 10%;" class="text-center">Qty</th>
            <th style="width: 12%;" class="text-center">Satuan</th>
            <th style="width: 16%;" class="text-right">Harga</th>
            <th style="width: 16%;" class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lineItems as $index => $item)
            <tr class="{{ $index % 2 === 1 ? 'row-alt' : '' }}">
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item['name'] }}</td>
                <td class="text-center">{{ $formatQty((float) $item['qty']) }}</td>
                <td class="text-center">{{ $item['uom'] }}</td>
                <td class="text-right">{{ number_format((float) $item['unit_price'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $item['line_total'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="empty">Tidak ada item pada budget ini.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="total-wrap">
    <tr>
        <td></td>
        <td style="width: 280px;">
            <div class="total-box">
                <table>
                    <tr>
                        <td class="total-label">Total:</td>
                        <td class="total-value">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
