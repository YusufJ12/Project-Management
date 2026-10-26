<?php

namespace App\Http\Controllers;

use App\Events\TaskCommentAddedEvent;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskCommentController extends Controller
{
    public function index(Request $request, Task $task): JsonResponse
    {
        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        $hasAccess = $user->isSuperAdmin() ||
            $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() ||
            $space->members()->where('users.id', $user->id)->exists();

        if (! $hasAccess) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        $comments = $task->comments()
            ->with('user:id,name,email')
            ->latest('id')
            ->get();

        return response()->json([
            'comments' => $comments,
        ]);
    }

    public function store(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        $hasAccess = $user->isSuperAdmin() ||
            $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() ||
            $space->members()->where('users.id', $user->id)->exists();

        if (! $hasAccess) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
        ]);

        ActivityLog::create([
            'workspace_id' => $space->workspace_id,
            'space_id' => $space->id,
            'list_id' => $task->list_id,
            'actor_id' => $user->id,
            'action' => 'comment_added',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => [
                'task_title' => $task->title,
                'comment_snippet' => mb_substr($comment->content, 0, 80),
            ],
        ]);

        $comment->load('user:id,name,email');

        DB::afterCommit(function () use ($space, $task, $comment) {
            try {
                broadcast(new TaskCommentAddedEvent($space->id, $task->id, $comment));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil dikirim.',
                'comment' => $comment->load('user:id,name,email'),
            ], 201);
        }

        return back()->with('success', 'Komentar berhasil dikirim.');
    }

    public function destroy(Request $request, Task $task, TaskComment $comment): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() || $comment->task_id !== $task->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        // Hanya author komentar atau manager/owner yang boleh menghapus
        $membership = $user->workspaces()->where('workspaces.id', $space->workspace_id)->first();
        $isWorkspaceAdmin = $membership && in_array($membership->pivot->role, ['owner', 'manager']);

        if ($comment->user_id !== $user->id && ! $isWorkspaceAdmin) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Anda tidak berhak menghapus komentar ini.'], 403);
            }
            return back()->with('error', 'Anda tidak berhak menghapus komentar ini.');
        }

        $comment->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil dihapus.',
            ]);
        }

        return back()->with('success', 'Komentar berhasil dihapus.');
    }
}
