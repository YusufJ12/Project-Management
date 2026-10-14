<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * Tampilkan daftar ruang kerja (Workspaces) dan anggota yang terdaftar.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $query = $user->isSuperAdmin()
            ? Workspace::query()
            : $user->workspaces();

        $workspaces = $query
            ->withCount(['members', 'spaces'])
            ->with(['owner:id,name,email', 'members:id,name,username,email'])
            ->latest('id')
            ->get()
            ->map(function ($ws) use ($user, $request) {
                $membership = $ws->members->firstWhere('id', $user->id);
                $role = $ws->owner_id === $user->id
                    ? 'owner'
                    : ($membership ? ($membership->pivot->role ?? 'member') : ($user->isSuperAdmin() ? 'super_admin' : 'member'));

                return [
                    'id' => $ws->id,
                    'name' => $ws->name,
                    'slug' => $ws->slug,
                    'owner' => $ws->owner,
                    'is_owner' => $ws->owner_id === $user->id || $user->isSuperAdmin(),
                    'can_manage' => $user->isSuperAdmin() || $ws->owner_id === $user->id || in_array($role, ['owner', 'admin'], true),
                    'role' => $role,
                    'members_count' => $ws->members_count,
                    'spaces_count' => $ws->spaces_count,
                    'is_active' => $ws->id === $request->session()->get('active_workspace_id'),
                    'created_at' => $ws->created_at->format('d M Y'),
                    'members' => $ws->members->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'username' => $m->username,
                        'email' => $m->email,
                        'role' => $m->id === $ws->owner_id ? 'owner' : ($m->pivot->role ?? 'member'),
                        'is_owner' => $m->id === $ws->owner_id,
                        'joined_at' => $m->pivot->joined_at ? \Carbon\Carbon::parse($m->pivot->joined_at)->format('d M Y') : '-',
                    ]),
                ];
            });

        // Semua pengguna sistem untuk opsi pilihan penambahan ke workspace
        $systemUsers = User::select('id', 'name', 'username', 'email', 'role')
            ->orderBy('name')
            ->get();

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
            'system_users' => $systemUsers,
        ]);
    }

    /**
     * Buat ruang kerja (Workspace) baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);

        $user = Auth::user();

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'owner_id' => $user->id,
        ]);

        $workspace->members()->attach($user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        if (! empty($validated['member_ids'])) {
            foreach ($validated['member_ids'] as $memberId) {
                if ((int) $memberId !== (int) $user->id) {
                    $workspace->members()->attach($memberId, [
                        'role' => 'member',
                        'joined_at' => now(),
                    ]);
                }
            }
        }

        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('workspaces.index')
            ->with('success', "Workspace '{$workspace->name}' berhasil dibuat!");
    }

    /**
     * Perbarui nama ruang kerja.
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! ($currentUser->isSuperAdmin() || $workspace->owner_id === $currentUser->id)) {
            abort(403, 'Hanya Pemilik Workspace atau Super Admin yang dapat mengubah nama ruang kerja.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $workspace->update([
            'name' => $validated['name'],
        ]);

        return back()->with('success', "Nama workspace berhasil diubah menjadi '{$workspace->name}'.");
    }

    /**
     * Hapus ruang kerja.
     */
    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! ($currentUser->isSuperAdmin() || $workspace->owner_id === $currentUser->id)) {
            abort(403, 'Hanya Pemilik Workspace atau Super Admin yang dapat menghapus ruang kerja.');
        }

        $name = $workspace->name;
        if ($request->session()->get('active_workspace_id') === $workspace->id) {
            $fallback = Workspace::where('id', '!=', $workspace->id)->first();
            if ($fallback) {
                $request->session()->put('active_workspace_id', $fallback->id);
            } else {
                $request->session()->forget('active_workspace_id');
            }
        }

        $workspace->delete();

        return redirect()->route('workspaces.index')->with('success', "Workspace '{$name}' berhasil dihapus.");
    }

    /**
     * Ganti ruang kerja aktif saat ini.
     */
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Verifikasi bahwa user adalah anggota workspace ini atau Super Admin
        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            return back()->with('error', 'Anda tidak memiliki hak akses ke workspace ini.');
        }

        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('dashboard')
            ->with('success', "Beralih ke workspace '{$workspace->name}'.");
    }

    /**
     * Tambahkan anggota user ke dalam ruang kerja.
     */
    public function addMember(Request $request, Workspace $workspace): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $this->canManageWorkspace($currentUser, $workspace)) {
            abort(403, 'Akses terbatas untuk Pengelola Workspace.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:admin,member'],
        ]);

        $targetUser = User::findOrFail($validated['user_id']);

        if ($workspace->members()->where('users.id', $targetUser->id)->exists()) {
            return back()->with('error', "Pengguna {$targetUser->name} sudah menjadi anggota di workspace ini.");
        }

        $workspace->members()->attach($targetUser->id, [
            'role' => $validated['role'],
            'joined_at' => now(),
        ]);

        // Daftarkan pengguna ke seluruh spaces yang ada di workspace ini
        $workspace->load('spaces');
        foreach ($workspace->spaces as $space) {
            if (! $space->members()->where('users.id', $targetUser->id)->exists()) {
                $space->members()->attach($targetUser->id, [
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }
        }

        ActivityLog::create([
            'workspace_id' => $workspace->id,
            'actor_id' => $currentUser->id,
            'action' => 'workspace_member_added',
            'subject_type' => Workspace::class,
            'subject_id' => $workspace->id,
            'metadata' => [
                'user_id' => $targetUser->id,
                'user_name' => $targetUser->name,
                'role' => $validated['role'],
            ],
        ]);

        return back()->with('success', "Pengguna {$targetUser->name} berhasil ditambahkan ke workspace '{$workspace->name}'!");
    }

    /**
     * Perbarui peran/role anggota di ruang kerja (admin / member).
     */
    public function updateMemberRole(Request $request, Workspace $workspace, User $user): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $this->canManageWorkspace($currentUser, $workspace)) {
            abort(403, 'Akses terbatas untuk Pengelola Workspace.');
        }

        if ($user->id === $workspace->owner_id) {
            return back()->with('error', 'Peran Pemilik (Owner) workspace tidak dapat diubah.');
        }

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        $workspace->members()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        return back()->with('success', "Peran {$user->name} di workspace berhasil diperbarui menjadi {$validated['role']}.");
    }

    /**
     * Keluarkan anggota dari ruang kerja.
     */
    public function removeMember(Workspace $workspace, User $user): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $this->canManageWorkspace($currentUser, $workspace)) {
            abort(403, 'Akses terbatas untuk Pengelola Workspace.');
        }

        if ($user->id === $workspace->owner_id) {
            return back()->with('error', 'Pemilik (Owner) workspace tidak dapat dikeluarkan.');
        }

        $workspace->members()->detach($user->id);

        // Keluarkan juga dari space di workspace ini
        $spaceIds = $workspace->spaces()->pluck('id');
        $user->spaces()->detach($spaceIds);

        ActivityLog::create([
            'workspace_id' => $workspace->id,
            'actor_id' => $currentUser->id,
            'action' => 'workspace_member_removed',
            'subject_type' => Workspace::class,
            'subject_id' => $workspace->id,
            'metadata' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
            ],
        ]);

        return back()->with('success', "Pengguna {$user->name} berhasil dikeluarkan dari workspace '{$workspace->name}'.");
    }

    /**
     * Helper validasi hak kelola ruang kerja.
     */
    protected function canManageWorkspace(User $user, Workspace $workspace): bool
    {
        return $user->isSuperAdmin()
            || $workspace->owner_id === $user->id
            || $user->hasWorkspaceRole($workspace->id, ['owner', 'admin']);
    }
}
