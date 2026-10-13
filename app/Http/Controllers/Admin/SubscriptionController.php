<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = Subscription::with(['tenant', 'package'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $subscriptions = $query->paginate(15)->withQueryString();
        $packages = Package::where('is_active', true)->get();

        return view('admin.subscriptions.index', compact('subscriptions', 'packages', 'status'));
    }

    public function extendGracePeriod(Request $request, string $id): RedirectResponse
    {
        $subscription = Subscription::with('tenant')->findOrFail($id);

        $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $days = (int) $request->input('days', 7);
        $newGraceDate = now()->addDays($days);

        $subscription->update([
            'status' => 'grace_period',
            'grace_period_until' => $newGraceDate,
        ]);

        AuditLog::log('extend_grace_period', 'Subscription', $subscription->id, "Memberikan perpanjangan masa tenggang (Grace Period) {$days} hari untuk {$subscription->tenant->company_name}");

        return back()->with('success', "Masa tenggang diperpanjang {$days} hari hingga {$newGraceDate->format('d M Y')}.");
    }
}
