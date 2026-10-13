<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\NodeTelemetry;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $trialTenants = Tenant::where('status', 'trial')->count();

        // Calculate approximate MRR
        $activeSubscriptions = Subscription::where('status', 'active')->with('package')->get();
        $mrr = $activeSubscriptions->sum(function ($sub) {
            if ($sub->billing_cycle === 'yearly') {
                return ($sub->package ? $sub->package->yearly_price : 0) / 12;
            }

            return $sub->package ? $sub->package->monthly_price : 0;
        });

        $unpaidInvoicesCount = Invoice::where('payment_status', 'unpaid')->count();
        $unpaidInvoicesSum = Invoice::where('payment_status', 'unpaid')->sum('amount');

        // Recent Telemetries
        $recentTelemetries = NodeTelemetry::with('tenant')
            ->latest('reported_at')
            ->take(5)
            ->get();

        // Recent Audit logs
        $recentAuditLogs = AuditLog::with('user')
            ->latest()
            ->take(6)
            ->get();

        // Latest Tenants
        $latestTenants = Tenant::with(['activeSubscription.package', 'latestTelemetry'])
            ->latest()
            ->take(6)
            ->get();

        return view('admin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'suspendedTenants',
            'trialTenants',
            'mrr',
            'unpaidInvoicesCount',
            'unpaidInvoicesSum',
            'recentTelemetries',
            'recentAuditLogs',
            'latestTenants'
        ));
    }
}
