<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoDatabaseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_and_member_relations_work_properly(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = Workspace::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'owner_id' => $owner->id,
        ]);

        $workspace->members()->attach([
            $owner->id => ['role' => 'owner', 'joined_at' => now()],
            $member->id => ['role' => 'member', 'joined_at' => now()],
        ]);

        $this->assertEquals($owner->id, $workspace->owner->id);
        $this->assertCount(2, $workspace->members);
        $this->assertTrue($workspace->members->contains($member));
    }

    public function test_space_list_and_stage_hierarchy(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $owner->id]);

        $space = Space::create([
            'workspace_id' => $workspace->id,
            'name' => 'Mobile App',
            'slug' => 'mobile-app',
            'status' => 'active',
        ]);

        $list = TaskList::create([
            'space_id' => $space->id,
            'name' => 'Sprint 1',
            'slug' => 'sprint-1',
        ]);

        $stage = TaskStage::create([
            'list_id' => $list->id,
            'name' => 'Done',
            'color' => '#22c55e',
            'is_done' => true,
            'position' => 1,
        ]);

        $this->assertEquals($workspace->id, $space->workspace->id);
        $this->assertEquals($space->id, $list->space->id);
        $this->assertTrue($stage->is_done);
        $this->assertCount(1, $list->stages);
    }

    public function test_task_creation_with_assignees_and_checklists(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $user->id]);
        $space = Space::create(['workspace_id' => $workspace->id, 'name' => 'Space', 'slug' => 'space']);
        $list = TaskList::create(['space_id' => $space->id, 'name' => 'List', 'slug' => 'list']);
        $stage = TaskStage::create(['list_id' => $list->id, 'name' => 'To Do', 'position' => 1]);

        $task = Task::create([
            'list_id' => $list->id,
            'stage_id' => $stage->id,
            'created_by' => $user->id,
            'title' => 'Test Task Feature',
            'priority' => 'urgent',
            'position' => 1,
        ]);

        $task->assignees()->attach($assignee->id);

        $checklist = TaskChecklist::create([
            'task_id' => $task->id,
            'title' => 'Subtask 1',
            'is_completed' => true,
            'completed_by' => $assignee->id,
        ]);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'content' => 'Nice progress!',
        ]);

        $this->assertEquals('urgent', $task->priority);
        $this->assertCount(1, $task->assignees);
        $this->assertTrue($task->assignees->contains($assignee));
        $this->assertCount(1, $task->checklists);
        $this->assertTrue($task->checklists->first()->is_completed);
        $this->assertCount(1, $task->comments);
    }

    public function test_activity_log_creation(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'owner_id' => $user->id]);

        $log = ActivityLog::create([
            'workspace_id' => $workspace->id,
            'actor_id' => $user->id,
            'action' => 'workspace_created',
            'subject_type' => Workspace::class,
            'subject_id' => $workspace->id,
            'metadata' => ['ip' => '127.0.0.1'],
        ]);

        $this->assertEquals('workspace_created', $log->action);
        $this->assertEquals('127.0.0.1', $log->metadata['ip']);
        $this->assertEquals($workspace->id, $log->workspace->id);
    }
}

