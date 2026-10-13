<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $credentials['login'];
        $password = $credentials['password'];
        $remember = $request->boolean('remember');

        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
        $attempt = [$isEmail ? 'email' : 'username' => $login, 'password' => $password, 'is_active' => true];

        if (Auth::attempt($attempt, $remember)) {
            $request->session()->regenerate();
            $user = Auth::user();

            if (! in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN], true)) {
                Auth::logout();

                return back()->withErrors(['login' => 'Akun Anda tidak memiliki hak akses administrator.']);
            }

            return redirect()->intended(route('admin.dashboard'))->with('success', 'Selamat datang kembali, '.$user->name);
        }

        return back()->withErrors([
            'login' => 'Email/Username atau password yang Anda masukkan salah.',
        ])->onlyInput('login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('info', 'Anda telah berhasil keluar dari sesi.');
    }
}
