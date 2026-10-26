<?php

namespace App\Events;

use App\Models\TaskStage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpaceStageChangedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $spaceId;
    public int $listId;
    public string $action;
    public TaskStage|array $stage;

    /**
     * Create a new event instance.
     */
    public function __construct(int $spaceId, int $listId, string $action, TaskStage|array $stage)
    {
        $this->spaceId = (int) $spaceId;
        $this->listId = (int) $listId;
        $this->action = $action;
        $this->stage = $stage;
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
            'spaceId' => $this->spaceId,
            'listId' => $this->listId,
            'action' => $this->action,
            'stage' => $this->stage,
        ];
    }
}
