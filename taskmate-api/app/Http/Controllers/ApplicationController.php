<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\ApplyRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Task;
use App\Notifications\ApplicationAccepted;
use App\Notifications\ApplicationReceived;
use App\Notifications\ApplicationRejected;
use App\Notifications\MemberLeft;
use App\Notifications\NotificationDispatcher;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ApplicationController extends Controller
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /**
     * Apply to an open task.
     */
    public function apply(ApplyRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('create', [Application::class, $task]);

        if ($task->status !== TaskStatus::Open) {
            abort(409, $this->closedMessage($task));
        }

        $existing = Application::query()
            ->where('task_id', $task->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing !== null) {
            abort(409, match ($existing->status) {
                ApplicationStatus::Left => 'You left this team and cannot re-apply.',
                ApplicationStatus::Rejected => 'You cannot re-apply to this task.',
                default => 'You have already applied to this task.',
            });
        }

        try {
            $application = new Application;
            $application->forceFill([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'message' => $request->validated('message'),
                'status' => ApplicationStatus::Pending,
            ]);
            $application->save();
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                abort(409, 'You have already applied to this task.');
            }

            throw $exception;
        }

        $this->notifications->send(
            $task->uploader_id,
            new ApplicationReceived(
                $task->id,
                $application->id,
                $application->user_id,
                $request->user()->name,
                $task->title,
            ),
            $application->user_id,
        );

        $application->load($this->applicationRelations());

        return ApiResponse::success(new ApplicationResource($application), 'Application submitted.', 201);
    }

    /**
     * Withdraw a pending application.
     */
    public function withdraw(Application $application): JsonResponse
    {
        Gate::authorize('withdraw', $application);

        if ($application->status !== ApplicationStatus::Pending) {
            abort(409, 'Only pending applications can be withdrawn.');
        }

        $application->delete();

        return ApiResponse::success(null, 'Application withdrawn.');
    }

    /**
     * List the authenticated user's applications.
     */
    public function myApplications(Request $request): AnonymousResourceCollection
    {
        $applications = QueryBuilder::for(Application::query())
            ->where('user_id', $request->user()->id)
            ->allowedFilters(
                AllowedFilter::exact('status'),
            )
            ->defaultSort('-created_at')
            ->with([
                'task' => fn (BelongsTo $query) => $query->withCount([
                    'applications as accepted_applications_count' => fn (Builder $query) => $query
                        ->where('status', ApplicationStatus::Accepted->value),
                    'reviews as my_reviews_count' => fn (Builder $query) => $query
                        ->where('reviewer_id', $request->user()->id),
                ]),
            ])
            ->paginate(12);

        return ApplicationResource::collection($applications);
    }

    /**
     * List the applications for a task as its owner.
     */
    public function taskApplications(Request $request, Task $task): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->id === $task->uploader_id,
            403,
            'Only the task owner can view applications.',
        );

        $applications = QueryBuilder::for(Application::query())
            ->where('task_id', $task->id)
            ->allowedFilters(
                AllowedFilter::exact('status'),
            )
            ->orderByRaw(
                'case when status = ? then 0 else 1 end',
                [ApplicationStatus::Pending->value],
            )
            ->orderByDesc('created_at')
            ->with($this->applicationRelations())
            ->paginate(20);

        return ApplicationResource::collection($applications);
    }

    /**
     * Accept a pending application and fill a team slot.
     */
    public function accept(Application $application): JsonResponse
    {
        Gate::authorize('accept', $application);

        $application = DB::transaction(function () use ($application): Application {
            $task = Task::query()
                ->whereKey($application->task_id)
                ->lockForUpdate()
                ->firstOrFail();

            $application->refresh();

            if ($application->status !== ApplicationStatus::Pending) {
                abort(409, 'Application is not pending.');
            }

            $accepted = $task->acceptedApplications()->count();

            if ($task->status === TaskStatus::Full || $accepted >= $task->team_size) {
                abort(409, 'Team is already full.');
            }

            if ($task->status !== TaskStatus::Open) {
                abort(409, 'Only open tasks can accept applications.');
            }

            $application->forceFill([
                'status' => ApplicationStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $accepted = $task->acceptedApplications()->count();

            if ($accepted >= $task->team_size) {
                $task->forceFill(['status' => TaskStatus::Full])->save();
            }

            $this->notifications->send(
                $application->user_id,
                new ApplicationAccepted($task->id, $application->id, $task->title),
                $task->uploader_id,
            );

            return $application;
        });

        $application->load($this->applicationRelations());

        return ApiResponse::success(new ApplicationResource($application), 'Application accepted.');
    }

    /**
     * Reject a pending application.
     */
    public function reject(Application $application): JsonResponse
    {
        Gate::authorize('reject', $application);

        if ($application->status !== ApplicationStatus::Pending) {
            abort(409, 'Only pending applications can be rejected.');
        }

        $application->forceFill(['status' => ApplicationStatus::Rejected])->save();

        $this->notifications->send(
            $application->user_id,
            new ApplicationRejected($application->task_id, $application->id, $application->task->title),
            $application->task->uploader_id,
        );

        $application->load($this->applicationRelations());

        return ApiResponse::success(new ApplicationResource($application), 'Application rejected.');
    }

    /**
     * Leave a task team as an accepted member.
     */
    public function leave(Application $application): JsonResponse
    {
        Gate::authorize('leave', $application);

        $application = DB::transaction(function () use ($application): Application {
            $task = Task::query()
                ->whereKey($application->task_id)
                ->lockForUpdate()
                ->firstOrFail();

            $application->refresh();

            if ($application->status !== ApplicationStatus::Accepted) {
                abort(409, 'Only accepted members can leave the team.');
            }

            if (! in_array($task->status, [TaskStatus::Open, TaskStatus::Full], true)) {
                abort(409, 'The project has already started, you cannot leave');
            }

            $application->forceFill([
                'status' => ApplicationStatus::Left,
                'left_at' => now(),
            ])->save();

            if ($task->status === TaskStatus::Full) {
                $task->forceFill(['status' => TaskStatus::Open])->save();
            }

            $this->notifications->send(
                $task->uploader_id,
                new MemberLeft($task->id, $application->user_id, $application->user->name, $task->title),
                $application->user_id,
            );

            return $application;
        });

        $application->load($this->applicationRelations());

        return ApiResponse::success(new ApplicationResource($application), 'You have left the team.');
    }

    /**
     * Build the readable message for a task that no longer accepts applications.
     */
    private function closedMessage(Task $task): string
    {
        return match ($task->status) {
            TaskStatus::Full => "This task's team is already full.",
            TaskStatus::InProgress => 'This task is already in progress.',
            TaskStatus::Completed => 'This task has already been completed.',
            TaskStatus::Cancelled => 'This task has been cancelled.',
            default => 'This task is not accepting applications.',
        };
    }

    /**
     * Determine whether the query exception is a duplicate key violation.
     */
    private function isDuplicateKey(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique violation')
            || ($exception->errorInfo[1] ?? null) === 1062;
    }

    /**
     * Eager load the relations required by the application resource.
     *
     * @return array<string, mixed>
     */
    private function applicationRelations(): array
    {
        return [
            'user' => fn (BelongsTo $query) => $query
                ->with('profileLinks')
                ->withAvg('reviewsReceived', 'rating')
                ->withCount('reviewsReceived'),
            'task' => fn (BelongsTo $query) => $query->withCount([
                'applications as accepted_applications_count' => fn (Builder $query) => $query
                    ->where('status', ApplicationStatus::Accepted->value),
            ]),
        ];
    }
}
