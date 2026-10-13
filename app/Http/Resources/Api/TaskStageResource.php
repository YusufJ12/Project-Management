<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'list_id' => $this->list_id,
            'name' => $this->name,
            'color' => $this->color,
            'is_done' => (bool) $this->is_done,
            'position' => $this->position,
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'tasks_count' => $this->tasks ? $this->tasks->count() : 0,
        ];
    }
}

