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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * Curated task briefs for seeded demo tasks.
     *
     * Each entry keeps a title and its description together so demo tasks read
     * like real projects rather than generated filler.
     *
     * @var array<int, array{title: string, description: string}>
     */
    private const TASK_BRIEFS = [
        [
            'title' => 'Build a Laravel dashboard for client reporting',
            'description' => 'We need a small team to build an admin dashboard that shows client revenue, active projects, and overdue invoices. The work covers backend queries, a responsive React frontend, and basic role-based access. Please share a similar dashboard you have shipped before we start.',
        ],
        [
            'title' => 'Design a mobile onboarding flow in Figma',
            'description' => 'Our product needs a three-screen onboarding flow for first-time mobile users. We are looking for clean, accessible screens with reusable components and a short handoff document for developers. Include empty and error states in the deliverable.',
        ],
        [
            'title' => 'Write SEO copy for a SaaS product launch',
            'description' => 'We are launching a project-management tool and need a landing page, three blog posts, and meta descriptions. The copy should explain the product in plain language and target teams comparing tools. Send two drafts so we can choose a direction together.',
        ],
        [
            'title' => 'Clean and deduplicate customer records',
            'description' => 'Our customer table contains duplicate contacts from several imports. We need someone to review the data, define safe matching rules, and clean the records without losing invoice history. Document the rules so the team can repeat the process monthly.',
        ],
        [
            'title' => 'Build a React component library for our admin app',
            'description' => 'We want a small set of shared React components: buttons, inputs, tables, modals, and form validation states. Each component should include usage examples and be easy to theme. The final handoff should include a short demo page showing every state.',
        ],
        [
            'title' => 'Create a brand refresh for a local coffee shop',
            'description' => 'The owner wants a warmer visual identity: logo variations, colour palette, typography, and social templates. Keep the existing handwritten logo as the starting point. Deliver editable source files along with a one-page brand guide.',
        ],
        [
            'title' => 'Set up automated tests for a Laravel API',
            'description' => 'Our task API has good coverage in a few places but none around reviews and team membership. Add feature tests for the main flows, keep the suite fast, and fix any issues the new tests uncover. Explain any test data you add.',
        ],
        [
            'title' => 'Migrate legacy blog content into the new CMS',
            'description' => 'We have about 150 posts in an old WordPress export that need to move into our new CMS. Preserve publish dates, categories, images, and internal links. Deliver a migration script plus a short report on anything that could not be mapped.',
        ],
        [
            'title' => 'Edit a 90-second product demo video',
            'description' => 'We have raw screen recordings and a voiceover for a short product demo. Cut it to roughly 90 seconds, add captions, simple transitions, and our logo at the end. Deliver an MP4 plus the editable project file.',
        ],
        [
            'title' => 'Translate onboarding emails into Spanish',
            'description' => 'We need five lifecycle emails translated into natural Spanish rather than literal word-for-word text. Keep the greeting and unsubscribe links intact, and flag any culture-specific references. Return a bilingual document for review.',
        ],
        [
            'title' => 'Fix responsive layout issues on a marketing site',
            'description' => 'Several landing pages break between 768px and 1024px, especially the pricing table and navigation. Find the root causes in the existing CSS, fix them without redesigning the pages, and check the result on real devices where possible.',
        ],
        [
            'title' => 'Implement password reset and email verification',
            'description' => 'Add password reset and email verification to our Laravel application using the built-in features. Cover the notification templates, rate limits, and a simple settings page showing verification status. Include tests for the full flow.',
        ],
        [
            'title' => 'Audit and improve MySQL query performance',
            'description' => 'A few reporting pages are slow because of unindexed filters and N+1 queries. Profile the top five endpoints, add the smallest safe indexes, and rewrite the worst queries. Share before-and-after timings with your pull request.',
        ],
        [
            'title' => 'Build a Figma design system for a dashboard',
            'description' => 'Create a compact design system with colour tokens, type scale, spacing rules, and 15 core components. The system should support both light and dark themes. Deliver a Figma library with clear naming and usage notes.',
        ],
        [
            'title' => 'Write documentation for a developer onboarding guide',
            'description' => 'New developers take too long to get the local app running. Write a step-by-step guide covering environment setup, seed data, tests, and common errors. Verify every command against a clean checkout before handing it over.',
        ],
        [
            'title' => 'Integrate a payment gateway into a checkout flow',
            'description' => 'We need a sandbox-ready payment integration with clear error handling and webhook verification. The checkout should show a summary before charging and a confirmation page afterwards. Do not touch live credentials at any point.',
        ],
        [
            'title' => 'Populate a demo database with realistic content',
            'description' => 'Our demo environment currently shows random Latin placeholder text on task pages. Replace it with curated titles and descriptions that read like real projects, and make sure a fresh seed produces the same result every time.',
        ],
        [
            'title' => 'Design email templates for weekly reports',
            'description' => 'We need two responsive email templates for a weekly summary: one for managers and one for clients. Keep them simple to edit in our existing sending tool. Include a plain-text fallback and a dark-mode friendly version.',
        ],
        [
            'title' => 'Review accessibility of a public task page',
            'description' => 'Run an accessibility review of our public task listing and detail pages. Check keyboard navigation, focus states, colour contrast, and screen-reader labels. Provide a prioritised list of fixes and implement the quick wins.',
        ],
        [
            'title' => 'Build a small points ledger for a community app',
            'description' => 'The app rewards members with points for completed work. We need a ledger that records escrow, release, and refund events with a clear audit trail. Add a balance page for users and tests that cover every state transition.',
        ],
        [
            'title' => 'Completed demo: landing page handoff',
            'description' => 'A finished landing page handoff with responsive layouts, reviewed copy, and a short note explaining the design decisions to the team.',
        ],
        [
            'title' => 'Completed paid: brand refresh',
            'description' => 'A completed brand refresh covering logo variations, palette, typography, and editable source files for future marketing work.',
        ],
        [
            'title' => 'Completed points: data cleanup',
            'description' => 'A finished data cleanup pass that removed duplicate customer records while preserving invoice history and documenting the matching rules.',
        ],
        [
            'title' => 'Prepare a proposal for a client website',
            'description' => 'Draft a short proposal for a five-page client website, including scope, timeline, and assumptions. Keep it editable so the team can adjust pricing before sending it to the client.',
        ],
        [
            'title' => 'Review pull requests for the reporting module',
            'description' => 'Review a small set of pull requests in the reporting module, focusing on query correctness and test coverage. Leave clear notes for the authors and approve only when the changes are safe.',
        ],
        [
            'title' => 'Reopen a task after a teammate left',
            'description' => 'A teammate withdrew after the work started, so this task needs a replacement. Review the current implementation, pick up the remaining items, and document anything that was left unfinished.',
        ],
        [
            'title' => 'Resume an in-progress API integration',
            'description' => 'This integration was started last week and needs someone to finish the remaining endpoints, verify the error handling, and add the missing feature tests.',
        ],
        [
            'title' => 'Coordinate kickoff notes for a new project',
            'description' => 'Collect the kickoff notes from the first planning call and share a short summary with the team, including owners and open questions for the next meeting.',
        ],
        [
            'title' => 'Draft a handoff checklist for the design team',
            'description' => 'Put together a short handoff checklist covering design files, assets, copy, and open decisions so the next teammate can start without guesswork.',
        ],
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

        $alice = User::factory()->create([
            'name' => 'Alice Demo',
            'email' => 'alice@taskmate.test',
            'password' => 'password',
            'country' => 'US',
            'bio' => 'Frontend developer looking for focused collaborations.',
            'skills' => ['React', 'Figma', 'UI Design'],
        ]);

        $bob = User::factory()->create([
            'name' => 'Bob Demo',
            'email' => 'bob@taskmate.test',
            'password' => 'password',
            'country' => 'GB',
            'bio' => 'Backend developer who enjoys Laravel and clean APIs.',
            'skills' => ['PHP', 'Laravel', 'MySQL'],
        ]);

        $namedUsers = collect([$alice, $bob]);

        foreach ($namedUsers as $user) {
            $points->grantBonus($user, $signupBonus, 'Welcome bonus');
        }

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

        $users = $users->concat($namedUsers)->values();
        $everyone = $users->concat([$demo])->values();

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

        foreach ($statuses as $index => $status) {
            $type = $index % 2 === 0 ? TaskType::Paid : TaskType::Points;
            $brief = $this->taskBrief($index);

            $task = Task::factory()->create([
                'uploader_id' => $index % 5 === 0 ? $demo->id : $users->random()->id,
                'title' => $brief['title'],
                'description' => $brief['description'],
                'team_size' => random_int(2, 5),
                'type' => $type,
                'reward' => $type === TaskType::Paid ? fake()->randomFloat(2, 50, 500) : random_int(20, 60),
                'status' => $status,
                'started_at' => in_array($status, [TaskStatus::InProgress, TaskStatus::Completed], true)
                    ? now()->subDays(random_int(1, 20))
                    : null,
                'completed_at' => $status === TaskStatus::Completed
                    ? now()->subDays(random_int(1, 10))
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
        $this->seedCompletedReviewScenarios($points, $demo, $users);
        $this->seedConversations($demo);
        $this->seedDemoNotifications($demo);
    }

    /**
     * Seed deterministic completed projects with released points and partial reviews.
     *
     * @param  Collection<int, User>  $users
     */
    private function seedCompletedReviewScenarios(PointsService $points, User $demo, Collection $users): void
    {
        $members = $users->values();

        $completedDemoBrief = $this->taskBrief(20);

        $demoTask = Task::factory()->completed()->create([
            'uploader_id' => $demo->id,
            'title' => $completedDemoBrief['title'],
            'description' => $completedDemoBrief['description'],
            'team_size' => 3,
            'type' => TaskType::Points,
            'reward' => 40,
            'started_at' => now()->subDays(12),
            'completed_at' => now()->subDays(5),
        ]);

        $firstMember = $members[0];
        $secondMember = $members[1];

        foreach ([$firstMember, $secondMember] as $member) {
            Application::factory()->accepted()->create([
                'task_id' => $demoTask->id,
                'user_id' => $member->id,
            ]);
        }

        $this->ensureUploaderHasPoints($points, $demoTask->uploader, 80);
        $points->escrow($demoTask->uploader, $demoTask, 80);
        $points->release($demoTask, $firstMember, 40);
        $points->release($demoTask, $secondMember, 40);

        $this->createReview($demoTask, $firstMember, $demo, 5, 'Clear brief and fast feedback.', now()->subDays(4));
        $this->createReview($demoTask, $demo, $firstMember, 5, 'Great work on the handoff.', now()->subDays(3));
        $this->createReview($demoTask, $secondMember, $firstMember, 4, null, now()->subDays(2));

        $paidTaskBrief = $this->taskBrief(21);

        $paidTask = Task::factory()->completed()->create([
            'uploader_id' => $members[2]->id,
            'title' => $paidTaskBrief['title'],
            'description' => $paidTaskBrief['description'],
            'team_size' => 2,
            'type' => TaskType::Paid,
            'reward' => 250,
            'completed_at' => now()->subDays(7),
        ]);

        Application::factory()->accepted()->create([
            'task_id' => $paidTask->id,
            'user_id' => $members[3]->id,
        ]);

        $this->createReview($paidTask, $members[2], $members[3], 5, 'Reliable collaborator.', now()->subDays(6));
        $this->createReview($paidTask, $members[3], $members[2], 4, 'Good communication.', now()->subDays(6));

        $otherTaskBrief = $this->taskBrief(22);

        $otherTask = Task::factory()->completed()->create([
            'uploader_id' => $members[4]->id,
            'title' => $otherTaskBrief['title'],
            'description' => $otherTaskBrief['description'],
            'team_size' => 3,
            'type' => TaskType::Points,
            'reward' => 30,
            'completed_at' => now()->subDays(2),
        ]);

        foreach ([$members[5], $members[6]] as $member) {
            Application::factory()->accepted()->create([
                'task_id' => $otherTask->id,
                'user_id' => $member->id,
            ]);
        }

        $this->ensureUploaderHasPoints($points, $otherTask->uploader, 60);
        $points->escrow($otherTask->uploader, $otherTask, 60);
        $points->release($otherTask, $members[5], 30);
        $points->release($otherTask, $members[6], 30);

        $this->createReview($otherTask, $members[5], $members[4], 4, null, now()->subDay());
    }

    /**
     * Curated task briefs available to the demo seeder.
     *
     * @return array<int, array{title: string, description: string}>
     */
    public static function taskBriefs(): array
    {
        return self::TASK_BRIEFS;
    }

    /**
     * Get a curated title and description pair for a seeded task.
     *
     * @return array{title: string, description: string}
     */
    private function taskBrief(int $index): array
    {
        return self::TASK_BRIEFS[$index % count(self::TASK_BRIEFS)];
    }

    /**
     * Create a seeded review with a stable timestamp.
     */
    private function createReview(
        Task $task,
        User $reviewer,
        User $reviewee,
        int $rating,
        ?string $comment,
        Carbon $createdAt,
    ): void {
        Review::factory()->create([
            'task_id' => $task->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
            'rating' => $rating,
            'comment' => $comment,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
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
                $brief = $this->taskBrief(23);

                $task = Task::factory()->open()->create([
                    'uploader_id' => $users->first()->id,
                    'title' => $brief['title'],
                    'description' => $brief['description'],
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
                    $task->forceFill(['status' => TaskStatus::Full])->save();
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
            $brief = $this->taskBrief(24);

            $demoTask = Task::factory()->open()->create([
                'uploader_id' => $demo->id,
                'title' => $brief['title'],
                'description' => $brief['description'],
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
                    $task->forceFill(['status' => TaskStatus::Full])->save();
                }

                if ($task->status === TaskStatus::Full && $task->accepted_applications_count < $task->team_size) {
                    $task->forceFill(['status' => TaskStatus::Open])->save();
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
            $brief = $this->taskBrief(25);

            $openTask = Task::factory()->open()->create([
                'uploader_id' => $demo->id,
                'title' => $brief['title'],
                'description' => $brief['description'],
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

        $brief = $this->taskBrief(26);

        $startedTask = Task::factory()->inProgress()->create([
            'uploader_id' => $demo->id,
            'title' => $brief['title'],
            'description' => $brief['description'],
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
            $kickoffBrief = $this->taskBrief(27);
            $handoffBrief = $this->taskBrief(28);

            $tasks = Task::factory(2)
                ->open()
                ->sequence(
                    [
                        'title' => $kickoffBrief['title'],
                        'description' => $kickoffBrief['description'],
                    ],
                    [
                        'title' => $handoffBrief['title'],
                        'description' => $handoffBrief['description'],
                    ],
                )
                ->create(['uploader_id' => $demo->id])
                ->toBase();
        }

        foreach ($partners as $partner) {
            $task = $tasks->random();

            [$userOneId, $userTwoId] = $demo->id < $partner->id
                ? [$demo->id, $partner->id]
                : [$partner->id, $demo->id];

            $conversation = new Conversation;
            $conversation->forceFill([
                'task_id' => $task->id,
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]);
            $conversation->save();

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

    /**
     * Seed a few read and unread database notifications for the demo account.
     */
    private function seedDemoNotifications(User $demo): void
    {
        $task = Task::query()->where('uploader_id', $demo->id)->first();
        $taskId = (int) ($task?->id ?? 0);
        $applicationId = (int) (Application::query()->where('task_id', $taskId)->value('id') ?? 0);
        $applicantId = (int) (Application::query()->where('task_id', $taskId)->value('user_id') ?? 0);
        $title = $task?->title ?? 'Demo task';
        $now = now();

        $definitions = [
            [
                'type' => 'application_received',
                'data' => [
                    'type' => 'application_received',
                    'title' => 'New application',
                    'body' => "Alice Demo applied to \"{$title}\".",
                    'task_id' => $taskId,
                    'application_id' => $applicationId,
                    'user_id' => $applicantId ?: $demo->id,
                ],
                'read_at' => null,
                'minutes_ago' => 4,
            ],
            [
                'type' => 'project_started',
                'data' => [
                    'type' => 'project_started',
                    'title' => 'Project started',
                    'body' => "\"{$title}\" is now in progress.",
                    'task_id' => $taskId,
                ],
                'read_at' => null,
                'minutes_ago' => 35,
            ],
            [
                'type' => 'review_received',
                'data' => [
                    'type' => 'review_received',
                    'title' => 'New review',
                    'body' => "Bob Demo rated you 5/5 for \"{$title}\".",
                    'task_id' => $taskId,
                    'user_id' => $applicantId ?: $demo->id,
                ],
                'read_at' => $now->copy()->subMinutes(90),
                'minutes_ago' => 120,
            ],
        ];

        $rows = [];

        foreach ($definitions as $definition) {
            $createdAt = $now->copy()->subMinutes($definition['minutes_ago']);

            $rows[] = [
                'id' => (string) Str::uuid(),
                'type' => $definition['type'],
                'notifiable_type' => User::class,
                'notifiable_id' => $demo->id,
                'data' => (string) json_encode($definition['data']),
                'read_at' => $definition['read_at'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        DB::table('notifications')->insert($rows);
    }
}
