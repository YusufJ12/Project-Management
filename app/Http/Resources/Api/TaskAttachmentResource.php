<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'original_name' => $this->original_name,
            'file_size' => $this->size_bytes,
            'size_bytes' => $this->size_bytes,
            'mime_type' => $this->detected_mime,
            'detected_mime' => $this->detected_mime,
            'created_at' => $this->created_at?->toISOString(),
            'download_url' => url("/api/v1/tasks/{$this->task_id}/attachments/{$this->id}/download"),
        ];
    }
}
