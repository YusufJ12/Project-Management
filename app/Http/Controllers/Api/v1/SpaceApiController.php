<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SpaceResource;
use App\Models\Space;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpaceApiController extends Controller
{
    /**
     * Check if user has manager/admin/owner permissions on the space.
     */
    protected function canManageSpace(User $user, Space $space): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $workspace = $space->relationLoaded('workspace') ? $space->workspace : $space->workspace()->first();
        if ($workspace && (int) $workspace->owner_id === (int) $user->id) {
            return true;
        }

        if ($user->hasWorkspaceRole($space->workspace_id, ['owner', 'admin'])) {
            return true;
        }

        return $space->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'manager')
            ->exists();
    }

    public function show(Request $request, Space $space): JsonResponse
    {
        $user = $request->user();
        $workspace = $space->relationLoaded('workspace') ? $space->workspace : $space->workspace()->first();

        $isAuthorized = $user->isSuperAdmin()
            || ($workspace && (int) $workspace->owner_id === (int) $user->id)
            || ($workspace && $user->hasWorkspaceRole($workspace->id, ['owner', 'admin']))
            || $space->members()->where('users.id', $user->id)->exists();

        if (! $isAuthorized) {
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

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        $wsMembership = $user->workspaces()->where('workspaces.id', $workspace->id)->first();
        $isWsOwner = (int) $workspace->owner_id === (int) $user->id;

        if (! $wsMembership && ! $isWsOwner && ! $user->isSuperAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak ke workspace ini.'], 403);
        }

        $wsRole = $wsMembership?->pivot?->role;

        if ($wsRole === 'viewer') {
            return response()->json([
                'status' => 'error',
                'message' => 'Viewer tidak diizinkan membuat space.',
            ], 403);
        }

        $canCreate = $user->isSuperAdmin()
            || $isWsOwner
            || in_array($wsRole, ['owner', 'admin', 'manager'], true);

        if (! $canCreate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya Workspace Owner, Workspace Admin, atau Manager yang dapat membuat space.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);

        $space = DB::transaction(function () use ($workspace, $validated, $user) {
            $space = Space::create([
                'workspace_id' => $workspace->id,
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
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
            $list = TaskList::create([
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
                TaskStage::create([
                    'list_id' => $list->id,
                    'name' => $s['name'],
                    'color' => $s['color'],
                    'is_done' => $s['is_done'],
                    'position' => $s['position'],
                ]);
            }

            return $space;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Space berhasil dibuat.',
            'data' => new SpaceResource($space),
        ], 201);
    }

    public function update(Request $request, Space $space): JsonResponse
    {
        $user = $request->user();

        if (! $this->canManageSpace($user, $space)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya Space Manager, Workspace Owner, atau Workspace Admin yang dapat memperbarui space.',
            ], 403);
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

        if (! $this->canManageSpace($user, $space)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya Space Manager, Workspace Owner, atau Workspace Admin yang dapat menghapus space.',
            ], 403);
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
        $workspace = $space->relationLoaded('workspace') ? $space->workspace : $space->workspace()->first();

        if (! $workspace) {
            return response()->json(['status' => 'error', 'message' => 'Workspace tidak ditemukan.'], 404);
        }

        $isMember = $user->isSuperAdmin()
            || (int) $workspace->owner_id === (int) $user->id
            || $user->hasWorkspaceRole($workspace->id, ['owner', 'admin'])
            || $space->members()->where('users.id', $user->id)->exists();

        if (! $isMember) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak ke space ini.'], 403);
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
