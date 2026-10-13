<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CompanyDiscoveryController extends Controller
{
    /**
     * Resolve company code to backend URL and tenant information for Mobile App & Web Client.
     *
     * GET /api/v1/discovery/company/{companyCode}
     */
    public function resolve(string $companyCode): JsonResponse
    {
        $code = trim(strtoupper($companyCode));

        if (empty($code)) {
            return response()->json([
                'found' => false,
                'message' => 'Kode perusahaan wajib diisi.',
            ], 400);
        }

        $cacheKey = "discovery_company_{$code}";

        $payload = Cache::remember($cacheKey, 600, function () use ($code) {
            $tenant = Tenant::where('company_code', $code)->first();

            if (! $tenant) {
                return null;
            }

            return $this->formatTenantPayload($tenant);
        });

        if (! $payload) {
            return response()->json([
                'found' => false,
                'message' => 'Perusahaan dengan kode ini tidak terdaftar.',
            ], 404);
        }

        if (! empty($payload['isSuspended'])) {
            return response()->json([
                'found' => false,
                'isSuspended' => true,
                'message' => $payload['suspensionReason'] ?? 'Layanan perusahaan sedang ditangguhkan.',
            ], 403);
        }

        if (($payload['status'] ?? '') === 'terminated') {
            return response()->json([
                'found' => false,
                'message' => 'Layanan perusahaan telah dihentikan.',
            ], 403);
        }

        return response()->json([
            'found' => true,
            'tenantId' => $payload['tenantId'],
            'companyCode' => $payload['companyCode'],
            'companyName' => $payload['companyName'],
            'apiBaseUrl' => $payload['apiBaseUrl'],
            'domainFrontend' => $payload['domainFrontend'],
            'logoUrl' => $payload['logoUrl'],
            'status' => $payload['status'],
        ]);
    }

    /**
     * Resolve frontend domain to tenant backend URL and metadata (for Web Admin PWA / SPA auto-detection).
     *
     * GET /api/v1/discovery/domain/{domain}
     */
    public function resolveDomain(string $domain): JsonResponse
    {
        $cleanDomain = trim($domain);
        $cleanDomain = preg_replace('#^https?://#i', '', $cleanDomain);
        $cleanDomain = explode('/', $cleanDomain)[0];
        $cleanDomain = explode(':', $cleanDomain)[0];
        $cleanDomain = strtolower(trim($cleanDomain));

        if (empty($cleanDomain)) {
            return response()->json([
                'found' => false,
                'message' => 'Domain wajib diisi.',
            ], 400);
        }

        $cacheKey = "discovery_domain_{$cleanDomain}";

        $payload = Cache::remember($cacheKey, 600, function () use ($cleanDomain) {
            $tenant = Tenant::whereRaw('LOWER(domain_frontend) = ?', [$cleanDomain])
                ->orWhere('domain_frontend', 'like', "%{$cleanDomain}%")
                ->first();

            if (! $tenant) {
                return null;
            }

            return $this->formatTenantPayload($tenant);
        });

        if (! $payload) {
            return response()->json([
                'found' => false,
                'message' => 'Domain tenant tidak terdaftar.',
            ], 404);
        }

        if (! empty($payload['isSuspended'])) {
            return response()->json([
                'found' => false,
                'isSuspended' => true,
                'message' => $payload['suspensionReason'] ?? 'Layanan perusahaan sedang ditangguhkan.',
            ], 403);
        }

        if (($payload['status'] ?? '') === 'terminated') {
            return response()->json([
                'found' => false,
                'message' => 'Layanan perusahaan telah dihentikan.',
            ], 403);
        }

        return response()->json([
            'found' => true,
            'tenantId' => $payload['tenantId'],
            'companyCode' => $payload['companyCode'],
            'companyName' => $payload['companyName'],
            'apiBaseUrl' => $payload['apiBaseUrl'],
            'domainFrontend' => $payload['domainFrontend'],
            'logoUrl' => $payload['logoUrl'],
            'status' => $payload['status'],
        ]);
    }

    /**
     * Helper to format tenant data consistently.
     */
    private function formatTenantPayload(Tenant $tenant): array
    {
        // Format domain_backend properly with scheme if needed
        $backendUrl = $tenant->domain_backend;
        if (! empty($backendUrl)) {
            $backendUrl = trim($backendUrl);
            if (! str_starts_with($backendUrl, 'http://') && ! str_starts_with($backendUrl, 'https://')) {
                $backendUrl = 'https://'.$backendUrl;
            }
            $backendUrl = rtrim($backendUrl, '/');
        }

        // Format domain_frontend properly
        $frontendUrl = $tenant->domain_frontend;
        if (! empty($frontendUrl)) {
            $frontendUrl = trim($frontendUrl);
            if (! str_starts_with($frontendUrl, 'http://') && ! str_starts_with($frontendUrl, 'https://')) {
                $frontendUrl = 'https://'.$frontendUrl;
            }
            $frontendUrl = rtrim($frontendUrl, '/');
        }

        return [
            'tenantId' => $tenant->id,
            'companyCode' => $tenant->company_code,
            'companyName' => $tenant->company_name,
            'apiBaseUrl' => $backendUrl,
            'domainFrontend' => $frontendUrl,
            'logoUrl' => $tenant->logo_asset_url,
            'status' => $tenant->status,
            'isSuspended' => $tenant->isSuspended(),
            'suspensionReason' => $tenant->suspension_reason,
        ];
    }
}
