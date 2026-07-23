<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>R&D Report - {{ $project->name }}</title>
    @include('pdf.partials.styles')
</head>
<body>
@php
    $rupiah = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
@endphp

<div class="mt-sm">
    <div class="page-subtitle" style="margin-bottom: 4px;">R&amp;D Project Report</div>
    <div class="page-title" style="font-size: 22px;">{{ $project->name }}</div>
    <p class="muted" style="margin-top: 6px;">Kategori: {{ $project->category }} | Status: {{ $project->status }} | PIC: {{ $project->picUser?->name ?? '-' }}</p>
    <p class="muted" style="margin-top: 2px;">Tanggal mulai: {{ $project->start_date?->format('d M Y') ?? '-' }} | Dicetak: {{ $generatedAt->format('d M Y H:i') }}</p>
</div>

<table class="stat-grid mt-md">
    <tr>
        <td class="stat-card" style="width: 33.33%;">
            <div class="stat-card-title">Estimated Budget</div>
            <div class="stat-card-value">{{ $rupiah($summary['estimated_budget_total']) }}</div>
        </td>
        <td class="stat-card" style="width: 33.33%;">
            <div class="stat-card-title">Actual Spend</div>
            <div class="stat-card-value">{{ $rupiah($summary['actual_spend_total']) }}</div>
        </td>
        <td class="stat-card" style="width: 33.33%;">
            <div class="stat-card-title">Variance</div>
            <div class="stat-card-value">{{ $rupiah($summary['variance']) }}</div>
        </td>
    </tr>
    <tr>
        <td class="stat-card">
            <div class="stat-card-title">Alat</div>
            <div class="stat-card-value">{{ $rupiah($summary['alat_total']) }}</div>
        </td>
        <td class="stat-card">
            <div class="stat-card-title">Bahan</div>
            <div class="stat-card-value">{{ $rupiah($summary['bahan_total']) }}</div>
        </td>
        <td class="stat-card">
            <div class="stat-card-title">HPP / Unit</div>
            <div class="stat-card-value">{{ $rupiah($summary['hpp_per_unit']) }}</div>
        </td>
    </tr>
</table>

<div class="mt-lg">
    <div class="section-title">Budget Planning</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga Est.</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($project->budgetItems as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->qty, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">{{ number_format((float) $item->estimated_unit_price, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->total_price, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr class="empty-row"><td colspan="4">Belum ada item budget.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-lg">
    <div class="section-title">Product Outputs</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Nama</th>
                <th class="text-center">Units</th>
                <th class="text-right">HPP / Unit</th>
                <th class="text-right">Allocated Cost</th>
            </tr>
        </thead>
        <tbody>
            @forelse($project->productOutputs as $output)
                <tr>
                    <td>{{ $output->name }}</td>
                    <td class="text-center">{{ rtrim(rtrim(number_format((float) $output->units_produced, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">{{ number_format((float) $summary['hpp_per_unit'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $summary['hpp_per_unit'] * (float) $output->units_produced, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr class="empty-row"><td colspan="4">Belum ada output produk.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-lg">
    <div class="section-title">Purchases</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Item</th>
                <th>Supplier</th>
                <th>Kategori</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($project->purchases as $purchase)
                <tr>
                    <td>{{ $purchase->purchase_date?->format('d M Y') }}</td>
                    <td>{{ $purchase->product?->name ?? '-' }}</td>
                    <td>{{ $purchase->supplier?->name ?? '-' }}</td>
                    <td>{{ ucfirst($purchase->category) }}</td>
                    <td class="text-center">{{ rtrim(rtrim(number_format((float) $purchase->qty, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">{{ number_format((float) $purchase->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $purchase->total_price, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr class="empty-row"><td colspan="7">Belum ada pembelian.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
