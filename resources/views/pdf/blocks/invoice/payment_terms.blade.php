@php
    $terms       = $project->payments ?? collect();
    $accentColor = $config['accent_color'] ?? ($settings['accent_color'] ?? config('pdf.theme.primary', '#1d4ed8'));
@endphp
@if ($terms->isNotEmpty())
<div class="doc-section">
    <div class="doc-section-title" style="color:{{ $accentColor }};">Termin Pembayaran</div>
    <table class="inner-table items-table" style="margin-top: 0;">
        <thead>
            <tr>
                <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};width:12%;">Termin</th>
                <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};">Catatan</th>
                <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};width:18%;">Persentase</th>
                <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};width:20%;">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($terms as $term)
                <tr>
                    <td class="text-center">{{ $term->term_number }}</td>
                    <td>{{ $term->note ?: 'Termin pembayaran' }}</td>
                    <td class="text-center">{{ number_format((float) $term->percentage, 0, ',', '.') }}%</td>
                    <td class="text-right">{{ $rupiah($term->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
