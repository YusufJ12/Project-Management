<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::user();

        if (! $user->is_active || ! in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN], true)) {
            Auth::logout();

            return redirect()->route('admin.login')->withErrors([
                'login' => 'Akses ditolak. Akun Anda tidak aktif atau bukan administrator.',
            ]);
        }

        return $next($request);
    }
}
