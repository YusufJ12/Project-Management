<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = Invoice::with(['tenant', 'subscription.package'])->latest();

        if ($status) {
            $query->where('payment_status', $status);
        }

        $invoices = $query->paginate(15)->withQueryString();
        $tenants = Tenant::where('status', '!=', 'terminated')->get();

        return view('admin.invoices.index', compact('invoices', 'tenants', 'status'));
    }

    public function markAsPaid(Request $request, string $id): RedirectResponse
    {
        $invoice = Invoice::with(['tenant', 'subscription'])->findOrFail($id);

        $invoice->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $request->input('payment_method', 'bank_transfer'),
            'payment_reference' => $request->input('payment_reference'),
        ]);

        // If associated with a subscription, ensure it is active
        if ($invoice->subscription && in_array($invoice->subscription->status, ['expired', 'grace_period'], true)) {
            $invoice->subscription->update([
                'status' => 'active',
                'end_date' => $invoice->subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);
        }

        AuditLog::log('mark_invoice_paid', 'Invoice', $invoice->id, "Menandai invoice {$invoice->invoice_number} ({$invoice->formatted_amount}) sebagai LUNAS untuk {$invoice->tenant->company_name}");

        return back()->with('success', "Invoice {$invoice->invoice_number} telah ditandai Lunas.");
    }
}
