<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with initial Super Admin only if none exists.
     */
    public function run(): void
    {
        // 1. Cek apakah sudah ada akun Super Admin di sistem
        $admin = User::where('role', 'super_admin')->first();

        if (! $admin) {
            $admin = User::create([
                'name' => 'NexSphere Super Admin',
                'username' => 'admin',
                'email' => 'admin@nexsphere.web.id',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'timezone' => 'Asia/Jakarta',
                'theme' => 'light',
            ]);
        }

        // 2. Pastikan Super Admin memiliki minimal 1 ruang kerja utama
        $workspace = Workspace::where('owner_id', $admin->id)->first();
        if (! $workspace) {
            $workspace = Workspace::create([
                'name' => 'NexSphere Workspace',
                'slug' => 'nexsphere-workspace',
                'owner_id' => $admin->id,
            ]);

            $workspace->members()->syncWithoutDetaching([
                $admin->id => ['role' => 'owner', 'joined_at' => now()],
            ]);
        }
    }
}
