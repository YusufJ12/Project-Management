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
}

