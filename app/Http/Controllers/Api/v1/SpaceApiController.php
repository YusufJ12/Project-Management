<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SpaceResource;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaceApiController extends Controller
{
    public function show(Request $request, Space $space): JsonResponse
    {
        $user = $request->user();

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak ke space ini.',
            ], 403);
        }

        $space->load([
            'members',
            'lists.stages.tasks' => function ($query) {
                $query->orderBy('position');
            },
            'lists.stages.tasks.assignees',
            'lists.stages.tasks.checklists',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => new SpaceResource($space),
        ]);
    }
}

