{{-- Standalone doc meta box (used when header does not include it inline) --}}
@php
    $showNumber  = $config['show_number'] ?? true;
    $showDate    = $config['show_date'] ?? true;
    $showStatus  = $config['show_status'] ?? true;
    $accentColor = $config['accent_color'] ?? ($settings['accent_color'] ?? '#1E3A5F');
@endphp
<div class="doc-section-title" style="color:{{ $accentColor }};">Data Dokumen</div>
<table style="width:100%;border-collapse:collapse;background:#fff;">
    @if ($showNumber)
        <tr><td style="padding:2px 0;font-size:9px;color:#6b7280;width:45%;">No. Dokumen</td><td style="text-align:right;font-size:9px;font-weight:700;">{{ $invoice['number'] ?? '-' }}</td></tr>
    @endif
    @if ($showDate)
        <tr><td style="padding:2px 0;font-size:9px;color:#6b7280;">Tanggal</td><td style="text-align:right;font-size:9px;font-weight:700;">{{ $generatedAt->locale('id')->translatedFormat('d F Y') }}</td></tr>
    @endif
    @if ($showStatus)
        <tr><td style="padding:2px 0;font-size:9px;color:#6b7280;">Status</td><td style="text-align:right;font-size:9px;font-weight:700;">{{ strtoupper($invoice['status'] ?? '') }}</td></tr>
    @endif
</table>
