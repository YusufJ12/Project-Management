<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = Tenant::with(['activeSubscription.package', 'latestTelemetry'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('company_code', 'like', "%{$search}%")
                    ->orWhere('pic_name', 'like', "%{$search}%")
                    ->orWhere('pic_email', 'like', "%{$search}%");
            });
        }

        $tenants = $query->paginate(12)->withQueryString();

        return view('admin.tenants.index', compact('tenants', 'status', 'search'));
    }

    public function create(): View
    {
        $packages = Package::where('is_active', true)->get();

        return view('admin.tenants.create', compact('packages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_code' => ['nullable', 'string', 'max:50', 'unique:tenants,company_code'],
            'pic_name' => ['required', 'string', 'max:255'],
            'pic_email' => ['required', 'email', 'max:255'],
            'pic_whatsapp' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'domain_frontend' => ['nullable', 'string', 'max:255'],
            'domain_backend' => ['nullable', 'string', 'max:255'],
            'package_id' => ['required', 'exists:packages,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'vps_ip_address' => ['nullable', 'string', 'max:45'],
            'vps_provider' => ['nullable', 'string', 'max:100'],
            'db_name' => ['nullable', 'string', 'max:100'],
            'db_user' => ['nullable', 'string', 'max:100'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'ssh_password' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('tenants/logos', 'public');
        }

        // Auto-generate company code if blank
        $companyCode = $validated['company_code'] ?? null;
        if (empty($companyCode)) {
            $codeSeed = strtoupper(preg_replace('/[^A-Z]/', '', Str::upper($validated['company_name'])));
            $companyCode = 'NEX-'.(substr($codeSeed, 0, 4) ?: Str::upper(Str::random(4)));
            // Check uniqueness
            if (Tenant::where('company_code', $companyCode)->exists()) {
                $companyCode .= '-'.rand(10, 99);
            }
        }

        $tenant = Tenant::create([
            'company_code' => $companyCode,
            'company_name' => $validated['company_name'],
            'logo_url' => $logoPath,
            'pic_name' => $validated['pic_name'],
            'pic_email' => $validated['pic_email'],
            'pic_whatsapp' => $validated['pic_whatsapp'] ?? null,
            'domain_frontend' => $validated['domain_frontend'] ?? null,
            'domain_backend' => $validated['domain_backend'] ?? null,
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        // Generate Master Hub Secret HMAC and internal keys
        $masterHubSecret = 'nexsec_'.Str::random(36);
        $jwtSecretKey = 'jwt_'.Str::random(36);
        $admsSecretToken = 'adms_'.Str::random(24);
        $superadminPassword = 'InitPass'.rand(1000, 9999).'!';

        TenantVault::create([
            'tenant_id' => $tenant->id,
            'vps_provider' => $validated['vps_provider'] ?? 'Domainesia',
            'vps_ip_address' => $validated['vps_ip_address'] ?? null,
            'ssh_port' => 22,
            'ssh_username' => 'root',
            'ssh_password' => $validated['ssh_password'] ?? ('Ssh_'.Str::random(12).'!'),
            'db_name' => $validated['db_name'] ?? ('nexhris_'.strtolower(str_replace('-', '_', $companyCode)).'_db'),
            'db_user' => $validated['db_user'] ?? ('nexhris_'.strtolower(str_replace('-', '_', $companyCode)).'_usr'),
            'db_password' => $validated['db_password'] ?? ('Pg_'.Str::random(16).'!'),
            'redis_password' => 'Redis_'.Str::random(16).'!',
            'master_hub_secret' => $masterHubSecret,
            'jwt_secret_key' => $jwtSecretKey,
            'adms_secret_token' => $admsSecretToken,
            'superadmin_username' => 'superadmin',
            'superadmin_password' => $superadminPassword,
            'last_rotated_at' => now(),
        ]);

        // Create subscription
        $package = Package::findOrFail($validated['package_id']);
        $endDate = $validated['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'start_date' => now(),
            'end_date' => $endDate,
            'billing_cycle' => $validated['billing_cycle'],
            'status' => 'active',
        ]);

        AuditLog::log('create_tenant', 'Tenant', $tenant->id, "Membuat tenant baru {$tenant->company_name} ({$tenant->company_code})");

        return redirect()->route('admin.tenants.show', $tenant->id)
            ->with('success', "Tenant {$tenant->company_name} berhasil dibuat!");
    }

    public function show(string $id): View
    {
        $tenant = Tenant::with(['vault', 'credentials', 'activeSubscription.package', 'latestTelemetry', 'telemetries' => function ($q) {
            $q->latest('reported_at')->take(20);
        }, 'invoices' => function ($q) {
            $q->latest()->take(10);
        }])->findOrFail($id);

        $packages = Package::where('is_active', true)->get();
        $auditLogs = AuditLog::where('target_id', $tenant->id)->with('user')->latest()->take(15)->get();

        return view('admin.tenants.show', compact('tenant', 'packages', 'auditLogs'));
    }

    /**
     * Endpoint JSON super ringan untuk pemantauan telemetri realtime 1 node VPS
     */
    public function liveTelemetry(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $telemetry = $tenant->latestTelemetry;

        if (! $telemetry) {
            return response()->json([
                'has_data' => false,
                'online' => false,
                'message' => 'Belum ada data telemetri dari node VPS.',
            ]);
        }

        $healthStatus = $tenant->node_health_status;
        $isOnline = $healthStatus === 'healthy';

        return response()->json([
            'has_data' => true,
            'online' => $isOnline,
            'health_status' => $healthStatus,
            'app_version' => $telemetry->app_version ?? '1.0.0',
            'total_employees' => $telemetry->total_employees ?? 0,
            'ram_usage_percent' => (float) ($telemetry->ram_usage_percent ?? 0),
            'cpu_usage_percent' => (float) ($telemetry->cpu_usage_percent ?? 0),
            'db_healthy' => (bool) $telemetry->db_healthy,
            'uptime_seconds' => (int) ($telemetry->uptime_seconds ?? 0),
            'uptime_formatted' => gmdate('H:i:s', $telemetry->uptime_seconds ?? 0),
            'reported_at' => $telemetry->reported_at ? $telemetry->reported_at->format('d M Y, H:i:s') : '-',
            'reported_ago' => $telemetry->reported_at ? $telemetry->reported_at->diffForHumans() : '-',
            'last_check' => now()->format('H:i:s'),
        ]);
    }

    public function edit(string $id): View
    {
        $tenant = Tenant::with('vault')->findOrFail($id);
        $packages = Package::where('is_active', true)->get();

        return view('admin.tenants.edit', compact('tenant', 'packages'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'pic_name' => ['required', 'string', 'max:255'],
            'pic_email' => ['required', 'email', 'max:255'],
            'pic_whatsapp' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'domain_frontend' => ['nullable', 'string', 'max:255'],
            'domain_backend' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:trial,active,suspended,terminated'],
            'suspension_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('logo')) {
            if ($tenant->logo_url) {
                $cleanOld = preg_replace('#^/?storage/#', '', $tenant->logo_url);
                if (Storage::disk('public')->exists($cleanOld)) {
                    Storage::disk('public')->delete($cleanOld);
                }
            }
            $validated['logo_url'] = $request->file('logo')->store('tenants/logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            if ($tenant->logo_url) {
                $cleanOld = preg_replace('#^/?storage/#', '', $tenant->logo_url);
                if (Storage::disk('public')->exists($cleanOld)) {
                    Storage::disk('public')->delete($cleanOld);
                }
            }
            $validated['logo_url'] = null;
        }

        $statusChanged = $tenant->status !== $validated['status'];
        if ($statusChanged && $validated['status'] === 'suspended') {
            $validated['suspended_at'] = now();
        } elseif ($statusChanged && $validated['status'] === 'active') {
            $validated['suspended_at'] = null;
        }

        unset($validated['logo'], $validated['remove_logo']);

        $tenant->update($validated);

        AuditLog::log('update_tenant', 'Tenant', $tenant->id, "Memperbarui data profil tenant {$tenant->company_name}");

        return redirect()->route('admin.tenants.show', $tenant->id)
            ->with('success', 'Data tenant berhasil diperbarui.');
    }

    /**
     * Remote Kill-Switch: Toggle Active / Suspended
     */
    public function toggleSuspension(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        if ($tenant->status === 'suspended') {
            $tenant->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
            AuditLog::log('unfreeze_tenant', 'Tenant', $tenant->id, "Mengaktifkan kembali tenant {$tenant->company_name}");

            return back()->with('success', "Layanan tenant {$tenant->company_name} telah diaktifkan kembali.");
        } else {
            $reason = $request->input('reason', 'Penangguhan akun oleh Administrator Master Hub');
            $tenant->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => $reason,
            ]);
            AuditLog::log('suspend_tenant', 'Tenant', $tenant->id, "Membekukan (Kill-Switch) tenant {$tenant->company_name}. Alasan: {$reason}");

            return back()->with('warning', "Layanan tenant {$tenant->company_name} telah DITANGGUHKAN (Suspended).");
        }
    }

    /**
     * AJAX endpoint to safely reveal encrypted credentials with UU PDP Audit Log
     */
    public function revealVaultCredential(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::with('vault')->findOrFail($id);
        $vault = $tenant->vault;

        if (! $vault) {
            return response()->json(['success' => false, 'message' => 'Brankas tidak ditemukan'], 404);
        }

        $field = $request->input('field');
        $allowedFields = [
            'ssh_password' => 'Password SSH',
            'db_password' => 'Password Database PostgreSQL',
            'redis_password' => 'Password Redis',
            'master_hub_secret' => 'Shared Secret Master Hub',
            'jwt_secret_key' => 'JWT Secret Key',
            'adms_secret_token' => 'Token ADMS Fingerprint',
            'superadmin_password' => 'Password SuperAdmin Web',
        ];

        if (! array_key_exists($field, $allowedFields)) {
            return response()->json(['success' => false, 'message' => 'Kolom rahasia tidak valid'], 400);
        }

        // Eloquent encrypted cast automatically decrypts AES-256 upon accessing property
        $decryptedValue = $vault->{$field};

        AuditLog::log('reveal_credential', 'Tenant', $tenant->id, "Melihat (reveal) {$allowedFields[$field]} milik tenant {$tenant->company_name}");

        return response()->json([
            'success' => true,
            'field' => $field,
            'value' => $decryptedValue,
        ]);
    }

    /**
     * Delete tenant and cascade vault, subscriptions, telemetries, and invoices
     */
    public function destroy(string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        $companyName = $tenant->company_name;
        $companyCode = $tenant->company_code;

        $tenant->delete();

        AuditLog::log('delete_tenant', 'Tenant', $id, "Menghapus tenant {$companyName} ({$companyCode}) dan seluruh brankas/lisensi terkait");

        return redirect()->route('admin.tenants.index')->with('success', "Tenant {$companyName} ({$companyCode}) berhasil dihapus beserta seluruh data brankasnya.");
    }
}
