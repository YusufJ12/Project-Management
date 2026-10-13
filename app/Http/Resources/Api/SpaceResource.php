<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $allTasks = $this->lists ? $this->lists->flatMap->stages->flatMap->tasks : collect();
        $totalTasks = $allTasks->count();
        $completedTasks = $allTasks->filter(fn ($t) => $t->stage?->is_done)->count();
        $progress = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->format('Y-m-d'),
            'due_at' => $this->due_at?->format('Y-m-d'),
            'progress' => $progress,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'members' => UserResource::collection($this->whenLoaded('members')),
            'stages' => TaskStageResource::collection($this->when(
                $this->lists && $this->lists->isNotEmpty(),
                fn () => $this->lists->first()->stages
            )),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

