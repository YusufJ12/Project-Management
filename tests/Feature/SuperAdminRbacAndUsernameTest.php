<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminRbacAndUsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_using_username(): void
    {
        $user = User::factory()->create([
            'username' => 'superuser99',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'login' => 'superuser99',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    public function test_user_can_login_using_email_with_login_field(): void
    {
        $user = User::factory()->create([
            'email' => 'login_user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'login' => 'login_user@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    public function test_super_admin_role_cannot_be_changed_via_update_role(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'username' => 'superboss',
        ]);

        $response = $this->actingAs($superAdmin)
            ->put("/users/{$superAdmin->id}/role", [
                'role' => 'member',
            ]);

        $response->assertSessionHas('error', 'Role Super Admin bersifat permanen dan tidak dapat diubah.');
        $this->assertEquals('super_admin', $superAdmin->fresh()->role);
    }

    public function test_super_admin_can_update_name_email_username_password_without_changing_super_admin_role(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'username' => 'old_user',
            'role' => 'super_admin',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->actingAs($superAdmin)
            ->put("/users/{$superAdmin->id}", [
                'name' => 'New Super Name',
                'email' => 'newsuper@example.com',
                'username' => 'new_super_user',
                'password' => 'newpassword123',
                'role' => 'member', // Mencoba mengoper role member
            ]);

        $response->assertSessionHas('success');
        $fresh = $superAdmin->fresh();
        $this->assertEquals('New Super Name', $fresh->name);
        $this->assertEquals('newsuper@example.com', $fresh->email);
        $this->assertEquals('new_super_user', $fresh->username);
        $this->assertEquals('super_admin', $fresh->role); // Role tetap super_admin
    }

    public function test_super_admin_cannot_be_deleted(): void
    {
        $superAdmin1 = User::factory()->create(['role' => 'super_admin']);
        $superAdmin2 = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin1)
            ->delete("/users/{$superAdmin2->id}");

        $response->assertSessionHas('error', 'Akun dengan role Super Admin bersifat permanen dan tidak dapat dihapus.');
        $this->assertDatabaseHas('users', ['id' => $superAdmin2->id]);
    }

    public function test_user_can_update_their_own_username_in_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'username' => 'budi_awal',
        ]);

        $response = $this->actingAs($user)
            ->put('/profile', [
                'name' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'username' => 'budi_baru',
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals('budi_baru', $user->fresh()->username);
    }
}

