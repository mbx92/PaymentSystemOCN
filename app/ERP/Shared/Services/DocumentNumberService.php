<?php

namespace App\ERP\Shared\Services;

use App\ERP\Core\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $module, string $documentType, array $defaults = []): string
    {
        return DB::transaction(function () use ($module, $documentType, $defaults): string {
            $sequence = DocumentSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['module' => $module, 'document_type' => $documentType],
                    [
                        'prefix' => $defaults['prefix'] ?? strtoupper(substr($documentType, 0, 3)),
                        'running_number' => 0,
                        'padding_length' => $defaults['padding_length'] ?? 6,
                    ]
                );

            $sequence->running_number = (int) $sequence->running_number + 1;
            $sequence->save();

            return sprintf(
                '%s-%s',
                $sequence->prefix,
                str_pad((string) $sequence->running_number, (int) $sequence->padding_length, '0', STR_PAD_LEFT)
            );
        });
    }
}
