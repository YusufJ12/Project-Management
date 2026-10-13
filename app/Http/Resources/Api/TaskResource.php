<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'list_id' => $this->list_id,
            'stage_id' => $this->stage_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'due_at' => $this->due_at?->format('Y-m-d'),
            'estimate_minutes' => $this->estimate_minutes,
            'position' => $this->position,
            'stage_name' => $this->stage?->name,
            'stage_color' => $this->stage?->color,
            'is_done' => (bool) $this->stage?->is_done,
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'checklists' => TaskChecklistResource::collection($this->whenLoaded('checklists')),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'checklists_count' => $this->checklists ? $this->checklists->count() : 0,
            'completed_checklists_count' => $this->checklists ? $this->checklists->where('is_completed', true)->count() : 0,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

