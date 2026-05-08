<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpSetting extends Model
{
    protected $fillable = [
        'app_name',
        'app_tagline',
        'app_logo_path',
    ];
}

