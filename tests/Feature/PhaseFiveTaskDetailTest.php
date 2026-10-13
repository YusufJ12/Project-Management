<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskChecklist;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseFiveTaskDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Space $space;
    private TaskList $list;
    private TaskStage $stage;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Alice Tester',
            'email' => 'alice@nexsphere.test',
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'name' => 'Alpha Team Workspace',
            'slug' => 'alpha-team-workspace',
        ]);

        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);

        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Web Redesign Project',
            'slug' => 'web-redesign-project',
            'status' => 'active',
        ]);

        $this->space->members()->attach($this->user->id, ['role' => 'manager']);

        $this->list = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Main Tasks',
            'slug' => 'main-tasks',
            'position' => 1,
        ]);

        $this->stage = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'In Progress',
            'color' => '#eab308',
            'is_done' => false,
            'position' => 1,
        ]);

        $this->task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage->id,
            'created_by' => $this->user->id,
            'title' => 'Implement Auth Modal',
            'description' => 'Original description',
            'priority' => 'medium',
            'position' => 1,
        ]);
    }

    public function test_user_can_view_task_details_via_json(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/tasks/{$this->task->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'task' => [
                    'id',
                    'title',
                    'description',
                    'priority',
                    'stage',
                    'assignees',
                    'checklists',
                    'comments',
                    'attachments',
                ],
                'activity_logs',
            ]);

        $this->assertEquals('Implement Auth Modal', $response->json('task.title'));
    }

    public function test_user_can_update_task_details_and_logs_activity(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/tasks/{$this->task->id}", [
                'title' => 'Updated Task Title',
                'description' => 'Updated new description',
                'priority' => 'urgent',
                'stage_id' => $this->stage->id,
                'due_at' => '2026-11-01',
                'estimate_minutes' => 180,
                'assignee_ids' => [$this->user->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'title' => 'Updated Task Title',
            'priority' => 'urgent',
            'estimate_minutes' => 180,
        ]);

        // Verifikasi assignee terhubung
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
        ]);

        // Verifikasi activity log tercatat
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'priority_changed',
            'subject_id' => $this->task->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'task_updated',
            'subject_id' => $this->task->id,
        ]);
    }

    public function test_user_can_add_toggle_and_delete_checklists(): void
    {
        // 1. Tambah checklist
        $addResponse = $this->actingAs($this->user)
            ->postJson("/tasks/{$this->task->id}/checklists", [
                'title' => 'Write automated test cases',
            ]);

        $addResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $checklistId = $addResponse->json('checklist.id');

        $this->assertDatabaseHas('task_checklists', [
            'id' => $checklistId,
            'task_id' => $this->task->id,
            'title' => 'Write automated test cases',
            'is_completed' => false,
        ]);

        // 2. Toggle checklist menjadi selesai
        $toggleResponse = $this->actingAs($this->user)
            ->postJson("/tasks/{$this->task->id}/checklists/{$checklistId}/toggle");

        $toggleResponse->assertStatus(200)
            ->assertJsonPath('checklist.is_completed', true);

        $this->assertDatabaseHas('task_checklists', [
            'id' => $checklistId,
            'is_completed' => true,
            'completed_by' => $this->user->id,
        ]);

        // 3. Hapus checklist
        $deleteResponse = $this->actingAs($this->user)
            ->deleteJson("/tasks/{$this->task->id}/checklists/{$checklistId}");

        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('task_checklists', [
            'id' => $checklistId,
        ]);
    }

    public function test_user_can_add_and_delete_comments(): void
    {
        // 1. Tambah komentar
        $addResponse = $this->actingAs($this->user)
            ->postJson("/tasks/{$this->task->id}/comments", [
                'content' => 'Review ini sudah siap untuk di-merge ke branch main.',
            ]);

        $addResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $commentId = $addResponse->json('comment.id');

        $this->assertDatabaseHas('task_comments', [
            'id' => $commentId,
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'content' => 'Review ini sudah siap untuk di-merge ke branch main.',
        ]);

        // Verifikasi log tercatat
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'comment_added',
            'subject_id' => $this->task->id,
        ]);

        // 2. Hapus komentar
        $deleteResponse = $this->actingAs($this->user)
            ->deleteJson("/tasks/{$this->task->id}/comments/{$commentId}");

        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('task_comments', [
            'id' => $commentId,
        ]);
    }

    public function test_user_can_upload_download_and_delete_attachments(): void
    {
        Storage::fake('public');

        $fakeFile = UploadedFile::fake()->create('architecture-spec.pdf', 1024, 'application/pdf');

        // 1. Upload file
        $uploadResponse = $this->actingAs($this->user)
            ->postJson("/tasks/{$this->task->id}/attachments", [
                'file' => $fakeFile,
            ]);

        $uploadResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $attachmentId = $uploadResponse->json('attachment.id');
        $storagePath = $uploadResponse->json('attachment.storage_path');

        $this->assertDatabaseHas('task_attachments', [
            'id' => $attachmentId,
            'task_id' => $this->task->id,
            'original_name' => 'architecture-spec.pdf',
        ]);

        Storage::disk('public')->assertExists($storagePath);

        // 2. Download file
        $downloadResponse = $this->actingAs($this->user)
            ->get("/tasks/{$this->task->id}/attachments/{$attachmentId}/download");

        $downloadResponse->assertStatus(200);

        // 3. Hapus attachment
        $deleteResponse = $this->actingAs($this->user)
            ->deleteJson("/tasks/{$this->task->id}/attachments/{$attachmentId}");

        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('task_attachments', [
            'id' => $attachmentId,
        ]);

        Storage::disk('public')->assertMissing($storagePath);
    }

    public function test_unauthorized_user_cannot_access_or_modify_task(): void
    {
        $intruder = User::factory()->create([
            'name' => 'Intruder Bob',
            'email' => 'bob@outside.test',
        ]);

        // Tidak boleh membaca task
        $readResponse = $this->actingAs($intruder)
            ->getJson("/tasks/{$this->task->id}");

        $readResponse->assertStatus(403);

        // Tidak boleh mengupdate task
        $updateResponse = $this->actingAs($intruder)
            ->putJson("/tasks/{$this->task->id}", [
                'title' => 'Hacked Task Title',
                'priority' => 'urgent',
            ]);

        $updateResponse->assertStatus(403);

        // Tidak boleh upload lampiran
        Storage::fake('public');
        $fakeFile = UploadedFile::fake()->create('malicious.exe', 100);

        $uploadResponse = $this->actingAs($intruder)
            ->postJson("/tasks/{$this->task->id}/attachments", [
                'file' => $fakeFile,
            ]);

        $uploadResponse->assertStatus(403);
    }
}

