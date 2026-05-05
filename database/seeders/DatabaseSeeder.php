<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = ['admin', 'manajer', 'anggota'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Create admin user
        $admin = User::factory()->create([
            'name'     => 'Admin',
            'email'    => 'admin@ocn.test',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        // Create manajer user
        $manajer = User::factory()->create([
            'name'     => 'Manajer',
            'email'    => 'manajer@ocn.test',
            'password' => bcrypt('password'),
        ]);
        $manajer->assignRole('manajer');

        // Create anggota user
        $anggota = User::factory()->create([
            'name'     => 'Budi Developer',
            'email'    => 'budi@ocn.test',
            'password' => bcrypt('password'),
        ]);
        $anggota->assignRole('anggota');
    }
}
