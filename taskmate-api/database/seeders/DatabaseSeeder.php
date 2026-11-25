<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\LinkPlatform;
use App\Enums\PointTransactionType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Application;
use App\Models\PointTransaction;
use App\Models\ProfileLink;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Skills available for seeded tasks.
     *
     * @var array<int, string>
     */
    private const TASK_SKILLS = [
        'Laravel', 'React', 'PHP', 'MySQL', 'UI Design', 'Figma', 'Content Writing',
        'SEO', 'Python', 'Data Entry', 'Video Editing', 'Translation',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@taskmate.test',
            'password' => 'password',
            'country' => 'BD',
            'bio' => 'Full-stack developer and designer who loves small, focused teams.',
            'skills' => ['Laravel', 'React', 'UI Design'],
            'points_balance' => 250,
        ]);

        $demo->profileLinks()->createMany([
            [
                'platform' => LinkPlatform::Github,
                'label' => 'GitHub',
                'url' => 'https://github.com/demouser',
                'sort_order' => 0,
            ],
            [
                'platform' => LinkPlatform::Portfolio,
                'label' => 'Portfolio',
                'url' => 'https://demo-portfolio.test',
                'sort_order' => 1,
            ],
            [
                'platform' => LinkPlatform::Behance,
                'label' => 'My Behance',
                'url' => 'https://www.behance.net/demouser',
                'sort_order' => 2,
            ],
        ]);

        $users = User::factory(10)->create();

        foreach ($users as $user) {
            $linkCount = random_int(0, 4);

            for ($i = 0; $i < $linkCount; $i++) {
                ProfileLink::factory()->create([
                    'user_id' => $user->id,
                    'sort_order' => $i,
                ]);
            }
        }

        $statuses = [
            TaskStatus::Open,
            TaskStatus::Open,
            TaskStatus::Open,
            TaskStatus::Open,
            TaskStatus::Open,
            TaskStatus::Open,
            TaskStatus::Open,
            TaskStatus::Open,
            TaskStatus::Full,
            TaskStatus::Full,
            TaskStatus::Full,
            TaskStatus::InProgress,
            TaskStatus::InProgress,
            TaskStatus::InProgress,
            TaskStatus::Completed,
            TaskStatus::Completed,
            TaskStatus::Completed,
            TaskStatus::Completed,
            TaskStatus::Cancelled,
            TaskStatus::Cancelled,
        ];

        $everyone = $users->push($demo);

        foreach ($statuses as $index => $status) {
            $type = $index % 2 === 0 ? TaskType::Paid : TaskType::Points;

            $task = Task::factory()->create([
                'uploader_id' => $index % 5 === 0 ? $demo->id : $users->random()->id,
                'team_size' => random_int(2, 5),
                'type' => $type,
                'reward' => $type === TaskType::Paid ? fake()->randomFloat(2, 50, 500) : 0,
                'status' => $status,
                'started_at' => in_array($status, [TaskStatus::InProgress, TaskStatus::Completed], true)
                    ? now()->subDays(random_int(1, 20))
                    : null,
                'skills' => fake()->randomElements(self::TASK_SKILLS, fake()->numberBetween(2, 5)),
            ]);

            $candidates = $everyone
                ->where('id', '!=', $task->uploader_id)
                ->shuffle()
                ->values();

            $acceptedTarget = match ($status) {
                TaskStatus::Open => random_int(0, max(0, $task->team_size - 1)),
                TaskStatus::Full,
                TaskStatus::InProgress,
                TaskStatus::Completed => min($task->team_size, $candidates->count()),
                TaskStatus::Cancelled => random_int(0, 1),
            };

            $accepted = $candidates->take($acceptedTarget);
            $remaining = $candidates->slice($acceptedTarget)->values();

            foreach ($accepted as $user) {
                Application::factory()->accepted()->create([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                ]);
            }

            $extraStatuses = [ApplicationStatus::Pending, ApplicationStatus::Rejected, ApplicationStatus::Left];
            $extraCount = min($remaining->count(), random_int(1, 5));

            for ($i = 0; $i < $extraCount; $i++) {
                $applicationStatus = $extraStatuses[$i % count($extraStatuses)];

                $factory = match ($applicationStatus) {
                    ApplicationStatus::Rejected => Application::factory()->rejected(),
                    ApplicationStatus::Left => Application::factory()->left(),
                    default => Application::factory(),
                };

                $factory->create([
                    'task_id' => $task->id,
                    'user_id' => $remaining[$i]->id,
                ]);
            }

            if ($type === TaskType::Points) {
                PointTransaction::factory()->create([
                    'user_id' => $task->uploader_id,
                    'task_id' => $task->id,
                    'amount' => -50 * $task->team_size,
                    'type' => PointTransactionType::Escrow,
                ]);

                if (in_array($status, [TaskStatus::Full, TaskStatus::InProgress, TaskStatus::Completed], true)) {
                    foreach ($accepted as $user) {
                        PointTransaction::factory()->create([
                            'user_id' => $user->id,
                            'task_id' => $task->id,
                            'amount' => 50,
                            'type' => PointTransactionType::Release,
                        ]);
                    }
                }
            }

            if ($status === TaskStatus::Completed) {
                foreach ($accepted->take(2) as $user) {
                    Review::factory()->create([
                        'task_id' => $task->id,
                        'reviewer_id' => $task->uploader_id,
                        'reviewee_id' => $user->id,
                    ]);

                    Review::factory()->create([
                        'task_id' => $task->id,
                        'reviewer_id' => $user->id,
                        'reviewee_id' => $task->uploader_id,
                    ]);
                }
            }
        }

        $this->seedDemoApplications($demo, $users);
        $this->normalizeTeamStatuses();
        $this->ensureLeftAndStartedScenarios($demo, $users);
    }

    /**
     * Give the demo user applications in every reviewable state and make sure
     * one of the demo user's own tasks has pending applicants.
     *
     * @param  Collection<int, User>  $users
     */
    private function seedDemoApplications(User $demo, Collection $users): void
    {
        $usedTaskIds = [];

        $ensureApplication = function (ApplicationStatus $status) use ($demo, $users, &$usedTaskIds): void {
            $task = Task::query()
                ->where('uploader_id', '!=', $demo->id)
                ->where('status', TaskStatus::Open)
                ->whereNotIn('id', $usedTaskIds)
                ->whereDoesntHave('applications', fn (Builder $query) => $query->where('user_id', $demo->id))
                ->whereRaw(
                    '(select count(*) from applications where applications.task_id = tasks.id and applications.status = ?) < tasks.team_size',
                    [ApplicationStatus::Accepted->value],
                )
                ->orderBy('id')
                ->first();

            if ($task === null) {
                $task = Task::factory()->open()->create([
                    'uploader_id' => $users->first()->id,
                    'team_size' => 3,
                ]);
            }

            $usedTaskIds[] = $task->id;

            $factory = match ($status) {
                ApplicationStatus::Accepted => Application::factory()->accepted(),
                ApplicationStatus::Rejected => Application::factory()->rejected(),
                default => Application::factory(),
            };

            $factory->create([
                'task_id' => $task->id,
                'user_id' => $demo->id,
            ]);

            if ($status === ApplicationStatus::Accepted) {
                $accepted = $task->acceptedApplications()->count();

                if ($accepted >= $task->team_size) {
                    $task->update(['status' => TaskStatus::Full]);
                }
            }
        };

        $ensureApplication(ApplicationStatus::Pending);
        $ensureApplication(ApplicationStatus::Accepted);
        $ensureApplication(ApplicationStatus::Rejected);

        $demoTask = Task::query()
            ->where('uploader_id', $demo->id)
            ->where('status', TaskStatus::Open)
            ->withCount([
                'applications as pending_applications_count' => fn (Builder $query) => $query
                    ->where('status', ApplicationStatus::Pending->value),
            ])
            ->first();

        if ($demoTask === null) {
            $demoTask = Task::factory()->open()->create([
                'uploader_id' => $demo->id,
                'team_size' => 3,
            ]);
        }

        if (($demoTask->pending_applications_count ?? 0) === 0) {
            $applicant = $users->first(
                fn (User $user) => $user->id !== $demo->id
                    && ! $demoTask->applications()->where('user_id', $user->id)->exists(),
            );

            if ($applicant !== null) {
                Application::factory()->create([
                    'task_id' => $demoTask->id,
                    'user_id' => $applicant->id,
                ]);
            }
        }
    }

    /**
     * Keep the task status in sync with the accepted team size.
     */
    private function normalizeTeamStatuses(): void
    {
        Task::query()
            ->whereIn('status', [TaskStatus::Open, TaskStatus::Full])
            ->withCount([
                'applications as accepted_applications_count' => fn (Builder $query) => $query
                    ->where('status', ApplicationStatus::Accepted->value),
            ])
            ->get()
            ->each(function (Task $task): void {
                if ($task->status === TaskStatus::Open && $task->accepted_applications_count >= $task->team_size) {
                    $task->update(['status' => TaskStatus::Full]);
                }

                if ($task->status === TaskStatus::Full && $task->accepted_applications_count < $task->team_size) {
                    $task->update(['status' => TaskStatus::Open]);
                }
            });
    }

    /**
     * Guarantee the seeded data includes a reopened task after a member left
     * and an in-progress task with a started team.
     *
     * @param  Collection<int, User>  $users
     */
    private function ensureLeftAndStartedScenarios(User $demo, Collection $users): void
    {
        $openTask = Task::query()
            ->where('status', TaskStatus::Open)
            ->whereDoesntHave('applications', fn (Builder $query) => $query
                ->where('status', ApplicationStatus::Left->value))
            ->withCount([
                'applications as accepted_applications_count' => fn (Builder $query) => $query
                    ->where('status', ApplicationStatus::Accepted->value),
            ])
            ->get()
            ->first(fn (Task $task) => $task->accepted_applications_count < $task->team_size);

        if ($openTask === null) {
            $openTask = Task::factory()->open()->create([
                'uploader_id' => $demo->id,
                'team_size' => 3,
                'started_at' => null,
            ]);
        }

        $leftUser = $users->first(
            fn (User $user) => $user->id !== $openTask->uploader_id
                && ! $openTask->applications()->where('user_id', $user->id)->exists(),
        );

        if ($leftUser === null) {
            $leftUser = User::factory()->create();
            $users->push($leftUser);
        }

        Application::factory()->left()->create([
            'task_id' => $openTask->id,
            'user_id' => $leftUser->id,
        ]);

        $startedTaskExists = Task::query()
            ->where('status', TaskStatus::InProgress)
            ->whereNotNull('started_at')
            ->whereHas('acceptedApplications')
            ->exists();

        if ($startedTaskExists) {
            return;
        }

        $startedTask = Task::factory()->inProgress()->create([
            'uploader_id' => $demo->id,
            'team_size' => 2,
            'started_at' => now()->subDays(3),
        ]);

        $users
            ->where('id', '!=', $demo->id)
            ->take($startedTask->team_size)
            ->each(fn (User $user) => Application::factory()->accepted()->create([
                'task_id' => $startedTask->id,
                'user_id' => $user->id,
            ]));
    }
}
