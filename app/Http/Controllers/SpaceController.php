<?php

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\TaskList;
use App\Models\TaskStage;
use App\Models\Workspace;
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

        $spaces = $workspace->spaces()
            ->whereNull('archived_at')
            ->with(['members:id,name,email', 'lists.tasks.stage'])
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
                    'starts_at' => $space->starts_at?->format('d M Y'),
                    'due_at' => $space->due_at?->format('d M Y'),
                    'members' => $space->members,
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'progress' => $progress,
                    'created_at' => $space->created_at->format('d M Y'),
                ];
            });

        return Inertia::render('Spaces/Index', [
            'workspace' => $workspace,
            'spaces' => $spaces,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->getActiveWorkspace($request);

        if (! $workspace) {
            return back()->with('error', 'Tidak ada workspace aktif.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $user = Auth::user();

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

        // Secara otomatis masukkan seluruh anggota workspace yang ada ke dalam space ini
        $otherMemberIds = $workspace->members()->where('users.id', '!=', $user->id)->pluck('users.id');
        foreach ($otherMemberIds as $memberId) {
            $space->members()->syncWithoutDetaching([
                $memberId => ['role' => 'member', 'joined_at' => now()],
            ]);
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

        return redirect()->route('spaces.show', $space->slug)
            ->with('success', "Space '{$space->name}' berhasil dibuat dengan papan Kanban bawaan!");
    }

    public function show(Request $request, Space $space): Response|RedirectResponse
    {
        $user = Auth::user();

        // Verifikasi bahwa user adalah anggota workspace dari space ini
        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return redirect()->route('spaces.index')
                ->with('error', 'Anda tidak memiliki akses ke Space ini.');
        }

        $space->load([
            'workspace:id,name,slug',
            'members:id,name,email',
            'lists.stages.tasks.assignees:id,name,email',
            'lists.stages.tasks.checklists.completedBy:id,name,email',
            'lists.stages.tasks.comments.user:id,name,email',
            'lists.stages.tasks.attachments.uploader:id,name,email',
            'lists.stages.tasks.creator:id,name,email',
        ]);

        return Inertia::render('Spaces/Show', [
            'space' => $space,
        ]);
    }

    public function sync(Request $request, Space $space): JsonResponse
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        $space->load([
            'workspace:id,name,slug',
            'members:id,name,email',
            'lists.stages.tasks.assignees:id,name,email',
            'lists.stages.tasks.checklists.completedBy:id,name,email',
            'lists.stages.tasks.comments.user:id,name,email',
            'lists.stages.tasks.attachments.uploader:id,name,email',
            'lists.stages.tasks.creator:id,name,email',
        ]);

        return response()->json([
            'space' => $space,
        ]);
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

