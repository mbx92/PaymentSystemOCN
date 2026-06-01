<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingSitePageVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'landing_site_id',
        'template_id',
        'theme_id',
        'status',
        'version_no',
        'created_by',
        'published_by',
        'published_at',
        'theme_overrides',
        'document',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'landing_site_id' => 'int',
            'template_id' => 'int',
            'theme_id' => 'int',
            'version_no' => 'int',
            'created_by' => 'int',
            'published_by' => 'int',
            'published_at' => 'datetime',
            'theme_overrides' => 'array',
            'document' => 'array',
            'meta' => 'array',
        ];
    }

    public function landingSite(): BelongsTo
    {
        return $this->belongsTo(LandingSite::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LandingSiteTemplate::class, 'template_id');
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(LandingSiteTheme::class, 'theme_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
