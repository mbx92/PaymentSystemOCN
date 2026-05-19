<?php

namespace Tests\Feature;

use App\Models\LandingSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandingSiteAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
    }

    public function test_admin_store_landing_site_normalizes_domain_before_saving(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('erp.admin.landing-sites.store'), [
                'name' => 'Sense Of Jewels',
                'domain' => ' HTTPS://SenseOfJewels.com/path ',
                'layout_key' => 'countdown',
                'warehouse_id' => null,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('landing_sites', [
            'name' => 'Sense Of Jewels',
            'domain' => 'senseofjewels.com',
        ]);
    }

    public function test_admin_store_landing_site_rejects_duplicate_normalized_domain(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        LandingSite::query()->create([
            'name' => 'Existing',
            'domain' => 'ocnetworks.web.id',
            'layout_key' => 'countdown',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('erp.admin.landing-sites'))
            ->post(route('erp.admin.landing-sites.store'), [
                'name' => 'Duplicate',
                'domain' => 'https://OCNetworks.Web.Id/',
                'layout_key' => 'countdown',
                'warehouse_id' => null,
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('erp.admin.landing-sites'))
            ->assertSessionHasErrors('domain');

        $this->assertEquals(1, LandingSite::query()->where('domain', 'ocnetworks.web.id')->count());
    }

    public function test_admin_can_check_landing_site_domain_from_frontend_utility(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $landingSite = LandingSite::query()->create([
            'name' => 'Existing',
            'domain' => 'ocnetworks.web.id',
            'layout_key' => 'countdown',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('erp.admin.landing-sites.domain-check', [
                'domain' => ' https://OCNETWORKS.WEB.ID/path ',
            ]))
            ->assertOk()
            ->assertJson([
                'normalized_domain' => 'ocnetworks.web.id',
                'exists' => true,
                'landing_site' => [
                    'id' => $landingSite->id,
                    'name' => 'Existing',
                    'domain' => 'ocnetworks.web.id',
                    'layout_key' => 'countdown',
                    'warehouse_id' => null,
                    'is_active' => true,
                ],
            ]);
    }
}
