<?php

namespace App\Services;

/**
 * Data struk POS untuk mengisi placeholder template.
 *
 * @param  array<int, array{sku: string, name: string, qty: float|int|string, unit_price: float|string, line_total: float|string, uom?: string, satuan?: string, discount_percent?: float|int|string}>  $lines
 */
final class ThermalPosReceiptData
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function __construct(
        public string $appName,
        public string $transactionNumber,
        public string $date,
        public string $time,
        public string $paymentMethod,
        public string $cashierName,
        public string $grossTotal,
        public string $discountTotal,
        public string $grandTotal,
        public string $cashPaid,
        public string $change,
        public array $lines,
    ) {}

    public static function sample(): self
    {
        return new self(
            appName: 'OCN ERP Suite',
            transactionNumber: 'POS-000123',
            date: now()->format('Y-m-d'),
            time: now()->format('H:i'),
            paymentMethod: 'Tunai',
            cashierName: 'John Doe',
            grossTotal: '125.000',
            discountTotal: '5.000',
            grandTotal: '120.000',
            cashPaid: '150.000',
            change: '30.000',
            lines: [
                ['sku' => 'SKU-001', 'name' => 'Produk contoh A', 'qty' => 2, 'unit_price' => '25.000', 'line_total' => '50.000', 'uom' => 'pcs', 'discount_percent' => 0],
                ['sku' => 'SKU-002', 'name' => 'Produk contoh B', 'qty' => 1, 'unit_price' => '75.000', 'line_total' => '70.000', 'uom' => 'box', 'discount_percent' => 6.67],
            ],
        );
    }

    public function withAppName(string $name): self
    {
        return new self(
            appName: $name,
            transactionNumber: $this->transactionNumber,
            date: $this->date,
            time: $this->time,
            paymentMethod: $this->paymentMethod,
            cashierName: $this->cashierName,
            grossTotal: $this->grossTotal,
            discountTotal: $this->discountTotal,
            grandTotal: $this->grandTotal,
            cashPaid: $this->cashPaid,
            change: $this->change,
            lines: $this->lines,
        );
    }
}
