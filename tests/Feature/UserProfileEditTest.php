<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile_edit_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@nexsphere.test',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->has('user', fn ($u) => $u
                ->where('id', $user->id)
                ->where('email', 'budi@nexsphere.test')
                ->etc()
            )
        );
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@nexsphere.test',
        ]);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Budi Pratama',
            'email' => 'budi.new@nexsphere.test',
        ]);

        $response->assertRedirect('/profile');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Budi Pratama',
            'email' => 'budi.new@nexsphere.test',
        ]);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'old-password-123',
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ]);

        $response->assertRedirect('/profile');
        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password-456', $user->password));
    }
}

