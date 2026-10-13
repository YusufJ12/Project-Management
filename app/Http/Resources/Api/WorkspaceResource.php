<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'owner_id' => $this->owner_id,
            'role' => $this->pivot?->role ?? ($this->owner_id === $request->user()?->id ? 'owner' : 'member'),
            'spaces_count' => $this->whenCounted('spaces'),
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

