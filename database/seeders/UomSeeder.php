<?php

namespace Database\Seeders;

use App\Models\Uom;
use App\Models\UomConversion;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    public function run(): void
    {
        $uoms = [
            ['code' => 'pcs', 'name' => 'Pieces'],
            ['code' => 'pack', 'name' => 'Pack'],
            ['code' => 'dus', 'name' => 'Dus/Karton'],
            ['code' => 'roll', 'name' => 'Roll'],
            ['code' => 'box', 'name' => 'Box'],
            ['code' => 'lusin', 'name' => 'Lusin/Dozen'],
            ['code' => 'rim', 'name' => 'Rim'],
            ['code' => 'kg', 'name' => 'Kilogram'],
            ['code' => 'gram', 'name' => 'Gram'],
            ['code' => 'meter', 'name' => 'Meter'],
            ['code' => 'set', 'name' => 'Set'],
            ['code' => 'unit', 'name' => 'Unit'],
            ['code' => 'liter', 'name' => 'Liter'],
            ['code' => 'ml', 'name' => 'Mililiter'],
            ['code' => 'lembar', 'name' => 'Lembar/Sheet'],
            ['code' => 'batang', 'name' => 'Batang'],
            ['code' => 'pair', 'name' => 'Pasang'],
            ['code' => 'bundle', 'name' => 'Bundle/Ikat'],
        ];

        foreach ($uoms as $uom) {
            Uom::query()->firstOrCreate(
                ['code' => $uom['code']],
                array_merge($uom, ['status' => 'active'])
            );
        }

        $conversions = [
            ['from' => 'pack', 'to' => 'pcs', 'multiplier' => 100],
            ['from' => 'dus', 'to' => 'pack', 'multiplier' => 10],
            ['from' => 'lusin', 'to' => 'pcs', 'multiplier' => 12],
            ['from' => 'rim', 'to' => 'lembar', 'multiplier' => 500],
            ['from' => 'kg', 'to' => 'gram', 'multiplier' => 1000],
            ['from' => 'meter', 'to' => 'pcs', 'multiplier' => 1],
            ['from' => 'liter', 'to' => 'ml', 'multiplier' => 1000],
        ];

        foreach ($conversions as $conv) {
            $from = Uom::query()->where('code', $conv['from'])->first();
            $to = Uom::query()->where('code', $conv['to'])->first();

            if ($from && $to) {
                UomConversion::query()->updateOrCreate(
                    ['from_uom_id' => $from->id, 'to_uom_id' => $to->id],
                    ['multiplier' => $conv['multiplier']]
                );
            }
        }
    }
}
