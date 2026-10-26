<?php

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    protected function getActiveWorkspace(Request $request): ?Workspace
    {
        $user = Auth::user();
        $wsId = $request->session()->get('active_workspace_id');

        if ($wsId) {
            $ws = $user->workspaces()->where('workspaces.id', $wsId)->first();
            if ($ws) {
                return $ws;
            }
        }

        $firstWs = $user->workspaces()->first();
        if ($firstWs) {
            $request->session()->put('active_workspace_id', $firstWs->id);
            return $firstWs;
        }

        return null;
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $this->getActiveWorkspace($request);

        if (! $workspace) {
            return redirect()->route('workspaces.index')
                ->with('info', 'Silakan buat atau pilih workspace terlebih dahulu.');
        }

        $user = Auth::user();
        $isWsOwnerOrAdmin = $workspace->owner_id === $user->id ||
            $user->isSuperAdmin() ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists();

        $spaces = $workspace->spaces()
            ->whereNull('archived_at')
            ->when(! $isWsOwnerOrAdmin, function ($query) use ($user) {
                $query->whereHas('members', function ($m) use ($user) {
                    $m->where('users.id', $user->id);
                });
            })
            ->with(['members:id,name,email,username', 'lists.tasks.stage'])
            ->latest()
            ->get()
            ->map(function ($space) {
                $allTasks = $space->lists->flatMap->tasks;
                $totalTasks = $allTasks->count();
                $completedTasks = $allTasks->where('stage.is_done', true)->count();
                $progress = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

                return [
                    'id' => $space->id,
                    'name' => $space->name,
                    'slug' => $space->slug,
                    'description' => $space->description,
                    'status' => $space->status,
                    'starts_at' => $space->starts_at?->translatedFormat('d M Y'),
                    'due_at' => $space->due_at?->translatedFormat('d M Y'),
                    'members' => $space->members,
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'progress' => $progress,
                    'created_at' => $space->created_at->translatedFormat('d M Y'),
                ];
            });

        // Load all workspace members for member assignment
        $workspaceMembers = $workspace->members()
            ->select('users.id', 'users.name', 'users.email', 'users.username')
            ->get();

        // Load all accessible workspaces of current user so they can choose workspace when creating space
        $userWorkspaces = ($user->isSuperAdmin() ? Workspace::query() : $user->workspaces())
            ->with(['members:id,name,email,username'])
            ->select('workspaces.id', 'workspaces.name', 'workspaces.slug')
            ->get();

        return Inertia::render('Spaces/Index', [
            'workspace' => $workspace,
            'spaces' => $spaces,
            'workspaceMembers' => $workspaceMembers,
            'userWorkspaces' => $userWorkspaces,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['nullable', 'exists:workspaces,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);

        $user = Auth::user();

        // Gunakan workspace_id yang dipilih atau workspace aktif
        $targetWorkspaceId = $validated['workspace_id'] ?? $request->session()->get('active_workspace_id');
        $workspace = $targetWorkspaceId
            ? ($user->isSuperAdmin() ? Workspace::find($targetWorkspaceId) : $user->workspaces()->where('workspaces.id', $targetWorkspaceId)->first())
            : $this->getActiveWorkspace($request);

        if (! $workspace) {
            return back()->with('error', 'Silakan pilih Workspace yang valid.');
        }

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

        // Masukkan hanya anggota yang dipilih oleh pembuat space (yang valid merupakan anggota workspace tersebut)
        if (! empty($validated['member_ids'])) {
            $validMemberIds = $workspace->members()->whereIn('users.id', $validated['member_ids'])->pluck('users.id');
            foreach ($validMemberIds as $memberId) {
                if ((int) $memberId !== (int) $user->id) {
                    $space->members()->syncWithoutDetaching([
                        $memberId => ['role' => 'member', 'joined_at' => now()],
                    ]);
                }
            }
        }

        // Set workspace aktif ke workspace pilihan ini
        $request->session()->put('active_workspace_id', $workspace->id);

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

        return redirect()->route('spaces.show', $space->slug)
            ->with('success', "Space '{$space->name}' berhasil dibuat dengan papan Kanban bawaan!");
    }

    public function show(Request $request, Space $space): Response|RedirectResponse
    {
        $user = Auth::user();
        $workspace = $space->workspace;

        // Otorisasi: Harus anggota space (atau workspace owner/admin, atau super admin)
        $isAuthorized = $user->isSuperAdmin() ||
            $workspace->owner_id === $user->id ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists() ||
            $space->members()->where('users.id', $user->id)->exists();

        if (! $isAuthorized) {
            return redirect()->route('spaces.index')
                ->with('error', 'Anda tidak memiliki akses ke Space ini. Hanya anggota yang terdaftar yang dapat melihat.');
        }

        $space->load([
            'workspace:id,name,slug',
            'members:id,name,email,username',
            'lists.stages.tasks.assignees:id,name,email',
            'lists.stages.tasks.checklists.completedBy:id,name,email',
            'lists.stages.tasks.comments.user:id,name,email',
            'lists.stages.tasks.attachments.uploader:id,name,email',
            'lists.stages.tasks.creator:id,name,email',
            'lists.stages.tasks.dependencies:id,title,start_date,due_at',
            'lists.stages.tasks.dependents:id,title',
        ]);

        $workspaceMembers = $workspace->members()
            ->select('users.id', 'users.name', 'users.email', 'users.username')
            ->get();

        $effectiveRole = $user->getEffectiveRoleInSpace($space);
        $canEditTasks = $effectiveRole !== 'viewer';

        return Inertia::render('Spaces/Show', [
            'space' => $space,
            'workspaceMembers' => $workspaceMembers,
            'userRole' => $effectiveRole,
            'effectiveRole' => $effectiveRole,
            'can_edit_tasks' => $canEditTasks,
        ]);
    }

    public function sync(Request $request, Space $space): JsonResponse
    {
        $user = Auth::user();
        $workspace = $space->workspace;

        $isAuthorized = $user->isSuperAdmin() ||
            $workspace->owner_id === $user->id ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists() ||
            $space->members()->where('users.id', $user->id)->exists();

        if (! $isAuthorized) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        $space->load([
            'workspace:id,name,slug',
            'members:id,name,email,username',
            'lists.stages.tasks.assignees:id,name,email',
            'lists.stages.tasks.checklists.completedBy:id,name,email',
            'lists.stages.tasks.comments.user:id,name,email',
            'lists.stages.tasks.attachments.uploader:id,name,email',
            'lists.stages.tasks.creator:id,name,email',
            'lists.stages.tasks.dependencies:id,title,start_date,due_at',
            'lists.stages.tasks.dependents:id,title',
        ]);

        $effectiveRole = $user->getEffectiveRoleInSpace($space);
        $canEditTasks = $effectiveRole !== 'viewer';

        return response()->json([
            'space' => $space,
            'userRole' => $effectiveRole,
            'effectiveRole' => $effectiveRole,
            'can_edit_tasks' => $canEditTasks,
        ]);
    }

    public function addMember(Request $request, Space $space): RedirectResponse
    {
        $user = Auth::user();
        $workspace = $space->workspace;

        // Hanya workspace owner, workspace admin, space manager, atau super admin yang bisa menambah anggota
        $canManage = $user->isSuperAdmin() ||
            $workspace->owner_id === $user->id ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists() ||
            $space->members()->where('users.id', $user->id)->wherePivot('role', 'manager')->exists();

        if (! $canManage) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk mengelola anggota Space ini.');
        }

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
            'role' => ['nullable', 'in:member,manager,viewer'],
        ]);

        $role = $validated['role'] ?? 'member';

        foreach ($validated['user_ids'] as $userId) {
            $space->members()->syncWithoutDetaching([
                $userId => [
                    'role' => $role,
                    'joined_at' => now(),
                ],
            ]);
        }

        return back()->with('success', 'Anggota berhasil ditambahkan ke Space ini!');
    }

    public function updateMemberRole(Request $request, Space $space, User $targetUser): RedirectResponse
    {
        $user = Auth::user();
        $workspace = $space->workspace;

        $canManage = $user->isSuperAdmin() ||
            $workspace->owner_id === $user->id ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists() ||
            $space->members()->where('users.id', $user->id)->wherePivot('role', 'manager')->exists();

        if (! $canManage) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk mengubah peran anggota di Space ini.');
        }

        $validated = $request->validate([
            'role' => ['required', 'in:member,manager,viewer'],
        ]);

        $space->members()->updateExistingPivot($targetUser->id, [
            'role' => $validated['role'],
        ]);

        return back()->with('success', "Peran {$targetUser->name} di Space ini berhasil diperbarui!");
    }

    public function removeMember(Request $request, Space $space, User $targetUser): RedirectResponse
    {
        $user = Auth::user();
        $workspace = $space->workspace;

        $canManage = $user->isSuperAdmin() ||
            $workspace->owner_id === $user->id ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists() ||
            $space->members()->where('users.id', $user->id)->wherePivot('role', 'manager')->exists();

        if (! $canManage) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk menghapus anggota dari Space ini.');
        }

        $space->members()->detach($targetUser->id);

        return back()->with('success', "{$targetUser->name} telah dikeluarkan dari Space ini.");
    }

    public function update(Request $request, Space $space): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return back()->with('error', 'Tidak berwenang mengedit Space ini.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,archived,completed'],
            'due_at' => ['nullable', 'date'],
        ]);

        $space->update($validated);

        return back()->with('success', 'Space berhasil diperbarui!');
    }

    public function destroy(Space $space): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return back()->with('error', 'Tidak berwenang menghapus Space ini.');
        }

        $name = $space->name;
        $space->delete();

        return redirect()->route('spaces.index')
            ->with('success', "Space '{$name}' dan seluruh task di dalamnya berhasil dihapus.");
    }
}

