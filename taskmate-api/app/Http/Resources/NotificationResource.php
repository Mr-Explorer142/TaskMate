<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->data) ? $this->data : [];

        return [
            'id' => (string) $this->id,
            'type' => $payload['type'] ?? $this->type,
            'title' => $payload['title'] ?? null,
            'body' => $payload['body'] ?? null,
            'data' => Arr::except($payload, ['type', 'title', 'body']),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
