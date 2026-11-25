<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    /**
     * List the participants the current user still needs to review.
     */
    public function pending(Request $request, Task $task): JsonResponse
    {
        $this->ensureCompleted($task);

        $viewerId = (int) $request->user()->id;
        $participantIds = $this->participantIds($task);

        abort_unless(
            in_array($viewerId, $participantIds, true),
            403,
            'Only task participants can view pending reviews.',
        );

        $reviewedIds = Review::query()
            ->where('task_id', $task->id)
            ->where('reviewer_id', $viewerId)
            ->pluck('reviewee_id');

        $pending = User::query()
            ->whereIn('id', $participantIds)
            ->whereKeyNot($viewerId)
            ->whereNotIn('id', $reviewedIds->all())
            ->get(['id', 'name', 'avatar']);

        return ApiResponse::success($pending);
    }

    /**
     * Submit a review for another participant of a completed task.
     */
    public function store(StoreReviewRequest $request, Task $task): JsonResponse
    {
        $this->ensureCompleted($task);

        $data = $request->validated();
        $reviewerId = (int) $request->user()->id;
        $revieweeId = (int) $data['reviewee_id'];
        $participantIds = $this->participantIds($task);

        abort_unless(
            in_array($reviewerId, $participantIds, true),
            422,
            'Only task participants can submit reviews.',
        );

        abort_unless(
            in_array($revieweeId, $participantIds, true),
            422,
            'The reviewee must be a task participant.',
        );

        abort_if($reviewerId === $revieweeId, 422, 'You cannot review yourself.');

        $this->ensureNotDuplicated($task, $reviewerId, $revieweeId);

        try {
            $review = Review::query()->create([
                'task_id' => $task->id,
                'reviewer_id' => $reviewerId,
                'reviewee_id' => $revieweeId,
                'rating' => (int) $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                abort(409, 'You have already reviewed this member for this task.');
            }

            throw $exception;
        }

        $review->load(['reviewer:id,name,avatar', 'task:id,title']);

        return ApiResponse::success(new ReviewResource($review), 'Review submitted.', 201);
    }

    /**
     * List reviews received by a user with a rating summary.
     */
    public function forUser(User $user): AnonymousResourceCollection
    {
        $reviews = Review::query()
            ->where('reviewee_id', $user->id)
            ->with(['reviewer:id,name,avatar', 'task:id,title'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(10);

        $countsByRating = Review::query()
            ->where('reviewee_id', $user->id)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $reviewsCount = (int) $countsByRating->sum();
        $ratingSum = $countsByRating->reduce(
            fn (int $carry, mixed $total, mixed $rating): int => $carry + (int) $rating * (int) $total,
            0,
        );

        $distribution = collect(range(1, 5))
            ->mapWithKeys(fn (int $rating): array => [(string) $rating => (int) ($countsByRating[$rating] ?? 0)])
            ->all();

        return ReviewResource::collection($reviews)->additional([
            'meta' => [
                'average_rating' => $reviewsCount > 0 ? round($ratingSum / $reviewsCount, 1) : null,
                'reviews_count' => $reviewsCount,
                'distribution' => $distribution,
            ],
        ]);
    }

    /**
     * Abort unless the task is completed.
     */
    private function ensureCompleted(Task $task): void
    {
        if ($task->status !== TaskStatus::Completed) {
            abort(409, 'Reviews are only available for completed tasks.');
        }
    }

    /**
     * Resolve the uploader and every accepted member of a task.
     *
     * @return array<int, int>
     */
    private function participantIds(Task $task): array
    {
        $participantIds = [(int) $task->uploader_id];

        foreach ($task->acceptedApplications()->pluck('user_id') as $userId) {
            $participantIds[] = (int) $userId;
        }

        return array_values(array_unique($participantIds));
    }

    /**
     * Reject a repeated review before relying on the unique index.
     */
    private function ensureNotDuplicated(Task $task, int $reviewerId, int $revieweeId): void
    {
        $exists = Review::query()
            ->where('task_id', $task->id)
            ->where('reviewer_id', $reviewerId)
            ->where('reviewee_id', $revieweeId)
            ->exists();

        abort_if($exists, 409, 'You have already reviewed this member for this task.');
    }

    /**
     * Determine whether a query exception came from a unique constraint.
     */
    private function isDuplicateKey(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique violation')
            || ($exception->errorInfo[1] ?? null) === 1062;
    }
}
