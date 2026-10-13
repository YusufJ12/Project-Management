<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeWorkspaceSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_workspaces_index(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Tech Corp', 'slug' => 'tech-corp', 'owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->get('/workspaces');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Workspaces/Index')
            ->has('workspaces', 1)
        );
    }

    public function test_user_can_create_a_workspace(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/workspaces', [
            'name' => 'Agency Studio',
        ]);

        $this->assertDatabaseHas('workspaces', ['name' => 'Agency Studio', 'owner_id' => $user->id]);
        $workspace = Workspace::where('name', 'Agency Studio')->first();
        $this->assertTrue($workspace->members->contains($user));
        $response->assertRedirect('/workspaces');
    }

    public function test_user_can_switch_active_workspace(): void
    {
        $user = User::factory()->create();
        $ws1 = Workspace::create(['name' => 'WS 1', 'slug' => 'ws-1', 'owner_id' => $user->id]);
        $ws2 = Workspace::create(['name' => 'WS 2', 'slug' => 'ws-2', 'owner_id' => $user->id]);
        $ws1->members()->attach($user->id, ['role' => 'owner']);
        $ws2->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->post("/workspaces/{$ws2->id}/switch");

        $response->assertRedirect('/dashboard');
        $this->assertEquals($ws2->id, session('active_workspace_id'));
    }

    public function test_user_can_view_spaces_index_for_active_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $space = Space::create([
            'workspace_id' => $workspace->id,
            'name' => 'Core Product',
            'slug' => 'core-product',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get('/spaces');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Spaces/Index')
            ->has('spaces', 1)
        );
    }

    public function test_user_can_create_a_space_with_default_list_and_stages(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->post('/spaces', [
                'name' => 'Mobile Banking',
                'description' => 'Aplikasi perbankan mobile generasi baru',
            ]);

        $space = Space::where('name', 'Mobile Banking')->first();
        $this->assertNotNull($space);
        $this->assertEquals($workspace->id, $space->workspace_id);

        // Creator should be a space member
        $this->assertTrue($space->members->contains($user));

        // Default list and 5 stages should be auto-created
        $this->assertCount(1, $space->lists);
        $defaultList = $space->lists->first();
        $this->assertCount(5, $defaultList->stages);

        $response->assertRedirect("/spaces/{$space->slug}");
    }

    public function test_user_can_view_space_detail_page(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $space = Space::create([
            'workspace_id' => $workspace->id,
            'name' => 'Redesign Project',
            'slug' => 'redesign-project',
        ]);

        $response = $this->actingAs($user)->get("/spaces/{$space->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Spaces/Show')
            ->where('space.name', 'Redesign Project')
        );
    }

    public function test_unauthorized_user_cannot_view_space_of_another_workspace(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $workspace1 = Workspace::create(['name' => 'Private WS', 'slug' => 'private-ws', 'owner_id' => $user1->id]);
        $workspace1->members()->attach($user1->id, ['role' => 'owner']);

        $space = Space::create([
            'workspace_id' => $workspace1->id,
            'name' => 'Top Secret Space',
            'slug' => 'top-secret-space',
        ]);

        // User 2 tries to access space of Workspace 1
        $response = $this->actingAs($user2)->get("/spaces/{$space->slug}");

        $response->assertRedirect('/spaces');
        $response->assertSessionHas('error');
    }

    public function test_user_can_update_space_details(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $space = Space::create([
            'workspace_id' => $workspace->id,
            'name' => 'Old Space Name',
            'slug' => 'old-space-name',
        ]);

        $response = $this->actingAs($user)->put("/spaces/{$space->id}", [
            'name' => 'Updated Space Name',
            'description' => 'Updated description',
            'status' => 'completed',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('spaces', [
            'id' => $space->id,
            'name' => 'Updated Space Name',
            'status' => 'completed',
        ]);
    }

    public function test_workspace_owner_can_add_member_to_workspace(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();

        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'member',
        ]);

        $response->assertRedirect();
        $this->assertTrue($workspace->members()->where('users.id', $newMember->id)->exists());
    }

    public function test_workspace_owner_can_update_member_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($owner)->put("/workspaces/{$workspace->id}/members/{$member->id}", [
            'role' => 'admin',
        ]);

        $response->assertRedirect();
        $this->assertEquals('admin', $workspace->members()->where('users.id', $member->id)->first()->pivot->role);
    }

    public function test_workspace_owner_can_remove_member_from_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertRedirect();
        $this->assertFalse($workspace->members()->where('users.id', $member->id)->exists());
    }

    public function test_cannot_remove_workspace_owner(): void
    {
        $owner = User::factory()->create();

        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$owner->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertTrue($workspace->members()->where('users.id', $owner->id)->exists());
    }

    public function test_workspace_owner_can_update_workspace_name(): void
    {
        $owner = User::factory()->create();

        $workspace = Workspace::create(['name' => 'Old WS', 'slug' => 'old-ws', 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->put("/workspaces/{$workspace->id}", [
            'name' => 'Brand New Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Brand New Name',
        ]);
    }
}


