<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $rawPassword = env('INITIAL_ADMIN_PASSWORD');
        if ($rawPassword === null || $rawPassword === '') {
            $this->command?->warn('Initial admin skipped: set INITIAL_ADMIN_PASSWORD in the environment.');

            return;
        }

        $this->ensureRolesAndAdminPermissions();

        $email = env('INITIAL_ADMIN_EMAIL', 'admin@example.com');
        $name = env('INITIAL_ADMIN_NAME', 'Administrator');

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $rawPassword],
        );

        $admin->syncRoles(['admin']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Initial admin ready: '.$email);
    }

    private function ensureRolesAndAdminPermissions(): void
    {
        $roles = ['admin', 'manajer', 'finance', 'sales', 'purchasing', 'inventory', 'hr', 'project', 'anggota'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $permissions = [
            'erp.core.manage',
            'erp.accounting.post-journal',
            'erp.sales.manage',
            'erp.purchasing.manage',
            'erp.inventory.manage',
            'erp.hr.manage',
            'erp.project.manage',
            'erp.reporting.view',
            'erp.period.close',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        Role::findByName('admin')->syncPermissions($permissions);
    }
}
