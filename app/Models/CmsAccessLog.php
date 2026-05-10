<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsAccessLog extends Model
{
    public const KIND_LANDING_PUBLIC = 'landing_public';

    public const KIND_CMS_ADMIN = 'cms_admin';

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
    ];

    public function landingSite(): BelongsTo
    {
        return $this->belongsTo(LandingSite::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
