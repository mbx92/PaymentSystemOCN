<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['number'] }}</title>
    @include('pdf.partials.styles')
    <style>
        .status-badge {
            border-color: {{ $invoice['status_color'] }};
            color: {{ $invoice['status_color'] }};
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand = [
        'name' => $invoice['company']['name'],
        'logo_data_uri' => $companyLogoPath,
    ];
@endphp

@include('pdf.partials.doc-header', [
    'docTitle' => 'INVOICE',
    'docSubtitle' => 'Dokumen Tagihan Resmi',
    'brand' => $brand,
    'brandLines' => [
        $invoice['company']['address'],
        'Telp: '.$invoice['company']['phone'].' | Email: '.$invoice['company']['email'],
        'NPWP: '.$invoice['company']['npwp'],
    ],
    'metaTitle' => 'Informasi Invoice',
    'metaRows' => [
        'Nomor' => $invoice['number'],
        'Tanggal' => $invoice['formatted_date'],
        'Jatuh Tempo' => $invoice['formatted_due_date'],
        'Status' => $invoice['status_label'],
    ],
])

<div class="mt-md">
    <div class="card card--accent">
        <div class="section-title">Tagihan Kepada</div>
        <div class="client-name">{{ $invoice['client']['name'] }}</div>
        <div class="muted">
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

@include('pdf.partials.summary-box', [
    'rows' => [
        ['Subtotal', $rupiah($invoice['subtotal'])],
        ['PPN', $rupiah(0)],
    ],
    'totalLabel' => 'Total',
    'totalValue' => $rupiah($invoice['subtotal']),
])

<table class="mt-lg">
    <tr>
        <td style="width: 58%; padding-right: 8mm;">
            <div class="card" style="min-height: 88px;">
                <div class="section-title">Catatan Pembayaran</div>
                <div class="muted">{{ $invoice['notes'] }}</div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="card" style="min-height: 88px;">
                <div class="section-title">Status &amp; Otorisasi</div>
                <div class="badge status-badge">{{ $invoice['status_label'] }}</div>
                <div class="muted">Dokumen ini diterbitkan secara internal dan siap digunakan sebagai invoice preview maupun file unduhan PDF.</div>
                @include('pdf.partials.signature', ['brand' => $brand])
            </div>
        </td>
    </tr>
</table>

@include('pdf.partials.footer-note', ['generatedAt' => $generatedAt])
</body>
</html>
