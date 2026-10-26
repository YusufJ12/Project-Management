<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskChecklist;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Models\Workspace;
use App\Services\FcmPushService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MobileFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private User $viewer;
    private User $outsider;
    private Workspace $workspace;
    private Space $space;
    private TaskList $list;
    private TaskStage $stageTodo;
    private TaskStage $stageDone;
    private Task $task;
    private string $ownerToken;
    private string $memberToken;
    private string $viewerToken;
    private string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'nexsphere-test-key',
            'broadcasting.connections.reverb.secret' => 'nexsphere-test-secret',
            'broadcasting.connections.reverb.app_id' => '123456',
        ]);

        require base_path('routes/channels.php');

        $this->owner = User::factory()->create([
            'name' => 'Alice Workspace Owner',
            'email' => 'alice@nexsphere.test',
        ]);

        $this->member = User::factory()->create([
            'name' => 'Bob Member',
            'email' => 'bob@nexsphere.test',
        ]);

        $this->viewer = User::factory()->create([
            'name' => 'Charlie Viewer',
            'email' => 'charlie@nexsphere.test',
        ]);

        $this->outsider = User::factory()->create([
            'name' => 'Eve Outsider',
            'email' => 'eve@nexsphere.test',
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->owner->id,
            'name' => 'Mobile Engineering Workspace',
            'slug' => 'mobile-eng-workspace',
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
        $this->workspace->members()->attach($this->member->id, ['role' => 'member']);
        $this->workspace->members()->attach($this->viewer->id, ['role' => 'viewer']);

        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Mobile Project',
            'slug' => 'mobile-project',
            'status' => 'active',
        ]);

        $this->space->members()->attach($this->owner->id, ['role' => 'manager']);
        $this->space->members()->attach($this->member->id, ['role' => 'member']);
        $this->space->members()->attach($this->viewer->id, ['role' => 'viewer']);

        $this->list = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Sprint 1',
            'slug' => 'sprint-1',
            'position' => 1,
        ]);

        $this->stageTodo = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'To Do',
            'color' => '#64748b',
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

        $this->task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->owner->id,
            'title' => 'Core Mobile Authentication',
            'description' => 'Implement Sanctum and FCM tokens',
            'priority' => 'high',
            'start_date' => '2026-10-26',
            'due_at' => '2026-10-30',
            'position' => 1,
        ]);

        $this->ownerToken = $this->owner->createToken('owner-token')->plainTextToken;
        $this->memberToken = $this->member->createToken('member-token')->plainTextToken;
        $this->viewerToken = $this->viewer->createToken('viewer-token')->plainTextToken;
        $this->outsiderToken = $this->outsider->createToken('outsider-token')->plainTextToken;
    }

    /** 1. BROADCASTING AUTH TESTS */

    public function test_broadcasting_auth_endpoint_authenticates_sanctum_user(): void
    {
        $response = $this->withToken($this->memberToken)
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-user.' . $this->member->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200);
        $this->assertArrayHasKey('auth', $response->json());
    }

    public function test_broadcasting_auth_endpoint_rejects_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/broadcasting/auth', [
            'channel_name' => 'private-user.' . $this->member->id,
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(401);
    }

    public function test_broadcasting_auth_endpoint_enforces_channel_access(): void
    {
        // Member trying to listen to another user's private channel
        $response = $this->withToken($this->memberToken)
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-user.' . $this->owner->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    /** 2. NOTIFICATION API TESTS */

    public function test_notification_api_index_returns_paginated_and_unread_count(): void
    {
        Notification::create([
            'user_id' => $this->member->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'task_assigned',
            'data' => ['title' => 'Notification 1', 'message' => 'Task assigned to you'],
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => $this->member->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'comment_added',
            'data' => ['title' => 'Notification 2', 'message' => 'New comment'],
            'read_at' => now(),
        ]);

        $response = $this->withToken($this->memberToken)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(2, 'data');
    }

    public function test_notification_api_mark_as_read_updates_status(): void
    {
        $notification = Notification::create([
            'user_id' => $this->member->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'task_assigned',
            'data' => ['title' => 'Task Assigned'],
            'read_at' => null,
        ]);

        $response = $this->withToken($this->memberToken)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notification_api_mark_as_read_denies_unauthorized_user(): void
    {
        $notification = Notification::create([
            'user_id' => $this->owner->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'task_assigned',
            'data' => ['title' => 'Owner notification'],
            'read_at' => null,
        ]);

        // Member cannot mark owner's notification as read
        $response = $this->withToken($this->memberToken)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(403);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notification_api_mark_all_as_read(): void
    {
        Notification::create([
            'user_id' => $this->member->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'notif_1',
            'data' => ['title' => 'N1'],
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => $this->member->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'notif_2',
            'data' => ['title' => 'N2'],
            'read_at' => null,
        ]);

        $response = $this->withToken($this->memberToken)
            ->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('unread_count', 0);

        $this->assertEquals(0, Notification::where('user_id', $this->member->id)->whereNull('read_at')->count());
    }

    /** 3. CHECKLIST DESTROY TESTS */

    public function test_checklist_can_be_deleted_by_authorized_member(): void
    {
        $checklist = $this->task->checklists()->create([
            'title' => 'Setup CI pipeline',
            'is_completed' => false,
            'position' => 1,
        ]);

        $response = $this->withToken($this->memberToken)
            ->deleteJson("/api/v1/tasks/{$this->task->id}/checklists/{$checklist->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('task_checklists', ['id' => $checklist->id]);
    }

    public function test_checklist_deletion_denied_for_viewer(): void
    {
        $checklist = $this->task->checklists()->create([
            'title' => 'Setup CI pipeline',
            'is_completed' => false,
            'position' => 1,
        ]);

        $response = $this->withToken($this->viewerToken)
            ->deleteJson("/api/v1/tasks/{$this->task->id}/checklists/{$checklist->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('task_checklists', ['id' => $checklist->id]);
    }

    public function test_checklist_deletion_denied_for_outsider(): void
    {
        $checklist = $this->task->checklists()->create([
            'title' => 'Setup CI pipeline',
            'is_completed' => false,
            'position' => 1,
        ]);

        $response = $this->withToken($this->outsiderToken)
            ->deleteJson("/api/v1/tasks/{$this->task->id}/checklists/{$checklist->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('task_checklists', ['id' => $checklist->id]);
    }

    public function test_checklist_deletion_rejects_mismatched_task(): void
    {
        $otherTask = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageTodo->id,
            'created_by' => $this->owner->id,
            'title' => 'Another Task',
            'position' => 2,
        ]);

        $checklist = $otherTask->checklists()->create([
            'title' => 'Checklist of other task',
            'is_completed' => false,
            'position' => 1,
        ]);

        $response = $this->withToken($this->memberToken)
            ->deleteJson("/api/v1/tasks/{$this->task->id}/checklists/{$checklist->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('task_checklists', ['id' => $checklist->id]);
    }

    /** 4. COMMENT DESTROY TESTS */

    public function test_comment_can_be_deleted_by_author(): void
    {
        $comment = $this->task->comments()->create([
            'user_id' => $this->member->id,
            'content' => 'I am working on this.',
        ]);

        $response = $this->withToken($this->memberToken)
            ->deleteJson("/api/v1/tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    public function test_comment_can_be_deleted_by_workspace_owner(): void
    {
        $comment = $this->task->comments()->create([
            'user_id' => $this->member->id,
            'content' => 'Member comment to be moderated.',
        ]);

        // Owner can delete comments from other members
        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v1/tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    public function test_comment_deletion_denied_for_non_author_regular_member(): void
    {
        $comment = $this->task->comments()->create([
            'user_id' => $this->owner->id,
            'content' => 'Owner comment.',
        ]);

        // Member cannot delete owner's comment
        $response = $this->withToken($this->memberToken)
            ->deleteJson("/api/v1/tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('task_comments', ['id' => $comment->id]);
    }

    public function test_comment_deletion_denied_for_outsider(): void
    {
        $comment = $this->task->comments()->create([
            'user_id' => $this->member->id,
            'content' => 'Member comment.',
        ]);

        $response = $this->withToken($this->outsiderToken)
            ->deleteJson("/api/v1/tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('task_comments', ['id' => $comment->id]);
    }

    /** 5. TASK RESOURCE ENHANCEMENT TESTS */

    public function test_task_resource_includes_dates_dependencies_attachments(): void
    {
        // 1. Setup dependency
        $prereqTask = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stageDone->id,
            'created_by' => $this->owner->id,
            'title' => 'Prerequisite Task',
            'start_date' => '2026-10-20',
            'due_at' => '2026-10-25',
            'position' => 2,
        ]);
        $this->task->dependencies()->attach($prereqTask->id);

        // 2. Setup attachment
        $attachment = TaskAttachment::create([
            'task_id' => $this->task->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'spec.pdf',
            'storage_path' => 'attachments/fake-spec.pdf',
            'size_bytes' => 1024,
            'detected_mime' => 'application/pdf',
            'checksum' => 'fakechecksum123',
        ]);

        // 3. Setup comment
        $this->task->comments()->create([
            'user_id' => $this->member->id,
            'content' => 'Ready for review',
        ]);

        // 4. Setup assignee
        $this->task->assignees()->attach($this->member->id);

        $response = $this->withToken($this->memberToken)
            ->getJson("/api/v1/tasks/{$this->task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $this->task->id)
            ->assertJsonPath('data.start_date', '2026-10-26')
            ->assertJsonPath('data.due_at', '2026-10-30')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'title',
                    'start_date',
                    'due_at',
                    'dependencies' => [
                        '*' => ['id', 'title', 'start_date', 'due_at', 'stage'],
                    ],
                    'attachments' => [
                        '*' => ['id', 'original_name', 'file_size', 'mime_type', 'created_at', 'download_url'],
                    ],
                    'assignees',
                    'comments',
                ],
            ]);

        $this->assertEquals($prereqTask->id, $response->json('data.dependencies.0.id'));
        $this->assertEquals('Done', $response->json('data.dependencies.0.stage'));
        $this->assertEquals('spec.pdf', $response->json('data.attachments.0.original_name'));
        $this->assertEquals(1024, $response->json('data.attachments.0.file_size'));
        $this->assertStringContainsString("/api/v1/tasks/{$this->task->id}/attachments/{$attachment->id}/download", $response->json('data.attachments.0.download_url'));
    }

    /** 6. USER DEVICE TOKEN TESTS (FCM) */

    public function test_user_can_register_device_token(): void
    {
        $response = $this->withToken($this->memberToken)
            ->postJson('/api/v1/devices/token', [
                'token' => 'fcm_token_abc_123',
                'device_type' => 'android',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.token', 'fcm_token_abc_123')
            ->assertJsonPath('data.device_type', 'android');

        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->member->id,
            'token' => 'fcm_token_abc_123',
            'device_type' => 'android',
        ]);
    }

    public function test_user_can_update_existing_device_token(): void
    {
        UserDeviceToken::create([
            'user_id' => $this->member->id,
            'token' => 'fcm_token_existing',
            'device_type' => 'android',
        ]);

        // Re-register with different device type or updated user
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v1/devices/token', [
                'token' => 'fcm_token_existing',
                'device_type' => 'ios',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->owner->id,
            'token' => 'fcm_token_existing',
            'device_type' => 'ios',
        ]);
    }

    public function test_user_can_delete_device_token(): void
    {
        UserDeviceToken::create([
            'user_id' => $this->member->id,
            'token' => 'fcm_token_to_remove',
            'device_type' => 'android',
        ]);

        $response = $this->withToken($this->memberToken)
            ->deleteJson('/api/v1/devices/token', [
                'token' => 'fcm_token_to_remove',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('user_device_tokens', [
            'token' => 'fcm_token_to_remove',
        ]);
    }

    public function test_device_token_routes_require_authentication(): void
    {
        $this->postJson('/api/v1/devices/token', [
            'token' => 'unauth_token',
        ])->assertStatus(401);

        $this->deleteJson('/api/v1/devices/token', [
            'token' => 'unauth_token',
        ])->assertStatus(401);
    }

    /** 7. FCM PUSH SERVICE & NOTIFICATION INTEGRATION TESTS */

    public function test_fcm_push_service_queries_tokens_and_dispatches_mock(): void
    {
        UserDeviceToken::create([
            'user_id' => $this->member->id,
            'token' => 'token_member_android',
            'device_type' => 'android',
        ]);

        UserDeviceToken::create([
            'user_id' => $this->owner->id,
            'token' => 'token_owner_ios',
            'device_type' => 'ios',
        ]);

        $result = FcmPushService::sendPushToUsers(
            [$this->member->id, $this->owner->id],
            'Test Title',
            'Test Body',
            ['task_id' => $this->task->id]
        );

        $this->assertEquals(2, $result['sent']);
        $this->assertContains('token_member_android', $result['tokens']);
        $this->assertContains('token_owner_ios', $result['tokens']);
    }

    public function test_notification_service_send_to_many_triggers_fcm_dispatch(): void
    {
        UserDeviceToken::create([
            'user_id' => $this->member->id,
            'token' => 'token_for_push_trigger',
            'device_type' => 'android',
        ]);

        // Calling NotificationService::sendToMany should create in-app notification and trigger FCM
        NotificationService::sendToMany(
            [$this->member->id],
            $this->workspace->id,
            'task_assigned',
            [
                'title' => 'Tugas Baru',
                'message' => 'Anda ditugaskan pada tugas baru',
                'task_id' => $this->task->id,
            ],
            $this->owner->id
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->member->id,
            'type' => 'task_assigned',
        ]);
    }
}
