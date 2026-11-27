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
        'Bookkeeping', 'Content Writing', 'Copywriting', 'Customer Support', 'Data Entry',
        'Event Planning', 'Fundraising', 'Graphic Design', 'HR Admin', 'Lesson Planning',
        'Legal Admin', 'Market Research', 'Photo Editing', 'Project Planning', 'Proofreading',
        'Real Estate', 'Social Media', 'Survey Design', 'Translation', 'Video Editing',
    ];

    /**
     * Curated task briefs for seeded demo tasks.
     *
     * Each entry keeps a title and its description together so demo tasks read
     * like real projects from a range of fields rather than generated filler.
     *
     * @var array<int, array{title: string, description: string}>
     */
    private const TASK_BRIEFS = [
        [
            'title' => 'Plan a community sports tournament',
            'description' => 'We need help organising a weekend basketball tournament for 40 local players. Prepare the schedule, venue checklist, and a simple volunteer roster. Please flag any conflicts with venue availability early.',
        ],
        [
            'title' => 'Create a social media content calendar',
            'description' => 'A small bakery needs a four-week content calendar for Instagram and Facebook. Include daily captions, photo ideas, and a few promotional posts for weekend specials. Keep the tone warm and local.',
        ],
        [
            'title' => 'Reconcile a month of expense receipts',
            'description' => 'We have a folder of scanned receipts from June and need them categorised in a spreadsheet. Match each receipt to the right project and total the amounts by category. Do not change any of the original files.',
        ],
        [
            'title' => 'Design a printable event flyer',
            'description' => 'Design a single-page A5 flyer for a charity run. Use the supplied logo and event details, and deliver both a print-ready PDF and an editable source file. Keep the key information readable from a distance.',
        ],
        [
            'title' => 'Write lesson plans for beginner English learners',
            'description' => 'Prepare three 45-minute lesson plans for adult beginners covering introductions, daily routines, and shopping. Include learning goals, a short activity, and a simple homework task for each lesson.',
        ],
        [
            'title' => 'Edit product photos for an online store',
            'description' => 'We have 30 raw product photos that need cropping, background cleanup, and consistent colour. Deliver web-ready images plus the edited source files, and keep each product\'s original filename.',
        ],
        [
            'title' => 'Draft a job description and interview scorecard',
            'description' => 'Write a clear job description for a part-time customer support role and a simple scorecard for interviews. Keep the language inclusive and list the must-have skills separately from the nice-to-haves.',
        ],
        [
            'title' => 'Summarise a competitor market study',
            'description' => 'Read the supplied competitor brochures and prepare a two-page summary of pricing, positioning, and gaps. Use tables where helpful and finish with three practical recommendations.',
        ],
        [
            'title' => 'Prepare a conference run-of-show',
            'description' => 'Build a minute-by-minute run-of-show for a one-day community conference. Include speaker slots, breaks, setup notes, and a contingency plan for late arrivals.',
        ],
        [
            'title' => 'Write product descriptions for a retail catalogue',
            'description' => 'Write short, accurate descriptions for 20 home-goods items. Each description should cover the main use, material, and one care instruction without inventing details.',
        ],
        [
            'title' => 'Create a plain-language patient explainer',
            'description' => 'Turn a dense clinic handout into a one-page explainer that patients can understand. Keep the medical facts exactly as provided and avoid giving any new advice.',
        ],
        [
            'title' => 'Organise a donor thank-you email sequence',
            'description' => 'Draft three short thank-you emails for first-time donors. Keep them personal, include clear next steps, and make sure each email works with our existing template.',
        ],
        [
            'title' => 'Clean up a property listing spreadsheet',
            'description' => 'Review 60 property listings for missing fields, duplicate entries, and inconsistent formatting. Return a cleaned spreadsheet with a short note explaining every change you made.',
        ],
        [
            'title' => 'Design a volunteer onboarding pack',
            'description' => 'Create a short welcome pack for new volunteers with a checklist, code of conduct summary, and FAQ. Keep it to two pages and make it easy to print.',
        ],
        [
            'title' => 'Write a press release for a local initiative',
            'description' => 'Draft a one-page press release announcing a neighbourhood clean-up day. Include who, what, when, where, and a quote from the organiser, using only the facts provided.',
        ],
        [
            'title' => 'Build a simple inventory tracking sheet',
            'description' => 'Set up a spreadsheet to track stock on hand, reorder points, and supplier details for a small shop. Add basic formulas so low-stock items are highlighted automatically.',
        ],
        [
            'title' => 'Translate a community newsletter into French',
            'description' => 'Translate a two-page community newsletter into natural French. Keep names, dates, and addresses unchanged, and flag any idioms that need a local alternative.',
        ],
        [
            'title' => 'Review a lease summary for a small business',
            'description' => 'Read the supplied lease summary and prepare a plain-language checklist of key dates, costs, and obligations. This is a summary only, not legal advice.',
        ],
        [
            'title' => 'Plan a podcast episode outline',
            'description' => 'Create a 30-minute podcast outline with an intro, three discussion segments, and closing notes. Include suggested questions for the guest and a short sponsor mention.',
        ],
        [
            'title' => 'Prepare a customer feedback survey',
            'description' => 'Write a 10-question survey for customers of a local café. Mix multiple-choice and short-answer questions, and keep the whole survey under three minutes to complete.',
        ],
        [
            'title' => 'Completed community garden handoff',
            'description' => 'A finished handoff for the community garden project with the volunteer roster, supplier list, and a short note on what is still pending.',
        ],
        [
            'title' => 'Completed paid: charity run flyer',
            'description' => 'A completed flyer design for the charity run, including the print-ready PDF and editable source files.',
        ],
        [
            'title' => 'Completed points: receipt reconciliation',
            'description' => 'A finished reconciliation of a month of receipts, categorised by project with totals and a short change log.',
        ],
        [
            'title' => 'Prepare a proposal for a school workshop',
            'description' => 'Draft a short proposal for a weekend workshop, including scope, schedule, and assumptions. Keep it editable so the team can adjust the budget before sending.',
        ],
        [
            'title' => 'Review vendor quotes for the office move',
            'description' => 'Compare three vendor quotes for the office move and summarise cost, timing, and included services. Leave clear notes on any missing information.',
        ],
        [
            'title' => 'Reopen an event after a volunteer withdrew',
            'description' => 'A volunteer withdrew after setup began, so this event needs a replacement. Review the current checklist, pick up the remaining tasks, and document anything that was left unfinished.',
        ],
        [
            'title' => 'Resume a newsletter production run',
            'description' => 'This newsletter was started last week and needs someone to finish the layout, proofread the copy, and prepare the final send file.',
        ],
        [
            'title' => 'Coordinate notes from the first planning call',
            'description' => 'Collect the notes from the first planning call and share a short summary with the team, including owners and open questions for the next meeting.',
        ],
        [
            'title' => 'Draft a handoff checklist for new volunteers',
            'description' => 'Put together a short checklist covering shifts, contacts, equipment, and open decisions so the next volunteer can start without guesswork.',
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
            'skills' => ['Project Planning', 'Content Writing', 'Event Planning'],
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
            'skills' => ['Copywriting', 'Social Media', 'Proofreading'],
        ]);

        $bob = User::factory()->create([
            'name' => 'Bob Demo',
            'email' => 'bob@taskmate.test',
            'password' => 'password',
            'country' => 'GB',
            'bio' => 'Backend developer who enjoys Laravel and clean APIs.',
            'skills' => ['Bookkeeping', 'Data Entry', 'Market Research'],
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
