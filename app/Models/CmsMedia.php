<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CmsMedia extends Model
{
    protected $table = 'cms_media';

    protected $fillable = [
        'user_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'alt_text',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'size_bytes' => 'int',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Same-origin path for public disk so thumbnails work when APP_URL host
     * differs from the browser URL (localhost vs 127.0.0.1, Vite dev, etc.).
     */
    public function publicUrl(): string
    {
        if ($this->disk === 'public') {
            $path = str_replace('\\', '/', (string) $this->path);

            return '/storage/'.ltrim($path, '/');
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * URL for the CMS media library UI: streams from storage through Laravel so
     * thumbnails work even when the public/storage symlink is missing (404 on /storage/...).
     */
    public function adminLibraryUrl(): string
    {
        if ($this->disk === 'public') {
            return route('erp.cms.media.file', ['cmsMedia' => $this], false);
        }

        return $this->publicUrl();
    }
}
