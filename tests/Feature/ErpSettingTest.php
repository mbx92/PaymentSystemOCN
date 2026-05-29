<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\ErpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class ErpSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_screen_settings_and_shared_props_include_them(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('erp.admin.erp-settings.update'), [
                'app_name' => 'OCN ERP Suite',
                'app_tagline' => 'Integrated Business Platform',
                'remove_logo' => false,
                'module_menu_layout' => 'grid',
                'screen_mode' => 'ipad_9_2021',
                'screen_density' => 'compact',
            ])
            ->assertRedirect();

        $setting = ErpSetting::query()->firstOrFail();

        $this->assertSame('ipad_9_2021', $setting->screen_mode);
        $this->assertSame('compact', $setting->screen_density);

        $this->actingAs($user)
            ->get(route('erp.admin.erp-settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Admin/ErpSettings')
                ->where('setting.screen_mode', 'ipad_9_2021')
                ->where('setting.screen_density', 'compact')
                ->where('erpSetting.screen_mode', 'ipad_9_2021')
                ->where('erpSetting.screen_density', 'compact')
            );
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);
    }
}
