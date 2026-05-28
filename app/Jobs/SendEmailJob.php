<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $recipient,
        private readonly string $subject,
        private readonly string $body,
        private readonly ?User $sender = null,
    ) {}

    public function handle(): void
    {
        activity()
            ->withProperties(['recipient' => $this->recipient, 'subject' => $this->subject])
            ->log('Email queued: '.$this->subject);
    }
}
