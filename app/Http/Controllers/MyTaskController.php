<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MyTaskController extends Controller
{
    /**
     * Tampilkan daftar tugas yang ditugaskan kepada pengguna saat ini.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $activeWorkspaceId = $request->session()->get('active_workspace_id');

        // Query tugas yang ditugaskan kepada user
        $query = Task::query()
            ->whereHas('assignees', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->with([
                'stage',
                'list.space.workspace',
                'assignees:id,name,email,avatar_path',
                'checklists:id,task_id,is_completed',
                'comments:id,task_id',
            ]);

        // Filter berdasarkan workspace aktif jika ada
        if ($activeWorkspaceId && $request->get('workspace') !== 'all') {
            $query->whereHas('list.space', function ($q) use ($activeWorkspaceId) {
                $q->where('workspace_id', $activeWorkspaceId);
            });
        }

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter Prioritas
        if ($priority = $request->get('priority')) {
            if ($priority !== 'all') {
                $query->where('priority', $priority);
            }
        }

        // Filter Status / Stage
        if ($status = $request->get('status')) {
            if ($status === 'completed') {
                $query->whereHas('stage', fn ($q) => $q->where('is_done', true));
            } elseif ($status === 'pending') {
                $query->whereHas('stage', fn ($q) => $q->where('is_done', false));
            }
        }

        // Filter Tenggat Waktu
        if ($due = $request->get('due')) {
            $today = now()->toDateString();
            if ($due === 'today') {
                $query->whereDate('due_at', $today);
            } elseif ($due === 'overdue') {
                $query->whereDate('due_at', '<', $today)
                    ->whereHas('stage', fn ($q) => $q->where('is_done', false));
            } elseif ($due === 'upcoming') {
                $query->whereDate('due_at', '>', $today);
            }
        }

        // Statistik Tugas User
        $baseStatsQuery = Task::whereHas('assignees', fn ($q) => $q->where('users.id', $user->id));
        if ($activeWorkspaceId && $request->get('workspace') !== 'all') {
            $baseStatsQuery->whereHas('list.space', fn ($q) => $q->where('workspace_id', $activeWorkspaceId));
        }

        $totalCount = (clone $baseStatsQuery)->count();
        $completedCount = (clone $baseStatsQuery)->whereHas('stage', fn ($q) => $q->where('is_done', true))->count();
        $pendingCount = (clone $baseStatsQuery)->whereHas('stage', fn ($q) => $q->where('is_done', false))->count();
        $overdueCount = (clone $baseStatsQuery)
            ->whereDate('due_at', '<', now()->toDateString())
            ->whereHas('stage', fn ($q) => $q->where('is_done', false))
            ->count();

        $tasks = $query->latest('updated_at')->paginate(15)->withQueryString();

        return Inertia::render('MyTasks/Index', [
            'tasks' => $tasks,
            'filters' => [
                'search' => $request->get('search', ''),
                'priority' => $request->get('priority', 'all'),
                'status' => $request->get('status', 'all'),
                'due' => $request->get('due', 'all'),
                'workspace' => $request->get('workspace', 'active'),
            ],
            'stats' => [
                'total' => $totalCount,
                'pending' => $pendingCount,
                'completed' => $completedCount,
                'overdue' => $overdueCount,
            ],
        ]);
    }
}

