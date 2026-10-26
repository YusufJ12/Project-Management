<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskChecklistToggledEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $spaceId;
    public int $taskId;
    public int $checklistId;
    public bool $isCompleted;
    public int $checklistsCount;
    public int $completedChecklistsCount;
    public array $actor;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $spaceId,
        int $taskId,
        int $checklistId,
        bool $isCompleted,
        int $checklistsCount,
        int $completedChecklistsCount,
        User|array $actor
    ) {
        $this->spaceId = (int) $spaceId;
        $this->taskId = (int) $taskId;
        $this->checklistId = (int) $checklistId;
        $this->isCompleted = (bool) $isCompleted;
        $this->checklistsCount = (int) $checklistsCount;
        $this->completedChecklistsCount = (int) $completedChecklistsCount;
        $this->actor = is_array($actor) ? $actor : [
            'id' => $actor->id,
            'name' => $actor->name,
        ];
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
        return [
            'taskId' => $this->taskId,
            'checklistId' => $this->checklistId,
            'isCompleted' => $this->isCompleted,
            'checklistsCount' => $this->checklistsCount,
            'completedChecklistsCount' => $this->completedChecklistsCount,
            'actor' => [
                'id' => $this->actor['id'] ?? null,
                'name' => $this->actor['name'] ?? null,
            ],
        ];
    }
}
