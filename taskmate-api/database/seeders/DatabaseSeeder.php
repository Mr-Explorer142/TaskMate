<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\LinkPlatform;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProfileLink;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use App\Services\PointsService;
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
        $points = app(PointsService::class);
        $signupBonus = (int) config('points.signup_bonus');

        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@taskmate.test',
            'password' => 'password',
            'country' => 'BD',
            'bio' => 'Full-stack developer and designer who loves small, focused teams.',
            'skills' => ['Laravel', 'React', 'UI Design'],
        ]);

        $points->grantBonus($demo, $signupBonus, 'Welcome bonus');

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
            $points->grantBonus($user, $signupBonus, 'Welcome bonus');

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
                'reward' => $type === TaskType::Paid ? fake()->randomFloat(2, 50, 500) : random_int(20, 60),
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

            if ($type === TaskType::Points && in_array($status, [TaskStatus::InProgress, TaskStatus::Completed], true)) {
                $required = (int) $task->reward * $accepted->count();

                $this->ensureUploaderHasPoints($points, $task->uploader, $required);
                $points->escrow($task->uploader, $task, $required);

                if ($status === TaskStatus::Completed) {
                    foreach ($accepted as $user) {
                        $points->release($task, $user, (int) $task->reward);
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
        $this->seedConversations($demo);
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
            app(PointsService::class)->grantBonus(
                $leftUser,
                (int) config('points.signup_bonus'),
                'Welcome bonus',
            );
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

        if ($startedTask->type === TaskType::Points) {
            $points = app(PointsService::class);
            $required = (int) $startedTask->reward * $startedTask->acceptedApplications()->count();

            $this->ensureUploaderHasPoints($points, $startedTask->uploader, $required);
            $points->escrow($startedTask->uploader, $startedTask, $required);
        }
    }

    /**
     * Top up an uploader so a seeded escrow can be funded.
     */
    private function ensureUploaderHasPoints(PointsService $points, User $uploader, int $required): void
    {
        $uploader->refresh();

        if ($uploader->points_balance < $required) {
            $points->grantBonus(
                $uploader,
                $required - $uploader->points_balance + 100,
                'Seed escrow top-up',
            );
        }
    }

    /**
     * Seed a few conversations between the demo user and other members.
     */
    private function seedConversations(User $demo): void
    {
        $partners = User::query()
            ->whereKeyNot($demo->id)
            ->inRandomOrder()
            ->take(3)
            ->get();

        $tasks = Task::query()
            ->where(function (Builder $query) use ($demo): void {
                $query->where('uploader_id', $demo->id)
                    ->orWhereHas('applications', fn (Builder $query) => $query
                        ->where('user_id', $demo->id));
            })
            ->get();

        if ($partners->isEmpty()) {
            return;
        }

        if ($tasks->isEmpty()) {
            $tasks = Task::factory(2)->open()->create(['uploader_id' => $demo->id])->toBase();
        }

        foreach ($partners as $partner) {
            $task = $tasks->random();

            [$userOneId, $userTwoId] = $demo->id < $partner->id
                ? [$demo->id, $partner->id]
                : [$partner->id, $demo->id];

            $conversation = Conversation::query()->create([
                'task_id' => $task->id,
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]);

            $messageCount = random_int(5, 15);
            $startedAt = now()->subDays(random_int(1, 7))->subMinutes($messageCount * 3);
            $lastPartnerIndex = $messageCount % 2 === 1 ? $messageCount : $messageCount - 1;

            for ($index = 1; $index <= $messageCount; $index++) {
                $sender = $index % 2 === 1 ? $partner : $demo;
                $sentAt = $startedAt->copy()->addMinutes($index * 3);

                Message::factory()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'body' => fake()->paragraph(),
                    'read_at' => $sender->id === $demo->id || $index !== $lastPartnerIndex
                        ? $sentAt->copy()
                        : null,
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                ]);
            }

            $conversation->forceFill([
                'created_at' => $startedAt,
                'updated_at' => $startedAt->copy()->addMinutes($messageCount * 3),
            ])->save();
        }
    }
}
