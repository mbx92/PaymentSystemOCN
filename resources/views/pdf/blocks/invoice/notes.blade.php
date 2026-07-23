@php
    $text        = $config['text'] ?? '';
    $accentColor = $config['accent_color'] ?? ($settings['accent_color'] ?? config('pdf.theme.primary', '#1d4ed8'));
@endphp
@if ($text)
<div class="doc-section">
    <div class="doc-section-title" style="color:{{ $accentColor }};">Catatan</div>
    <div class="muted" style="padding-top:2px;">{{ $text }}</div>
</div>
@endif
