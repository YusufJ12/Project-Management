<?php

namespace App\Events;

use App\Models\TaskComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommentAddedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $spaceId;
    public int $taskId;
    public TaskComment|array $comment;

    /**
     * Create a new event instance.
     */
    public function __construct(int $spaceId, int $taskId, TaskComment|array $comment)
    {
        $this->spaceId = (int) $spaceId;
        $this->taskId = (int) $taskId;
        $this->comment = $comment;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('space.' . $this->spaceId),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        if (is_array($this->comment)) {
            $user = $this->comment['user'] ?? null;
            return [
                'taskId' => $this->taskId,
                'comment' => [
                    'id' => $this->comment['id'] ?? null,
                    'content' => $this->comment['content'] ?? null,
                    'created_at' => $this->comment['created_at'] ?? null,
                    'user' => [
                        'id' => $user['id'] ?? null,
                        'name' => $user['name'] ?? null,
                        'email' => $user['email'] ?? null,
                    ],
                ],
            ];
        }

        $user = $this->comment->relationLoaded('user')
            ? $this->comment->user
            : $this->comment->user()->first();

        return [
            'taskId' => $this->taskId,
            'comment' => [
                'id' => $this->comment->id,
                'content' => $this->comment->content,
                'created_at' => $this->comment->created_at?->toISOString(),
                'user' => [
                    'id' => $user?->id,
                    'name' => $user?->name,
                    'email' => $user?->email,
                ],
            ],
        ];
    }
}
