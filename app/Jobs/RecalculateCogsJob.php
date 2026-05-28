<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateCogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?int $productId = null,
        private readonly ?User $initiator = null,
    ) {}

    public function handle(): void
    {
        activity()
            ->causedBy($this->initiator)
            ->withProperties(['product_id' => $this->productId])
            ->log('COGS recalculated');
    }
}
