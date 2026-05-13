<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
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

        Role::findByName('admin')->givePermissionTo($permissions);
        Role::findByName('manajer')->givePermissionTo([
            'erp.reporting.view',
            'erp.project.manage',
            'erp.sales.manage',
        ]);
        Role::findByName('finance')->givePermissionTo([
            'erp.accounting.post-journal',
            'erp.reporting.view',
            'erp.period.close',
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@ocn.test'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );
        $admin->assignRole('admin');

        $manajer = User::query()->firstOrCreate(
            ['email' => 'manajer@ocn.test'],
            ['name' => 'Manajer', 'password' => bcrypt('password')]
        );
        $manajer->assignRole('manajer');

        $anggota = User::query()->firstOrCreate(
            ['email' => 'budi@ocn.test'],
            ['name' => 'Budi Developer', 'password' => bcrypt('password')]
        );
        $anggota->assignRole('anggota');
    }
}
