<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantCredentialController extends Controller
{
    /**
     * Simpan kredensial baru untuk tenant
     */
    public function store(Request $request, string $tenantId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:server,hosting,database,api,email,other'],
            'label' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'secret_value' => ['required', 'string'],
            'url_or_host' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $credential = $tenant->credentials()->create([
            'category' => $validated['category'],
            'label' => $validated['label'],
            'username' => $validated['username'] ?? null,
            'secret_value' => $validated['secret_value'],
            'url_or_host' => $validated['url_or_host'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::log(
            'create_credential',
            'TenantCredential',
            $credential->id,
            "Menambahkan kredensial baru '{$credential->label}' ({$credential->category_label}) untuk tenant {$tenant->company_name}"
        );

        return back()->with('success', "Kredensial '{$credential->label}' berhasil disimpan ke Brankas.");
    }

    /**
     * Perbarui data kredensial
     */
    public function update(Request $request, string $tenantId, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $credential = $tenant->credentials()->findOrFail($id);

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:server,hosting,database,api,email,other'],
            'label' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'secret_value' => ['nullable', 'string'],
            'url_or_host' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $updateData = [
            'category' => $validated['category'],
            'label' => $validated['label'],
            'username' => $validated['username'] ?? null,
            'url_or_host' => $validated['url_or_host'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        // Hanya update password/secret jika diisi baru
        if (! empty($validated['secret_value'])) {
            $updateData['secret_value'] = $validated['secret_value'];
        }

        $credential->update($updateData);

        AuditLog::log(
            'update_credential',
            'TenantCredential',
            $credential->id,
            "Memperbarui data kredensial '{$credential->label}' milik tenant {$tenant->company_name}"
        );

        return back()->with('success', "Kredensial '{$credential->label}' berhasil diperbarui.");
    }

    /**
     * Hapus kredensial
     */
    public function destroy(string $tenantId, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $credential = $tenant->credentials()->findOrFail($id);
        $label = $credential->label;

        $credential->delete();

        AuditLog::log(
            'delete_credential',
            'TenantCredential',
            $id,
            "Menghapus kredensial '{$label}' milik tenant {$tenant->company_name}"
        );

        return back()->with('success', "Kredensial '{$label}' berhasil dihapus dari Brankas.");
    }

    /**
     * AJAX endpoint untuk mengintip (reveal) password terenkripsi dengan Audit Log UU PDP
     */
    public function reveal(Request $request, string $tenantId, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $credential = $tenant->credentials()->findOrFail($id);

        AuditLog::log(
            'reveal_custom_credential',
            'TenantCredential',
            $credential->id,
            "Melihat (reveal) password kredensial '{$credential->label}' milik tenant {$tenant->company_name}"
        );

        return response()->json([
            'success' => true,
            'id' => $credential->id,
            'value' => $credential->secret_value,
        ]);
    }
}
