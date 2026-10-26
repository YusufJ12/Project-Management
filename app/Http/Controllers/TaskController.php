<?php

namespace App\Http\Controllers;

use App\Events\TaskCreatedEvent;
use App\Events\TaskDeletedEvent;
use App\Events\TaskMovedEvent;
use App\Events\TaskUpdatedEvent;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Services\TaskNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
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

        $user = Auth::user();
        $list = TaskList::with('space.workspace')->findOrFail($validated['list_id']);
        $space = $list->space;
        $workspace = $space->workspace;

        // Otorisasi: Pengguna harus anggota workspace
        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Anda tidak memiliki hak akses untuk membuat task di proyek ini.'], 403);
            }
            return back()->with('error', 'Anda tidak memiliki hak akses untuk membuat task di proyek ini.');
        }

        // Cek peran Viewer
        if ($user->isViewerInSpace($space)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.'], 403);
            }
            return back()->with('error', 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.');
        }

        // Pastikan stage milik list dan space yang sama
        $stage = TaskStage::where('id', $validated['stage_id'])
            ->where('list_id', $list->id)
            ->whereHas('list', fn ($q) => $q->where('space_id', $space->id))
            ->first();

        if (! $stage) {
            $msg = 'Stage tidak valid atau tidak termasuk dalam Space yang sama.';
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['stage_id' => [$msg]]], 422);
            }
            return back()->withErrors(['stage_id' => $msg]);
        }

        // Validasi isolasi tenant untuk assignee_ids: harus anggota workspace
        if (! empty($validated['assignee_ids'])) {
            $assigneeIds = array_unique($validated['assignee_ids']);
            $validMembersCount = $workspace ? $workspace->members()->whereIn('users.id', $assigneeIds)->count() : 0;
            if ($validMembersCount !== count($assigneeIds)) {
                $msg = 'Satu atau lebih assignee bukan anggota dari workspace ini.';
                if ($request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['assignee_ids' => [$msg]]], 422);
                }
                return back()->withErrors(['assignee_ids' => $msg]);
            }
        }

        // Validasi isolasi tenant untuk dependency_ids: harus task dalam Space yang sama
        if (! empty($validated['dependency_ids'])) {
            $depIds = array_unique($validated['dependency_ids']);
            $validDepsCount = Task::whereIn('id', $depIds)
                ->whereHas('list', fn ($q) => $q->where('space_id', $space->id))
                ->count();
            if ($validDepsCount !== count($depIds)) {
                $msg = 'Satu atau lebih dependensi tidak valid atau berasal dari Space berbeda.';
                if ($request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['dependency_ids' => [$msg]]], 422);
                }
                return back()->withErrors(['dependency_ids' => $msg]);
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

        $task->load([
            'stage',
            'assignees:id,name,email',
            'checklists.completedBy:id,name,email',
        ]);

        DB::afterCommit(function () use ($task, $user, $space) {
            try {
                broadcast(new TaskCreatedEvent($task, $user, $space->id));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Task '{$task->title}' berhasil dibuat!",
                'task' => $task,
            ], 201);
        }

        return back()->with('success', "Task '{$task->title}' berhasil dibuat!");
    }

    public function show(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $task->load([
            'list.space.workspace',
            'stage',
            'creator:id,name,email',
            'assignees:id,name,email',
            'checklists.completedBy:id,name,email',
            'comments.user:id,name,email',
            'attachments.uploader:id,name,email',
            'dependencies:id,title,start_date,due_at',
            'dependents:id,title,start_date,due_at',
        ]);

        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        $activityLogs = ActivityLog::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->with('actor:id,name,email')
            ->latest()
            ->take(30)
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'task' => $task,
                'activity_logs' => $activityLogs,
            ]);
        }

        return redirect()->route('spaces.show', $space->slug);
    }

    public function update(Request $request, Task $task): JsonResponse|RedirectResponse
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

        $user = Auth::user();
        $task->load('list.space.workspace', 'stage');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        // Cek peran Viewer
        if ($user->isViewerInSpace($space)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.'], 403);
            }
            return back()->with('error', 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.');
        }

        $wasDone = (bool) ($task->stage?->is_done);
        $oldStartDate = $task->start_date ? Carbon::parse($task->start_date)->toDateString() : null;
        $oldDueAt = $task->due_at ? Carbon::parse($task->due_at)->toDateString() : null;

        // Cek validasi tenant isolasi untuk stage
        if (! empty($validated['stage_id']) && (int) $validated['stage_id'] !== (int) $task->stage_id) {
            $newStage = TaskStage::where('id', $validated['stage_id'])
                ->where('list_id', $task->list_id)
                ->whereHas('list', fn ($q) => $q->where('space_id', $space->id))
                ->first();

            if (! $newStage) {
                $msg = 'Stage tidak valid atau tidak termasuk dalam Space ini.';
                if ($request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['stage_id' => [$msg]]], 422);
                }
                return back()->withErrors(['stage_id' => $msg]);
            }

            ActivityLog::create([
                'workspace_id' => $space->workspace_id,
                'space_id' => $space->id,
                'list_id' => $task->list_id,
                'actor_id' => $user->id,
                'action' => 'stage_changed',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'metadata' => [
                    'title' => $task->title,
                    'stage' => $newStage->name,
                ],
            ]);
        }

        // Validasi isolasi tenant untuk assignee_ids: harus anggota workspace
        if (isset($validated['assignee_ids'])) {
            $assigneeIds = array_unique((array) $validated['assignee_ids']);
            $workspace = $space->workspace;
            $validMembersCount = $workspace ? $workspace->members()->whereIn('users.id', $assigneeIds)->count() : 0;
            if ($validMembersCount !== count($assigneeIds)) {
                $msg = 'Satu atau lebih assignee bukan anggota dari workspace ini.';
                if ($request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['assignee_ids' => [$msg]]], 422);
                }
                return back()->withErrors(['assignee_ids' => $msg]);
            }
        }

        // Validasi isolasi tenant untuk dependency_ids: harus task dalam Space yang sama
        if (isset($validated['dependency_ids'])) {
            $depIds = array_unique((array) $validated['dependency_ids']);
            if (in_array($task->id, $depIds, true)) {
                $msg = 'Task tidak dapat bergantung pada dirinya sendiri.';
                if ($request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['dependency_ids' => [$msg]]], 422);
                }
                return back()->withErrors(['dependency_ids' => $msg]);
            }

            $validDepsCount = Task::whereIn('id', $depIds)
                ->whereHas('list', fn ($q) => $q->where('space_id', $space->id))
                ->count();
            if ($validDepsCount !== count($depIds)) {
                $msg = 'Satu atau lebih dependensi tidak valid atau berasal dari Space berbeda.';
                if ($request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['dependency_ids' => [$msg]]], 422);
                }
                return back()->withErrors(['dependency_ids' => $msg]);
            }
        }

        // Cek perubahan prioritas
        if ($validated['priority'] !== $task->priority) {
            ActivityLog::create([
                'workspace_id' => $space->workspace_id,
                'space_id' => $space->id,
                'list_id' => $task->list_id,
                'actor_id' => $user->id,
                'action' => 'priority_changed',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'metadata' => [
                    'title' => $task->title,
                    'old_priority' => $task->priority,
                    'new_priority' => $validated['priority'],
                ],
            ]);
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
            $task->assignees()->sync($validated['assignee_ids']);
        }

        // Sync dependencies if provided
        if (isset($validated['dependency_ids'])) {
            $oldDepIds = $task->dependencies()->pluck('tasks.id')->toArray();
            $task->dependencies()->sync($validated['dependency_ids']);
            $newDepIds = (array) $validated['dependency_ids'];

            // Catat log penambahan dependensi baru
            $addedIds = array_diff($newDepIds, $oldDepIds);
            foreach ($addedIds as $addId) {
                $prereqTask = Task::find($addId);
                if ($prereqTask) {
                    ActivityLog::create([
                        'workspace_id' => $space->workspace_id,
                        'space_id' => $space->id,
                        'list_id' => $task->list_id,
                        'actor_id' => $user->id,
                        'action' => 'dependency_added',
                        'subject_type' => Task::class,
                        'subject_id' => $task->id,
                        'metadata' => [
                            'title' => $task->title,
                            'prerequisite_title' => $prereqTask->title,
                        ],
                    ]);
                }
            }

            // Catat log penghapusan dependensi jika ada yang dicopot
            $removedIds = array_diff($oldDepIds, $newDepIds);
            if (! empty($removedIds)) {
                ActivityLog::create([
                    'workspace_id' => $space->workspace_id,
                    'space_id' => $space->id,
                    'list_id' => $task->list_id,
                    'actor_id' => $user->id,
                    'action' => 'dependency_removed',
                    'subject_type' => Task::class,
                    'subject_id' => $task->id,
                    'metadata' => [
                        'title' => $task->title,
                    ],
                ]);
            }

            $task->adjustDatesToDependencies();
        }

        // Check if dates changed to trigger cascading timeline shift
        $task->refresh();
        $newStartDate = $task->start_date ? Carbon::parse($task->start_date)->toDateString() : null;
        $newDueAt = $task->due_at ? Carbon::parse($task->due_at)->toDateString() : null;

        if ($oldStartDate !== $newStartDate || $oldDueAt !== $newDueAt) {
            $datesSummary = ($newStartDate ? Carbon::parse($newStartDate)->format('d M Y') : 'Tanpa tgl') . ' s/d ' . ($newDueAt ? Carbon::parse($newDueAt)->format('d M Y') : 'Tanpa tenggat');
            ActivityLog::create([
                'workspace_id' => $space->workspace_id,
                'space_id' => $space->id,
                'list_id' => $task->list_id,
                'actor_id' => $user->id,
                'action' => 'timeline_adjusted',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'metadata' => [
                    'title' => $task->title,
                    'old_start_date' => $oldStartDate,
                    'new_start_date' => $newStartDate,
                    'old_due_at' => $oldDueAt,
                    'new_due_at' => $newDueAt,
                    'dates_summary' => $datesSummary,
                ],
            ]);

            $task->cascadeTimeline();
        }

        // Cek apakah berpindah ke stage done (sebelumnya belum done)
        $task->load('stage');
        $isDone = (bool) ($task->stage?->is_done);
        if (! $wasDone && $isDone) {
            TaskNotificationService::notifyTaskCompleted($task, $user);
        }

        ActivityLog::create([
            'workspace_id' => $space->workspace_id,
            'space_id' => $space->id,
            'list_id' => $task->list_id,
            'actor_id' => $user->id,
            'action' => 'task_updated',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => [
                'title' => $task->title,
            ],
        ]);

        $task->load([
            'stage',
            'assignees:id,name,email',
            'checklists.completedBy:id,name,email',
            'comments.user:id,name,email',
            'attachments.uploader:id,name,email',
            'dependencies:id,title,start_date,due_at',
            'dependents:id,title,start_date,due_at',
        ]);

        DB::afterCommit(function () use ($task, $user, $space) {
            try {
                broadcast(new TaskUpdatedEvent($task, $user, $space->id));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task berhasil diperbarui.',
                'task' => $task,
            ]);
        }

        return back()->with('success', "Task '{$task->title}' berhasil diperbarui.");
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'exists:task_stages,id'],
            'position' => ['required', 'integer', 'min:1'],
        ]);

        $user = Auth::user();
        $task->load('list.space.workspace', 'stage');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        // Cek peran Viewer
        if ($user->isViewerInSpace($space)) {
            return response()->json(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.'], 403);
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
                // Reorder dalam stage yang sama
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
                // Pindah antar stage yang berbeda
                // Geser ke atas task di stage lama
                Task::where('stage_id', $oldStageId)
                    ->where('id', '!=', $task->id)
                    ->where('position', '>', $oldPosition)
                    ->decrement('position');

                // Beri ruang di stage baru
                Task::where('stage_id', $newStageId)
                    ->where('position', '>=', $newPosition)
                    ->increment('position');

                // Update task ke stage baru dan posisi baru
                $task->update([
                    'stage_id' => $newStageId,
                    'position' => $newPosition,
                ]);

                // Catat di activity log
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
                        'is_done' => $targetStage->is_done,
                    ],
                ]);
            }
        });

        // Email notification jika berpindah ke stage done
        if (! $wasDone && $isDone) {
            $task->load('stage');
            TaskNotificationService::notifyTaskCompleted($task, $user);
        }

        $task->load([
            'stage',
            'assignees:id,name,email',
            'checklists.completedBy:id,name,email',
        ]);

        DB::afterCommit(function () use ($task, $oldStageId, $newStageId, $newPosition, $user, $space) {
            try {
                broadcast(new TaskMovedEvent($task->id, $oldStageId, $newStageId, $newPosition, $task, $user, $space->id));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil dipindahkan.',
            'task' => [
                'id' => $task->id,
                'stage_id' => $task->stage_id,
                'position' => $task->position,
            ],
        ]);
    }

    public function destroy(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $task->load('list.space.workspace');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Tidak berwenang menghapus task ini.'], 403);
            }
            return back()->with('error', 'Tidak berwenang menghapus task ini.');
        }

        // Cek peran Viewer
        if ($user->isViewerInSpace($space)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.'], 403);
            }
            return back()->with('error', 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.');
        }

        $title = $task->title;
        $taskId = (int) $task->id;
        $stageId = (int) $task->stage_id;
        $spaceId = (int) $space->id;

        ActivityLog::create([
            'workspace_id' => $space->workspace_id,
            'space_id' => $space->id,
            'list_id' => $task->list_id,
            'actor_id' => $user->id,
            'action' => 'task_deleted',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => [
                'title' => $title,
            ],
        ]);

        $task->delete();

        DB::afterCommit(function () use ($spaceId, $taskId, $stageId, $user) {
            try {
                broadcast(new TaskDeletedEvent($spaceId, $taskId, $stageId, $user));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Task '{$title}' berhasil dihapus.",
            ]);
        }

        return back()->with('success', "Task '{$title}' berhasil dihapus.");
    }
}
