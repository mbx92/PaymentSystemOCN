<?php

namespace App\Observers;

use App\Models\CmsMedia;
use Illuminate\Support\Facades\Storage;

class CmsMediaObserver
{
    public function deleted(CmsMedia $cmsMedia): void
    {
        if ($cmsMedia->disk && $cmsMedia->path) {
            Storage::disk($cmsMedia->disk)->delete($cmsMedia->path);
        }
    }
}
