<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SpaceResource;
use App\Http\Resources\Api\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $workspaces = $user->workspaces()
            ->withCount(['spaces', 'members'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => WorkspaceResource::collection($workspaces),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']) . '-' . \Illuminate\Support\Str::random(4),
            'owner_id' => $user->id,
        ]);

        $workspace->members()->attach($user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Workspace berhasil dibuat.',
            'data' => new WorkspaceResource($workspace),
        ], 201);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        if ($workspace->owner_id !== $user->id && ! $user->isSuperAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $workspace->update(['name' => $validated['name']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Workspace berhasil diperbarui.',
            'data' => new WorkspaceResource($workspace),
        ]);
    }

    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        if ($workspace->owner_id !== $user->id && ! $user->isSuperAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Hanya pemilik yang dapat menghapus workspace.'], 403);
        }

        $workspace->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Workspace berhasil dihapus.',
        ]);
    }

    public function spaces(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak ke workspace ini.',
            ], 403);
        }

        $isWsOwnerOrAdmin = $user->isSuperAdmin()
            || $workspace->owner_id === $user->id
            || $user->hasWorkspaceRole($workspace->id, ['owner', 'admin']);

        $spaces = $workspace->spaces()
            ->whereNull('archived_at')
            ->when(! $isWsOwnerOrAdmin, function ($query) use ($user) {
                $query->whereHas('members', function ($m) use ($user) {
                    $m->where('users.id', $user->id);
                });
            })
            ->with(['members', 'lists.stages.tasks.stage'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'workspace' => new WorkspaceResource($workspace),
            'data' => SpaceResource::collection($spaces),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaces = $user->workspaces()->with(['spaces.lists.tasks.stage'])->get();

        $allSpaces = $workspaces->flatMap->spaces;
        $allTasks = $allSpaces->flatMap->lists->flatMap->tasks;

        $totalWorkspaces = $workspaces->count();
        $totalSpaces = $allSpaces->count();
        $totalTasks = $allTasks->count();
        $completedTasks = $allTasks->where('stage.is_done', true)->count();
        $activeTasks = $allTasks->where('stage.is_done', false)->count();
        $overdueTasks = $allTasks->filter(fn ($t) => ! $t->stage?->is_done && $t->due_at && $t->due_at->isPast())->count();
        $completionRate = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'totalWorkspaces' => $totalWorkspaces,
                'totalSpaces' => $totalSpaces,
                'totalTasks' => $totalTasks,
                'completedTasks' => $completedTasks,
                'activeTasks' => $activeTasks,
                'overdueTasks' => $overdueTasks,
                'completionRate' => $completionRate,
            ],
        ]);
    }

    public function activityLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        $logs = \App\Models\ActivityLog::whereIn('workspace_id', $workspaceIds)
            ->with(['actor:id,name,avatar_path'])
            ->latest()
            ->take(30)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $logs->map(fn ($l) => [
                'id' => $l->id,
                'action' => $l->action,
                'description' => $l->description,
                'actor_name' => $l->actor?->name ?? 'Sistem',
                'created_at' => $l->created_at?->diffForHumans() ?? $l->created_at?->toIso8601String(),
            ]),
        ]);
    }
}

