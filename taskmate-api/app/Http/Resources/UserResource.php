<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'country' => $this->country,
            'bio' => $this->bio,
            'skills' => $this->skills ?? [],
            'avatar_url' => $this->avatar_url,
            'has_cv' => $this->has_cv,
            'cv_original_name' => $this->cv_original_name,
            'cv_uploaded_at' => $this->cv_uploaded_at?->toIso8601String(),
            'average_rating' => $this->average_rating,
            'reviews_count' => (int) ($this->reviews_received_count ?? 0),
            'points_balance' => $this->points_balance,
            'profile_links' => ProfileLinkResource::collection($this->whenLoaded('profileLinks')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
