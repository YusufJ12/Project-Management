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

class CustomTaskStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_custom_stage(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Agency WS', 'slug' => 'agency-ws', 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $space = Space::create(['workspace_id' => $workspace->id, 'name' => 'App Space', 'slug' => 'app-space']);
        $space->members()->attach($owner->id, ['role' => 'manager']);

        $list = TaskList::create(['space_id' => $space->id, 'name' => 'Main List', 'slug' => 'main-list']);

        $response = $this->actingAs($owner)->postJson(route('stages.store'), [
            'list_id' => $list->id,
            'name' => 'Testing & QA',
            'color' => '#8b5cf6',
            'is_done' => false,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('task_stages', [
            'list_id' => $list->id,
            'name' => 'Testing & QA',
            'color' => '#8b5cf6',
            'position' => 1,
        ]);
    }

    public function test_user_can_delete_stage_and_tasks_are_migrated(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Agency WS', 'slug' => 'agency-ws', 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $space = Space::create(['workspace_id' => $workspace->id, 'name' => 'App Space', 'slug' => 'app-space']);
        $space->members()->attach($owner->id, ['role' => 'manager']);

        $list = TaskList::create(['space_id' => $space->id, 'name' => 'Main List', 'slug' => 'main-list']);

        $stage1 = TaskStage::create(['list_id' => $list->id, 'name' => 'Backlog', 'color' => '#6b7280', 'position' => 1]);
        $stage2 = TaskStage::create(['list_id' => $list->id, 'name' => 'In QA', 'color' => '#8b5cf6', 'position' => 2]);

        $task = Task::create([
            'list_id' => $list->id,
            'stage_id' => $stage2->id,
            'created_by' => $owner->id,
            'title' => 'Test task in QA',
            'priority' => 'medium',
            'position' => 1,
        ]);

        $response = $this->actingAs($owner)->deleteJson(route('stages.destroy', $stage2));
        $response->assertStatus(200);

        $this->assertDatabaseMissing('task_stages', ['id' => $stage2->id]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'stage_id' => $stage1->id,
        ]);
    }
}
