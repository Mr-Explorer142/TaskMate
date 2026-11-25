<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NotificationDispatcher;
use App\Notifications\ProjectStarted;
use App\Services\PointsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

function daySevenNotificationsFor(User $user): Collection
{
    return DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->id)
        ->get();
}

function daySevenAcceptedApplication(Task $task, User $user): Application
{
    $application = new Application;
    $application->forceFill([
        'task_id' => $task->id,
        'user_id' => $user->id,
        'status' => ApplicationStatus::Accepted,
        'accepted_at' => now(),
    ]);
    $application->save();

    return $application;
}

it('notifies the task owner when an application is received', function () {
    $owner = User::factory()->create();
    $applicant = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);

    $response = $this->postJson("/api/v1/tasks/{$task->id}/apply");

    $response->assertCreated();

    $notifications = daySevenNotificationsFor($owner);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe('application_received');

    $payload = json_decode($notifications->first()->data, true);

    expect($payload)->toHaveKeys(['type', 'title', 'body', 'task_id', 'application_id', 'user_id'])
        ->and($payload['type'])->toBe('application_received')
        ->and($payload['task_id'])->toBe($task->id)
        ->and($payload['user_id'])->toBe($applicant->id);

    expect(daySevenNotificationsFor($applicant))->toBeEmpty();
});

it('notifies the applicant when their application is accepted', function () {
    $owner = authAs();
    $applicant = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 1]);
    $application = Application::factory()->create(['task_id' => $task->id, 'user_id' => $applicant->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/accept")->assertOk();

    $notifications = daySevenNotificationsFor($applicant);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe('application_accepted')
        ->and(json_decode($notifications->first()->data, true))->toHaveKeys(['type', 'title', 'body', 'task_id', 'application_id']);

    expect(daySevenNotificationsFor($owner))->toBeEmpty();
});

it('notifies the applicant when their application is rejected', function () {
    $owner = authAs();
    $applicant = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);
    $application = Application::factory()->create(['task_id' => $task->id, 'user_id' => $applicant->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/reject")->assertOk();

    $notifications = daySevenNotificationsFor($applicant);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe('application_rejected');

    expect(daySevenNotificationsFor($owner))->toBeEmpty();
});

it('notifies the owner when an accepted member leaves the team', function () {
    $owner = User::factory()->create();
    $member = authAs();
    $task = Task::factory()->full()->create(['uploader_id' => $owner->id, 'team_size' => 1]);
    $application = daySevenAcceptedApplication($task, $member);

    $this->patchJson("/api/v1/applications/{$application->id}/leave")->assertOk();

    $notifications = daySevenNotificationsFor($owner);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe('member_left')
        ->and(json_decode($notifications->first()->data, true))->toHaveKeys(['type', 'title', 'body', 'task_id', 'user_id']);

    expect(daySevenNotificationsFor($member))->toBeEmpty();
});

it('notifies every accepted member when a project starts in one bulk insert', function () {
    $owner = authAs();
    $firstMember = User::factory()->create();
    $secondMember = User::factory()->create();
    $task = Task::factory()->paid()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);

    daySevenAcceptedApplication($task, $firstMember);
    daySevenAcceptedApplication($task, $secondMember);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->patchJson("/api/v1/tasks/{$task->id}/start")->assertOk();

    $notificationInserts = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'insert into "notifications"'));

    expect($notificationInserts)->toHaveCount(1);

    foreach ([$firstMember, $secondMember] as $member) {
        $notifications = daySevenNotificationsFor($member);

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->type)->toBe('project_started');
    }

    expect(daySevenNotificationsFor($owner))->toBeEmpty();
});

it('notifies every accepted member when a project completes and mentions released points', function () {
    $owner = authAs();
    $member = User::factory()->create();
    $task = Task::factory()->points()->inProgress()->create([
        'uploader_id' => $owner->id,
        'team_size' => 1,
        'reward' => 25,
    ]);

    daySevenAcceptedApplication($task, $member);

    $points = app(PointsService::class);
    $points->grantBonus($owner, 100, 'Test top-up');
    $points->escrow($owner, $task, 25);

    $this->patchJson("/api/v1/tasks/{$task->id}/complete")->assertOk();

    $notifications = daySevenNotificationsFor($member);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe('project_completed')
        ->and(json_decode($notifications->first()->data, true)['body'])->toContain('25 points');

    expect(daySevenNotificationsFor($owner))->toBeEmpty();
});

it('notifies every accepted member when an in-progress project is cancelled', function () {
    $owner = authAs();
    $member = User::factory()->create();
    $task = Task::factory()->paid()->inProgress()->create([
        'uploader_id' => $owner->id,
        'team_size' => 1,
    ]);

    daySevenAcceptedApplication($task, $member);

    $this->patchJson("/api/v1/tasks/{$task->id}/cancel")->assertOk();

    $notifications = daySevenNotificationsFor($member);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe('project_cancelled');

    expect(daySevenNotificationsFor($owner))->toBeEmpty();
});

it('notifies the reviewee when a review is received', function () {
    $owner = User::factory()->create();
    $reviewer = authAs();
    $task = Task::factory()->completed()->create(['uploader_id' => $owner->id]);
    daySevenAcceptedApplication($task, $reviewer);

    $this->postJson("/api/v1/tasks/{$task->id}/reviews", [
        'reviewee_id' => $owner->id,
        'rating' => 5,
        'comment' => 'Great collaboration.',
    ])->assertCreated();

    $notifications = daySevenNotificationsFor($owner);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe('review_received')
        ->and(json_decode($notifications->first()->data, true))->toHaveKeys(['type', 'title', 'body', 'task_id', 'user_id']);

    expect(daySevenNotificationsFor($reviewer))->toBeEmpty();
});

it('does not create notifications for direct messages', function () {
    $first = authAs();
    $second = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $first->id]);
    $conversation = Conversation::factory()->create([
        'task_id' => $task->id,
        'user_one_id' => min($first->id, $second->id),
        'user_two_id' => max($first->id, $second->id),
    ]);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'Hello there',
    ])->assertCreated();

    expect(daySevenNotificationsFor($first))->toBeEmpty()
        ->and(daySevenNotificationsFor($second))->toBeEmpty();
});

it('dispatches queued notifications after the transaction commits', function () {
    $actor = authAs();
    $singleRecipient = User::factory()->create();
    $bulkRecipients = User::factory(2)->create();

    DB::transaction(function () use ($actor, $singleRecipient, $bulkRecipients): void {
        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->send($singleRecipient->id, new ProjectStarted(1, 'Committed project'), $actor->id);
        $dispatcher->send(
            $bulkRecipients->pluck('id')->all(),
            new ProjectStarted(2, 'Committed bulk project'),
            $actor->id,
        );
    });

    expect(daySevenNotificationsFor($singleRecipient))->toHaveCount(1);

    foreach ($bulkRecipients as $recipient) {
        expect(daySevenNotificationsFor($recipient))->toHaveCount(1);
    }
});

it('does not dispatch notifications when the transaction rolls back', function () {
    $actor = authAs();
    $recipient = User::factory()->create();

    try {
        DB::transaction(function () use ($actor, $recipient): void {
            app(NotificationDispatcher::class)->send(
                [$recipient->id],
                new ProjectStarted(1, 'Rolled back project'),
                $actor->id,
            );

            throw new RuntimeException('Force rollback.');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('notifications')->count())->toBe(0);
});
