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

class PhaseFourKanbanEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected Space $space;
    protected TaskList $list;
    protected TaskStage $stage1;
    protected TaskStage $stage2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $this->user->id]);
        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);

        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Space',
            'slug' => 'space',
        ]);
        $this->space->members()->attach($this->user->id, ['role' => 'manager']);

        $this->list = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Sprint 1',
            'slug' => 'sprint-1',
        ]);

        $this->stage1 = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'To Do',
            'color' => '#0284c7',
            'position' => 1,
        ]);

        $this->stage2 = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'Done',
            'color' => '#22c55e',
            'is_done' => true,
            'position' => 2,
        ]);
    }

    public function test_user_can_create_task_via_quick_add(): void
    {
        $response = $this->actingAs($this->user)->post('/tasks', [
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'title' => 'Quick Add Kanban Task',
            'priority' => 'high',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'title' => 'Quick Add Kanban Task',
            'priority' => 'high',
            'position' => 1,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'workspace_id' => $this->workspace->id,
            'action' => 'task_created',
        ]);
    }

    public function test_user_can_move_task_to_another_stage(): void
    {
        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->user->id,
            'title' => 'Task to move',
            'position' => 1,
        ]);

        $response = $this->actingAs($this->user)->postJson("/tasks/{$task->id}/move", [
            'stage_id' => $this->stage2->id,
            'position' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'task' => [
                'id' => $task->id,
                'stage_id' => $this->stage2->id,
                'position' => 1,
            ],
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'stage_id' => $this->stage2->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'workspace_id' => $this->workspace->id,
            'action' => 'task_moved',
        ]);
    }

    public function test_user_can_reorder_task_within_same_stage(): void
    {
        $task1 = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->user->id,
            'title' => 'Task 1',
            'position' => 1,
        ]);

        $task2 = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->user->id,
            'title' => 'Task 2',
            'position' => 2,
        ]);

        // Move task 2 to position 1
        $response = $this->actingAs($this->user)->postJson("/tasks/{$task2->id}/move", [
            'stage_id' => $this->stage1->id,
            'position' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $task2->fresh()->position);
        $this->assertEquals(2, $task1->fresh()->position);
    }

    public function test_unauthorized_user_cannot_move_task(): void
    {
        $stranger = User::factory()->create();
        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->user->id,
            'title' => 'Private Task',
            'position' => 1,
        ]);

        $response = $this->actingAs($stranger)->postJson("/tasks/{$task->id}/move", [
            'stage_id' => $this->stage2->id,
            'position' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_task(): void
    {
        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->user->id,
            'title' => 'Task to delete',
            'position' => 1,
        ]);

        $response = $this->actingAs($this->user)->delete("/tasks/{$task->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}

