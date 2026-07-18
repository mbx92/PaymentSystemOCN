<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShelfResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasEmpty = false;
        $hasLow = false;

        if ($this->relationLoaded('slots')) {
            foreach ($this->slots as $slot) {
                if ($slot->qty === 0) {
                    $hasEmpty = true;
                } elseif ($slot->qty < $slot->min_qty) {
                    $hasLow = true;
                }
            }
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'row' => $this->row_position,
            'col' => $this->col_position,
            '_aggregated' => [
                'hasEmpty' => $hasEmpty,
                'hasLow' => $hasLow,
            ],
        ];
    }
}
