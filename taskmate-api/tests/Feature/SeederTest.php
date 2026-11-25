<?php

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\PointTransaction;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;

it('seeds completed projects with settled escrow and reviewable teams', function () {
    $this->seed();

    $completedTasks = Task::query()
        ->where('status', TaskStatus::Completed->value)
        ->get();

    expect($completedTasks->count())->toBeGreaterThanOrEqual(3)
        ->and($completedTasks->every(fn (Task $task): bool => $task->completed_at !== null))->toBeTrue()
        ->and(Review::query()->count())->toBeGreaterThan(0);

    foreach ($completedTasks->filter(fn (Task $task): bool => $task->type === TaskType::Points) as $task) {
        expect((int) $task->escrow_amount)->toBe(0)
            ->and((int) PointTransaction::query()->where('task_id', $task->id)->sum('amount'))->toBe(0);
    }

    $demo = User::query()->where('email', 'demo@taskmate.test')->firstOrFail();

    $demoTaskWithPendingReviews = Task::query()
        ->where('status', TaskStatus::Completed->value)
        ->where(function ($query) use ($demo): void {
            $query->where('uploader_id', $demo->id)
                ->orWhereHas('acceptedApplications', fn ($query) => $query->where('user_id', $demo->id));
        })
        ->get()
        ->first(function (Task $task) use ($demo): bool {
            $participants = collect([(int) $task->uploader_id])
                ->merge($task->acceptedApplications()->pluck('user_id')->map(fn ($id): int => (int) $id))
                ->unique();

            $reviewedIds = Review::query()
                ->where('task_id', $task->id)
                ->where('reviewer_id', $demo->id)
                ->pluck('reviewee_id')
                ->map(fn ($id): int => (int) $id);

            return $participants->contains(fn (int $id): bool => $id !== $demo->id && ! $reviewedIds->contains($id));
        });

    expect($demoTaskWithPendingReviews)->not->toBeNull();
});
