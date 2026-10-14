<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseSevenMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Space $space;
    private TaskList $list;
    private TaskStage $stage1;
    private TaskStage $stage2;
    private Task $task;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Diana Mobile Developer',
            'email' => 'diana@nexsphere.test',
            'password' => bcrypt('password123'),
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'name' => 'Mobile Core Workspace',
            'slug' => 'mobile-core-workspace',
        ]);

        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);

        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Flutter Mobile Client',
            'slug' => 'flutter-mobile-client',
            'status' => 'active',
        ]);

        $this->space->members()->attach($this->user->id, ['role' => 'manager']);

        $this->list = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Sprint Mobile',
            'slug' => 'sprint-mobile',
            'position' => 1,
        ]);

        $this->stage1 = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'Backlog',
            'color' => '#64748b',
            'is_done' => false,
            'position' => 1,
        ]);

        $this->stage2 = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'In Progress',
            'color' => '#0284c7',
            'is_done' => false,
            'position' => 2,
        ]);

        $this->task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->user->id,
            'title' => 'Implement BLoC State Management',
            'priority' => 'high',
            'position' => 1,
        ]);

        $this->token = $this->user->createToken('test-mobile-token')->plainTextToken;
    }

    public function test_user_can_login_via_api_and_receive_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'diana@nexsphere.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    public function test_user_can_login_via_username(): void
    {
        $this->user->update(['username' => 'dianadev']);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'dianadev',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Edward Flutter Dev',
            'email' => 'edward@nexsphere.test',
            'password' => 'secret12345',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email'],
                'default_workspace' => ['id', 'name'],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'edward@nexsphere.test']);
    }

    public function test_user_can_get_profile_and_workspaces_via_auth_me(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.email', 'diana@nexsphere.test')
            ->assertJsonCount(1, 'workspaces');
    }

    public function test_user_can_get_spaces_and_tasks_for_kanban_mobile(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/spaces/{$this->space->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Flutter Mobile Client')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'stages' => [
                        '*' => [
                            'id',
                            'name',
                            'tasks',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_create_and_move_task_via_api(): void
    {
        // 1. Buat task baru via API
        $createResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/tasks', [
                'list_id' => $this->list->id,
                'stage_id' => $this->stage1->id,
                'title' => 'Design Offline Cache Engine',
                'priority' => 'urgent',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Design Offline Cache Engine');

        $taskId = $createResponse->json('data.id');

        // 2. Pindahkan task ke stage lain (drag-and-drop mobile sync)
        $moveResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tasks/{$taskId}/move", [
                'stage_id' => $this->stage2->id,
                'position' => 1,
            ]);

        $moveResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.stage_id', $this->stage2->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'stage_id' => $this->stage2->id,
        ]);
    }

    public function test_user_can_toggle_checklist_and_add_comment_via_api(): void
    {
        // 1. Tambah checklist via API
        $chkResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tasks/{$this->task->id}/checklists", [
                'title' => 'Setup Dio interceptor',
            ]);

        $chkResponse->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $chkId = $chkResponse->json('data.id');

        // 2. Toggle checklist
        $toggleResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tasks/{$this->task->id}/checklists/{$chkId}/toggle");

        $toggleResponse->assertStatus(200)
            ->assertJsonPath('data.is_completed', true);

        // 3. Tambah komentar
        $commentResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tasks/{$this->task->id}/comments", [
                'content' => 'API interceptor berhasil dipasang.',
            ]);

        $commentResponse->assertStatus(201)
            ->assertJsonPath('data.content', 'API interceptor berhasil dipasang.');
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Token sudah dihapus dari database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
        ]);

        $this->app['auth']->forgetGuards();

        $retryResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/auth/me');

        $retryResponse->assertStatus(401);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/workspaces');

        $response->assertStatus(401);
    }
}
