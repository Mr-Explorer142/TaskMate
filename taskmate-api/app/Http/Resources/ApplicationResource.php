<?php

namespace App\Http\Resources;

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
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
            'status' => $this->status->value,
            'message' => $this->message,
            'created_at' => $this->created_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'left_at' => $this->left_at?->toIso8601String(),
            'applicant' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
                'bio' => $this->user->bio,
                'skills' => $this->user->skills ?? [],
                'average_rating' => $this->user->average_rating,
                'reviews_count' => (int) ($this->user->reviews_received_count ?? 0),
                'has_cv' => $this->user->has_cv,
                'profile_links' => ProfileLinkResource::collection($this->user->profileLinks),
            ]),
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'type' => $this->task->type->value,
                'reward' => $this->task->reward,
                'status' => $this->task->status->value,
                'team_size' => $this->task->team_size,
                'accepted_count' => $this->task->acceptedCount(),
                'pending_reviews_count' => $this->pendingReviewsCount($request),
            ]),
        ];
    }

    /**
     * Count reviews the current user still owes for a completed task.
     */
    private function pendingReviewsCount(Request $request): int
    {
        $viewerId = $request->user()?->id;

        if (
            $viewerId === null
            || (int) $this->user_id !== (int) $viewerId
            || $this->status !== ApplicationStatus::Accepted
            || $this->task->status !== TaskStatus::Completed
        ) {
            return 0;
        }

        return max(
            0,
            $this->task->acceptedCount() - (int) ($this->task->my_reviews_count ?? 0)
        );
    }
}
