<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice Pembayaran — {{ $project->name }}</title>
    @include('pdf.partials.styles')
    <style>
        @php
            $__primary = config('pdf.theme.primary', '#1E3A5F');
            $__primaryContent = config('pdf.theme.primary_content', '#ffffff');
            $__base300 = config('pdf.theme.base_300', '#d9dde3');
            $__muted = config('pdf.theme.muted', '#6b7280');
            $__baseContent = config('pdf.theme.base_content', '#1f2937');
        @endphp

        .brand-box {
            width: 160px;
            background: {{ $__primary }};
            color: {{ $__primaryContent }};
            text-align: center;
            padding: 18px 12px;
        }
        .brand-box .logo-img { width: 56px; height: 56px; object-fit: contain; margin: 0 auto 12px; display: block; }
        .brand-box .logo-fallback {
            width: 56px; height: 56px; margin: 0 auto 12px;
            border: 3px solid rgba(255,255,255,0.55);
            line-height: 50px; font-weight: 800; font-size: 17px; color: {{ $__primaryContent }};
        }
        .brand-box .brand-name { font-size: 11px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: {{ $__primaryContent }}; }
        .brand-box .brand-tagline { margin-top: 5px; font-size: 8px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.85; }

        .hr { width: 100%; border: 0; border-top: 1.5px solid {{ $__base300 }}; margin: 16px 0; }

        .totals-inner { width: 240px; margin-left: auto; border-collapse: collapse; }
        .totals-inner td { border: 0; padding: 4px 0; font-size: 10.5px; }
        .totals-inner td:first-child { color: {{ $__muted }}; font-weight: 600; padding-right: 12px; }
        .totals-inner td:last-child  { text-align: right; font-weight: 700; }
        .totals-inner .row-grand td {
            border-top: 2px solid {{ $__baseContent }};
            padding-top: 8px; font-size: 12.5px; font-weight: 800; color: {{ $__baseContent }};
        }

        .summary-strip {
            width: 100%; margin-top: 16px;
            border-top: 1px solid {{ $__base300 }};
            border-bottom: 1px solid {{ $__base300 }};
        }
        .summary-strip td { padding: 10px 14px; width: 25%; }
        .sum-label { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: {{ $__muted }}; margin-bottom: 3px; }
        .sum-value { font-size: 13px; font-weight: 800; color: {{ $__baseContent }}; }
        .sum-value.c-green { color: #059669; }
        .sum-value.c-red   { color: #dc2626; }
        .sum-value.c-blue  { color: {{ $__primary }}; }

        .closing-line { margin-top: 20px; font-size: 10.5px; font-weight: 700; font-style: italic; color: {{ $__muted }}; line-height: 1.7; }

        .page-footer {
            position: fixed; bottom: -14mm; left: 0; right: 0; height: 12mm;
            background: {{ $__baseContent }}; padding: 0 15mm;
            font-size: 8.5px; font-weight: 700; color: #cbd5e1;
            display: table; width: 100%;
        }
        .footer-left  { display: table-cell; vertical-align: middle; }
        .footer-right { display: table-cell; vertical-align: middle; text-align: right; color: #94a3b8; }
    </style>
</head>
<body>
@php
    use Illuminate\Support\Str;

    $brand       = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => 'Integrated Business Platform', 'logo_data_uri' => null];
    $printedAt   = $generatedAt->format('d F Y');
    $payments    = $project->payments ?? collect();
    $paidCount   = $payments->filter(fn ($p) => $p->paid_at)->count();
    $totalPaid   = (float) $payments->filter(fn ($p) => $p->paid_at)->sum('amount');
    $totalDue    = (float) $payments->sum('amount');
    $remaining   = max($totalDue - $totalPaid, 0);

    $docNumber  = $project->invoice_number
        ?: ('PMNT-PRJ-'.strtoupper(substr(str_replace('-', '', (string) $project->getKey()), -8)));

    $statusLabel = match(true) {
        $remaining <= 0 => 'Lunas',
        $totalPaid > 0  => 'Sebagian',
        default         => 'Belum Dibayar',
    };
    $statusBadge = match($statusLabel) {
        'Lunas'    => 'badge-success',
        'Sebagian' => 'badge-warning',
        default    => 'badge-danger',
    };

    $termLabels  = [1 => 'Pertama', 2 => 'Kedua', 3 => 'Ketiga', 4 => 'Keempat', 5 => 'Kelima'];
    $progressPct = $totalDue > 0 ? number_format(($totalPaid / $totalDue) * 100, 0) : 0;
    $unpaidCount = $payments->count() - $paidCount;
@endphp

<table>
    <tr>
        <td>
            <div class="page-title" style="font-weight: 300;">INVOICE</div>
            <div class="page-subtitle">Tagihan Pembayaran Project</div>

            <div class="section-title" style="padding-bottom: 1px;">Nomor Dokumen</div>
            <div class="muted" style="font-weight: 700; color: #1e293b; margin-bottom: 8px;">{{ $docNumber }}</div>

            <div class="section-title" style="padding-bottom: 1px;">Tanggal Cetak</div>
            <div class="muted" style="font-weight: 700; color: #1e293b; margin-bottom: 8px;">{{ $printedAt }}</div>

            <div class="section-title" style="padding-bottom: 1px;">Status</div>
            <div><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></div>
        </td>
        <td style="width: 170px; text-align: right;">
            <div class="brand-box">
                @if(!empty($brand['logo_data_uri']))
                    <img class="logo-img" src="{{ $brand['logo_data_uri'] }}" alt="Logo">
                @else
                    <div class="logo-fallback">OCN</div>
                @endif
                <div class="brand-name">{{ $brand['name'] }}</div>
                <div class="brand-tagline">{{ $brand['tagline'] }}</div>
            </div>
        </td>
    </tr>
</table>

<hr class="hr">

<table>
    <tr>
        <td style="width: 50%; padding-right: 20px;">
            <div class="section-title">Tagihan Kepada</div>
            <table class="meta-table">
                <tr><td class="label">Klien</td><td class="value">{{ $project->client_name }}</td></tr>
                <tr><td class="label">Kontak</td><td class="value">{{ $project->client_contact ?: '-' }}</td></tr>
            </table>
        </td>
        <td style="width: 50%;">
            <div class="section-title">Detail Project</div>
            <table class="meta-table">
                <tr><td class="label">Nama</td><td class="value">{{ $project->name }}</td></tr>
                <tr><td class="label">Tipe</td><td class="value">{{ $project->projectTypeLabel() ?: '-' }}</td></tr>
                <tr><td class="label">Mulai</td><td class="value">{{ $project->started_at?->format('d F Y') ?: '-' }}</td></tr>
                <tr><td class="label">Selesai</td><td class="value">{{ $project->finished_at?->format('d F Y') ?: '-' }}</td></tr>
                <tr><td class="label">Nilai Kontrak</td><td class="value">Rp {{ number_format((float) $project->total_value, 0, ',', '.') }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="mt-md section-title">Rincian Termin Pembayaran</div>
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th>Keterangan</th>
            <th class="text-center" style="width: 9%;">%</th>
            <th class="text-right" style="width: 22%;">Jumlah</th>
            <th class="text-center" style="width: 13%;">Status</th>
            <th class="text-right" style="width: 15%;">Tgl Bayar</th>
        </tr>
    </thead>
    <tbody>
        @foreach($payments as $term)
        @php
            $isPaid   = $term->paid_at !== null;
            $tLabel   = 'Termin '.($termLabels[$term->term_number] ?? $term->term_number);
            $sBadge   = $isPaid ? 'badge-success' : 'badge-danger';
            $sText    = $isPaid ? 'Lunas' : 'Belum';
        @endphp
        <tr>
            <td class="text-center muted">{{ $term->term_number }}</td>
            <td>
                <span class="strong">{{ $tLabel }}</span>
                @if($term->note)
                    <div class="desc-sub">{{ $term->note }}</div>
                @endif
            </td>
            <td class="text-center">{{ number_format((float) $term->percentage, 0, ',', '.') }}%</td>
            <td class="text-right strong">Rp {{ number_format((float) $term->amount, 0, ',', '.') }}</td>
            <td class="text-center"><span class="badge {{ $sBadge }}">{{ $sText }}</span></td>
            <td class="text-right muted">{{ $term->paid_at ? $term->paid_at->format('d/m/Y') : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="mt-sm">
    <tr>
        <td></td>
        <td>
            <table class="totals-inner">
                <tr>
                    <td>Total Nilai Kontrak</td>
                    <td>Rp {{ number_format($totalDue, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Sudah Dibayar</td>
                    <td>Rp {{ number_format($totalPaid, 0, ',', '.') }}</td>
                </tr>
                <tr class="row-grand">
                    <td>Sisa Tagihan</td>
                    <td>Rp {{ number_format($remaining, 0, ',', '.') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="summary-strip">
    <tr>
        <td>
            <div class="sum-label">Total Termin</div>
            <div class="sum-value c-blue">{{ $payments->count() }} termin</div>
        </td>
        <td>
            <div class="sum-label">Sudah Lunas</div>
            <div class="sum-value c-green">{{ $paidCount }} termin</div>
        </td>
        <td>
            <div class="sum-label">Belum Lunas</div>
            <div class="sum-value {{ $unpaidCount > 0 ? 'c-red' : 'c-green' }}">{{ $unpaidCount }} termin</div>
        </td>
        <td>
            <div class="sum-label">Progress</div>
            <div class="sum-value {{ $remaining <= 0 ? 'c-green' : 'c-blue' }}">{{ $progressPct }}%</div>
        </td>
    </tr>
</table>

<table class="mt-lg">
    <tr>
        <td style="width: 50%; padding-right: 20px;">
            <div class="section-title">Ketentuan Pembayaran</div>
            <div class="muted">
                Invoice ini adalah dokumen resmi tagihan project.<br>
                Pembayaran dilakukan sesuai jadwal termin yang disepakati.<br>
                Hubungi kami jika ada pertanyaan terkait tagihan ini.
            </div>
        </td>
        <td style="width: 50%;">
            <div class="section-title">Catatan</div>
            <div class="muted">
                Dokumen ini digenerate secara otomatis oleh sistem pada {{ $printedAt }}.
                @if($project->description)
                    <br><em>{{ Str::limit($project->description, 120) }}</em>
                @endif
            </div>
        </td>
    </tr>
</table>

<div class="closing-line">
    TERIMA KASIH ATAS KEPERCAYAAN ANDA. KAMI BERKOMITMEN<br>
    UNTUK MENYELESAIKAN PROJECT DENGAN SEBAIK-BAIKNYA.
</div>

<div class="page-footer">
    <span class="footer-left">{{ $brand['name'] }} &nbsp;·&nbsp; {{ $brand['tagline'] }}</span>
    <span class="footer-right">Dicetak: {{ $printedAt }}</span>
</div>

</body>
</html>
