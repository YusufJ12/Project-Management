<?php

namespace App\Events;

use App\Models\TaskAttachment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAttachmentUploadedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $spaceId;
    public int $taskId;
    public TaskAttachment|array $attachment;

    /**
     * Create a new event instance.
     */
    public function __construct(int $spaceId, int $taskId, TaskAttachment|array $attachment)
    {
        $this->spaceId = (int) $spaceId;
        $this->taskId = (int) $taskId;
        $this->attachment = $attachment;
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
        if (is_array($this->attachment)) {
            $uploader = $this->attachment['uploader'] ?? null;
            return [
                'taskId' => $this->taskId,
                'attachment' => [
                    'id' => $this->attachment['id'] ?? null,
                    'original_name' => $this->attachment['original_name'] ?? null,
                    'size_bytes' => $this->attachment['size_bytes'] ?? null,
                    'detected_mime' => $this->attachment['detected_mime'] ?? null,
                    'created_at' => $this->attachment['created_at'] ?? null,
                    'uploader' => [
                        'id' => $uploader['id'] ?? null,
                        'name' => $uploader['name'] ?? null,
                    ],
                ],
            ];
        }

        $uploader = $this->attachment->relationLoaded('uploader')
            ? $this->attachment->uploader
            : $this->attachment->uploader()->first();

        return [
            'taskId' => $this->taskId,
            'attachment' => [
                'id' => $this->attachment->id,
                'original_name' => $this->attachment->original_name,
                'size_bytes' => $this->attachment->size_bytes,
                'detected_mime' => $this->attachment->detected_mime,
                'created_at' => $this->attachment->created_at?->toISOString(),
                'uploader' => [
                    'id' => $uploader?->id,
                    'name' => $uploader?->name,
                ],
            ],
        ];
    }
}
