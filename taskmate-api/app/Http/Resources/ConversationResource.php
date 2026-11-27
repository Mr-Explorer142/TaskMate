<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;

        $otherUser = null;

        if ($this->relationLoaded('userOne') && $this->relationLoaded('userTwo')) {
            $otherUser = $this->user_one_id === $viewerId ? $this->userTwo : $this->userOne;
        }

        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ]),
            'other_user' => $otherUser === null ? null : [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'avatar_url' => $otherUser->avatar_url,
            ],
            'last_message' => $this->whenLoaded('lastMessage', fn () => $this->lastMessage === null ? null : [
                'id' => $this->lastMessage->id,
                'sender_id' => $this->lastMessage->sender_id,
                'body' => Str::limit($this->lastMessage->bodyForViewer($viewerId), 120),
                'created_at' => $this->lastMessage->created_at?->toIso8601String(),
                'is_deleted_for_everyone' => $this->lastMessage->isDeletedForEveryone(),
            ]),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
