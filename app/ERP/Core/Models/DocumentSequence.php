<?php

namespace App\ERP\Core\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'module',
        'document_type',
        'prefix',
        'running_number',
        'padding_length',
    ];

    protected function casts(): array
    {
        return [
            'running_number' => 'int',
            'padding_length' => 'int',
        ];
    }
}
