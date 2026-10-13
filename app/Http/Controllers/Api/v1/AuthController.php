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
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kredensial login tidak cocok.',
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
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil. Token telah dicabut.',
        ]);
    }
}

