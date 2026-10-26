<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskChecklistController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
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

        if ($user->isViewerInSpace($space)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.'], 403);
            }
            return back()->with('error', 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.');
        }

        $maxPosition = $task->checklists()->max('position') ?? 0;

        $checklist = $task->checklists()->create([
            'title' => $validated['title'],
            'is_completed' => false,
            'position' => $maxPosition + 1,
        ]);

        ActivityLog::create([
            'workspace_id' => $space->workspace_id,
            'space_id' => $space->id,
            'list_id' => $task->list_id,
            'actor_id' => $user->id,
            'action' => 'checklist_added',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => [
                'task_title' => $task->title,
                'checklist_title' => $checklist->title,
            ],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Checklist item berhasil ditambahkan.',
                'checklist' => $checklist,
            ], 201);
        }

        return back()->with('success', 'Checklist berhasil ditambahkan.');
    }

    public function toggle(Request $request, Task $task, TaskChecklist $checklist): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() || $checklist->task_id !== $task->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        if ($user->isViewerInSpace($space)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.'], 403);
            }
            return back()->with('error', 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.');
        }

        $newCompleted = ! $checklist->is_completed;

        $checklist->update([
            'is_completed' => $newCompleted,
            'completed_at' => $newCompleted ? now() : null,
            'completed_by' => $newCompleted ? $user->id : null,
        ]);

        ActivityLog::create([
            'workspace_id' => $space->workspace_id,
            'space_id' => $space->id,
            'list_id' => $task->list_id,
            'actor_id' => $user->id,
            'action' => $newCompleted ? 'checklist_completed' : 'checklist_uncompleted',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => [
                'task_title' => $task->title,
                'checklist_title' => $checklist->title,
                'is_completed' => $newCompleted,
            ],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $newCompleted ? 'Checklist diselesaikan.' : 'Checklist dibuka kembali.',
                'checklist' => $checklist->load('completedBy:id,name,email'),
            ]);
        }

        return back()->with('success', 'Status checklist diperbarui.');
    }

    public function destroy(Request $request, Task $task, TaskChecklist $checklist): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() || $checklist->task_id !== $task->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        if ($user->isViewerInSpace($space)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.'], 403);
            }
            return back()->with('error', 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.');
        }

        $checklist->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Checklist item berhasil dihapus.',
            ]);
        }

        return back()->with('success', 'Checklist berhasil dihapus.');
    }
}

