<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public Notification|array $notification;
    public int $unreadCount;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, Notification|array $notification, int $unreadCount = 0)
    {
        $this->userId = (int) $userId;
        $this->notification = $notification;
        $this->unreadCount = (int) $unreadCount;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->userId),
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $notifData = is_array($this->notification)
            ? $this->notification
            : [
                'id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'workspace_id' => $this->notification->workspace_id,
                'type' => $this->notification->type,
                'data' => $this->notification->data,
                'read_at' => $this->notification->read_at?->toISOString(),
                'created_at' => $this->notification->created_at?->toISOString() ?? now()->toISOString(),
            ];

        return [
            'notification' => $notifData,
            'unread_count' => $this->unreadCount,
        ];
    }
}

