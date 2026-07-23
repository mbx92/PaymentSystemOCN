<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota {{ $invoice['number'] }}</title>
    @include('pdf.partials.styles')
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => 'Integrated Business Platform', 'logo_data_uri' => null];
    $taxAmount = 0;

    $summaryRows = [
        ['Subtotal', $rupiah($itemsSubtotal)],
        ['PPN', $rupiah($taxAmount)],
    ];
    if (($totalDiscount ?? 0) > 0) {
        $summaryRows[] = ['Total Tagihan', $rupiah($invoice['amount'])];
        $summaryRows[] = ['Diskon (Potongan)', '− '.$rupiah($totalDiscount)];
        $summaryTotalLabel = 'Jumlah Bayar (Kas)';
        $summaryTotalValue = $rupiah($cashReceived ?? 0);
    } else {
        $summaryTotalLabel = 'Total Dokumen';
        $summaryTotalValue = $rupiah($invoice['amount']);
    }
@endphp

@include('pdf.partials.doc-header', [
    'docTitle' => 'NOTA PENJUALAN',
    'docSubtitle' => 'Lampiran Item Penjualan',
    'brand' => $brand,
    'brandLines' => [$brand['tagline'] ?? '', 'Project: '.$project->name],
    'metaTitle' => 'Data Dokumen',
    'metaRows' => [
        'No. Nota' => 'NOTA/'.$invoice['number'],
        'Tanggal' => $generatedAt->locale('id')->translatedFormat('d F Y'),
        'Invoice' => $invoice['number'],
        'Status' => strtoupper($invoice['status']),
    ],
])

<div class="mt-sm">
    <div class="card">
        <div class="section-title">Customer</div>
        <div class="client-name">{{ $project->client_name }}</div>
        <div class="muted">{{ $project->client_contact ?: '-' }}</div>
    </div>
</div>

<table class="items-table">
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
            <tr class="empty-row">
                <td colspan="6">Belum ada item penjualan.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="mt-sm">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Catatan</div>
                <div class="muted">
                    Nilai resmi mengikuti invoice <strong>{{ $invoice['number'] }}</strong>.<br>
                    Dicetak {{ $generatedAt->locale('id')->translatedFormat('d F Y H:i') }} WITA.
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <table class="summary-box" style="width: 100%; margin-left: 0;">
                @foreach ($summaryRows as $row)
                    <tr>
                        <td class="label">{{ $row[0] }}</td>
                        <td class="value">{{ $row[1] }}</td>
                    </tr>
                @endforeach
                <tr class="summary-total">
                    <td>{{ $summaryTotalLabel }}</td>
                    <td class="text-right">{{ $summaryTotalValue }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="mt-sm">
    <tr>
        <td style="width: 58%;"></td>
        <td style="width: 42%;">
            @include('pdf.partials.signature', ['label' => 'Disiapkan oleh,', 'brand' => $brand])
        </td>
    </tr>
</table>
</body>
</html>
