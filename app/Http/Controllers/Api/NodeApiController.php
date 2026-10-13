<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NodeTelemetry;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeApiController extends Controller
{
    /**
     * Heartbeat & Telemetry API called periodically by client VPS (.NET 10 API)
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $secret = $request->header('X-Hub-Secret') ?: $request->input('hubSecret');

        $companyCode = $request->input('companyCode');
        if (empty($companyCode)) {
            return response()->json([
                'success' => false,
                'message' => 'companyCode diperlukan.',
            ], 400);
        }

        $tenant = Tenant::with(['vault', 'activeSubscription.package'])->where('company_code', $companyCode)->first();

        if (! $tenant || ! $tenant->vault) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant tidak terdaftar di Central Control Plane.',
            ], 404);
        }

        // Verify Shared HMAC Secret
        if (empty($secret) || ! hash_equals((string) $tenant->vault->master_hub_secret, (string) $secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi X-Hub-Secret gagal. Secret tidak valid.',
            ], 401);
        }

        // Simpan / update data telemetri (selalu 1 data terkini per tenant agar tidak terjadi penumpukan database)
        $telemetry = NodeTelemetry::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'app_version' => $request->input('appVersion', '1.0.0'),
                'total_employees' => (int) $request->input('totalEmployees', 0),
                'ram_usage_percent' => $request->input('ramUsagePercent') !== null ? (float) $request->input('ramUsagePercent') : null,
                'cpu_usage_percent' => $request->input('cpuUsagePercent') !== null ? (float) $request->input('cpuUsagePercent') : null,
                'db_healthy' => $request->boolean('dbHealthy', true),
                'uptime_seconds' => (int) $request->input('uptimeSeconds', 0),
                'ip_address' => $request->ip(),
                'extra_stats' => $request->input('extraStats'),
                'reported_at' => now(),
            ]
        );

        // Hapus duplikat lama yang tersisa dari versi sebelum updateOrCreate agar database 100% bersih
        NodeTelemetry::where('tenant_id', $tenant->id)->where('id', '!=', $telemetry->id)->delete();

        // Check if tenant is Suspended (Remote Kill-Switch activated)
        if ($tenant->isSuspended()) {
            return response()->json([
                'status' => 'Suspended',
                'notice' => $tenant->suspension_reason ?: 'Layanan NexSphere-HRIS ditangguhkan sementara. Hubungi tim Administrator atau Finance.',
                'licenseExpiresAt' => null,
                'maxEmployees' => 0,
                'enabledModules' => [],
            ], 403);
        }

        $subscription = $tenant->activeSubscription;
        if (! $subscription) {
            return response()->json([
                'status' => 'Expired',
                'notice' => 'Masa langganan Anda telah berakhir. Silakan lakukan pembayaran.',
                'licenseExpiresAt' => null,
                'maxEmployees' => 0,
                'enabledModules' => ['CORE_HR'], // Read-only / minimum fallback
            ]);
        }

        return response()->json([
            'status' => 'Active',
            'companyCode' => $tenant->company_code,
            'companyName' => $tenant->company_name,
            'companyLogoUrl' => $tenant->logo_asset_url,
            'packageName' => $subscription->package?->name ?? 'Custom',
            'packageSlug' => $subscription->package?->slug ?? 'custom',
            'licenseExpiresAt' => $subscription->end_date ? $subscription->end_date->toIso8601String() : null,
            'maxEmployees' => $subscription->getEffectiveMaxEmployees(),
            'enabledModules' => $subscription->getEffectiveModules(),
            'serverNotice' => null,
        ]);
    }
}
