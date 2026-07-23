<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Penjualan {{ $invoice['number'] }}</title>
    @include('pdf.partials.styles')
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
    'docTitle' => 'NOTA PENJUALAN',
    'docSubtitle' => 'Ringkasan Penjualan dan Serah Terima',
    'brand' => $brand,
    'brandLines' => [
        $invoice['company']['address'],
        $invoice['company']['phone'].' | '.$invoice['company']['email'],
    ],
    'metaTitle' => 'Data Dokumen',
    'metaRows' => [
        'No. Nota' => 'NOTA/'.$invoice['number'],
        'Tanggal' => $invoice['formatted_date'],
        'Relasi Invoice' => $invoice['number'],
    ],
])

<table class="mt-md">
    <tr>
        <td style="width: 56%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Pembeli</div>
                <div class="muted">
                    <span class="client-name">{{ $invoice['client']['name'] }}</span><br>
                    {{ $invoice['client']['address'] }}<br>
                    Telp: {{ $invoice['client']['phone'] }}<br>
                    Email: {{ $invoice['client']['email'] }}
                </div>
            </div>
        </td>
        <td style="width: 44%;">
            <div class="card">
                <div class="section-title">Keterangan</div>
                <div class="muted">
                    Nota penjualan ini merangkum item yang ditagihkan kepada pelanggan dan dapat digunakan sebagai lampiran operasional bersama invoice utama.
                </div>
            </div>
        </td>
    </tr>
</table>

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

<table class="mt-md">
    <tr>
        <td style="width: 58%; padding-right: 8mm;">
            <div class="card">
                <div class="section-title">Catatan</div>
                <div class="muted">
                    {{ $invoice['notes'] }}<br><br>
                    Status invoice terkait saat ini: <strong>{{ $invoice['status_label'] }}</strong>.
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <table class="summary-box" style="width: 100%; margin-left: 0;">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="value">{{ $rupiah($invoice['subtotal']) }}</td>
                </tr>
                <tr>
                    <td class="label">PPN</td>
                    <td class="value">{{ $rupiah(0) }}</td>
                </tr>
                <tr class="summary-total">
                    <td>Total</td>
                    <td class="text-right">{{ $rupiah($invoice['subtotal']) }}</td>
                </tr>
            </table>
            @include('pdf.partials.signature', ['brand' => $brand])
        </td>
    </tr>
</table>
</body>
</html>
