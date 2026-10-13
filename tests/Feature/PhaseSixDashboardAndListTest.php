<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseSixDashboardAndListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Space $space;
    private TaskList $list;
    private TaskStage $todoStage;
    private TaskStage $doneStage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Charlie Team Lead',
            'email' => 'charlie@nexsphere.test',
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'name' => 'NexSphere Cloud Workspace',
            'slug' => 'nexsphere-cloud-workspace',
        ]);

        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);

        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Infrastructure Setup',
            'slug' => 'infrastructure-setup',
            'status' => 'active',
        ]);

        $this->space->members()->attach($this->user->id, ['role' => 'manager']);

        $this->list = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Sprint 1',
            'slug' => 'sprint-1',
            'position' => 1,
        ]);

        $this->todoStage = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'To Do',
            'color' => '#0284c7',
            'is_done' => false,
            'position' => 1,
        ]);

        $this->doneStage = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'Done',
            'color' => '#22c55e',
            'is_done' => true,
            'position' => 2,
        ]);
    }

    public function test_user_can_view_dashboard_with_calculated_metrics_and_my_tasks(): void
    {
        // Buat 1 task aktif yang ditugaskan ke user
        $myTask = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->todoStage->id,
            'created_by' => $this->user->id,
            'title' => 'Configure cPanel Redis Cache',
            'priority' => 'urgent',
            'due_at' => now()->addDays(2),
            'position' => 1,
        ]);
        $myTask->assignees()->attach($this->user->id);

        // Buat 1 task selesai
        Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->doneStage->id,
            'created_by' => $this->user->id,
            'title' => 'Setup MySQL Database',
            'priority' => 'medium',
            'position' => 2,
        ]);

        // Buat 1 task overdue
        Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->todoStage->id,
            'created_by' => $this->user->id,
            'title' => 'Expired SSL Check',
            'priority' => 'high',
            'due_at' => now()->subDays(3),
            'position' => 3,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('stats')
                ->where('stats.totalSpaces', 1)
                ->where('stats.activeTasks', 2)
                ->where('stats.completedTasks', 1)
                ->where('stats.overdueTasks', 1)
                ->where('stats.urgentTasksCount', 2) // urgent + high
                ->where('stats.completionRate', 33) // 1/3 = 33%
                ->has('myTasks', 1)
                ->where('myTasks.0.title', 'Configure cPanel Redis Cache')
            );
    }

    public function test_user_can_fetch_notifications_via_api(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'task_assigned',
            'data' => [
                'title' => 'Tugas Baru',
                'message' => 'Anda ditugaskan pada tugas: Configure cPanel Redis Cache',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'notifications');
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $notif = Notification::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'task_deadline_approaching',
            'data' => [
                'title' => 'Tenggat Waktu Menjelang',
                'message' => 'Tugas akan jatuh tempo besok.',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/notifications/{$notif->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'alert_1',
            'data' => ['title' => 'Pesan 1'],
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'alert_2',
            'data' => ['title' => 'Pesan 2'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $unreadCount = Notification::where('user_id', $this->user->id)->whereNull('read_at')->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $otherUser = User::factory()->create();

        $notif = Notification::create([
            'user_id' => $otherUser->id,
            'workspace_id' => $this->workspace->id,
            'type' => 'private_alert',
            'data' => ['title' => 'Rahasia'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/notifications/{$notif->id}/read");

        $response->assertStatus(403);
    }

    public function test_user_can_render_space_show_page_for_list_view(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get("/spaces/{$this->space->slug}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Spaces/Show')
                ->has('space')
                ->where('space.id', $this->space->id)
            );
    }
}

