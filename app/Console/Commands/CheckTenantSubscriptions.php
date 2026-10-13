<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckTenantSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:check-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatisasi siklus langganan tenant: invoice H-7, masa tenggang (grace period), dan auto-suspend';

    public function handle(): int
    {
        $this->info('Memulai pengecekan siklus langganan tenant...');
        $today = Carbon::today();

        $this->generateRenewalInvoices($today);
        $this->handleGracePeriod($today);
        $this->handleAutoSuspension($today);

        $this->info('Pengecekan siklus langganan selesai.');

        return Command::SUCCESS;
    }

    /**
     * 1. H-7 Jatuh Tempo: Generate invoice tagihan baru (INV/YYYY/MM/XXXX) jika belum ada.
     */
    protected function generateRenewalInvoices(Carbon $today): void
    {
        $hMinus7Date = $today->copy()->addDays(7)->toDateString();

        // Cari subscription aktif yang akan berakhir antara hari ini s/d H+7
        $subscriptions = Subscription::with(['tenant', 'package', 'invoices'])
            ->where('status', 'active')
            ->whereDate('end_date', '<=', $hMinus7Date)
            ->whereDate('end_date', '>=', $today->toDateString())
            ->get();

        foreach ($subscriptions as $sub) {
            $tenant = $sub->tenant;
            if (! $tenant || $tenant->status === 'terminated') {
                continue;
            }

            // Periksa apakah invoice untuk siklus renewal ini sudah pernah dibuat
            $existingInvoice = $sub->invoices()
                ->whereDate('due_date', $sub->end_date->toDateString())
                ->first();

            if ($existingInvoice) {
                continue;
            }

            $amount = $sub->billing_cycle === 'yearly'
                ? ($sub->package?->yearly_price ?? 0)
                : ($sub->package?->monthly_price ?? 0);

            $invoiceNumber = $this->generateInvoiceNumber($today);

            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $sub->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $amount,
                'due_date' => $sub->end_date,
                'payment_status' => 'unpaid',
                'notes' => 'Tagihan perpanjangan langganan otomatis paket ' . ($sub->package?->name ?? 'Custom') . ' (' . ucfirst($sub->billing_cycle) . ')',
            ]);

            AuditLog::log(
                'generate_renewal_invoice',
                'Invoice',
                $invoice->id,
                "Tagihan perpanjangan dibuat otomatis untuk {$tenant->company_name} (Invoice: {$invoiceNumber}, Jumlah: {$invoice->formatted_amount}, Jatuh Tempo: {$sub->end_date->format('d M Y')})"
            );

            $this->line(" [H-7 Invoice] Dibuat: {$invoiceNumber} untuk tenant: {$tenant->company_name}");
        }
    }

    /**
     * 2. Hari H Jatuh Tempo: Jika belum bayar, ubah status langganan ke grace_period (7 hari).
     */
    protected function handleGracePeriod(Carbon $today): void
    {
        // Cari subscription aktif yang end_date sudah lewat dari hari ini
        $expiredSubs = Subscription::with(['tenant', 'invoices'])
            ->where('status', 'active')
            ->whereDate('end_date', '<', $today->toDateString())
            ->get();

        foreach ($expiredSubs as $sub) {
            $tenant = $sub->tenant;
            if (! $tenant || $tenant->status === 'terminated') {
                continue;
            }

            // Cek apakah ada invoice lunas untuk perpanjangan ini
            $hasPaid = $sub->invoices()
                ->where('payment_status', 'paid')
                ->whereDate('paid_at', '>=', $sub->end_date->toDateString())
                ->exists();

            if ($hasPaid) {
                continue;
            }

            $graceUntil = $today->copy()->addDays(7)->toDateString();

            $sub->update([
                'status' => 'grace_period',
                'grace_period_until' => $graceUntil,
            ]);

            AuditLog::log(
                'enter_grace_period',
                'Tenant',
                $tenant->id,
                "Langganan {$tenant->company_name} memasuki masa tenggang (grace period) 7 hari sampai {$graceUntil} karena tagihan belum dibayar."
            );

            $this->warn(" [Grace Period] Tenant {$tenant->company_name} memasuki masa tenggang hingga {$graceUntil}");
        }
    }

    /**
     * 3. Melewati Grace Period (H+7): Auto-suspend tenant dan set status expired.
     */
    protected function handleAutoSuspension(Carbon $today): void
    {
        // Cari subscription grace_period yang grace_period_until sudah lewat dari hari ini
        $subsToSuspend = Subscription::with(['tenant'])
            ->where('status', 'grace_period')
            ->whereDate('grace_period_until', '<', $today->toDateString())
            ->get();

        foreach ($subsToSuspend as $sub) {
            $tenant = $sub->tenant;
            if (! $tenant || $tenant->status === 'terminated') {
                continue;
            }

            $sub->update([
                'status' => 'expired',
            ]);

            if ($tenant->status !== 'suspended') {
                $tenant->update([
                    'status' => 'suspended',
                    'suspension_reason' => 'Masa tenggang pembayaran langganan telah berakhir',
                    'suspended_at' => now(),
                ]);

                AuditLog::log(
                    'auto_suspend_subscription',
                    'Tenant',
                    $tenant->id,
                    "Tenant {$tenant->company_name} ditangguhkan otomatis oleh sistem karena masa tenggang pembayaran langganan telah berakhir."
                );

                $this->error(" [Auto-Suspend] Tenant {$tenant->company_name} telah ditangguhkan otomatis.");
            }
        }
    }

    /**
     * Format invoice number: INV/YYYY/MM/XXXX
     */
    protected function generateInvoiceNumber(Carbon $date): string
    {
        $prefix = 'INV/' . $date->format('Y/m') . '/';
        $latest = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->first();

        $seq = 1;
        if ($latest && preg_match('#/(\d+)$#', $latest->invoice_number, $matches)) {
            $seq = intval($matches[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
