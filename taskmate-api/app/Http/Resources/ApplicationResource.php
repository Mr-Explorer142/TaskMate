<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
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
            'status' => $this->status->value,
            'message' => $this->message,
            'created_at' => $this->created_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'applicant' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
                'bio' => $this->user->bio,
                'skills' => $this->user->skills ?? [],
                'average_rating' => $this->user->average_rating,
                'has_cv' => $this->user->has_cv,
                'profile_links' => ProfileLinkResource::collection($this->user->profileLinks),
            ]),
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'type' => $this->task->type->value,
                'reward' => $this->task->reward,
                'status' => $this->task->status->value,
                'team_size' => $this->task->team_size,
                'accepted_count' => $this->task->acceptedCount(),
            ]),
        ];
    }
}
