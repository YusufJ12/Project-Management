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

    public function store(Request $request, \App\Models\Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        if (! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak ke workspace ini.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);

        $space = Space::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']) . '-' . \Illuminate\Support\Str::random(4),
            'description' => $validated['description'] ?? null,
            'status' => 'active',
            'starts_at' => $validated['starts_at'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
        ]);

        // Attach creator as manager
        $space->members()->attach($user->id, [
            'role' => 'manager',
            'joined_at' => now(),
        ]);

        if (! empty($validated['member_ids'])) {
            $validMembers = $workspace->members()->whereIn('users.id', $validated['member_ids'])->pluck('users.id');
            foreach ($validMembers as $mId) {
                if ((int) $mId !== (int) $user->id) {
                    $space->members()->syncWithoutDetaching([
                        $mId => ['role' => 'member', 'joined_at' => now()],
                    ]);
                }
            }
        }

        // Create default Task List
        $list = \App\Models\TaskList::create([
            'space_id' => $space->id,
            'name' => 'Main Tasks',
            'slug' => 'main-tasks',
            'status' => 'active',
            'position' => 1,
        ]);

        // Create default Kanban Stages
        $defaultStages = [
            ['name' => 'Backlog', 'color' => '#64748b', 'is_done' => false, 'position' => 1],
            ['name' => 'To Do', 'color' => '#0284c7', 'is_done' => false, 'position' => 2],
            ['name' => 'In Progress', 'color' => '#eab308', 'is_done' => false, 'position' => 3],
            ['name' => 'In Review', 'color' => '#8b5cf6', 'is_done' => false, 'position' => 4],
            ['name' => 'Done', 'color' => '#22c55e', 'is_done' => true, 'position' => 5],
        ];

        foreach ($defaultStages as $s) {
            \App\Models\TaskStage::create([
                'list_id' => $list->id,
                'name' => $s['name'],
                'color' => $s['color'],
                'is_done' => $s['is_done'],
                'position' => $s['position'],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Space berhasil dibuat.',
            'data' => new SpaceResource($space),
        ], 201);
    }

    public function update(Request $request, Space $space): JsonResponse
    {
        $user = $request->user();

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,archived,completed'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ]);

        $space->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Space berhasil diperbarui.',
            'data' => new SpaceResource($space),
        ]);
    }

    public function destroy(Request $request, Space $space): JsonResponse
    {
        $user = $request->user();

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $space->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Space berhasil dihapus.',
        ]);
    }

    public function timeline(Request $request, Space $space): JsonResponse
    {
        $user = $request->user();

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $tasks = \App\Models\Task::whereHas('list', fn ($q) => $q->where('space_id', $space->id))
            ->with(['stage', 'assignees'])
            ->orderBy('due_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'starts_at' => $space->starts_at?->format('Y-m-d'),
                'due_at' => $space->due_at?->format('Y-m-d'),
            ],
            'data' => $tasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
                'due_at' => $t->due_at?->format('Y-m-d'),
                'created_at' => $t->created_at?->format('Y-m-d'),
                'stage' => [
                    'id' => $t->stage?->id,
                    'name' => $t->stage?->name ?? 'Tahapan',
                    'color' => $t->stage?->color ?? '#64748b',
                    'is_done' => $t->stage?->is_done ?? false,
                ],
                'assignees' => $t->assignees->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                ]),
            ]),
        ]);
    }
}

