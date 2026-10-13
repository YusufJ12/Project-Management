<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get();

        $unreadCount = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        if ($notification->user_id !== $user->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        $notification->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notifikasi ditandai telah dibaca.',
            ]);
        }

        return back()->with('success', 'Notifikasi telah dibaca.');
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Semua notifikasi ditandai telah dibaca.',
            ]);
        }

        return back()->with('success', 'Semua notifikasi ditandai telah dibaca.');
    }
}

