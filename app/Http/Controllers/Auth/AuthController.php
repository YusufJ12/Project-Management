<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $loginValue = $request->input('login') ?? $request->input('email');
        if ($loginValue !== null) {
            $request->merge(['login' => $loginValue]);
        }

        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($loginValue, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $loginValue,
            'password' => $request->input('password'),
        ];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Selamat datang kembali, '.Auth::user()->name.'!');
        }

        $errorKey = $request->has('email') && ! $request->filled('login') ? 'email' : 'login';

        return back()->withErrors([
            $errorKey => 'Email/Username atau kata sandi tidak cocok dengan data kami.',
            'login' => 'Email/Username atau kata sandi tidak cocok dengan data kami.',
        ])->onlyInput('login', 'email');
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Buat Workspace Pribadi Awal
        $workspace = \App\Models\Workspace::create([
            'name' => $user->name . "'s Workspace",
            'slug' => \Illuminate\Support\Str::slug($user->name . '-ws-' . \Illuminate\Support\Str::random(4)),
            'owner_id' => $user->id,
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

        // Buat Space Awal
        $space = \App\Models\Space::create([
            'workspace_id' => $workspace->id,
            'name' => 'General Project',
            'slug' => 'general-project',
            'description' => 'Ruang kerja pertama untuk mengelola tugas dan proyek Anda.',
            'status' => 'active',
        ]);

        $space->members()->attach($user->id, ['role' => 'manager', 'joined_at' => now()]);

        // Buat List Awal
        $list = \App\Models\TaskList::create([
            'space_id' => $space->id,
            'name' => 'Main Tasks',
            'slug' => 'main-tasks',
            'status' => 'active',
            'position' => 1,
        ]);

        // Buat Kolom Status Default Kanban
        $defaultStages = [
            ['name' => 'Backlog', 'color' => '#64748b', 'is_done' => false, 'position' => 1],
            ['name' => 'To Do', 'color' => '#0284c7', 'is_done' => false, 'position' => 2],
            ['name' => 'In Progress', 'color' => '#eab308', 'is_done' => false, 'position' => 3],
            ['name' => 'In Review', 'color' => '#8b5cf6', 'is_done' => false, 'position' => 4],
            ['name' => 'Done', 'color' => '#22c55e', 'is_done' => true, 'position' => 5],
        ];

        foreach ($defaultStages as $s) {
            \App\Models\TaskStage::create([
                'list_id' => $list->id,
                'name' => $s['name'],
                'color' => $s['color'],
                'is_done' => $s['is_done'],
                'position' => $s['position'],
            ]);
        }

        Auth::login($user);
        $request->session()->put('active_workspace_id', $workspace->id);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'Akun dan Workspace Anda berhasil dibuat! Selamat datang di ProjectSphere.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

