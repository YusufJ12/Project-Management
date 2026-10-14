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

    public function spaces(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        if (! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak ke workspace ini.',
            ], 403);
        }

        $spaces = $workspace->spaces()
            ->whereNull('archived_at')
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

