<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function store(Request $request): RedirectResponse
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
        ]);

        $user = Auth::user();
        $list = TaskList::with('space.workspace')->findOrFail($validated['list_id']);
        $space = $list->space;

        // Otorisasi: Pengguna harus anggota workspace
        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk membuat task di proyek ini.');
        }

        // Pastikan stage milik list yang sama
        $stage = TaskStage::where('id', $validated['stage_id'])
            ->where('list_id', $list->id)
            ->firstOrFail();

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
        ]);

        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        // Cek perubahan stage
        if (! empty($validated['stage_id']) && (int) $validated['stage_id'] !== (int) $task->stage_id) {
            $newStage = TaskStage::where('id', $validated['stage_id'])
                ->where('list_id', $task->list_id)
                ->firstOrFail();

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

        if ($request->wantsJson()) {
            $task->load([
                'stage',
                'assignees:id,name,email',
                'checklists.completedBy:id,name,email',
                'comments.user:id,name,email',
                'attachments.uploader:id,name,email',
            ]);

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
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        $newStageId = (int) $validated['stage_id'];
        $newPosition = (int) $validated['position'];
        $oldStageId = (int) $task->stage_id;
        $oldPosition = (int) $task->position;

        $targetStage = TaskStage::where('id', $newStageId)
            ->where('list_id', $task->list_id)
            ->firstOrFail();

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

    public function destroy(Task $task): RedirectResponse
    {
        $user = Auth::user();
        $task->load('list.space');

        if (! $user->workspaces()->where('workspaces.id', $task->list->space->workspace_id)->exists()) {
            return back()->with('error', 'Tidak berwenang menghapus task ini.');
        }

        $title = $task->title;
        $task->delete();

        return back()->with('success', "Task '{$title}' berhasil dihapus.");
    }
}

