@php
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite')];
@endphp
<div class="signature-block">
    {{ $label ?? 'Hormat kami,' }}<br><br>
    @if (!empty($namePlaceholder))
        {{ $namePlaceholder }}<br>
    @endif
    {{ $brand['name'] }}
</div>
