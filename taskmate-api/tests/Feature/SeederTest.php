<?php

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Application;
use App\Models\PointTransaction;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

it('seeds the documented demo accounts with the demo password', function () {
    $this->seed();

    foreach (['demo@taskmate.test', 'alice@taskmate.test', 'bob@taskmate.test'] as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect(Hash::check('password', $user->password))->toBeTrue();
    }
});

it('seeds read and unread database notifications for the demo account', function () {
    $this->seed();

    $demo = User::query()->where('email', 'demo@taskmate.test')->firstOrFail();

    $notifications = DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $demo->id)
        ->get();

    expect($notifications->count())->toBeGreaterThanOrEqual(3)
        ->and($notifications->whereNull('read_at')->count())->toBeGreaterThanOrEqual(1)
        ->and($notifications->whereNotNull('read_at')->count())->toBeGreaterThanOrEqual(1);

    foreach ($notifications as $notification) {
        $payload = json_decode($notification->data, true);

        expect($payload)->toHaveKeys(['type', 'title', 'body'])
            ->and($payload['type'])->toBe($notification->type);
    }
});

it('keeps seeded accepted counts, balances and escrow consistent', function () {
    $this->seed();

    foreach (Task::query()->get() as $task) {
        expect($task->acceptedApplications()->count())->toBeLessThanOrEqual($task->team_size);

        if ($task->status === TaskStatus::InProgress && $task->type === TaskType::Points) {
            expect((int) $task->escrow_amount)->toBe((int) $task->reward * $task->acceptedApplications()->count());
        }
    }

    foreach (User::query()->get() as $user) {
        expect((int) $user->points_balance)->toBe((int) PointTransaction::query()->where('user_id', $user->id)->sum('amount'));
    }

    expect(Application::query()->count())->toBeGreaterThan(0);
});
