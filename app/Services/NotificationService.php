<?php

namespace App\Services;

use App\Events\NotificationSentEvent;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create in-app notification and broadcast in realtime to the recipient.
     */
    public static function send(
        int $userId,
        int $workspaceId,
        string $type,
        array $data
    ): ?Notification {
        try {
            $notification = Notification::create([
                'user_id' => $userId,
                'workspace_id' => $workspaceId,
                'type' => $type,
                'data' => $data,
                'read_at' => null,
            ]);

            $unreadCount = Notification::where('user_id', $userId)
                ->whereNull('read_at')
                ->count();

            // Broadcast after DB transaction commit
            DB::afterCommit(function () use ($userId, $notification, $unreadCount) {
                try {
                    broadcast(new NotificationSentEvent($userId, $notification, $unreadCount));
                } catch (\Throwable $e) {
                    Log::warning("Gagal broadcast notification ke user {$userId}: " . $e->getMessage());
                }
            });

            return $notification;
        } catch (\Throwable $e) {
            Log::warning("Gagal membuat notifikasi untuk user {$userId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send notifications to multiple recipients (excluding the actor).
     */
    public static function sendToMany(
        iterable $userIds,
        int $workspaceId,
        string $type,
        array $data,
        ?int $excludeUserId = null
    ): void {
        foreach ($userIds as $id) {
            $id = (int) $id;
            if ($excludeUserId && $id === (int) $excludeUserId) {
                continue;
            }
            self::send($id, $workspaceId, $type, $data);
        }
    }
}

