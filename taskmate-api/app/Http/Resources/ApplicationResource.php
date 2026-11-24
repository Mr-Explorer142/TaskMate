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
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'message' => $this->message,
            'status' => $this->status->value,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'left_at' => $this->left_at?->toIso8601String(),
            'task' => $this->whenLoaded('task', fn () => new TaskResource($this->task)),
            'user' => $this->whenLoaded('user', fn () => new PublicUserResource($this->user)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
