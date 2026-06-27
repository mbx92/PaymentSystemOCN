@php
    $label       = $config['label'] ?? 'Customer';
    $showContact = $config['show_contact'] ?? true;
    $accentColor = $config['accent_color'] ?? ($settings['accent_color'] ?? '#1E3A5F');
@endphp
<div class="doc-section-title" style="color:{{ $accentColor }};">{{ $label }}</div>
<div style="font-size:12px;font-weight:700;color:#243447;padding-bottom:2px;">{{ $project->client_name ?? '-' }}</div>
@if ($showContact)
    <div style="font-size:9px;color:#4b5563;">{{ $project->client_contact ?: '-' }}</div>
@endif
