<?php

namespace App\Http\Resources;

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user('sanctum')?->id;
        $isOwner = $viewerId !== null && $viewerId === $this->uploader_id;
        $myApplication = $this->relationLoaded('applications')
            ? $this->applications->first()
            : null;
        $acceptedCount = $this->acceptedCount();
        $canChangeTeam = in_array($this->status, [TaskStatus::Open, TaskStatus::Full], true);
        $isParticipant = $viewerId !== null
            && ($isOwner || $myApplication?->status === ApplicationStatus::Accepted);
        $isCompleted = $this->status === TaskStatus::Completed;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'team_size' => $this->team_size,
            'type' => $this->type->value,
            'reward' => $this->reward,
            'reward_currency' => 'USD',
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'deadline' => $this->deadline?->toDateString(),
            'skills' => $this->skills ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'accepted_count' => $acceptedCount,
            'open_slots' => $this->openSlots(),
            'is_full' => $this->isFull(),
            'can_start' => $isOwner && $canChangeTeam && $acceptedCount >= 1,
            'can_complete' => $isOwner && $this->status === TaskStatus::InProgress,
            'can_leave' => $myApplication?->status === ApplicationStatus::Accepted && $canChangeTeam,
            'is_participant' => $isParticipant,
            'pending_reviews_count' => $viewerId === null
                ? null
                : ($isParticipant && $isCompleted
                    ? max(0, $acceptedCount - (int) ($this->my_reviews_count ?? 0))
                    : 0),
            'applications_count' => $this->when($isOwner, fn () => (int) ($this->applications_count ?? 0)),
            'uploader' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
                'avatar_url' => $this->uploader->avatar_url,
                'country' => $this->uploader->country,
                'average_rating' => $this->uploader->average_rating,
                'reviews_count' => (int) ($this->uploader->reviews_received_count ?? 0),
            ]),
            'is_owner' => $isOwner,
            'pending_count' => $this->when($isOwner, fn () => (int) ($this->pending_applications_count ?? 0)),
            'my_application_id' => $this->relationLoaded('applications')
                ? $this->applications->first()?->id
                : null,
            'my_application_status' => $this->relationLoaded('applications')
                ? $this->applications->first()?->status?->value
                : null,
        ];
    }
}
