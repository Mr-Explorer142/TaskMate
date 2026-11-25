<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PointTransactionResource extends JsonResource
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
            'amount' => $this->amount,
            'type' => $this->type->value,
            'note' => $this->note,
            'task' => $this->whenLoaded('task', fn () => $this->task === null ? null : [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
