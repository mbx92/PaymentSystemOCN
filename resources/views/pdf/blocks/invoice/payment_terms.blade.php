@php
    $terms       = $project->payments ?? collect();
    $accentColor = $config['accent_color'] ?? ($settings['accent_color'] ?? '#1E3A5F');
@endphp
@if ($terms->isNotEmpty())
<div class="doc-section">
    <div class="doc-section-title" style="color:{{ $accentColor }};">Termin Pembayaran</div>
    <table class="inner-table">
        <thead>
            <tr>
                <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};width:12%;">Termin</th>
                <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};">Catatan</th>
                <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};width:18%;">Persentase</th>
                <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};width:20%;">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($terms as $term)
                <tr style="background:#fff;">
                    <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:center;">{{ $term->term_number }}</td>
                    <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;">{{ $term->note ?: 'Termin pembayaran' }}</td>
                    <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:center;">{{ number_format((float) $term->percentage, 0, ',', '.') }}%</td>
                    <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($term->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
