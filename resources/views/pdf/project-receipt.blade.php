<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kwitansi {{ $invoice['number'] }}</title>
    @include('pdf.partials.styles')
    <style>
        .amount-card {
            border: 1px solid {{ config('pdf.theme.primary', '#1E3A5F') }};
            background: {{ config('pdf.theme.primary', '#1E3A5F') }};
            color: {{ config('pdf.theme.primary_content', '#ffffff') }};
            padding: 10px 12px;
        }
        .amount-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.85;
            padding-bottom: 4px;
        }
        .amount-value {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
        }
        .detail-table {
            margin-top: 12px;
            border: 1px solid {{ config('pdf.theme.base_300', '#d9dde3') }};
        }
        .detail-table th {
            background: {{ config('pdf.theme.primary', '#1E3A5F') }};
            color: {{ config('pdf.theme.primary_content', '#ffffff') }};
            padding: 7px 6px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
        }
        .detail-table td {
            padding: 7px 7px;
            font-size: 9px;
            border-top: 1px solid {{ config('pdf.theme.base_300', '#dbe3ef') }};
        }
        .detail-table td:first-child {
            width: 28%;
            color: {{ config('pdf.theme.muted', '#6b7280') }};
            font-weight: 700;
        }
    </style>
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => 'Integrated Business Platform', 'logo_data_uri' => null];
@endphp

@include('pdf.partials.doc-header', [
    'docTitle' => 'KWITANSI',
    'docSubtitle' => 'Bukti Penerimaan Pembayaran',
    'brand' => $brand,
    'brandLines' => [$brand['tagline'] ?? '', 'Project: '.$project->name],
    'metaTitle' => 'Informasi Dokumen',
    'metaRows' => [
        'No. Invoice' => $invoice['number'],
        'Tgl Bayar' => $cashIn->date?->locale('id')->translatedFormat('d F Y') ?: '-',
        'Tgl Cetak' => $generatedAt->locale('id')->translatedFormat('d F Y'),
        'Status' => 'LUNAS / DITERIMA',
    ],
])

<table class="mt-md">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Diterima Dari</div>
                <div class="client-name">{{ $project->client_name }}</div>
                <div class="muted">
                    {{ $project->client_contact ?: 'Kontak klien belum diisi' }}<br>
                    Untuk pembayaran invoice project <strong>{{ $project->name }}</strong>
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="amount-card">
                <div class="amount-label">Jumlah Diterima</div>
                <div class="amount-value">{{ $rupiah($cashIn->amount) }}</div>
                @if (($discountAmount ?? 0) > 0)
                    <div style="margin-top: 6px; opacity: 0.9; font-size: 8.5px; line-height: 1.45;">
                        Tagihan dilunasi {{ $rupiah($settledAmount) }}<br>
                        Diskon (potongan) − {{ $rupiah($discountAmount) }}
                    </div>
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="detail-table">
    <thead>
        <tr>
            <th colspan="2">Detail Pembayaran</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Keperluan</td>
            <td>Pembayaran invoice project {{ $project->name }}</td>
        </tr>
        <tr>
            <td>Metode Bayar</td>
            <td>{{ $cashIn->paymentMethod?->name ?: '-' }}</td>
        </tr>
        @if (($discountAmount ?? 0) > 0)
            <tr>
                <td>Tagihan Dilunasi</td>
                <td>{{ $rupiah($settledAmount) }}</td>
            </tr>
            <tr>
                <td>Diskon (Potongan)</td>
                <td>− {{ $rupiah($discountAmount) }}</td>
            </tr>
        @endif
        <tr>
            <td>Keterangan</td>
            <td>{{ $cashIn->note ?: '-' }}</td>
        </tr>
        <tr>
            <td>No. Referensi</td>
            <td>{{ $cashIn->id ? 'CASHIN-'.$cashIn->id : '-' }}</td>
        </tr>
        <tr>
            <td>Sisa Invoice</td>
            <td>{{ $rupiah($invoice['remaining_amount'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<table class="mt-md">
    <tr>
        <td style="width: 58%; padding-right: 6mm;">
            <div class="card">
                <div class="section-title">Catatan</div>
                <div class="muted">
                    Kwitansi ini diterbitkan sebagai bukti penerimaan pembayaran dari klien untuk invoice <strong>{{ $invoice['number'] }}</strong>.
                </div>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="card">
                <div class="section-title">Otorisasi</div>
                <div class="badge badge-success">Pembayaran Diterima</div>
                <div class="muted">Dokumen dibuat otomatis oleh sistem.</div>
                @include('pdf.partials.signature', ['brand' => $brand, 'namePlaceholder' => 'Finance'])
            </div>
        </td>
    </tr>
</table>

@include('pdf.partials.footer-note', ['generatedAt' => $generatedAt])
</body>
</html>
