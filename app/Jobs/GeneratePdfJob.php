<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $view,
        private readonly array $data,
        private readonly User $user,
        private readonly string $filename,
    ) {}

    public function handle(): void
    {
        activity()
            ->performedOn($this->user)
            ->causedBy($this->user)
            ->withProperties(['filename' => $this->filename])
            ->log('PDF generated: '.$this->filename);
    }
}
