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
    }
}
