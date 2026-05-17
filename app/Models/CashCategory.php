<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashCategory extends Model
{
    public const RETIRED_KEYS = [
        'cash_in' => [
            'dana_material_client',
        ],
        'cash_out' => [
            'pemakaian_dana_material_client',
        ],
    ];

    protected $fillable = [
        'domain',
        'key',
        'label',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    public static function retiredKeysFor(string $domain): array
    {
        return self::RETIRED_KEYS[$domain] ?? [];
    }

    public static function isRetired(string $domain, string $key): bool
    {
        return in_array($key, self::retiredKeysFor($domain), true);
    }
}
