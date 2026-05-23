<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice Pembayaran — {{ $project->name }}</title>
    <style>
        /* ── Page setup ─────────────────────────────── */
        @page {
            margin-top: 20mm;
            margin-bottom: 22mm;
            margin-left: 18mm;
            margin-right: 18mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.5;
        }

        /* ── Header ─────────────────────────────────── */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .header-table td { border: 0; padding: 0; vertical-align: top; }

        .doc-title {
            font-size: 36px;
            font-weight: 300;
            letter-spacing: 0.14em;
            color: #1d4ed8;
            line-height: 1;
            margin-bottom: 6px;
        }
        .doc-subtitle {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 16px;
        }
        .meta-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.10em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 2px;
        }
        .meta-value {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }

        /* ── Brand box ───────────────────────────────── */
        .brand-box {
            width: 160px;
            background: #1d4ed8;
            color: #ffffff;
            text-align: center;
            padding: 18px 12px;
            border-radius: 2px;
        }
        .logo-img { width: 60px; height: 60px; object-fit: contain; margin: 0 auto 12px; display: block; }
        .logo-fallback {
            width: 60px; height: 60px;
            margin: 0 auto 12px;
            border: 4px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            line-height: 52px;
            font-weight: 800;
            font-size: 18px;
        }
        .brand-name { font-size: 11px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; }
        .brand-tagline { margin-top: 5px; font-size: 8px; font-weight: 600; letter-spacing: 0.10em; text-transform: uppercase; color: #bfdbfe; }

        /* ── Badge ───────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-pending { background: #dbeafe; color: #1e40af; }

        /* ── Divider ─────────────────────────────────── */
        .hr { width: 100%; border: 0; border-top: 1.5px solid #e2e8f0; margin: 18px 0; }

        /* ── Two-col info section ────────────────────── */
        .info-cols { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-cols td { width: 50%; border: 0; padding: 0; vertical-align: top; }
        .info-cols td:first-child { padding-right: 20px; }

        .section-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #94a3b8;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .kv-table { border-collapse: collapse; width: 100%; }
        .kv-table td { border: 0; padding: 2px 0; vertical-align: top; font-size: 11px; }
        .kv-table td:first-child { width: 90px; color: #64748b; padding-right: 6px; }
        .kv-table td:last-child { font-weight: 600; color: #1e293b; }

        /* ── Termin table ────────────────────────────── */
        .table-heading {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.10em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 8px;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead tr { background: #f8fafc; }
        .data-table th {
            padding: 7px 8px;
            border-top: 1.5px solid #cbd5e1;
            border-bottom: 1.5px solid #cbd5e1;
            text-align: left;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }
        .data-table td {
            padding: 9px 8px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 11px;
            vertical-align: top;
        }
        .data-table tbody tr:last-child td { border-bottom: 1.5px solid #cbd5e1; }
        .data-table .note-cell { font-size: 10px; color: #94a3b8; margin-top: 2px; }

        .text-right  { text-align: right  !important; }
        .text-center { text-align: center !important; }
        .font-bold   { font-weight: 700; }
        .text-muted  { color: #94a3b8; }

        /* ── Totals ──────────────────────────────────── */
        .totals-outer { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .totals-outer td { border: 0; padding: 0; vertical-align: top; }
        .totals-inner { width: 240px; margin-left: auto; border-collapse: collapse; }
        .totals-inner td { border: 0; padding: 4px 0; font-size: 11px; }
        .totals-inner td:first-child { color: #64748b; font-weight: 600; padding-right: 12px; }
        .totals-inner td:last-child  { text-align: right; font-weight: 700; }
        .totals-inner .row-grand td  {
            border-top: 2px solid #1e293b;
            padding-top: 8px;
            font-size: 13px;
            font-weight: 800;
            color: #1e293b;
        }

        /* ── Summary strip ───────────────────────────── */
        .summary-strip {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #1d4ed8;
            border-radius: 3px;
        }
        .summary-strip td {
            border: 0;
            padding: 12px 16px;
            vertical-align: top;
            width: 25%;
        }
        .sum-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin-bottom: 3px; }
        .sum-value { font-size: 14px; font-weight: 800; color: #1e293b; }
        .sum-value.c-green { color: #059669; }
        .sum-value.c-red   { color: #dc2626; }
        .sum-value.c-blue  { color: #1d4ed8; }

        /* ── Footer notes ────────────────────────────── */
        .notes-cols { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .notes-cols td { border: 0; padding: 0; vertical-align: top; width: 50%; font-size: 10.5px; color: #64748b; line-height: 1.6; }
        .notes-cols td:first-child { padding-right: 20px; }
        .note-heading { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.10em; color: #1d4ed8; margin-bottom: 4px; }

        .closing-line {
            margin-top: 22px;
            font-size: 11px;
            font-weight: 700;
            font-style: italic;
            letter-spacing: 0.03em;
            color: #475569;
            line-height: 1.7;
        }

        /* ── Fixed footer ────────────────────────────── */
        .page-footer {
            position: fixed;
            bottom: -16mm;
            left: 0; right: 0;
            height: 14mm;
            background: #1e293b;
            padding: 0 18mm;
            font-size: 9px;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.06em;
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        .footer-left  { display: table-cell; vertical-align: middle; }
        .footer-right { display: table-cell; vertical-align: middle; text-align: right; color: #475569; }
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
        default    => 'badge-pending',
    };

    $termLabels  = [1 => 'Pertama', 2 => 'Kedua', 3 => 'Ketiga', 4 => 'Keempat', 5 => 'Kelima'];
    $progressPct = $totalDue > 0 ? number_format(($totalPaid / $totalDue) * 100, 0) : 0;
    $unpaidCount = $payments->count() - $paidCount;
@endphp

{{-- ═══ HEADER ═══ --}}
<table class="header-table">
    <tr>
        <td>
            <div class="doc-title">INVOICE</div>
            <div class="doc-subtitle">Tagihan Pembayaran Project</div>

            <div class="meta-label">Nomor Dokumen</div>
            <div class="meta-value">{{ $docNumber }}</div>

            <div class="meta-label">Tanggal Cetak</div>
            <div class="meta-value">{{ $printedAt }}</div>

            <div class="meta-label">Status</div>
            <div style="margin-top: 3px;"><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></div>
        </td>
        <td style="width: 170px; text-align: right;">
            <div class="brand-box">
                @if($brand['logo_data_uri'])
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

{{-- ═══ INFO KLIEN & PROJECT ═══ --}}
<table class="info-cols">
    <tr>
        <td>
            <div class="section-label">Tagihan Kepada</div>
            <table class="kv-table">
                <tr><td>Klien</td><td>{{ $project->client_name }}</td></tr>
                <tr><td>Kontak</td><td>{{ $project->client_contact ?: '-' }}</td></tr>
            </table>
        </td>
        <td>
            <div class="section-label">Detail Project</div>
            <table class="kv-table">
                <tr><td>Nama</td><td>{{ $project->name }}</td></tr>
                <tr><td>Tipe</td><td>{{ $project->projectTypeLabel() ?: '-' }}</td></tr>
                <tr><td>Mulai</td><td>{{ $project->started_at?->format('d F Y') ?: '-' }}</td></tr>
                <tr><td>Selesai</td><td>{{ $project->finished_at?->format('d F Y') ?: '-' }}</td></tr>
                <tr><td>Nilai Kontrak</td><td>Rp {{ number_format((float) $project->total_value, 0, ',', '.') }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- ═══ TABEL TERMIN ═══ --}}
<div class="table-heading">Rincian Termin Pembayaran</div>
<table class="data-table">
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
            $sBadge   = $isPaid ? 'badge-success' : 'badge-pending';
            $sText    = $isPaid ? 'Lunas' : 'Belum';
        @endphp
        <tr>
            <td class="text-center text-muted">{{ $term->term_number }}</td>
            <td>
                <span class="font-bold">{{ $tLabel }}</span>
                @if($term->note)
                    <div class="note-cell">{{ $term->note }}</div>
                @endif
            </td>
            <td class="text-center">{{ number_format((float) $term->percentage, 0, ',', '.') }}%</td>
            <td class="text-right font-bold">Rp {{ number_format((float) $term->amount, 0, ',', '.') }}</td>
            <td class="text-center"><span class="badge {{ $sBadge }}">{{ $sText }}</span></td>
            <td class="text-right text-muted">{{ $term->paid_at ? $term->paid_at->format('d/m/Y') : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ═══ TOTALS ═══ --}}
<table class="totals-outer">
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

{{-- ═══ SUMMARY STRIP ═══ --}}
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

{{-- ═══ NOTES ═══ --}}
<table class="notes-cols">
    <tr>
        <td>
            <div class="note-heading">Ketentuan Pembayaran</div>
            Invoice ini adalah dokumen resmi tagihan project.<br>
            Pembayaran dilakukan sesuai jadwal termin yang disepakati.<br>
            Hubungi kami jika ada pertanyaan terkait tagihan ini.
        </td>
        <td>
            <div class="note-heading">Catatan</div>
            Dokumen ini digenerate secara otomatis oleh sistem pada {{ $printedAt }}.
            @if($project->description)
                <br><em>{{ Str::limit($project->description, 120) }}</em>
            @endif
        </td>
    </tr>
</table>

<div class="closing-line">
    TERIMA KASIH ATAS KEPERCAYAAN ANDA. KAMI BERKOMITMEN<br>
    UNTUK MENYELESAIKAN PROJECT DENGAN SEBAIK-BAIKNYA.
</div>

{{-- ═══ PAGE FOOTER ═══ --}}
<div class="page-footer">
    <span class="footer-left">{{ $brand['name'] }} &nbsp;·&nbsp; {{ $brand['tagline'] }}</span>
    <span class="footer-right">Dicetak: {{ $printedAt }}</span>
</div>

</body>
</html>
