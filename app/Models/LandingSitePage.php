<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingSitePage extends Model
{
    protected $fillable = [
        'landing_site_id',
        'headline',
        'subheadline',
        'body',
        'countdown_at',
        'primary_cta_text',
        'primary_cta_url',
        'secondary_cta_text',
        'secondary_cta_url',
        'contact_text',
        'seo_title',
        'seo_description',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'landing_site_id' => 'int',
            'countdown_at' => 'datetime',
            'is_published' => 'bool',
        ];
    }

    public function landingSite(): BelongsTo
    {
        return $this->belongsTo(LandingSite::class);
    }
}
