<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['number'] }}</title>
    @include('pdf.partials.styles')
    <style>
        .status-badge.status-paid    { border-color: #166534; color: #166534; }
        .status-badge.status-partial { border-color: #92400e; color: #92400e; }
        .status-badge.status-unpaid  { border-color: #991b1b; color: #991b1b; }
    </style>
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => 'Integrated Business Platform', 'logo_data_uri' => null];
    $lineItems = collect($lineItems ?? []);
    $subtotal = (float) ($lineItemsSubtotal ?? $lineItems->sum('subtotal'));
    $taxAmount = 0;
    $statusLabel = match($invoice['status']) {
        'paid' => 'LUNAS',
        'partial' => 'DIBAYAR SEBAGIAN',
        default => 'BELUM DIBAYAR',
    };
    $statusClass = match($invoice['status']) {
        'paid' => 'status-paid',
        'partial' => 'status-partial',
        default => 'status-unpaid',
    };
@endphp

@include('pdf.partials.doc-header', [
    'docTitle' => 'INVOICE',
    'docSubtitle' => 'Dokumen Tagihan Project',
    'brand' => $brand,
    'brandLines' => [$brand['tagline'] ?? '', 'Project: '.$project->name],
    'metaTitle' => 'Informasi Invoice',
    'metaRows' => [
        'Nomor' => $invoice['number'],
        'Tanggal' => \Carbon\Carbon::parse($invoice['created_at'] ?? now())->locale('id')->translatedFormat('d F Y'),
        'Jatuh Tempo' => $generatedAt->copy()->addDays(14)->locale('id')->translatedFormat('d F Y'),
        'Status' => $statusLabel,
    ],
])

<table class="mt-md">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card card--accent">
                <div class="section-title">Tagihan Kepada</div>
                <div class="client-name">{{ $project->client_name }}</div>
                <div class="muted">
                    {{ $project->client_contact ?: 'Kontak klien belum diisi' }}<br>
                    {{ $project->projectTypeLabel() ?: 'Jenis project belum diisi' }}
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="card">
                <div class="section-title">Ringkasan Project</div>
                <table class="meta-table">
                    <tr><td class="label">Total Item</td><td class="value">{{ $lineItems->count() }}</td></tr>
                    <tr><td class="label">Tgl Selesai</td><td class="value">{{ $project->finished_at?->locale('id')->translatedFormat('d M Y') ?: '-' }}</td></tr>
                    <tr><td class="label">Sudah Dibayar</td><td class="value">{{ $rupiah($invoice['paid_amount'] ?? 0) }}</td></tr>
                    <tr><td class="label">Sisa Tagihan</td><td class="value">{{ $rupiah($invoice['remaining_amount'] ?? 0) }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="items-table">
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
                    @if (!empty($item['description']) && $index < 3)
                        <div class="desc-sub">{{ $item['description'] }}</div>
                    @endif
                </td>
                <td class="text-center">{{ number_format((float) $item['qty'], 0, ',', '.') }}</td>
                <td class="text-center">{{ $item['uom'] ?? 'unit' }}</td>
                <td class="text-right">{{ $rupiah($item['unit_price']) }}</td>
                <td class="text-right">{{ $rupiah($item['subtotal']) }}</td>
            </tr>
        @empty
            <tr class="empty-row">
                <td colspan="6">Belum ada item tagihan.</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if (($project->payments ?? collect())->isNotEmpty())
    <table class="items-table mt-sm">
        <thead>
            <tr>
                <th style="width: 12%;">Termin</th>
                <th>Catatan</th>
                <th style="width: 18%;">Persentase</th>
                <th style="width: 20%;">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($project->payments as $term)
                <tr>
                    <td class="text-center">{{ $term->term_number }}</td>
                    <td>{{ $term->note ?: 'Termin pembayaran' }}</td>
                    <td class="text-center">{{ number_format((float) $term->percentage, 0, ',', '.') }}%</td>
                    <td class="text-right">{{ $rupiah($term->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@include('pdf.partials.summary-box', [
    'rows' => [
        ['Subtotal', $rupiah($subtotal)],
        ['PPN', $rupiah($taxAmount)],
        ['Sudah Dibayar', $rupiah($invoice['paid_amount'] ?? 0)],
    ],
    'totalLabel' => 'Sisa Tagihan',
    'totalValue' => $rupiah($invoice['remaining_amount'] ?? 0),
])

<table class="mt-lg">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Catatan Pembayaran</div>
                <div class="muted">
                    Cantumkan nomor invoice <strong>{{ $invoice['number'] }}</strong> pada berita transfer.
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="card">
                <div class="section-title">Status &amp; Otorisasi</div>
                <div class="badge status-badge {{ $statusClass }}">{{ $statusLabel }}</div>
                <div class="muted">Dokumen dibuat otomatis oleh sistem.</div>
                @include('pdf.partials.signature', ['brand' => $brand])
            </div>
        </td>
    </tr>
</table>

@include('pdf.partials.footer-note', ['generatedAt' => $generatedAt])
</body>
</html>
