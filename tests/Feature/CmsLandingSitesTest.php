<?php

namespace Tests\Feature;

use App\Models\LandingSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CmsLandingSitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
    }

    public function test_cms_sites_page_renders_existing_landing_sites_in_paginator_shape(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        LandingSite::query()->create([
            'name' => 'OCNetworks',
            'domain' => 'ocnetworks.web.id',
            'layout_key' => 'countdown',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('erp.cms.sites'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Admin/LandingSites')
                ->where('cmsModule', true)
                ->has('landingSites.data', 1)
                ->where('landingSites.data.0.domain', 'ocnetworks.web.id'));
    }
}
