<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
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
            'name' => $this->name,
            'country' => $this->country,
            'bio' => $this->bio,
            'skills' => $this->skills ?? [],
            'avatar' => $this->avatar,
            'average_rating' => $this->average_rating,
            'reviews_count' => (int) ($this->reviews_received_count ?? 0),
            'has_cv' => $this->has_cv,
            'profile_links' => ProfileLinkResource::collection($this->whenLoaded('profileLinks')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
