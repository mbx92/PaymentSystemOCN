{{-- Standalone doc meta box (used when header does not include it inline) --}}
@php
    $showNumber  = $config['show_number'] ?? true;
    $showDate    = $config['show_date'] ?? true;
    $showStatus  = $config['show_status'] ?? true;
    $accentColor = $config['accent_color'] ?? ($settings['accent_color'] ?? config('pdf.theme.primary', '#1d4ed8'));
@endphp
<div class="doc-section-title" style="color:{{ $accentColor }};">Data Dokumen</div>
<table class="meta-table">
    @if ($showNumber)
        <tr><td class="label">No. Dokumen</td><td class="value">{{ $invoice['number'] ?? '-' }}</td></tr>
    @endif
    @if ($showDate)
        <tr><td class="label">Tanggal</td><td class="value">{{ $generatedAt->locale('id')->translatedFormat('d F Y') }}</td></tr>
    @endif
    @if ($showStatus)
        <tr><td class="label">Status</td><td class="value">{{ strtoupper($invoice['status'] ?? '') }}</td></tr>
    @endif
</table>
