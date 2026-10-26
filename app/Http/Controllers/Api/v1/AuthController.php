<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Http\Resources\Api\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $loginValue = trim((string) ($request->input('login') ?? $request->input('email') ?? $request->input('username')));
        $password = (string) $request->input('password');

        if (empty($loginValue) || empty($password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau Username dan kata sandi wajib diisi.',
            ], 422);
        }

        $field = filter_var($loginValue, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $loginValue)->first();

        // Fallback: jika tidak ditemukan berdasarkan field utama, cari di kolom alternatif
        if (! $user) {
            $user = User::where('email', $loginValue)->orWhere('username', $loginValue)->first();
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email/Username atau kata sandi tidak cocok.',
            ], 422);
        }

        $token = $user->createToken('flutter-mobile-app')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Buat workspace bawaan
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => "Workspace {$user->name}",
            'slug' => Str::slug("workspace-{$user->name}") . '-' . Str::random(4),
        ]);

        $workspace->members()->attach($user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $token = $user->createToken('flutter-mobile-app')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftaran berhasil.',
            'token' => $token,
            'user' => new UserResource($user),
            'default_workspace' => new WorkspaceResource($workspace),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('workspaces');

        return response()->json([
            'status' => 'success',
            'user' => new UserResource($user),
            'workspaces' => WorkspaceResource::collection($user->workspaces),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil. Token telah dicabut.',
        ]);
    }
}

