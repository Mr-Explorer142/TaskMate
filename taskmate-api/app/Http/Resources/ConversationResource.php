<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
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
            'user_one_id' => $this->user_one_id,
            'user_two_id' => $this->user_two_id,
            'task' => $this->whenLoaded('task', fn () => new TaskResource($this->task)),
            'user_one' => $this->whenLoaded('userOne', fn () => new PublicUserResource($this->userOne)),
            'user_two' => $this->whenLoaded('userTwo', fn () => new PublicUserResource($this->userTwo)),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
