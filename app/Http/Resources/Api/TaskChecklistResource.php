<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'title' => $this->title,
            'is_completed' => (bool) $this->is_completed,
            'position' => $this->position,
            'completed_at' => $this->completed_at?->toISOString(),
            'completed_by' => new UserResource($this->whenLoaded('completedBy')),
        ];
    }
}

