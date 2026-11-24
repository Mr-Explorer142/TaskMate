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
        $viewerId = $request->user()?->id;
        $isOwner = $viewerId !== null && $viewerId === $this->uploader_id;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'team_size' => $this->team_size,
            'type' => $this->type->value,
            'reward' => $this->reward,
            'status' => $this->status->value,
            'deadline' => $this->deadline?->toDateString(),
            'skills' => $this->skills ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'accepted_count' => $this->acceptedCount(),
            'open_slots' => $this->openSlots(),
            'is_full' => $this->isFull(),
            'applications_count' => $this->when($isOwner, fn () => (int) ($this->applications_count ?? 0)),
            'uploader' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
                'avatar' => $this->uploader->avatar,
                'average_rating' => $this->uploader->average_rating,
            ]),
            'is_owner' => $isOwner,
            'my_application_status' => $this->relationLoaded('applications')
                ? $this->applications->first()?->status?->value
                : null,
        ];
    }
}
