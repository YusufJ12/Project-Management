<?php

namespace App\Http\Controllers;

use App\Models\TaskList;
use App\Models\TaskStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskStageController extends Controller
{
    /**
     * Tambah kolom tahapan / status baru (Stage) di dalam List / Space
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'exists:lists,id'],
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_done' => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();
        $list = TaskList::with('space.workspace')->findOrFail($validated['list_id']);
        $space = $list->space;
        $workspace = $space->workspace;

        $canManage = $user->isSuperAdmin() ||
            $workspace->owner_id === $user->id ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists() ||
            $space->members()->where('users.id', $user->id)->wherePivot('role', 'manager')->exists() ||
            $space->members()->where('users.id', $user->id)->exists();

        if (! $canManage) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Anda tidak memiliki hak akses untuk menambah tahapan di proyek ini.');
        }

        $maxPosition = TaskStage::where('list_id', $list->id)->max('position') ?? 0;

        $stage = TaskStage::create([
            'list_id' => $list->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
            'is_done' => $validated['is_done'] ?? false,
            'position' => $maxPosition + 1,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'stage' => $stage->load('tasks'),
                'message' => "Tahapan '{$stage->name}' berhasil ditambahkan!",
            ]);
        }

        return back()->with('success', "Tahapan status '{$stage->name}' berhasil ditambahkan!");
    }

    /**
     * Ubah nama, warna, atau status is_done dari tahapan (Stage)
     */
    public function update(Request $request, TaskStage $stage): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_done' => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();
        $stage->load('list.space.workspace');
        $space = $stage->list->space;
        $workspace = $space->workspace;

        $canManage = $user->isSuperAdmin() ||
            $workspace->owner_id === $user->id ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists() ||
            $space->members()->where('users.id', $user->id)->wherePivot('role', 'manager')->exists();

        if (! $canManage) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Anda tidak berwenang mengubah tahapan ini.');
        }

        $stage->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? $stage->color,
            'is_done' => $validated['is_done'] ?? $stage->is_done,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'stage' => $stage,
                'message' => 'Tahapan berhasil diperbarui!',
            ]);
        }

        return back()->with('success', 'Tahapan berhasil diperbarui!');
    }

    /**
     * Hapus tahapan (Stage). Jika ada task di dalamnya, task dipindahkan ke stage pertama atau dihapus.
     */
    public function destroy(Request $request, TaskStage $stage): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $stage->load('list.space.workspace');
        $space = $stage->list->space;
        $workspace = $space->workspace;

        $canManage = $user->isSuperAdmin() ||
            $workspace->owner_id === $user->id ||
            $workspace->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists() ||
            $space->members()->where('users.id', $user->id)->wherePivot('role', 'manager')->exists();

        if (! $canManage) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Anda tidak berwenang menghapus tahapan ini.');
        }

        // Cari stage lain untuk menampung task jika ada
        $fallbackStage = TaskStage::where('list_id', $stage->list_id)
            ->where('id', '!=', $stage->id)
            ->orderBy('position')
            ->first();

        if ($fallbackStage) {
            $stage->tasks()->update(['stage_id' => $fallbackStage->id]);
        }

        $name = $stage->name;
        $stage->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Tahapan '{$name}' berhasil dihapus.",
            ]);
        }

        return back()->with('success', "Tahapan status '{$name}' berhasil dihapus.");
    }
}
