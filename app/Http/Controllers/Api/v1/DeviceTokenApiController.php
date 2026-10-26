<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:500'],
            'device_type' => ['nullable', 'string', 'in:android,ios,web'],
        ]);

        $user = $request->user();

        $deviceToken = UserDeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'device_type' => $validated['device_type'] ?? 'android',
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Token perangkat berhasil didaftarkan.',
            'data' => [
                'id' => $deviceToken->id,
                'token' => $deviceToken->token,
                'device_type' => $deviceToken->device_type,
                'last_used_at' => $deviceToken->last_used_at?->toISOString(),
            ],
        ], 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = $request->user();

        UserDeviceToken::where('user_id', $user->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Token perangkat berhasil dihapus.',
        ]);
    }
}
