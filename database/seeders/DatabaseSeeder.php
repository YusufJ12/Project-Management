<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with initial Super Admin only.
     */
    public function run(): void
    {
        // 1. Seed Super Admin Tunggal
        $admin = User::updateOrCreate(
            ['email' => 'admin@nexsphere.web.id'],
            [
                'name' => 'NexSphere Super Admin',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'timezone' => 'Asia/Jakarta',
                'theme' => 'light',
            ]
        );

        // 2. Buat Ruang Kerja Utama Bersih untuk Super Admin
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'nexsphere-workspace'],
            [
                'name' => 'NexSphere Workspace',
                'owner_id' => $admin->id,
            ]
        );

        // Attach Workspace Members (Hanya Super Admin)
        $workspace->members()->sync([
            $admin->id => ['role' => 'owner', 'joined_at' => now()],
        ]);
    }
}
