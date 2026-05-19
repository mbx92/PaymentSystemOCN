<?php

namespace Tests\Feature;

use App\Models\LandingSite;
use App\Models\LandingSitePage;
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

    public function test_custom_countdown_landing_prefers_per_site_countdown_at(): void
    {
        config()->set('app.ocnetworks_launch_at', '2026-06-30T09:00:00+08:00');

        $landing = LandingSite::query()->create([
            'name' => 'Sense of Jewels',
            'domain' => 'senseofjewels.com',
            'layout_key' => 'countdown',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        $landingPage = LandingSitePage::query()->create([
            'landing_site_id' => $landing->id,
            'headline' => 'Launching Soon',
            'countdown_at' => '2026-07-10 12:30:00',
            'is_published' => true,
        ]);

        $this->get('http://senseofjewels.com/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/LandingCountdown')
                ->where('landing.domain', 'senseofjewels.com')
                ->where('countdownAt', $landingPage->countdown_at->toIso8601String()));
    }
}
