<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $budgetNumber }}</title>
    @include('pdf.partials.styles')
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => '', 'logo_data_uri' => null];
    $lineItems = collect($lineItems ?? []);
    $grandTotal = (float) ($grandTotal ?? $lineItems->sum('subtotal'));
@endphp

@include('pdf.partials.doc-header', [
    'docTitle' => 'RAB',
    'docSubtitle' => 'Rencana Anggaran Biaya',
    'brand' => $brand,
    'metaTitle' => 'Informasi Dokumen',
    'metaRows' => [
        'Dokumen' => $budget->name,
        'Tanggal' => $generatedAt->locale('id')->translatedFormat('d F Y'),
    ],
])

<table class="mt-md">
    <tr>
        <td style="width: 50%; padding-right: 6px;">
            <div class="card">
                <div class="section-title">Customer</div>
                <div class="client-name" style="padding-bottom: 0;">{{ $budget->client_name }}</div>
            </div>
        </td>
        <td style="width: 50%; padding-left: 6px;">
            <div class="card">
                <div class="section-title">Tipe Project</div>
                <div class="client-name" style="padding-bottom: 0;">{{ $budget->projectTypeLabel() ?: '-' }}</div>
            </div>
        </td>
    </tr>
    @if ($budget->description)
        <tr>
            <td colspan="2" style="padding-top: 10px;">
                <div class="card">
                    <div class="section-title">Catatan</div>
                    <div class="muted">{{ $budget->description }}</div>
                </div>
            </td>
        </tr>
    @endif
</table>

<table class="items-table">
    <thead>
        <tr>
            <th style="width: 7%;" class="text-center">#</th>
            <th>Item</th>
            <th style="width: 12%;" class="text-center">Qty</th>
            <th style="width: 12%;" class="text-center">Satuan</th>
            <th style="width: 20%;" class="text-right">Harga Satuan</th>
            <th style="width: 20%;" class="text-right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lineItems as $index => $item)
            <tr>
                <td class="text-center muted">{{ $index + 1 }}</td>
                <td class="strong">{{ $item['name'] }}</td>
                <td class="text-center">{{ rtrim(rtrim(number_format((float) $item['qty'], 2, ',', '.'), '0'), ',') }}</td>
                <td class="text-center muted">{{ $item['uom'] ?? 'unit' }}</td>
                <td class="text-right">{{ $rupiah($item['unit_price'] ?? 0) }}</td>
                <td class="text-right strong">{{ $rupiah($item['subtotal'] ?? 0) }}</td>
            </tr>
        @empty
            <tr class="empty-row">
                <td colspan="6">Belum ada item pada RAB ini.</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if ($lineItems->isNotEmpty())
    @include('pdf.partials.summary-box', [
        'rows' => [],
        'totalLabel' => 'Total Penawaran',
        'totalValue' => $rupiah($grandTotal),
    ])
@endif

<div class="card mt-md">
    <div class="section-title">Ketentuan Biaya Tambahan</div>
    <div class="muted">
        RAB ini disusun berdasarkan kebutuhan project yang teridentifikasi di awal. Apabila di lapangan ditemukan kebutuhan tambahan alat dan/atau bahan di luar RAB ini, akan timbul biaya tambahan sesuai kebutuhan aktual, yang akan dikonfirmasikan dan disetujui terlebih dahulu oleh customer sebelum dikerjakan. Sebaliknya, apabila ada alat atau bahan pada RAB ini yang dikurangi atau tidak jadi digunakan, maka total biaya akan disesuaikan kembali.
    </div>
</div>

<div class="footer-note" style="text-align: left;">
    Penawaran ini disusun berdasarkan kebutuhan project. Harga belum termasuk revisi di luar scope tanpa persetujuan tertulis.
</div>
</body>
</html>
