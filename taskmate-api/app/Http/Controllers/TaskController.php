<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TeamMemberResource;
use App\Models\Task;
use App\Services\PointsService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class TaskController extends Controller
{
    /**
     * List tasks that are available to the public.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tasks = $this->buildQuery($request)
            ->paginate($this->perPage($request));

        return TaskResource::collection($tasks);
    }

    /**
     * List the authenticated user's posted tasks.
     */
    public function myTasks(Request $request): AnonymousResourceCollection
    {
        $tasks = $this->buildQuery($request, allStatuses: true)
            ->where('uploader_id', $request->user()->id)
            ->paginate($this->perPage($request));

        return TaskResource::collection($tasks);
    }

    /**
     * Show a single task.
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        $task = $this->loadRelations($task, $request);

        return ApiResponse::success(new TaskResource($task), 'Task.');
    }

    /**
     * List the accepted members of a task team.
     */
    public function team(Task $task): AnonymousResourceCollection
    {
        $members = $task->acceptedApplications()
            ->with([
                'user' => fn (BelongsTo $query) => $query
                    ->select(['id', 'name', 'avatar', 'bio', 'skills'])
                    ->withAvg('reviewsReceived', 'rating'),
            ])
            ->orderBy('accepted_at')
            ->get();

        $acceptedCount = $members->count();

        return TeamMemberResource::collection($members)->additional([
            'meta' => [
                'team_size' => $task->team_size,
                'accepted_count' => $acceptedCount,
                'open_slots' => max(0, $task->team_size - $acceptedCount),
            ],
        ]);
    }

    /**
     * Start a task and lock its team.
     */
    public function start(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('start', $task);

        $task = DB::transaction(function () use ($task): Task {
            $task = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($task->status, [TaskStatus::Open, TaskStatus::Full], true)) {
                abort(409, 'Only open or full tasks can be started.');
            }

            if ($task->acceptedApplications()->count() < 1) {
                abort(422, 'Accept at least one member before starting');
            }

            if ($task->type === TaskType::Points) {
                $required = (int) $task->reward * $task->acceptedApplications()->count();

                app(PointsService::class)->escrow($task->uploader, $task, $required);
            }

            $task->update([
                'status' => TaskStatus::InProgress,
                'started_at' => now(),
            ]);

            $task->applications()
                ->where('status', ApplicationStatus::Pending->value)
                ->update(['status' => ApplicationStatus::Rejected->value]);

            return $task;
        });

        $task = $this->loadRelations($task->fresh(), $request);

        return ApiResponse::success(new TaskResource($task), 'Project started.');
    }

    /**
     * Create a task.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::query()->create([
            ...$request->validated(),
            'uploader_id' => $request->user()->id,
            'status' => TaskStatus::Open,
        ]);

        $task = $this->loadRelations($task, $request);

        return ApiResponse::success(new TaskResource($task), 'Task created.', 201);
    }

    /**
     * Update an existing task.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $data = $request->validated();

        DB::transaction(function () use ($task, $data): void {
            $task->refresh();

            if ($task->status === TaskStatus::InProgress) {
                abort(409, 'This task is already in progress and can no longer be edited.');
            }

            if (! in_array($task->status, [TaskStatus::Open, TaskStatus::Full], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only open or full tasks can be updated.',
                ]);
            }

            $accepted = $task->acceptedCount();

            if (array_key_exists('team_size', $data) && $data['team_size'] < $accepted) {
                throw ValidationException::withMessages([
                    'team_size' => "Team size cannot be lower than the {$accepted} accepted member(s).",
                ]);
            }

            if (array_key_exists('type', $data)) {
                $newType = TaskType::from($data['type']);

                if ($newType !== $task->type && $task->applications()->exists()) {
                    throw ValidationException::withMessages([
                        'type' => 'The task type cannot be changed once it has applications.',
                    ]);
                }
            }

            $task->fill($data);

            if (array_key_exists('team_size', $data)) {
                if ($task->status === TaskStatus::Full && $data['team_size'] > $accepted) {
                    $task->status = TaskStatus::Open;
                } elseif ($task->status === TaskStatus::Open && $accepted > 0 && $data['team_size'] === $accepted) {
                    $task->status = TaskStatus::Full;
                }
            }

            $task->save();
        });

        $task = $this->loadRelations($task->fresh(), $request);

        return ApiResponse::success(new TaskResource($task), 'Task updated.');
    }

    /**
     * Cancel a task and reject its pending applications.
     */
    public function cancel(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('cancel', $task);

        DB::transaction(function () use ($task): void {
            $task->refresh();

            if (! in_array($task->status, [TaskStatus::Open, TaskStatus::Full], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only open or full tasks can be cancelled.',
                ]);
            }

            $task->update(['status' => TaskStatus::Cancelled]);

            $task->applications()
                ->where('status', ApplicationStatus::Pending->value)
                ->update(['status' => ApplicationStatus::Rejected->value]);
        });

        $task = $this->loadRelations($task->fresh(), $request);

        return ApiResponse::success(new TaskResource($task), 'Task cancelled.');
    }

    /**
     * Delete a task that has no accepted members.
     */
    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        if ($task->acceptedApplications()->exists()) {
            abort(409, 'This task has accepted members. Cancel the task instead of deleting it.');
        }

        if (! in_array($task->status, [TaskStatus::Open, TaskStatus::Cancelled], true)) {
            abort(409, 'Only open or cancelled tasks without accepted members can be deleted.');
        }

        $task->delete();

        return ApiResponse::success(null, 'Task deleted.');
    }

    /**
     * Build the shared filtered, sorted, and eager-loaded task query.
     */
    private function buildQuery(Request $request, bool $allStatuses = false): QueryBuilder
    {
        $query = QueryBuilder::for(Task::query())
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($value));
                    $term = '%'.$escaped.'%';

                    $query->where(function (Builder $query) use ($term): void {
                        $query->whereRaw("title LIKE ? ESCAPE '!'", [$term])
                            ->orWhereRaw("description LIKE ? ESCAPE '!'", [$term]);
                    });
                }),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('skill', function (Builder $query, mixed $value): void {
                    $skills = is_array($value) ? $value : explode(',', (string) $value);
                    $skills = array_values(array_filter(
                        array_map(fn (mixed $skill) => is_string($skill) ? trim($skill) : '', $skills),
                        fn (string $skill) => $skill !== '',
                    ));

                    if ($skills === []) {
                        return;
                    }

                    $query->where(function (Builder $query) use ($skills): void {
                        foreach ($skills as $skill) {
                            $query->orWhereJsonContains('skills', $skill);
                        }
                    });
                }),
                AllowedFilter::callback('min_reward', function (Builder $query, mixed $value): void {
                    if (is_numeric($value)) {
                        $query->where('reward', '>=', (float) $value);
                    }
                }),
                AllowedFilter::callback('max_reward', function (Builder $query, mixed $value): void {
                    if (is_numeric($value)) {
                        $query->where('reward', '<=', (float) $value);
                    }
                }),
                AllowedFilter::callback('has_open_slots', function (Builder $query, mixed $value): void {
                    if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        return;
                    }

                    $query->whereRaw(
                        '(select count(*) from applications where applications.task_id = tasks.id and applications.status = ?) < tasks.team_size',
                        [ApplicationStatus::Accepted->value],
                    );
                }),
                AllowedFilter::exact('uploader_id'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('reward'),
                AllowedSort::field('deadline'),
                AllowedSort::field('team_size'),
            )
            ->defaultSort('-created_at');

        if (! $allStatuses && ! $request->filled('filter.status')) {
            $query->whereIn('status', [TaskStatus::Open->value, TaskStatus::Full->value]);
        }

        return $query
            ->with([
                'uploader' => fn (BelongsTo $query) => $query->withAvg('reviewsReceived', 'rating'),
                'applications' => fn (HasMany $query) => $query->where('user_id', $request->user()->id),
            ])
            ->withCount([
                'applications',
                'applications as accepted_applications_count' => fn (Builder $query) => $query
                    ->where('status', ApplicationStatus::Accepted->value),
                'applications as pending_applications_count' => fn (Builder $query) => $query
                    ->where('status', ApplicationStatus::Pending->value),
            ]);
    }

    /**
     * Eager load the relations required by the task resource.
     */
    private function loadRelations(Task $task, Request $request): Task
    {
        return $task->load([
            'uploader' => fn (BelongsTo $query) => $query->withAvg('reviewsReceived', 'rating'),
            'applications' => fn (HasMany $query) => $query->where('user_id', $request->user()->id),
        ])->loadCount([
            'applications',
            'applications as accepted_applications_count' => fn (Builder $query) => $query
                ->where('status', ApplicationStatus::Accepted->value),
            'applications as pending_applications_count' => fn (Builder $query) => $query
                ->where('status', ApplicationStatus::Pending->value),
        ]);
    }

    /**
     * Resolve the requested page size within the allowed bounds.
     */
    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 12);

        if ($perPage < 1) {
            return 12;
        }

        return min($perPage, 50);
    }
}
