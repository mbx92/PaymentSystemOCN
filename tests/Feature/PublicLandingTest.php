<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocnetworks_host_renders_countdown_landing(): void
    {
        config()->set('app.ocnetworks_launch_at', '2026-06-30T09:00:00+08:00');

        $this->get('http://ocnetworks.web.id/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/LandingCountdown')
                ->where('landing.domain', 'ocnetworks.web.id')
                ->where('countdownAt', '2026-06-30T09:00:00+08:00'));
    }
}
