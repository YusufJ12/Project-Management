<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyTasksAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $member;
    protected Workspace $workspace;
    protected Space $space;
    protected TaskList $taskList;
    protected TaskStage $todoStage;
    protected TaskStage $doneStage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@nexsphere.test',
            'role' => 'super_admin',
        ]);

        $this->member = User::factory()->create([
            'name' => 'John Member',
            'email' => 'member@nexsphere.test',
            'role' => 'member',
        ]);

        $this->workspace = Workspace::create([
            'name' => 'NexSphere Workspace',
            'slug' => 'nexsphere-workspace',
            'owner_id' => $this->superAdmin->id,
        ]);

        $this->workspace->members()->attach($this->superAdmin->id, ['role' => 'owner']);
        $this->workspace->members()->attach($this->member->id, ['role' => 'member']);

        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Core Development',
            'slug' => 'core-dev',
        ]);

        $this->taskList = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Sprint 1',
            'slug' => 'sprint-1',
        ]);

        $this->todoStage = TaskStage::create([
            'list_id' => $this->taskList->id,
            'name' => 'To Do',
            'position' => 1,
            'is_done' => false,
        ]);

        $this->doneStage = TaskStage::create([
            'list_id' => $this->taskList->id,
            'name' => 'Done',
            'position' => 2,
            'is_done' => true,
        ]);
    }

    public function test_user_can_access_my_tasks_page_and_see_assigned_tasks(): void
    {
        $task = Task::create([
            'list_id' => $this->taskList->id,
            'stage_id' => $this->todoStage->id,
            'title' => 'Buat Fitur Autentikasi',
            'priority' => 'urgent',
            'created_by' => $this->superAdmin->id,
        ]);

        $task->assignees()->attach($this->member->id);

        $response = $this->actingAs($this->member)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/my-tasks');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('MyTasks/Index')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Buat Fitur Autentikasi')
            ->has('stats', fn ($stats) => $stats
                ->where('total', 1)
                ->where('pending', 1)
                ->where('completed', 0)
                ->etc()
            )
        );
    }

    public function test_user_can_access_activity_logs_page(): void
    {
        ActivityLog::create([
            'workspace_id' => $this->workspace->id,
            'space_id' => $this->space->id,
            'actor_id' => $this->superAdmin->id,
            'action' => 'task_created',
            'subject_type' => Task::class,
            'subject_id' => 1,
            'metadata' => [
                'title' => 'Task Pertama',
                'description' => 'Super Administrator membuat task baru.',
            ],
        ]);

        $response = $this->actingAs($this->member)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/activity-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ActivityLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.description', 'Super Administrator membuat task baru.')
        );
    }

    public function test_regular_member_cannot_access_user_rbac_page(): void
    {
        $response = $this->actingAs($this->member)->get('/users');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_user_rbac_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data')
            ->has('stats', fn ($stats) => $stats
                ->where('super_admin', 1)
                ->where('member', 1)
                ->etc()
            )
        );
    }

    public function test_super_admin_can_update_user_role(): void
    {
        $response = $this->actingAs($this->superAdmin)->put("/users/{$this->member->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $this->member->id,
            'role' => 'admin',
        ]);
    }

    public function test_super_admin_cannot_demote_last_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->put("/users/{$this->superAdmin->id}/role", [
            'role' => 'member',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->id,
            'role' => 'super_admin',
        ]);
    }

    public function test_super_admin_can_create_new_user(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/users', [
            'name' => 'Rina Wijaya',
            'email' => 'rina@nexsphere.test',
            'password' => 'password123',
            'role' => 'admin',
            'workspace_id' => $this->workspace->id,
        ]);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'email' => 'rina@nexsphere.test',
            'role' => 'admin',
        ]);
    }

    public function test_super_admin_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->superAdmin)->delete("/users/{$this->superAdmin->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->id,
        ]);
    }
}
