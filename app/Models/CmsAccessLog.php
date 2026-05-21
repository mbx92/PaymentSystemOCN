<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsAccessLog extends Model
{
    public const KIND_LANDING_PUBLIC = 'landing_public';

    public const KIND_CMS_ADMIN = 'cms_admin';

    public const EVENT_PAGE_VIEW = 'page_view';

    public const EVENT_CTA_CLICK = 'cta_click';

    public const EVENT_PAGE_EXIT = 'page_exit';

    protected $fillable = [
        'kind',
        'landing_site_id',
        'user_id',
        'path',
        'route_name',
        'method',
        'ip_address',
        'user_agent',
        'country_code',
        'country_name',
        'region_name',
        'city',
        'device_type',
        'browser',
        'os',
        'referrer',
        'event_name',
        'event_meta',
    ];

    protected function casts(): array
    {
        return [
            'event_meta' => 'array',
        ];
    }

    public function landingSite(): BelongsTo
    {
        return $this->belongsTo(LandingSite::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
