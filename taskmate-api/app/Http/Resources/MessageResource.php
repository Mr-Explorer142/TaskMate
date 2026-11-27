<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $this->bodyForViewer($viewerId),
            'created_at' => $this->created_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'reply_to_id' => $this->reply_to_id,
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'sender_id' => $this->replyTo->sender_id,
                'body' => Str::limit($this->replyTo->bodyForViewer($viewerId), 120),
                'created_at' => $this->replyTo->created_at?->toIso8601String(),
                'is_deleted_for_everyone' => $this->replyTo->isDeletedForEveryone(),
            ]),
            'is_deleted_for_everyone' => $this->isDeletedForEveryone(),
            'is_deleted_for_me' => $viewerId !== null && $this->isDeletedForUser($viewerId),
        ];
    }
}
