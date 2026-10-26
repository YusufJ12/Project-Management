<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\TaskChecklistToggledEvent;
use App\Events\TaskCommentAddedEvent;
use App\Events\TaskCreatedEvent;
use App\Events\TaskDeletedEvent;
use App\Events\TaskMovedEvent;
use App\Events\TaskUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TaskChecklistResource;
use App\Http\Resources\Api\TaskCommentResource;
use App\Http\Resources\Api\TaskResource;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Services\NotificationService;
use App\Services\TaskNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'exists:lists,id'],
            'stage_id' => ['required', 'exists:task_stages,id'],
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['exists:users,id'],
            'dependency_ids' => ['nullable', 'array'],
            'dependency_ids.*' => ['exists:tasks,id'],
        ]);

        $user = $request->user();
        $list = TaskList::with('space.workspace')->findOrFail($validated['list_id']);
        $space = $list->space;
        $workspace = $space->workspace;

        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        if ($user->isViewerInSpace($space)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                'error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
            ], 403);
        }

        $stage = TaskStage::where('id', $validated['stage_id'])
            ->where('list_id', $list->id)
            ->whereHas('list', fn ($q) => $q->where('space_id', $space->id))
            ->first();

        if (! $stage) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tahapan (stage) tidak valid atau tidak termasuk dalam Space yang sama.',
                'errors' => ['stage_id' => ['Tahapan tidak valid atau tidak termasuk dalam Space yang sama.']],
            ], 422);
        }

        // Validasi isolasi tenant untuk assignee_ids: harus anggota workspace
        if (! empty($validated['assignee_ids'])) {
            $assigneeIds = array_unique($validated['assignee_ids']);
            $validMembersCount = $workspace ? $workspace->members()->whereIn('users.id', $assigneeIds)->count() : 0;
            if ($validMembersCount !== count($assigneeIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Satu atau lebih assignee bukan anggota dari workspace ini.',
                    'errors' => ['assignee_ids' => ['Satu atau lebih assignee bukan anggota dari workspace ini.']],
                ], 422);
            }
        }

        // Validasi isolasi tenant untuk dependency_ids: harus task dalam Space yang sama
        if (! empty($validated['dependency_ids'])) {
            $depIds = array_unique($validated['dependency_ids']);
            $validDepsCount = Task::whereIn('id', $depIds)
                ->whereHas('list', fn ($q) => $q->where('space_id', $space->id))
                ->count();
            if ($validDepsCount !== count($depIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Satu atau lebih dependensi tidak valid atau berasal dari Space berbeda.',
                    'errors' => ['dependency_ids' => ['Satu atau lebih dependensi tidak valid atau berasal dari Space berbeda.']],
                ], 422);
            }
        }

        $maxPosition = Task::where('list_id', $list->id)
            ->where('stage_id', $stage->id)
            ->max('position') ?? 0;

        $task = Task::create([
            'list_id' => $list->id,
            'stage_id' => $stage->id,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'start_date' => $validated['start_date'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'position' => $maxPosition + 1,
        ]);

        if (! empty($validated['assignee_ids'])) {
            $task->assignees()->sync($validated['assignee_ids']);

            NotificationService::sendToMany(
                $validated['assignee_ids'],
                $space->workspace_id,
                'task_assigned',
                [
                    'title' => 'Tugas Baru Ditugaskan',
                    'message' => "{$user->name} menugaskan Anda pada: {$task->title}",
                    'actor_name' => $user->name,
                    'actor_avatar' => $user->avatar_path ?? null,
                    'url' => route('spaces.show', ['space' => $space->slug]) . "?task_id={$task->id}&tab=comments",
                    'task_id' => $task->id,
                    'space_slug' => $space->slug,
                ],
                $user->id
            );
        }

        if (! empty($validated['dependency_ids'])) {
            $task->dependencies()->sync($validated['dependency_ids']);
            $task->adjustDatesToDependencies();
        }

        ActivityLog::create([
            'workspace_id' => $space->workspace_id,
            'space_id' => $space->id,
            'list_id' => $list->id,
            'actor_id' => $user->id,
            'action' => 'task_created',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => [
                'title' => $task->title,
                'stage' => $stage->name,
                'priority' => $task->priority,
            ],
        ]);

        $task->load(['stage', 'assignees', 'checklists']);

        DB::afterCommit(function () use ($task, $user, $space) {
            try {
                broadcast(new TaskCreatedEvent($task, $user, $space->id));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Task berhasil dibuat.',
            'data' => new TaskResource($task),
        ], 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $task->load([
            'stage',
            'assignees',
            'checklists.completedBy',
            'comments.user',
            'dependencies:id,title,start_date,due_at',
            'dependents:id,title,start_date,due_at',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => new TaskResource($task),
        ]);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'stage_id' => ['nullable', 'exists:task_stages,id'],
            'start_date' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'estimate_minutes' => ['nullable', 'integer', 'min:0'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['exists:users,id'],
            'dependency_ids' => ['nullable', 'array'],
            'dependency_ids.*' => ['exists:tasks,id'],
        ]);

        $user = $request->user();
        $task->load('list.space.workspace', 'stage');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        if ($user->isViewerInSpace($space)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                'error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
            ], 403);
        }

        $wasDone = (bool) ($task->stage?->is_done);
        $oldStartDate = $task->start_date ? Carbon::parse($task->start_date)->toDateString() : null;
        $oldDueAt = $task->due_at ? Carbon::parse($task->due_at)->toDateString() : null;

        // Validasi isolasi tenant untuk stage
        if (! empty($validated['stage_id'])) {
            $newStage = TaskStage::where('id', $validated['stage_id'])
                ->where('list_id', $task->list_id)
                ->whereHas('list', fn ($q) => $q->where('space_id', $space->id))
                ->first();

            if (! $newStage) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tahapan (stage) tidak valid atau tidak termasuk dalam Space ini.',
                    'errors' => ['stage_id' => ['Tahapan tidak valid atau tidak termasuk dalam Space ini.']],
                ], 422);
            }
        }

        // Validasi isolasi tenant untuk assignee_ids: harus anggota workspace
        if (isset($validated['assignee_ids'])) {
            $assigneeIds = array_unique((array) $validated['assignee_ids']);
            $workspace = $space->workspace;
            $validMembersCount = $workspace ? $workspace->members()->whereIn('users.id', $assigneeIds)->count() : 0;
            if ($validMembersCount !== count($assigneeIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Satu atau lebih assignee bukan anggota dari workspace ini.',
                    'errors' => ['assignee_ids' => ['Satu atau lebih assignee bukan anggota dari workspace ini.']],
                ], 422);
            }
        }

        // Validasi isolasi tenant untuk dependency_ids: harus task dalam Space yang sama
        if (isset($validated['dependency_ids'])) {
            $depIds = array_unique((array) $validated['dependency_ids']);
            if (in_array($task->id, $depIds, true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Task tidak dapat bergantung pada dirinya sendiri.',
                    'errors' => ['dependency_ids' => ['Task tidak dapat bergantung pada dirinya sendiri.']],
                ], 422);
            }

            $validDepsCount = Task::whereIn('id', $depIds)
                ->whereHas('list', fn ($q) => $q->where('space_id', $space->id))
                ->count();
            if ($validDepsCount !== count($depIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Satu atau lebih dependensi tidak valid atau berasal dari Space berbeda.',
                    'errors' => ['dependency_ids' => ['Satu atau lebih dependensi tidak valid atau berasal dari Space berbeda.']],
                ], 422);
            }
        }

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'stage_id' => $validated['stage_id'] ?? $task->stage_id,
            'start_date' => $validated['start_date'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'estimate_minutes' => $validated['estimate_minutes'] ?? null,
        ]);

        if (isset($validated['assignee_ids'])) {
            $oldAssigneeIds = $task->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
            $task->assignees()->sync($validated['assignee_ids']);
            $newlyAssignedIds = array_diff(array_map('intval', (array) $validated['assignee_ids']), $oldAssigneeIds);

            if (! empty($newlyAssignedIds)) {
                NotificationService::sendToMany(
                    $newlyAssignedIds,
                    $space->workspace_id,
                    'task_assigned',
                    [
                        'title' => 'Tugas Baru Ditugaskan',
                        'message' => "{$user->name} menugaskan Anda pada: {$task->title}",
                        'actor_name' => $user->name,
                        'actor_avatar' => $user->avatar_path ?? null,
                        'url' => route('spaces.show', ['space' => $space->slug]) . "?task_id={$task->id}&tab=comments",
                        'task_id' => $task->id,
                        'space_slug' => $space->slug,
                    ],
                    $user->id
                );
            }
        }

        if (isset($validated['dependency_ids'])) {
            $task->dependencies()->sync($validated['dependency_ids']);
            $task->adjustDatesToDependencies();
        }

        $task->refresh();
        $newStartDate = $task->start_date ? Carbon::parse($task->start_date)->toDateString() : null;
        $newDueAt = $task->due_at ? Carbon::parse($task->due_at)->toDateString() : null;

        if ($oldStartDate !== $newStartDate || $oldDueAt !== $newDueAt) {
            $task->cascadeTimeline();
        }

        // Email notification jika berubah ke status done
        $task->load('stage');
        $isDone = (bool) ($task->stage?->is_done);
        if (! $wasDone && $isDone) {
            TaskNotificationService::notifyTaskCompleted($task, $user);
        }

        $task->load(['stage', 'assignees', 'checklists']);

        DB::afterCommit(function () use ($task, $user, $space) {
            try {
                broadcast(new TaskUpdatedEvent($task, $user, $space->id));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Task berhasil diperbarui.',
            'data' => new TaskResource($task),
        ]);
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'exists:task_stages,id'],
            'position' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $task->load('list.space.workspace', 'stage');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        if ($user->isViewerInSpace($space)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                'error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
            ], 403);
        }

        $newStageId = (int) $validated['stage_id'];
        $newPosition = (int) $validated['position'];
        $oldStageId = (int) $task->stage_id;
        $oldPosition = (int) $task->position;

        $targetStage = TaskStage::where('id', $newStageId)
            ->where('list_id', $task->list_id)
            ->firstOrFail();

        $wasDone = (bool) ($task->stage?->is_done);
        $isDone = (bool) ($targetStage->is_done);

        DB::transaction(function () use ($task, $oldStageId, $newStageId, $oldPosition, $newPosition, $targetStage, $space, $user) {
            if ($oldStageId === $newStageId) {
                if ($oldPosition < $newPosition) {
                    Task::where('stage_id', $oldStageId)
                        ->where('id', '!=', $task->id)
                        ->whereBetween('position', [$oldPosition + 1, $newPosition])
                        ->decrement('position');
                } elseif ($oldPosition > $newPosition) {
                    Task::where('stage_id', $oldStageId)
                        ->where('id', '!=', $task->id)
                        ->whereBetween('position', [$newPosition, $oldPosition - 1])
                        ->increment('position');
                }

                $task->update(['position' => $newPosition]);
            } else {
                Task::where('stage_id', $oldStageId)
                    ->where('id', '!=', $task->id)
                    ->where('position', '>', $oldPosition)
                    ->decrement('position');

                Task::where('stage_id', $newStageId)
                    ->where('position', '>=', $newPosition)
                    ->increment('position');

                $task->update([
                    'stage_id' => $newStageId,
                    'position' => $newPosition,
                ]);

                ActivityLog::create([
                    'workspace_id' => $space->workspace_id,
                    'space_id' => $space->id,
                    'list_id' => $task->list_id,
                    'actor_id' => $user->id,
                    'action' => 'task_moved',
                    'subject_type' => Task::class,
                    'subject_id' => $task->id,
                    'metadata' => [
                        'title' => $task->title,
                        'new_stage' => $targetStage->name,
                    ],
                ]);
            }
        });

        // In-app realtime notification jika berpindah stage
        if ($oldStageId !== $newStageId) {
            $recipientIds = $task->assignees()->pluck('users.id')->all();
            if ($task->creator_id) {
                $recipientIds[] = (int) $task->creator_id;
            }
            NotificationService::sendToMany(
                array_unique($recipientIds),
                $space->workspace_id,
                'task_moved',
                [
                    'title' => 'Status Tugas Diperbarui',
                    'message' => "{$user->name} memindahkan \"{$task->title}\" ke {$targetStage->name}",
                    'actor_name' => $user->name,
                    'actor_avatar' => $user->avatar_path ?? null,
                    'url' => route('spaces.show', ['space' => $space->slug]) . "?task_id={$task->id}&tab=comments",
                    'task_id' => $task->id,
                    'space_slug' => $space->slug,
                ],
                $user->id
            );
        }

        // Email notification jika berpindah ke stage done
        if (! $wasDone && $isDone) {
            $task->load('stage');
            TaskNotificationService::notifyTaskCompleted($task, $user);
        }

        $task->load(['stage', 'assignees', 'checklists']);

        DB::afterCommit(function () use ($task, $oldStageId, $newStageId, $newPosition, $user, $space) {
            try {
                broadcast(new TaskMovedEvent($task->id, $oldStageId, $newStageId, $newPosition, $task, $user, $space->id));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Task berhasil dipindahkan.',
            'data' => new TaskResource($task),
        ]);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $task->load('list.space.workspace');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        if ($user->isViewerInSpace($space)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                'error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
            ], 403);
        }

        $taskId = (int) $task->id;
        $stageId = (int) $task->stage_id;
        $spaceId = (int) $space->id;

        $task->delete();

        DB::afterCommit(function () use ($spaceId, $taskId, $stageId, $user) {
            try {
                broadcast(new TaskDeletedEvent($spaceId, $taskId, $stageId, $user));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Task berhasil dihapus.',
        ]);
    }

    public function addChecklist(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $task->load('list.space.workspace');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        if ($user->isViewerInSpace($space)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                'error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
            ], 403);
        }

        $maxPos = $task->checklists()->max('position') ?? 0;
        $checklist = $task->checklists()->create([
            'title' => $validated['title'],
            'is_completed' => false,
            'position' => $maxPos + 1,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Checklist item berhasil ditambahkan.',
            'data' => new TaskChecklistResource($checklist),
        ], 201);
    }

    public function toggleChecklist(Request $request, Task $task, TaskChecklist $checklist): JsonResponse
    {
        $user = $request->user();
        $task->load('list.space.workspace');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() || $checklist->task_id !== $task->id) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        if ($user->isViewerInSpace($space)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                'error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
            ], 403);
        }

        $newStatus = ! $checklist->is_completed;
        $checklist->update([
            'is_completed' => $newStatus,
            'completed_at' => $newStatus ? now() : null,
            'completed_by' => $newStatus ? $user->id : null,
        ]);

        $checklist->load('completedBy');

        $checklistsCount = $task->checklists()->count();
        $completedChecklistsCount = $task->checklists()->where('is_completed', true)->count();

        DB::afterCommit(function () use ($space, $task, $checklist, $newStatus, $checklistsCount, $completedChecklistsCount, $user) {
            try {
                broadcast(new TaskChecklistToggledEvent(
                    $space->id,
                    $task->id,
                    $checklist->id,
                    $newStatus,
                    $checklistsCount,
                    $completedChecklistsCount,
                    $user
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Status checklist diperbarui.',
            'data' => new TaskChecklistResource($checklist),
        ]);
    }

    public function addComment(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $task->load('list.space.workspace');
        $space = $task->list->space;

        $hasAccess = $user->isSuperAdmin() ||
            $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() ||
            $space->members()->where('users.id', $user->id)->exists();

        if (! $hasAccess) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
        ]);

        $comment->load('user');

        $recipientIds = $task->assignees()->pluck('users.id')->all();
        if ($task->creator_id) {
            $recipientIds[] = (int) $task->creator_id;
        }
        if (empty($recipientIds)) {
            $recipientIds = $space->members()->wherePivot('role', 'manager')->pluck('users.id')->all();
        }

        NotificationService::sendToMany(
            array_unique($recipientIds),
            $space->workspace_id,
            'comment_added',
            [
                'title' => "Komentar Baru: {$task->title}",
                'message' => "{$user->name}: " . mb_substr($comment->content, 0, 80),
                'actor_name' => $user->name,
                'actor_avatar' => $user->avatar_path ?? null,
                'url' => route('spaces.show', ['space' => $space->slug]) . "?task_id={$task->id}",
                'task_id' => $task->id,
                'space_slug' => $space->slug,
            ],
            $user->id
        );

        DB::afterCommit(function () use ($space, $task, $comment) {
            try {
                broadcast(new TaskCommentAddedEvent($space->id, $task->id, $comment))->toOthers();
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Komentar berhasil dikirim.',
            'data' => new TaskCommentResource($comment),
        ], 201);
    }

    public function myTasks(Request $request): JsonResponse
    {
        $user = $request->user();

        $tasks = Task::query()
            ->where(function ($query) use ($user) {
                $query->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhere('created_by', $user->id);
            })
            ->with(['stage', 'list.space.workspace', 'assignees', 'checklists'])
            ->latest()
            ->take(50)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => TaskResource::collection($tasks),
        ]);
    }
}
