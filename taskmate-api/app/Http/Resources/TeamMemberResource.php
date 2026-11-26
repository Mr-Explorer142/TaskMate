<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'avatar_url' => $this->user->avatar_url,
            'bio' => $this->user->bio,
            'skills' => $this->user->skills ?? [],
            'average_rating' => $this->user->average_rating,
            'reviews_count' => (int) ($this->user->reviews_received_count ?? 0),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
        ];
    }
}
