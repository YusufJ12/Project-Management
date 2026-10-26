<?php

namespace Tests\Feature;

use App\Events\NotificationSentEvent;
use App\Events\SpaceStageChangedEvent;
use App\Events\TaskAttachmentDeletedEvent;
use App\Events\TaskAttachmentUploadedEvent;
use App\Events\TaskChecklistToggledEvent;
use App\Events\TaskCommentAddedEvent;
use App\Events\TaskCreatedEvent;
use App\Events\TaskDeletedEvent;
use App\Events\TaskMovedEvent;
use App\Events\TaskUpdatedEvent;
use App\Models\Notification;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskChecklist;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealtimeBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private User $outsider;
    private Workspace $workspace;
    private Space $space;
    private TaskList $list;
    private TaskStage $stage1;
    private TaskStage $stage2;

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

        $this->owner = User::factory()->create(['name' => 'WS Owner']);
        $this->member = User::factory()->create(['name' => 'Space Member']);
        $this->outsider = User::factory()->create(['name' => 'Outsider User']);

        $this->workspace = Workspace::create([
            'name' => 'Engineering WS',
            'slug' => 'engineering-ws',
            'owner_id' => $this->owner->id,
        ]);
        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
        $this->workspace->members()->attach($this->member->id, ['role' => 'member']);

        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Core App',
            'slug' => 'core-app',
        ]);
        $this->space->members()->attach($this->owner->id, ['role' => 'manager']);
        $this->space->members()->attach($this->member->id, ['role' => 'member']);

        $this->list = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Sprint 1',
            'slug' => 'sprint-1',
        ]);

        $this->stage1 = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'To Do',
            'color' => '#3b82f6',
            'position' => 1,
            'is_done' => false,
        ]);

        $this->stage2 = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'Done',
            'color' => '#10b981',
            'position' => 2,
            'is_done' => true,
        ]);
    }

    public function test_all_domain_events_implement_should_broadcast_now(): void
    {
        $events = [
            TaskCreatedEvent::class,
            TaskUpdatedEvent::class,
            TaskMovedEvent::class,
            TaskDeletedEvent::class,
            TaskChecklistToggledEvent::class,
            TaskCommentAddedEvent::class,
            TaskAttachmentUploadedEvent::class,
            TaskAttachmentDeletedEvent::class,
            SpaceStageChangedEvent::class,
            NotificationSentEvent::class,
        ];

        foreach ($events as $eventClass) {
            $interfaces = class_implements($eventClass);
            $this->assertArrayHasKey(
                ShouldBroadcastNow::class,
                $interfaces,
                "{$eventClass} must implement ShouldBroadcastNow"
            );
        }
    }

    public function test_event_channels_and_payload_structures(): void
    {
        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->owner->id,
            'title' => 'Test Task',
            'priority' => 'high',
            'position' => 1,
        ]);

        // 1. TaskCreatedEvent
        $createdEvent = new TaskCreatedEvent($task, $this->owner, $this->space->id);
        $this->assertEquals(['private-space.' . $this->space->id], array_map(fn($c) => $c->name, $createdEvent->broadcastOn()));
        $createdPayload = $createdEvent->broadcastWith();
        $this->assertArrayHasKey('task', $createdPayload);
        $this->assertEquals(['id' => $this->owner->id, 'name' => $this->owner->name], $createdPayload['actor']);

        // 2. TaskUpdatedEvent
        $updatedEvent = new TaskUpdatedEvent($task, $this->owner, $this->space->id);
        $this->assertEquals(['private-space.' . $this->space->id], array_map(fn($c) => $c->name, $updatedEvent->broadcastOn()));
        $updatedPayload = $updatedEvent->broadcastWith();
        $this->assertArrayHasKey('task', $updatedPayload);
        $this->assertEquals($this->owner->id, $updatedPayload['actor']['id']);

        // 3. TaskMovedEvent
        $movedEvent = new TaskMovedEvent($task->id, $this->stage1->id, $this->stage2->id, 2, $task, $this->owner, $this->space->id);
        $this->assertEquals(['private-space.' . $this->space->id], array_map(fn($c) => $c->name, $movedEvent->broadcastOn()));
        $movedPayload = $movedEvent->broadcastWith();
        $this->assertEquals($task->id, $movedPayload['taskId']);
        $this->assertEquals($this->stage1->id, $movedPayload['oldStageId']);
        $this->assertEquals($this->stage2->id, $movedPayload['newStageId']);
        $this->assertEquals(2, $movedPayload['newPosition']);

        // 4. TaskDeletedEvent
        $deletedEvent = new TaskDeletedEvent($this->space->id, $task->id, $this->stage1->id, $this->owner);
        $this->assertEquals(['private-space.' . $this->space->id], array_map(fn($c) => $c->name, $deletedEvent->broadcastOn()));
        $deletedPayload = $deletedEvent->broadcastWith();
        $this->assertEquals($task->id, $deletedPayload['taskId']);
        $this->assertEquals($this->stage1->id, $deletedPayload['stageId']);

        // 5. TaskChecklistToggledEvent
        $toggledEvent = new TaskChecklistToggledEvent($this->space->id, $task->id, 99, true, 5, 3, $this->owner);
        $this->assertEquals(['private-space.' . $this->space->id], array_map(fn($c) => $c->name, $toggledEvent->broadcastOn()));
        $toggledPayload = $toggledEvent->broadcastWith();
        $this->assertEquals(99, $toggledPayload['checklistId']);
        $this->assertTrue($toggledPayload['isCompleted']);
        $this->assertEquals(5, $toggledPayload['checklistsCount']);
        $this->assertEquals(3, $toggledPayload['completedChecklistsCount']);

        // 6. TaskCommentAddedEvent
        $comment = $task->comments()->create(['user_id' => $this->owner->id, 'content' => 'Hello']);
        $commentEvent = new TaskCommentAddedEvent($this->space->id, $task->id, $comment);
        $commentPayload = $commentEvent->broadcastWith();
        $this->assertEquals($task->id, $commentPayload['taskId']);
        $this->assertEquals('Hello', $commentPayload['comment']['content']);
        $this->assertEquals($this->owner->email, $commentPayload['comment']['user']['email']);

        // 7. TaskAttachmentUploadedEvent
        $attachment = $task->attachments()->create([
            'uploaded_by' => $this->owner->id,
            'original_name' => 'doc.pdf',
            'storage_path' => 'tasks/doc.pdf',
            'size_bytes' => 1024,
            'detected_mime' => 'application/pdf',
            'checksum' => 'abc',
        ]);
        $uploadEvent = new TaskAttachmentUploadedEvent($this->space->id, $task->id, $attachment);
        $uploadPayload = $uploadEvent->broadcastWith();
        $this->assertEquals('doc.pdf', $uploadPayload['attachment']['original_name']);
        $this->assertArrayNotHasKey('storage_path', $uploadPayload['attachment']);
        $this->assertArrayNotHasKey('checksum', $uploadPayload['attachment']);

        // 8. TaskAttachmentDeletedEvent
        $attachmentDeleteEvent = new TaskAttachmentDeletedEvent($this->space->id, $task->id, $attachment->id, $this->owner);
        $attachmentDeletePayload = $attachmentDeleteEvent->broadcastWith();
        $this->assertEquals($attachment->id, $attachmentDeletePayload['attachmentId']);

        // 9. SpaceStageChangedEvent
        $stageEvent = new SpaceStageChangedEvent($this->space->id, $this->list->id, 'created', $this->stage1);
        $stagePayload = $stageEvent->broadcastWith();
        $this->assertEquals('created', $stagePayload['action']);
        $this->assertEquals($this->space->id, $stagePayload['spaceId']);
    }

    public function test_channel_authorization_for_space(): void
    {
        // 1. Authorized space member
        $response = $this->actingAs($this->member)->post('/broadcasting/auth', [
            'channel_name' => 'private-space.' . $this->space->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(200);

        // 2. Workspace owner
        $response = $this->actingAs($this->owner)->post('/broadcasting/auth', [
            'channel_name' => 'private-space.' . $this->space->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(200);

        // 3. Super admin
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $response = $this->actingAs($superAdmin)->post('/broadcasting/auth', [
            'channel_name' => 'private-space.' . $this->space->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(200);

        // 4. Outsider user (not member of workspace or space)
        $response = $this->actingAs($this->outsider)->post('/broadcasting/auth', [
            'channel_name' => 'private-space.' . $this->space->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(403);

        // 5. Workspace member who is not in space and not owner/admin
        $otherWsMember = User::factory()->create();
        $this->workspace->members()->attach($otherWsMember->id, ['role' => 'member']);
        $response = $this->actingAs($otherWsMember)->post('/broadcasting/auth', [
            'channel_name' => 'private-space.' . $this->space->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(403);
    }

    public function test_channel_authorization_for_workspace(): void
    {
        // Workspace member
        $response = $this->actingAs($this->member)->post('/broadcasting/auth', [
            'channel_name' => 'private-workspace.' . $this->workspace->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(200);

        // Outsider
        $response = $this->actingAs($this->outsider)->post('/broadcasting/auth', [
            'channel_name' => 'private-workspace.' . $this->workspace->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(403);
    }

    public function test_web_task_crud_dispatches_realtime_events(): void
    {
        Event::fake([
            TaskCreatedEvent::class,
            TaskUpdatedEvent::class,
            TaskMovedEvent::class,
            TaskDeletedEvent::class,
        ]);

        // 1. Store
        $response = $this->actingAs($this->owner)->post(route('tasks.store'), [
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'title' => 'Web Task Created',
            'priority' => 'high',
        ]);
        $response->assertSessionHasNoErrors();
        Event::assertDispatched(TaskCreatedEvent::class, function ($event) {
            return $event->task->title === 'Web Task Created' && $event->spaceId === $this->space->id;
        });

        $task = Task::where('title', 'Web Task Created')->firstOrFail();

        // 2. Update
        $response = $this->actingAs($this->owner)->put(route('tasks.update', $task), [
            'title' => 'Web Task Updated',
            'priority' => 'urgent',
        ]);
        $response->assertSessionHasNoErrors();
        Event::assertDispatched(TaskUpdatedEvent::class, function ($event) use ($task) {
            return $event->task->id === $task->id && $event->task->title === 'Web Task Updated';
        });

        // 3. Move
        $response = $this->actingAs($this->owner)->post(route('tasks.move', $task), [
            'stage_id' => $this->stage2->id,
            'position' => 1,
        ]);
        $response->assertStatus(200);
        Event::assertDispatched(TaskMovedEvent::class, function ($event) use ($task) {
            return $event->taskId === $task->id && $event->newStageId === $this->stage2->id;
        });

        // 4. Destroy
        $response = $this->actingAs($this->owner)->delete(route('tasks.destroy', $task));
        Event::assertDispatched(TaskDeletedEvent::class, function ($event) use ($task) {
            return $event->taskId === $task->id && $event->spaceId === $this->space->id;
        });
    }

    public function test_web_checklist_toggle_dispatches_realtime_event(): void
    {
        Event::fake([TaskChecklistToggledEvent::class]);

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->owner->id,
            'title' => 'Checklist Task',
            'position' => 1,
        ]);

        $checklist = $task->checklists()->create([
            'title' => 'Sub item 1',
            'is_completed' => false,
            'position' => 1,
        ]);

        $response = $this->actingAs($this->member)->postJson(route('tasks.checklists.toggle', [$task, $checklist]));
        $response->assertStatus(200);

        Event::assertDispatched(TaskChecklistToggledEvent::class, function ($event) use ($task, $checklist) {
            return $event->taskId === $task->id
                && $event->checklistId === $checklist->id
                && $event->isCompleted === true;
        });
    }

    public function test_web_comment_dispatches_realtime_event(): void
    {
        Event::fake([TaskCommentAddedEvent::class]);

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->owner->id,
            'title' => 'Comment Task',
            'position' => 1,
        ]);

        $response = $this->actingAs($this->member)->postJson(route('tasks.comments.store', $task), [
            'content' => 'This is a live discussion comment.',
        ]);
        $response->assertStatus(201);

        Event::assertDispatched(TaskCommentAddedEvent::class, function ($event) use ($task) {
            return $event->taskId === $task->id
                && $event->spaceId === $this->space->id;
        });
    }

    public function test_web_attachment_dispatches_realtime_events(): void
    {
        Event::fake([
            TaskAttachmentUploadedEvent::class,
            TaskAttachmentDeletedEvent::class,
        ]);
        Storage::fake('local');
        Storage::fake('public');

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->owner->id,
            'title' => 'Attachment Task',
            'position' => 1,
        ]);

        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        // Store attachment
        $response = $this->actingAs($this->owner)->postJson(route('tasks.attachments.store', $task), [
            'file' => $file,
        ]);
        $response->assertStatus(201);
        Event::assertDispatched(TaskAttachmentUploadedEvent::class, function ($event) use ($task) {
            return $event->taskId === $task->id && $event->spaceId === $this->space->id;
        });

        $attachment = TaskAttachment::where('task_id', $task->id)->firstOrFail();

        // Delete attachment
        $response = $this->actingAs($this->owner)->deleteJson(route('tasks.attachments.destroy', [$task, $attachment]));
        $response->assertStatus(200);
        Event::assertDispatched(TaskAttachmentDeletedEvent::class, function ($event) use ($task, $attachment) {
            return $event->taskId === $task->id && $event->attachmentId === $attachment->id;
        });
    }

    public function test_web_stage_crud_dispatches_stage_changed_event(): void
    {
        Event::fake([SpaceStageChangedEvent::class]);

        // 1. Store stage
        $response = $this->actingAs($this->owner)->postJson(route('stages.store'), [
            'list_id' => $this->list->id,
            'name' => 'Review',
            'color' => '#f59e0b',
        ]);
        $response->assertStatus(200);
        Event::assertDispatched(SpaceStageChangedEvent::class, function ($event) {
            return $event->action === 'created' && $event->spaceId === $this->space->id;
        });

        $newStage = TaskStage::where('name', 'Review')->firstOrFail();

        // 2. Update stage
        $response = $this->actingAs($this->owner)->putJson(route('stages.update', $newStage), [
            'name' => 'Review Updated',
            'color' => '#10b981',
        ]);
        $response->assertStatus(200);
        Event::assertDispatched(SpaceStageChangedEvent::class, function ($event) {
            return $event->action === 'updated' && $event->spaceId === $this->space->id;
        });

        // 3. Destroy stage
        $response = $this->actingAs($this->owner)->deleteJson(route('stages.destroy', $newStage));
        $response->assertStatus(200);
        Event::assertDispatched(SpaceStageChangedEvent::class, function ($event) {
            return $event->action === 'deleted' && $event->spaceId === $this->space->id;
        });
    }

    public function test_mobile_api_actions_dispatch_broadcast_events(): void
    {
        Event::fake([
            TaskCreatedEvent::class,
            TaskUpdatedEvent::class,
            TaskMovedEvent::class,
            TaskDeletedEvent::class,
            TaskChecklistToggledEvent::class,
            TaskCommentAddedEvent::class,
        ]);

        $apiMember = User::factory()->create();
        $this->workspace->members()->attach($apiMember->id, ['role' => 'member']);
        $this->space->members()->attach($apiMember->id, ['role' => 'member']);

        // 1. Mobile Store Task
        $response = $this->actingAs($apiMember, 'sanctum')->postJson('/api/v1/tasks', [
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'title' => 'Mobile Task',
            'priority' => 'medium',
        ]);
        $response->assertStatus(201);
        Event::assertDispatched(TaskCreatedEvent::class, function ($event) {
            return $event->task->title === 'Mobile Task';
        });

        $task = Task::where('title', 'Mobile Task')->firstOrFail();

        // 2. Mobile Update Task
        $response = $this->actingAs($apiMember, 'sanctum')->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Mobile Task Updated',
            'priority' => 'urgent',
        ]);
        $response->assertStatus(200);
        Event::assertDispatched(TaskUpdatedEvent::class, function ($event) use ($task) {
            return $event->task->id === $task->id && $event->task->title === 'Mobile Task Updated';
        });

        // 3. Mobile Move Task
        $response = $this->actingAs($apiMember, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/move", [
            'stage_id' => $this->stage2->id,
            'position' => 1,
        ]);
        $response->assertStatus(200);
        Event::assertDispatched(TaskMovedEvent::class, function ($event) use ($task) {
            return $event->taskId === $task->id && $event->newStageId === $this->stage2->id;
        });

        // 4. Mobile Toggle Checklist
        $checklist = $task->checklists()->create(['title' => 'Mobile check', 'position' => 1]);
        $response = $this->actingAs($apiMember, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/checklists/{$checklist->id}/toggle");
        $response->assertStatus(200);
        Event::assertDispatched(TaskChecklistToggledEvent::class, function ($event) use ($checklist) {
            return $event->checklistId === $checklist->id;
        });

        // 5. Mobile Add Comment
        $response = $this->actingAs($apiMember, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/comments", [
            'content' => 'Mobile comment message',
        ]);
        $response->assertStatus(201);
        Event::assertDispatched(TaskCommentAddedEvent::class, function ($event) use ($task) {
            return $event->taskId === $task->id;
        });

        // 6. Mobile Destroy Task
        $response = $this->actingAs($apiMember, 'sanctum')->deleteJson("/api/v1/tasks/{$task->id}");
        $response->assertStatus(200);
        Event::assertDispatched(TaskDeletedEvent::class, function ($event) use ($task) {
            return $event->taskId === $task->id;
        });
    }

    public function test_notification_sent_event_channel_and_payload(): void
    {
        $notification = Notification::create([
            'user_id' => $this->member->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'comment_added',
            'data' => [
                'title' => 'Komentar Baru',
                'message' => 'Halo ini komentar',
            ],
            'read_at' => null,
        ]);

        $event = new NotificationSentEvent($this->member->id, $notification, 3);
        $channels = array_map(fn ($c) => $c->name, $event->broadcastOn());

        $this->assertContains('private-App.Models.User.' . $this->member->id, $channels);
        $this->assertContains('private-user.' . $this->member->id, $channels);

        $payload = $event->broadcastWith();
        $this->assertEquals(3, $payload['unread_count']);
        $this->assertEquals('Komentar Baru', $payload['notification']['data']['title']);
        $this->assertEquals($this->member->id, $payload['notification']['user_id']);
    }

    public function test_task_comment_creates_notification_and_broadcasts_to_assignee(): void
    {
        Event::fake([NotificationSentEvent::class, TaskCommentAddedEvent::class]);

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->owner->id,
            'title' => 'Design Architecture',
            'priority' => 'high',
            'position' => 1,
        ]);
        $task->assignees()->attach($this->member->id);

        // Owner writes a comment
        $response = $this->actingAs($this->owner)->postJson("/tasks/{$task->id}/comments", [
            'content' => 'Tolong review spec ini secepatnya.',
        ]);

        $response->assertStatus(201);

        // Member must receive an in-app Notification record
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->member->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'comment_added',
        ]);

        // Owner (the author) must NOT receive a notification for their own comment
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'comment_added',
        ]);

        Event::assertDispatched(NotificationSentEvent::class, function ($event) {
            return $event->userId === $this->member->id;
        });
    }

    public function test_task_move_creates_notification_for_assignees(): void
    {
        Event::fake([NotificationSentEvent::class, TaskMovedEvent::class]);

        $task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage1->id,
            'created_by' => $this->owner->id,
            'title' => 'Implement Feature',
            'priority' => 'medium',
            'position' => 1,
        ]);
        $task->assignees()->attach($this->member->id);

        // Owner moves task to Done
        $response = $this->actingAs($this->owner)->postJson("/tasks/{$task->id}/move", [
            'stage_id' => $this->stage2->id,
            'position' => 1,
        ]);

        $response->assertStatus(200);

        // Member gets notified of stage change
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->member->id,
            'type' => 'task_moved',
        ]);

        Event::assertDispatched(NotificationSentEvent::class, function ($event) {
            return $event->userId === $this->member->id;
        });
    }
}
