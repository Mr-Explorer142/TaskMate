<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'reviewee_id' => $this->reviewee_id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
                'avatar_url' => $this->reviewer->avatar_url,
            ]),
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ]),
        ];
    }
}
