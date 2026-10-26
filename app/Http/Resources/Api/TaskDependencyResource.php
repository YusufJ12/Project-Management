<?php

namespace App\Http\Resources\Api;

use App\Models\TaskStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDependencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stageName = $this->stage?->name;
        if (! $stageName && $this->stage_id) {
            $stageName = TaskStage::where('id', $this->stage_id)->value('name');
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_at' => $this->due_at?->format('Y-m-d'),
            'stage' => $stageName,
            'stage_name' => $stageName,
        ];
    }
}
