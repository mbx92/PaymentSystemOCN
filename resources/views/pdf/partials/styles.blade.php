@php
    $__theme = array_merge([
        'primary' => '#1E3A5F',
        'primary_content' => '#ffffff',
        'base_100' => '#ffffff',
        'base_200' => '#f4f5f7',
        'base_300' => '#d9dde3',
        'base_content' => '#1f2937',
        'muted' => '#6b7280',
    ], config('pdf.theme', []));
@endphp
<style>
    @page {
        size: A4;
        margin: 13mm 14mm;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        width: 100%;
        font-family: "DejaVu Sans", sans-serif;
        font-size: 10.5px;
        line-height: 1.4;
        color: {{ $__theme['base_content'] }};
        background: #ffffff;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    td, th {
        vertical-align: top;
    }

    .mt-sm { margin-top: 7px; }
    .mt-md { margin-top: 11px; }
    .mt-lg { margin-top: 15px; }

    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .muted { color: {{ $__theme['muted'] }}; }
    .strong { font-weight: 700; }

    /* ── Brand / page header ─────────────────────────── */
    .page-title {
        font-size: 21px;
        font-weight: 700;
        letter-spacing: 2px;
        line-height: 1.1;
        color: {{ $__theme['primary'] }};
        margin: 0 0 1px;
    }

    .page-subtitle {
        font-size: 8.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.1px;
        line-height: 1.2;
        color: {{ $__theme['muted'] }};
        margin-bottom: 4px;
    }

    .brand-name {
        font-size: 13px;
        font-weight: 700;
        line-height: 1.15;
        color: {{ $__theme['base_content'] }};
    }

    .brand-meta {
        font-size: 9px;
        color: {{ $__theme['muted'] }};
        line-height: 1.25;
        margin-top: 2px;
    }

    .logo-box {
        width: 52px;
        height: 52px;
        border: 1px solid {{ $__theme['base_300'] }};
        background: #ffffff;
        text-align: center;
    }

    .logo-box img {
        width: 40px;
        height: 40px;
        margin-top: 5px;
    }

    .logo-fallback {
        line-height: 52px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        color: {{ $__theme['primary'] }};
    }

    /* ── Info / meta box (top-right document info) ──────── */
    .info-box {
        border-top: 2px solid {{ $__theme['primary'] }};
        padding-top: 5px;
    }

    .section-title {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.1px;
        color: {{ $__theme['primary'] }};
        padding-bottom: 4px;
        border-bottom: 1px solid {{ $__theme['base_300'] }};
        margin-bottom: 4px;
    }

    .meta-table td {
        padding: 2px 0;
        font-size: 9.5px;
    }

    .meta-table .label {
        width: 44%;
        color: {{ $__theme['muted'] }};
    }

    .meta-table .value {
        text-align: right;
        font-weight: 700;
        color: {{ $__theme['base_content'] }};
    }

    /* ── Section blocks (bill-to, notes, status) ──────────── */
    /* Flat by default: a thin top rule + heading, no filled box. */
    .card {
        border-top: 1px solid {{ $__theme['base_300'] }};
        padding-top: 6px;
    }

    .card--accent {
        border-top: 2px solid {{ $__theme['primary'] }};
    }

    .client-name {
        font-size: 12px;
        font-weight: 700;
        color: {{ $__theme['base_content'] }};
        padding-bottom: 2px;
    }

    /* ── Items / data tables ──────────────────────────── */
    .items-table {
        margin-top: 10px;
        border: 1px solid {{ $__theme['base_300'] }};
    }

    .items-table thead th {
        background: {{ $__theme['primary'] }};
        color: {{ $__theme['primary_content'] }};
        padding: 6px 7px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        text-align: left;
        border: 1px solid {{ $__theme['primary'] }};
    }

    .items-table tbody td {
        padding: 5px 7px;
        font-size: 9.5px;
        border: 1px solid {{ $__theme['base_300'] }};
    }

    .items-table tbody tr:nth-child(even) td {
        background: {{ $__theme['base_200'] }};
    }

    .items-table .desc-title {
        font-weight: 700;
        color: {{ $__theme['base_content'] }};
        padding-bottom: 1px;
    }

    .items-table .desc-sub {
        font-size: 8.5px;
        color: {{ $__theme['muted'] }};
    }

    .items-table .empty-row td {
        padding: 22px 8px;
        text-align: center;
        color: {{ $__theme['muted'] }};
    }

    /* ── Summary / totals box ─────────────────────────── */
    .summary-box {
        width: 82mm;
        margin-left: auto;
        border: 1px solid {{ $__theme['base_300'] }};
    }

    .summary-box td {
        padding: 5px 10px;
        border-bottom: 1px solid {{ $__theme['base_300'] }};
        font-size: 9.5px;
    }

    .summary-box .label {
        color: {{ $__theme['muted'] }};
    }

    .summary-box .value {
        text-align: right;
        font-weight: 700;
        color: {{ $__theme['base_content'] }};
    }

    .summary-box .summary-total td {
        background: {{ $__theme['primary'] }};
        color: {{ $__theme['primary_content'] }};
        border-bottom: 0;
        font-size: 11px;
        font-weight: 700;
    }

    /* ── Status badges ─────────────────────────────────── */
    /* Square tag, outline only — no fill, no pill radius. */
    .badge {
        display: inline-block;
        border: 1px solid {{ $__theme['primary'] }};
        color: {{ $__theme['primary'] }};
        padding: 3px 9px;
        font-size: 8.5px;
        font-weight: 700;
        letter-spacing: 0.8px;
        text-transform: uppercase;
    }

    .badge-success { border-color: #166534; color: #166534; }
    .badge-warning { border-color: #92400e; color: #92400e; }
    .badge-danger  { border-color: #991b1b; color: #991b1b; }
    .badge-info    { border-color: {{ $__theme['primary'] }}; color: {{ $__theme['primary'] }}; }

    /* ── Signature / footer ───────────────────────────── */
    .signature-block {
        margin-top: 10px;
        padding-top: 18px;
        border-top: 1px solid {{ $__theme['base_300'] }};
        font-size: 9.5px;
        color: {{ $__theme['muted'] }};
        text-align: center;
    }

    .footer-note {
        margin-top: 8px;
        font-size: 8.5px;
        color: {{ $__theme['muted'] }};
        text-align: right;
    }

    /* ── Stat row (report-style summary figures) ─────────── */
    /* Flat columns separated by thin rules, no filled cards. */
    .stat-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4px;
    }

    .stat-card {
        padding: 8px 12px 8px 0;
        border-top: 1px solid {{ $__theme['base_300'] }};
    }

    .stat-card-title {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        color: {{ $__theme['muted'] }};
    }

    .stat-card-value {
        margin-top: 4px;
        font-size: 14px;
        font-weight: 700;
        color: {{ $__theme['base_content'] }};
    }

    /* ── Document-template block wrapper ──────────────────── */
    /* Flat: a thin top rule separates each block, no filled box. */
    .doc-section {
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px solid {{ $__theme['base_300'] }};
    }

    .doc-section-title {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding-bottom: 6px;
        margin-bottom: 2px;
    }

    .doc-section .inner-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid {{ $__theme['base_300'] }};
        background: #ffffff;
    }
</style>
