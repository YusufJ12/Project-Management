<?php

namespace Tests\Feature;

use App\Mail\TaskCompletedMail;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseEightRevisionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected User $member;
    protected User $viewer;
    protected Workspace $workspace;
    protected Space $space;
    protected TaskList $list;
    protected TaskStage $stageTodo;
    protected TaskStage $stageDone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'WS Owner', 'email' => 'owner@example.com']);
        $this->manager = User::factory()->create(['name' => 'Space Manager', 'email' => 'manager@example.com']);
        $this->member = User::factory()->create(['name' => 'Space Member', 'email' => 'member@example.com']);
        $this->viewer = User::factory()->create(['name' => 'Space Viewer', 'email' => 'viewer@example.com']);

        $this->workspace = Workspace::create([
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
            'owner_id' => $this->owner->id,
        ]);
        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
        $this->workspace->members()->attach($this->manager->id, ['role' => 'member']);
        $this->workspace->members()->attach($this->member->id, ['role' => 'member']);
        $this->workspace->members()->attach($this->viewer->id, ['role' => 'member']);

        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Project Alpha',
            'slug' => 'project-alpha',
        ]);
        $this->space->members()->attach($this->manager->id, ['role' => 'manager']);
        $this->space->members()->attach($this->member->id, ['role' => 'member']);
        $this->space->members()->attach($this->viewer->id, ['role' => 'viewer']);

        $this->list = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Sprint List',
            'slug' => 'sprint-list',
        ]);

        $this->stageTodo = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'To Do',
            'color' => '#0284c7',
            'is_done' => false,
            'position' => 1,
        ]);

        $this->stageDone = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'Done',
            'color' => '#22c55e',
            'is_done' => true,
            'position' => 2,
        ]);
    }

    /** 1. TASK DEPENDENCIES & CASCADING TIMELINE TESTS */

    public function test_task_dependencies_relationship_and_migration(): void
    {
        $task1 = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Prerequisite Task',
            'start_date' => '2026-11-01',
            'due_at' => '2026-11-05',
        ]);

        $task2 = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Dependent Task',
            'start_date' => '2026-11-06',
            'due_at' => '2026-11-10',
        ]);

        $task2->dependencies()->attach($task1->id);

        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $task2->id,
            'depends_on_task_id' => $task1->id,
            'dependency_type' => 'finish_to_start',
        ]);

        $this->assertTrue($task2->dependencies->contains($task1));
        $this->assertTrue($task1->dependents->contains($task2));
    }

    public function test_cascading_timeline_shifts_dependent_tasks_preserving_duration(): void
    {
        $taskA = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Task A',
            'start_date' => '2026-11-01',
            'due_at' => '2026-11-05',
        ]);

        $taskB = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Task B',
            'start_date' => '2026-11-02',
            'due_at' => '2026-11-05', // duration = 3 days
        ]);

        $taskC = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Task C',
            'start_date' => '2026-11-04',
            'due_at' => '2026-11-06', // duration = 2 days
        ]);

        $taskB->dependencies()->attach($taskA->id);
        $taskC->dependencies()->attach($taskB->id);

        // Task A's due date shifts to Nov 10
        $taskA->update([
            'due_at' => '2026-11-10',
        ]);
        $taskA->cascadeTimeline();

        $taskB->refresh();
        $taskC->refresh();

        // Task B should start Nov 11 (day after Task A finishes) with 3 days duration -> due Nov 14
        $this->assertEquals('2026-11-11', $taskB->start_date->toDateString());
        $this->assertEquals('2026-11-14', $taskB->due_at->toDateString());

        // Task C should start Nov 15 (day after Task B finishes) with 2 days duration -> due Nov 17
        $this->assertEquals('2026-11-15', $taskC->start_date->toDateString());
        $this->assertEquals('2026-11-17', $taskC->due_at->toDateString());
    }

    public function test_cascading_timeline_detects_cycles_without_infinite_loop(): void
    {
        $task1 = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Loop 1',
            'start_date' => '2026-11-01',
            'due_at' => '2026-11-05',
        ]);

        $task2 = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Loop 2',
            'start_date' => '2026-11-06',
            'due_at' => '2026-11-10',
        ]);

        // Create circular dependency
        $task2->dependencies()->attach($task1->id);
        $task1->dependencies()->attach($task2->id);

        // Trigger cascading timeline - should not crash or recurse infinitely
        $task1->update(['due_at' => '2026-11-12']);
        $task1->cascadeTimeline();

        $this->assertTrue(true);
    }

    public function test_task_controller_update_supports_dependency_ids_and_triggers_cascade(): void
    {
        $taskA = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Prereq A',
            'start_date' => '2026-11-01',
            'due_at' => '2026-11-05',
        ]);

        $taskB = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Dependent B',
            'start_date' => '2026-11-06',
            'due_at' => '2026-11-09',
        ]);

        // Update Task B to depend on Task A via API
        $response = $this->actingAs($this->member)->putJson("/tasks/{$taskB->id}", [
            'title' => 'Dependent B Updated',
            'priority' => 'high',
            'dependency_ids' => [$taskA->id],
        ]);

        $response->assertStatus(200);
        $this->assertTrue($taskB->fresh()->dependencies->contains($taskA));

        // Now update Task A's due date to Nov 15 via Controller
        $updateAResponse = $this->actingAs($this->member)->putJson("/tasks/{$taskA->id}", [
            'title' => 'Prereq A Shifted',
            'priority' => 'medium',
            'start_date' => '2026-11-01',
            'due_at' => '2026-11-15',
        ]);

        $updateAResponse->assertStatus(200);

        // Task B should have been cascaded to Nov 16
        $taskB->refresh();
        $this->assertEquals('2026-11-16', $taskB->start_date->toDateString());
    }

    public function test_space_controller_show_and_sync_loads_dependencies_and_effective_role(): void
    {
        $taskA = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Task 1',
        ]);

        $taskB = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Task 2',
        ]);
        $taskB->dependencies()->attach($taskA->id);

        // Sync route
        $syncResponse = $this->actingAs($this->viewer)->getJson("/spaces/{$this->space->slug}/sync");
        $syncResponse->assertStatus(200);
        $syncResponse->assertJson([
            'can_edit_tasks' => false,
            'effectiveRole' => 'viewer',
        ]);

        // Manager sync should allow editing
        $managerSync = $this->actingAs($this->manager)->getJson("/spaces/{$this->space->slug}/sync");
        $managerSync->assertStatus(200);
        $managerSync->assertJson([
            'can_edit_tasks' => true,
            'effectiveRole' => 'manager',
        ]);
    }

    /** 2. VIEWER ROLE TESTS */

    public function test_workspace_controller_allows_viewer_role(): void
    {
        $newUser = User::factory()->create();

        // Add member with viewer role
        $response = $this->actingAs($this->owner)->post("/workspaces/{$this->workspace->id}/members", [
            'user_id' => $newUser->id,
            'role' => 'viewer',
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $newUser->id,
            'role' => 'viewer',
        ]);

        // Update member role to viewer
        $updateResponse = $this->actingAs($this->owner)->put("/workspaces/{$this->workspace->id}/members/{$newUser->id}", [
            'role' => 'viewer',
        ]);
        $updateResponse->assertRedirect();
    }

    public function test_viewer_is_rejected_from_mutating_tasks(): void
    {
        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Original Task',
        ]);

        // 1. Task Creation
        $createResponse = $this->actingAs($this->viewer)->postJson('/tasks', [
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'title' => 'Viewer Task',
        ]);
        $createResponse->assertStatus(403);
        $createResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);

        // 2. Task Update
        $updateResponse = $this->actingAs($this->viewer)->putJson("/tasks/{$task->id}", [
            'title' => 'Viewer Update',
            'priority' => 'urgent',
        ]);
        $updateResponse->assertStatus(403);
        $updateResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);

        // 3. Task Move
        $moveResponse = $this->actingAs($this->viewer)->postJson("/tasks/{$task->id}/move", [
            'stage_id' => $this->stageDone->id,
            'position' => 1,
        ]);
        $moveResponse->assertStatus(403);
        $moveResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);

        // 4. Task Delete
        $deleteResponse = $this->actingAs($this->viewer)->deleteJson("/tasks/{$task->id}");
        $deleteResponse->assertStatus(403);
        $deleteResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);
    }

    public function test_viewer_is_rejected_from_mutating_stages(): void
    {
        $stage = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'In Review',
            'position' => 3,
        ]);

        // Create stage
        $storeResponse = $this->actingAs($this->viewer)->postJson('/stages', [
            'list_id' => $this->list->id,
            'name' => 'Viewer Stage',
        ]);
        $storeResponse->assertStatus(403);
        $storeResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);

        // Update stage
        $updateResponse = $this->actingAs($this->viewer)->putJson("/stages/{$stage->id}", [
            'name' => 'Updated Stage',
        ]);
        $updateResponse->assertStatus(403);
        $updateResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);

        // Delete stage
        $deleteResponse = $this->actingAs($this->viewer)->deleteJson("/stages/{$stage->id}");
        $deleteResponse->assertStatus(403);
        $deleteResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);
    }

    public function test_viewer_is_rejected_from_mutating_checklists_and_attachments(): void
    {
        Storage::fake('public');

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Task with Checklist',
        ]);

        // Checklist Store
        $clStoreResponse = $this->actingAs($this->viewer)->postJson("/tasks/{$task->id}/checklists", [
            'title' => 'Viewer Checklist',
        ]);
        $clStoreResponse->assertStatus(403);
        $clStoreResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);

        // Attachment Store
        $file = UploadedFile::fake()->create('document.pdf', 500);
        $attStoreResponse = $this->actingAs($this->viewer)->postJson("/tasks/{$task->id}/attachments", [
            'file' => $file,
        ]);
        $attStoreResponse->assertStatus(403);
        $attStoreResponse->assertJson(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);
    }

    public function test_viewer_can_read_and_post_comments(): void
    {
        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Discussion Task',
        ]);

        // Viewer post comment
        $commentResponse = $this->actingAs($this->viewer)->postJson("/tasks/{$task->id}/comments", [
            'content' => 'Viewer comment in discussion log.',
        ]);
        $commentResponse->assertStatus(201);
        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $this->viewer->id,
            'content' => 'Viewer comment in discussion log.',
        ]);

        // Viewer read comments
        $indexResponse = $this->actingAs($this->viewer)->getJson("/tasks/{$task->id}/comments");
        $indexResponse->assertStatus(200);
        $this->assertCount(1, $indexResponse->json('comments'));
    }

    /** 3. EMAIL NOTIFICATION TO PROJECT OWNER ON TASK COMPLETION TESTS */

    public function test_email_sent_to_space_manager_when_task_moves_to_done(): void
    {
        Mail::fake();

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Complete this task',
            'position' => 1,
        ]);

        // Move task to Done stage
        $response = $this->actingAs($this->member)->postJson("/tasks/{$task->id}/move", [
            'stage_id' => $this->stageDone->id,
            'position' => 1,
        ]);

        $response->assertStatus(200);

        // Verify email sent to Space Manager
        Mail::assertSent(TaskCompletedMail::class, function ($mail) {
            return $mail->hasTo($this->manager->email) &&
                $mail->task->title === 'Complete this task';
        });
    }

    public function test_email_sent_to_workspace_owner_if_no_space_managers(): void
    {
        Mail::fake();

        // Detach manager so only workspace owner is left as project owner
        $this->space->members()->detach($this->manager->id);

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Owner fallback task',
            'position' => 1,
        ]);

        // Update task to Done stage
        $response = $this->actingAs($this->member)->putJson("/tasks/{$task->id}", [
            'title' => 'Owner fallback task',
            'priority' => 'medium',
            'stage_id' => $this->stageDone->id,
        ]);

        $response->assertStatus(200);

        // Verify email sent to Workspace Owner
        Mail::assertSent(TaskCompletedMail::class, function ($mail) {
            return $mail->hasTo($this->owner->email);
        });
    }

    /** 4. MOBILE API CONSISTENCY TESTS */

    public function test_mobile_api_viewer_cannot_mutate_and_completion_sends_email(): void
    {
        Mail::fake();

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'API Task',
            'position' => 1,
        ]);

        // Mobile API viewer rejection
        $viewerResponse = $this->actingAs($this->viewer, 'sanctum')->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Hacked title',
            'priority' => 'low',
        ]);
        $viewerResponse->assertStatus(403);
        $viewerResponse->assertJson(['message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.']);

        // Mobile API completion triggers email
        $completeResponse = $this->actingAs($this->member, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/move", [
            'stage_id' => $this->stageDone->id,
            'position' => 1,
        ]);
        $completeResponse->assertStatus(200);

        Mail::assertSent(TaskCompletedMail::class, function ($mail) {
            return $mail->hasTo($this->manager->email);
        });
    }

    public function test_smtp_failure_is_safely_caught_and_does_not_cause_500_error_on_move(): void
    {
        Mail::shouldReceive('to->send')
            ->andThrow(new \RuntimeException('Connection refused to SMTP server'));

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->atLeast()->once();

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Resilience Test Task',
            'position' => 1,
        ]);

        $response = $this->actingAs($this->member)->postJson("/tasks/{$task->id}/move", [
            'stage_id' => $this->stageDone->id,
            'position' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($this->stageDone->id, $task->fresh()->stage_id);
    }

    public function test_smtp_failure_is_safely_caught_on_mobile_api_move(): void
    {
        Mail::shouldReceive('to->send')
            ->andThrow(new \RuntimeException('SMTP host unreachable'));

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->atLeast()->once();

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Mobile API Resilience Task',
            'position' => 1,
        ]);

        $response = $this->actingAs($this->member, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/move", [
            'stage_id' => $this->stageDone->id,
            'position' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($this->stageDone->id, $task->fresh()->stage_id);
    }

    public function test_non_done_stage_move_does_not_send_completion_email(): void
    {
        Mail::fake();

        $stageReview = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'In Review',
            'color' => '#f59e0b',
            'is_done' => false,
            'position' => 2,
        ]);

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Review Task',
            'position' => 1,
        ]);

        $response = $this->actingAs($this->member)->postJson("/tasks/{$task->id}/move", [
            'stage_id' => $stageReview->id,
            'position' => 1,
        ]);

        $response->assertStatus(200);
        Mail::assertNothingSent();
    }

    public function test_removing_dependency_via_task_update_unlinks_it(): void
    {
        $taskA = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Prereq A',
            'start_date' => '2026-11-01',
            'due_at' => '2026-11-05',
        ]);

        $taskB = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->member->id,
            'title' => 'Dependent B',
            'start_date' => '2026-11-06',
            'due_at' => '2026-11-09',
        ]);

        $taskB->dependencies()->attach($taskA->id);
        $this->assertTrue($taskB->fresh()->dependencies->contains($taskA));

        // Update Task B with empty dependency_ids to detach
        $response = $this->actingAs($this->member)->putJson("/tasks/{$taskB->id}", [
            'title' => 'Dependent B Unlinked',
            'priority' => 'low',
            'dependency_ids' => [],
        ]);

        $response->assertStatus(200);
        $this->assertFalse($taskB->fresh()->dependencies->contains($taskA));
    }
}
