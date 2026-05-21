<?php

namespace Tests\Feature;

use App\Models\CmsAccessLog;
use App\Models\LandingSite;
use App\Models\LandingSitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->assertSame(0, CmsAccessLog::query()->count());
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

        $this->assertDatabaseHas('cms_access_logs', [
            'kind' => CmsAccessLog::KIND_LANDING_PUBLIC,
            'landing_site_id' => $landing->id,
            'event_name' => CmsAccessLog::EVENT_PAGE_VIEW,
            'path' => '/',
        ]);
    }

    public function test_landing_track_endpoint_records_cta_click_and_page_exit_events(): void
    {
        $landing = LandingSite::query()->create([
            'name' => 'Sense of Jewels',
            'domain' => 'senseofjewels.com',
            'layout_key' => 'coming_soon',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        LandingSitePage::query()->create([
            'landing_site_id' => $landing->id,
            'headline' => 'Launching Soon',
            'primary_cta_text' => 'Chat Admin',
            'primary_cta_url' => 'https://wa.me/628123',
            'is_published' => true,
        ]);

        $this->postJson('http://senseofjewels.com/landing/track', [
            'event_name' => 'cta_click',
            'event_meta' => [
                'cta_kind' => 'primary',
                'cta_text' => 'Chat Admin',
                'cta_url' => 'https://wa.me/628123',
            ],
        ])->assertOk();

        $this->postJson('http://senseofjewels.com/landing/track', [
            'event_name' => 'page_exit',
            'event_meta' => [
                'active_ms' => 12000,
                'visible_ms' => 11000,
                'max_scroll_percent' => 78.5,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('cms_access_logs', [
            'kind' => CmsAccessLog::KIND_LANDING_PUBLIC,
            'landing_site_id' => $landing->id,
            'event_name' => CmsAccessLog::EVENT_CTA_CLICK,
        ]);

        $this->assertDatabaseHas('cms_access_logs', [
            'kind' => CmsAccessLog::KIND_LANDING_PUBLIC,
            'landing_site_id' => $landing->id,
            'event_name' => CmsAccessLog::EVENT_PAGE_EXIT,
        ]);
    }

    public function test_public_landing_is_rate_limited_per_host_and_ip(): void
    {
        RateLimiter::clear('senseofjewels.com|127.0.0.1');

        $landing = LandingSite::query()->create([
            'name' => 'Sense of Jewels',
            'domain' => 'senseofjewels.com',
            'layout_key' => 'coming_soon',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        LandingSitePage::query()->create([
            'landing_site_id' => $landing->id,
            'headline' => 'Launching Soon',
            'is_published' => true,
        ]);

        for ($i = 0; $i < 60; $i++) {
            $this->get('http://senseofjewels.com/')->assertOk();
        }

        $this->get('http://senseofjewels.com/')->assertStatus(429);
    }

    public function test_landing_track_is_rate_limited_per_host_and_ip(): void
    {
        RateLimiter::clear('senseofjewels.com|127.0.0.1');

        $landing = LandingSite::query()->create([
            'name' => 'Sense of Jewels',
            'domain' => 'senseofjewels.com',
            'layout_key' => 'coming_soon',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        LandingSitePage::query()->create([
            'landing_site_id' => $landing->id,
            'headline' => 'Launching Soon',
            'is_published' => true,
        ]);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('http://senseofjewels.com/landing/track', [
                'event_name' => 'cta_click',
                'event_meta' => [
                    'cta_kind' => 'primary',
                    'cta_text' => 'Chat Admin',
                    'cta_url' => 'https://wa.me/628123',
                ],
            ])->assertOk();
        }

        $this->postJson('http://senseofjewels.com/landing/track', [
            'event_name' => 'cta_click',
            'event_meta' => [
                'cta_kind' => 'primary',
                'cta_text' => 'Chat Admin',
                'cta_url' => 'https://wa.me/628123',
            ],
        ])->assertStatus(429);
    }
}
