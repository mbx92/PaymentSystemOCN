@php
    $text        = $config['text'] ?? '';
    $accentColor = $config['accent_color'] ?? ($settings['accent_color'] ?? '#1E3A5F');
@endphp
@if ($text)
<div class="doc-section">
    <div class="doc-section-title" style="color:{{ $accentColor }};">Catatan</div>
    <div style="font-size:9px;color:#4b5563;background:#fff;padding:6px 8px;border:1px solid #e5e7eb;">{{ $text }}</div>
</div>
@endif
