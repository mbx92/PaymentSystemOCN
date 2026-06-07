<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShelfItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slots = $this->slots()
            ->with('product')
            ->orderBy('tier', 'desc')
            ->orderBy('slot_position')
            ->get();

        $tiers = $slots->groupBy('tier')->map(function ($tierSlots, $tierNum) {
            $label = match ((int) $tierNum) {
                4 => 'Tingkat 4 (atas)',
                3 => 'Tingkat 3',
                2 => 'Tingkat 2',
                1 => 'Tingkat 1 (bawah)',
                default => "Tingkat {$tierNum}",
            };

            return [
                'tier' => (int) $tierNum,
                'label' => $label,
                'slots' => $tierSlots->map(function ($slot) use ($tierNum) {
                    return [
                        'id' => $slot->id,
                        'tier' => (int) $tierNum,
                        'slot_position' => $slot->slot_position,
                        'product_name' => $slot->product?->name ?? 'Kosong',
                        'sku' => $slot->product?->sku ?? '-',
                        'qty' => $slot->qty,
                        'min_qty' => $slot->min_qty,
                    ];
                })->values(),
            ];
        })->values();

        return [
            'shelf' => [
                'id' => $this->id,
                'code' => $this->code,
                'name' => $this->name,
            ],
            'tiers' => $tiers,
        ];
    }
}
