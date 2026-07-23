@php
    $label       = $config['label'] ?? 'Customer';
    $showContact = $config['show_contact'] ?? true;
    $accentColor = $config['accent_color'] ?? ($settings['accent_color'] ?? config('pdf.theme.primary', '#1d4ed8'));
@endphp
<div class="doc-section-title" style="color:{{ $accentColor }};">{{ $label }}</div>
<div class="client-name">{{ $project->client_name ?? '-' }}</div>
@if ($showContact)
    <div class="muted">{{ $project->client_contact ?: '-' }}</div>
@endif
