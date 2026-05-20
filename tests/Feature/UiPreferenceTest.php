<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModuleWorkspaceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UiPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_ui_preferences_with_normalized_module_order(): void
    {
        $user = User::factory()->create();
        $defaultProjectOrder = ModuleWorkspaceRegistry::defaultMenuOrderFor('projects');

        $response = $this
            ->actingAs($user)
            ->patchJson(route('ui.preferences.update'), [
                'module_menu_order' => [
                    'module' => 'projects',
                    'order' => [
                        'daftar-project',
                        'unknown-key',
                        'daftar-project',
                        'overview-project',
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ui_preferences.module_menu_orders.projects.0', 'daftar-project')
            ->assertJsonPath('ui_preferences.module_menu_orders.projects.1', 'overview-project')
            ->assertJsonCount(count($defaultProjectOrder), 'ui_preferences.module_menu_orders.projects');

        $preferences = $user->fresh()->resolvedUiPreferences();

        $this->assertSame('daftar-project', $preferences['module_menu_orders']['projects'][0]);
        $this->assertCount(count($defaultProjectOrder), $preferences['module_menu_orders']['projects']);
    }

    public function test_ui_preferences_are_isolated_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->patchJson(route('ui.preferences.update'), [
            'module_menu_order' => [
                'module' => 'sales',
                'order' => ['invoice-project', 'transaksi', 'pos-produk'],
            ],
        ])->assertOk();

        $this->assertSame(
            ['invoice-project', 'transaksi', 'pos-produk'],
            $userA->fresh()->resolvedUiPreferences()['module_menu_orders']['sales'],
        );
        $this->assertArrayNotHasKey('sales', $userB->fresh()->resolvedUiPreferences()['module_menu_orders']);
    }

    public function test_shared_inertia_props_include_default_and_saved_ui_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('uiPreferences.module_menu_orders', [])
            );

        $this->actingAs($user)->patchJson(route('ui.preferences.update'), [
            'module_menu_order' => [
                'module' => 'projects',
                'order' => ['daftar-project', 'overview-project'],
            ],
        ])->assertOk();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('uiPreferences.module_menu_orders.projects.0', 'daftar-project')
            );
    }
}
