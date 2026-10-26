<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $manager;
    private User $member;
    private User $viewer;
    private User $outsider;
    private Workspace $workspace;
    private Space $space;
    private TaskList $list;
    private TaskStage $stage;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Owner & Workspace
        $this->owner = User::factory()->create(['name' => 'Owner Alice', 'email' => 'owner@nexsphere.test']);
        $this->workspace = Workspace::create([
            'owner_id' => $this->owner->id,
            'name' => 'Security Test Workspace',
            'slug' => 'security-test-workspace',
        ]);
        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);

        // 2. Space Manager
        $this->manager = User::factory()->create(['name' => 'Manager Bob', 'email' => 'manager@nexsphere.test']);
        $this->workspace->members()->attach($this->manager->id, ['role' => 'member']);

        // 3. Regular Member
        $this->member = User::factory()->create(['name' => 'Member Charlie', 'email' => 'member@nexsphere.test']);
        $this->workspace->members()->attach($this->member->id, ['role' => 'member']);

        // 4. Viewer
        $this->viewer = User::factory()->create(['name' => 'Viewer Dave', 'email' => 'viewer@nexsphere.test']);
        $this->workspace->members()->attach($this->viewer->id, ['role' => 'viewer']);

        // 5. Outsider (belongs to different workspace)
        $this->outsider = User::factory()->create(['name' => 'Outsider Eve', 'email' => 'outsider@othercorp.test']);
        $otherWs = Workspace::create(['owner_id' => $this->outsider->id, 'name' => 'Other WS', 'slug' => 'other-ws']);
        $otherWs->members()->attach($this->outsider->id, ['role' => 'owner']);

        // Create Space and assign roles
        $this->space = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Confidential Project Space',
            'slug' => 'confidential-project-space',
            'status' => 'active',
        ]);
        $this->space->members()->attach($this->owner->id, ['role' => 'manager']);
        $this->space->members()->attach($this->manager->id, ['role' => 'manager']);
        $this->space->members()->attach($this->member->id, ['role' => 'member']);
        $this->space->members()->attach($this->viewer->id, ['role' => 'viewer']);

        $this->list = TaskList::create([
            'space_id' => $this->space->id,
            'name' => 'Main Task List',
            'slug' => 'main-task-list',
            'position' => 1,
        ]);

        $this->stage = TaskStage::create([
            'list_id' => $this->list->id,
            'name' => 'In Progress',
            'color' => '#0284c7',
            'is_done' => false,
            'position' => 1,
        ]);

        $this->task = Task::create([
            'list_id' => $this->list->id,
            'stage_id' => $this->stage->id,
            'created_by' => $this->owner->id,
            'title' => 'Core Security Hardening',
            'priority' => 'high',
            'position' => 1,
        ]);
    }

    // =========================================================================
    // 1. API OTORISASI & ROLE PARITY (SPACE API)
    // =========================================================================

    public function test_regular_member_cannot_delete_space_via_api(): void
    {
        $token = $this->member->createToken('member-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/spaces/{$this->space->id}");

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('spaces', ['id' => $this->space->id]);
    }

    public function test_viewer_cannot_delete_space_via_api(): void
    {
        $token = $this->viewer->createToken('viewer-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/spaces/{$this->space->id}");

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('spaces', ['id' => $this->space->id]);
    }

    public function test_space_manager_can_delete_space_via_api(): void
    {
        $token = $this->manager->createToken('manager-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/spaces/{$this->space->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('spaces', ['id' => $this->space->id]);
    }

    public function test_viewer_cannot_create_space_via_api(): void
    {
        $token = $this->viewer->createToken('viewer-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/spaces", [
                'name' => 'Unauthorized Space',
            ]);

        $response->assertStatus(403);
    }

    public function test_viewer_cannot_update_space_via_api(): void
    {
        $token = $this->viewer->createToken('viewer-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/spaces/{$this->space->id}", [
                'name' => 'Renamed Space by Viewer',
            ]);

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_view_space_timeline(): void
    {
        $token = $this->outsider->createToken('outsider-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/spaces/{$this->space->id}/timeline");

        $response->assertStatus(403);
    }

    public function test_viewer_can_view_space_timeline(): void
    {
        $token = $this->viewer->createToken('viewer-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/spaces/{$this->space->id}/timeline");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    // =========================================================================
    // 2. ISOLASI TENANT & VALIDASI LINTAS WORKSPACE (PREVENT IDOR)
    // =========================================================================

    public function test_cross_workspace_assignee_rejected_on_task_creation(): void
    {
        $token = $this->owner->createToken('owner-token')->plainTextToken;

        // Mencoba assign user dari workspace lain ($this->outsider)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/tasks', [
                'list_id' => $this->list->id,
                'stage_id' => $this->stage->id,
                'title' => 'Malicious Cross-Tenant Assignment',
                'assignee_ids' => [$this->outsider->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assignee_ids']);
    }

    public function test_cross_workspace_assignee_rejected_on_task_update(): void
    {
        $token = $this->owner->createToken('owner-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/tasks/{$this->task->id}", [
                'title' => 'Updated Core Task',
                'priority' => 'urgent',
                'assignee_ids' => [$this->outsider->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assignee_ids']);
    }

    public function test_cross_space_or_workspace_dependency_rejected(): void
    {
        // Buat task di space lain (atau workspace lain)
        $otherSpace = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Completely Different Space',
            'slug' => 'completely-different-space',
        ]);
        $otherList = TaskList::create(['space_id' => $otherSpace->id, 'name' => 'Other List', 'slug' => 'other-list']);
        $otherStage = TaskStage::create(['list_id' => $otherList->id, 'name' => 'Todo', 'position' => 1]);
        $alienTask = Task::create([
            'list_id' => $otherList->id,
            'stage_id' => $otherStage->id,
            'created_by' => $this->owner->id,
            'title' => 'Alien Task In Other Space',
        ]);

        $token = $this->owner->createToken('owner-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/tasks/{$this->task->id}", [
                'title' => 'Core Security Task',
                'priority' => 'high',
                'dependency_ids' => [$alienTask->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dependency_ids']);
    }

    public function test_task_cannot_depend_on_itself(): void
    {
        $token = $this->owner->createToken('owner-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/tasks/{$this->task->id}", [
                'title' => 'Self-referencing task',
                'priority' => 'high',
                'dependency_ids' => [$this->task->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dependency_ids']);
    }

    public function test_cross_space_stage_id_rejected_on_task_creation(): void
    {
        // Buat stage dari space lain
        $otherSpace = Space::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Second Space',
            'slug' => 'second-space',
        ]);
        $otherList = TaskList::create(['space_id' => $otherSpace->id, 'name' => 'List 2', 'slug' => 'list-2']);
        $alienStage = TaskStage::create(['list_id' => $otherList->id, 'name' => 'Stage 2', 'position' => 1]);

        $token = $this->owner->createToken('owner-token')->plainTextToken;

        // Mencoba membuat task dengan list_id milik Space 1, tapi stage_id milik Space 2
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/tasks', [
                'list_id' => $this->list->id,
                'stage_id' => $alienStage->id,
                'title' => 'Confused stage mismatch task',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stage_id']);
    }

    // =========================================================================
    // 3. PRIVATE ATTACHMENT STORAGE & STRICT MIME VALIDATION
    // =========================================================================

    public function test_attachment_stored_on_local_private_disk_and_not_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $fakeFile = UploadedFile::fake()->create('project-spec.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->owner)
            ->postJson("/tasks/{$this->task->id}/attachments", [
                'file' => $fakeFile,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $storagePath = $response->json('attachment.storage_path');

        // File wajib tersimpan di disk 'local' privat
        Storage::disk('local')->assertExists($storagePath);

        // File TIDAK boleh bocor ke disk 'public' publik
        Storage::disk('public')->assertMissing($storagePath);

        // Nama file di disk harus UUID/acak dan tidak memakai nama asli mentah
        $this->assertStringNotContainsString('project-spec.pdf', $storagePath);
    }

    public function test_dangerous_attachment_extensions_are_strictly_rejected(): void
    {
        Storage::fake('local');

        $dangerousFiles = [
            UploadedFile::fake()->create('webshell.php', 10, 'application/x-php'),
            UploadedFile::fake()->create('script.phtml', 10, 'text/html'),
            UploadedFile::fake()->create('trojan.exe', 10, 'application/octet-stream'),
            UploadedFile::fake()->create('malware.sh', 10, 'text/x-shellscript'),
            UploadedFile::fake()->create('xss.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->create('phishing.html', 10, 'text/html'),
            UploadedFile::fake()->create('bad.js', 10, 'application/javascript'),
        ];

        foreach ($dangerousFiles as $file) {
            $response = $this->actingAs($this->owner)
                ->postJson("/tasks/{$this->task->id}/attachments", [
                    'file' => $file,
                ]);

            $response->assertStatus(422);
        }
    }

    public function test_attachment_download_and_preview_requires_workspace_auth(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('secret.pdf', 100, 'application/pdf');
        $upload = $this->actingAs($this->owner)
            ->postJson("/tasks/{$this->task->id}/attachments", ['file' => $file]);

        $attachmentId = $upload->json('attachment.id');

        // Outsider tanpa akses workspace mencoba download
        $unauthDownload = $this->actingAs($this->outsider)
            ->get("/tasks/{$this->task->id}/attachments/{$attachmentId}/download");
        $unauthDownload->assertStatus(403);

        // Outsider tanpa akses workspace mencoba preview
        $unauthPreview = $this->actingAs($this->outsider)
            ->get("/tasks/{$this->task->id}/attachments/{$attachmentId}/preview");
        $unauthPreview->assertStatus(403);

        // Member sah dapat preview dan mendapat nosniff header
        $authPreview = $this->actingAs($this->member)
            ->get("/tasks/{$this->task->id}/attachments/{$attachmentId}/preview");
        $authPreview->assertStatus(200)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // =========================================================================
    // 4. API RATE LIMITING & SANCTUM PROTECTION
    // =========================================================================

    public function test_api_login_brute_force_throttled_after_10_requests(): void
    {
        // 10 permintaan login salah berturut-turut
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'login' => 'owner@nexsphere.test',
                'password' => 'wrongpassword',
            ]);
            $response->assertStatus(422);
        }

        // Permintaan ke-11 harus diblokir oleh throttle:10,1 (HTTP 429 Too Many Requests)
        $throttleResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner@nexsphere.test',
            'password' => 'wrongpassword',
        ]);

        $throttleResponse->assertStatus(429);
    }

    public function test_api_logout_revokes_current_sanctum_token(): void
    {
        $token = $this->owner->createToken('logout-test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Pastikan token sudah dicabut dari personal_access_tokens
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->owner->id,
            'name' => 'logout-test-token',
        ]);
    }
}
