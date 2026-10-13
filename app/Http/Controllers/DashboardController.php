<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $wsId = $request->session()->get('active_workspace_id');

        $workspace = null;
        if ($wsId) {
            $workspace = $user->workspaces()->where('workspaces.id', $wsId)->first();
        }
        if (! $workspace) {
            $workspace = $user->workspaces()->first();
            if ($workspace) {
                $request->session()->put('active_workspace_id', $workspace->id);
            }
        }

        if (! $workspace) {
            return Inertia::render('Dashboard', [
                'stats' => [
                    'totalWorkspaces' => 0,
                    'totalSpaces' => 0,
                    'activeTasks' => 0,
                    'completedTasks' => 0,
                    'overdueTasks' => 0,
                ],
                'recentSpaces' => [],
            ]);
        }

        // Hitung metrik riil dari database
        $spaces = $workspace->spaces()
            ->whereNull('archived_at')
            ->with(['lists.tasks.stage'])
            ->latest()
            ->get();

        $allTasks = $spaces->flatMap->lists->flatMap->tasks;
        $totalSpaces = $spaces->count();
        $totalTasksCount = $allTasks->count();
        $completedTasks = $allTasks->where('stage.is_done', true)->count();
        $activeTasks = $allTasks->where('stage.is_done', false)->count();
        $overdueTasks = $allTasks->filter(function ($t) {
            return ! $t->stage?->is_done && $t->due_at && $t->due_at->isPast();
        })->count();
        $urgentTasksCount = $allTasks->filter(function ($t) {
            return ! $t->stage?->is_done && in_array($t->priority, ['urgent', 'high']);
        })->count();
        $completionRate = $totalTasksCount > 0 ? (int) round(($completedTasks / $totalTasksCount) * 100) : 0;

        $recentSpaces = $spaces->take(5)->map(function ($space) {
            $tasks = $space->lists->flatMap->tasks;
            $tot = $tasks->count();
            $done = $tasks->where('stage.is_done', true)->count();
            $prog = $tot > 0 ? (int) round(($done / $tot) * 100) : 0;

            return [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'status' => $space->status,
                'progress' => $prog,
                'total_tasks' => $tot,
                'completed_tasks' => $done,
                'due_at' => $space->due_at ? $space->due_at->format('d M Y') : 'Tanpa deadline',
            ];
        });

        // Tugas saya yang belum selesai di workspace ini
        $myTasks = \App\Models\Task::whereHas('assignees', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->whereHas('list.space', function ($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })
            ->with(['stage', 'list.space:id,name,slug'])
            ->whereHas('stage', function ($q) {
                $q->where('is_done', false);
            })
            ->orderBy('due_at')
            ->take(6)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'priority' => $t->priority,
                    'due_at' => $t->due_at ? $t->due_at->format('d M Y') : '-',
                    'stage_name' => $t->stage?->name,
                    'stage_color' => $t->stage?->color,
                    'space_name' => $t->list?->space?->name,
                    'space_slug' => $t->list?->space?->slug,
                ];
            });

        // Riwayat aktivitas terbaru di workspace ini
        $recentActivities = \App\Models\ActivityLog::where('workspace_id', $workspace->id)
            ->with('actor:id,name,email')
            ->latest()
            ->take(8)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'action' => $a->action,
                    'actor_name' => $a->actor?->name ?? 'System',
                    'metadata' => $a->metadata,
                    'time_ago' => $a->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalWorkspaces' => $user->workspaces()->count(),
                'totalSpaces' => $totalSpaces,
                'activeTasks' => $activeTasks,
                'completedTasks' => $completedTasks,
                'overdueTasks' => $overdueTasks,
                'urgentTasksCount' => $urgentTasksCount,
                'completionRate' => $completionRate,
            ],
            'recentSpaces' => $recentSpaces,
            'myTasks' => $myTasks,
            'recentActivities' => $recentActivities,
        ]);
    }
}
