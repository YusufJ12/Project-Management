<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Tampilkan direktori pengguna & matriks RBAC sistem.
     */
    public function index(Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        // Hanya super_admin atau admin yang boleh mengakses modul RBAC pengguna
        if (! $currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Administrator sistem.');
        }

        $query = User::query()
            ->with([
                'workspaces:id,name',
            ])
            ->withCount(['assignedTasks', 'ownedWorkspaces']);

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Filter Role
        if ($role = $request->get('role')) {
            if ($role !== 'all') {
                $query->where('role', $role);
            }
        }

        $users = $query->latest('id')->paginate(15)->withQueryString();

        // Metrics
        $totalUsers = User::count();
        $totalSuperAdmins = User::where('role', 'super_admin')->count();
        $totalAdmins = User::where('role', 'admin')->count();
        $totalMembers = User::where('role', 'member')->count();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->get('search', ''),
                'role' => $request->get('role', 'all'),
            ],
            'stats' => [
                'total' => $totalUsers,
                'super_admin' => $totalSuperAdmins,
                'admin' => $totalAdmins,
                'member' => $totalMembers,
            ],
            'availableWorkspaces' => Workspace::select('id', 'name')->get(),
        ]);
    }

    /**
     * Daftarkan/buat akun pengguna baru dari panel Super Admin.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang berwenang menambahkan pengguna secara langsung.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'member'])],
            'workspace_id' => ['nullable', 'exists:workspaces,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'timezone' => 'Asia/Jakarta',
            'theme' => 'light',
        ]);

        // Hubungkan ke workspace jika dipilih
        if (! empty($validated['workspace_id'])) {
            $wsRole = $validated['role'] === 'super_admin' || $validated['role'] === 'admin' ? 'admin' : 'member';
            $user->workspaces()->attach($validated['workspace_id'], [
                'role' => $wsRole,
                'joined_at' => now(),
            ]);
        }

        return redirect()->route('users.index')->with('success', "Pengguna {$user->name} berhasil ditambahkan dengan role {$user->role}.");
    }

    /**
     * Perbarui data pengguna (nama, email, username, password opsional, role).
     * Catatan: Jika pengguna adalah Super Admin, rolenya mutlak tidak dapat diubah.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang berwenang mengedit data pengguna.');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
        ];

        // Jika bukan super_admin, role boleh diubah
        if (! $user->isSuperAdmin()) {
            $rules['role'] = ['required', Rule::in(['admin', 'member', 'super_admin'])];
        }

        $validated = $request->validate($rules);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->username = $validated['username'] ?? null;

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // Role Super Admin bersifat permanen dan tidak boleh diubah
        if (! $user->isSuperAdmin() && isset($validated['role'])) {
            $user->role = $validated['role'];
        }

        $user->save();

        return redirect()->back()->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    /**
     * Perbarui peran/role sistem pengguna (RBAC).
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang berwenang mengubah Role RBAC pengguna.');
        }

        // Ketentuan: Role Super Admin bersifat permanen dan tidak dapat diubah oleh siapapun
        if ($user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Role Super Admin bersifat permanen dan tidak dapat diubah.');
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in(['super_admin', 'admin', 'member'])],
        ]);

        $user->role = $validated['role'];
        $user->save();

        return redirect()->back()->with('success', "Role pengguna {$user->name} berhasil diperbarui menjadi {$user->role}.");
    }

    /**
     * Hapus akun pengguna dari sistem.
     */
    public function destroy(User $user): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang berwenang menghapus pengguna.');
        }

        if ($user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Akun dengan role Super Admin bersifat permanen dan tidak dapat dihapus.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()->back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "Pengguna {$userName} berhasil dihapus dari sistem.");
    }
}

