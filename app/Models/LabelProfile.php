<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelProfile extends Model
{
    protected $fillable = [
        'name',
        'width_mm',
        'height_mm',
        'dpi',
        'margin_left_mm',
        'margin_top_mm',
        'gap_mm',
        'rows',
        'protocol',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'dpi' => 'integer',
            'margin_left_mm' => 'decimal:2',
            'margin_top_mm' => 'decimal:2',
            'gap_mm' => 'decimal:2',
            'rows' => 'integer',
        ];
    }

    public function erpSettings(): HasMany
    {
        return $this->hasMany(ErpSetting::class, 'label_smb_profile_id');
    }

    public static function mmToDots(float $mm, int $dpi): int
    {
        return (int) round($mm * $dpi / 25.4);
    }

    public function widthDots(): int
    {
        return self::mmToDots((float) $this->width_mm, (int) $this->dpi);
    }

    public function heightDots(): int
    {
        return self::mmToDots((float) $this->height_mm, (int) $this->dpi);
    }

    public function marginLeftDots(): int
    {
        return self::mmToDots((float) $this->margin_left_mm, (int) $this->dpi);
    }

    public function marginTopDots(): int
    {
        return self::mmToDots((float) $this->margin_top_mm, (int) $this->dpi);
    }

    public function gapDots(): int
    {
        return max(0, self::mmToDots((float) $this->gap_mm, (int) $this->dpi));
    }
}
