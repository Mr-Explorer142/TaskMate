<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\ApplyRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Task;
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
            abort(409, $existing->status === ApplicationStatus::Rejected
                ? 'You cannot re-apply to this task.'
                : 'You have already applied to this task.');
        }

        try {
            $application = Application::query()->create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'message' => $request->validated('message'),
                'status' => ApplicationStatus::Pending,
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                abort(409, 'You have already applied to this task.');
            }

            throw $exception;
        }

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

            $application->update([
                'status' => ApplicationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $accepted = $task->acceptedApplications()->count();

            if ($accepted >= $task->team_size) {
                $task->update(['status' => TaskStatus::Full]);
            }

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

        $application->update(['status' => ApplicationStatus::Rejected]);
        $application->load($this->applicationRelations());

        return ApiResponse::success(new ApplicationResource($application), 'Application rejected.');
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
                ->withAvg('reviewsReceived', 'rating'),
            'task' => fn (BelongsTo $query) => $query->withCount([
                'applications as accepted_applications_count' => fn (Builder $query) => $query
                    ->where('status', ApplicationStatus::Accepted->value),
            ]),
        ];
    }
}
