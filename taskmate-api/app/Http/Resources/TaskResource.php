<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uploader_id' => $this->uploader_id,
            'title' => $this->title,
            'description' => $this->description,
            'team_size' => $this->team_size,
            'type' => $this->type->value,
            'reward' => $this->reward,
            'status' => $this->status->value,
            'deadline' => $this->deadline?->toDateString(),
            'accepted_count' => $this->acceptedCount(),
            'open_slots' => $this->openSlots(),
            'is_full' => $this->isFull(),
            'uploader' => $this->whenLoaded('uploader', fn () => new PublicUserResource($this->uploader)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
