<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NodeTelemetry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = $request->query('action');
        $query = AuditLog::with('user')->latest();

        if ($action) {
            $query->where('action', $action);
        }

        $logs = $query->paginate(20)->withQueryString();

        return view('admin.audit-logs.index', compact('logs', 'action'));
    }

    public function telemetries(Request $request): View
    {
        $tenantId = $request->query('tenant_id');
        $query = NodeTelemetry::with('tenant')->latest('reported_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $telemetries = $query->paginate(25)->withQueryString();

        $totalNodes = NodeTelemetry::distinct('tenant_id')->count('tenant_id');
        $onlineNodes = NodeTelemetry::where('reported_at', '>=', now()->subMinutes(5))
            ->distinct('tenant_id')
            ->count('tenant_id');
        $offlineNodes = max(0, $totalNodes - $onlineNodes);
        $avgRam = round((float) (NodeTelemetry::avg('ram_usage_percent') ?? 0), 1);
        $avgCpu = round((float) (NodeTelemetry::avg('cpu_usage_percent') ?? 0), 1);

        return view('admin.telemetries.index', compact(
            'telemetries',
            'tenantId',
            'totalNodes',
            'onlineNodes',
            'offlineNodes',
            'avgRam',
            'avgCpu'
        ));
    }
}
