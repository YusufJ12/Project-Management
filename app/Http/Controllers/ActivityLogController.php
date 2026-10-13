<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Tampilkan riwayat log aktivitas tim dan audit trail.
     */
    public function index(Request $request): Response
    {
        $activeWorkspaceId = $request->session()->get('active_workspace_id');

        $query = ActivityLog::query()
            ->with([
                'actor:id,name,email,avatar_path',
                'space:id,name,slug',
                'workspace:id,name',
            ]);

        // Filter workspace jika ada
        if ($activeWorkspaceId && $request->get('workspace') !== 'all') {
            $query->where('workspace_id', $activeWorkspaceId);
        }

        // Filter Action
        if ($action = $request->get('action')) {
            if ($action !== 'all') {
                $query->where('action', $action);
            }
        }

        // Search in action or metadata
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('metadata', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest('created_at')->paginate(20)->withQueryString();

        // Unique action types for dropdown filter
        $actionTypes = [
            'all' => 'Semua Aktivitas',
            'task_created' => 'Pembuatan Task',
            'task_moved' => 'Perpindahan Kolom Kanban',
            'task_updated' => 'Pembaruan Task',
            'comment_added' => 'Komentar Tim',
            'checklist_added' => 'Penambahan Subtask',
            'checklist_toggled' => 'Penyelesaian Subtask',
            'attachment_uploaded' => 'Upload Lampiran',
            'space_created' => 'Pembuatan Space',
        ];

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs,
            'actionTypes' => $actionTypes,
            'filters' => [
                'action' => $request->get('action', 'all'),
                'search' => $request->get('search', ''),
                'workspace' => $request->get('workspace', 'active'),
            ],
            'totalLogs' => ActivityLog::when($activeWorkspaceId, fn ($q) => $q->where('workspace_id', $activeWorkspaceId))->count(),
        ]);
    }
}
