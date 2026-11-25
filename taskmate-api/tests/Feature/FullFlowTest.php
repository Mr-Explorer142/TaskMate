<?php

use App\Enums\TaskStatus;
use App\Models\Application;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function daySevenFullFlowNotificationTypes(User $user): array
{
    return DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->id)
        ->pluck('type')
        ->all();
}

it('runs the complete task journey from registration through reviews', function () {
    $register = function (string $name, string $email): User {
        $this->postJson('/api/v1/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        return User::query()->where('email', $email)->firstOrFail();
    };

    $owner = $register('Flow Owner', 'owner@full-flow.test');
    $firstMember = $register('Flow First', 'first@full-flow.test');
    $secondMember = $register('Flow Second', 'second@full-flow.test');
    $thirdMember = $register('Flow Third', 'third@full-flow.test');

    $bonus = (int) config('points.signup_bonus');

    foreach ([$owner, $firstMember, $secondMember, $thirdMember] as $user) {
        expect((int) $user->points_balance)->toBe($bonus);
    }

    authAs($owner);

    $taskResponse = $this->postJson('/api/v1/tasks', [
        'title' => 'Full flow collaboration',
        'description' => 'A complete points task used to verify the entire journey.',
        'team_size' => 2,
        'type' => 'points',
        'reward' => 50,
    ]);

    $taskResponse->assertCreated();

    $task = Task::query()->where('title', 'Full flow collaboration')->firstOrFail();

    foreach ([$firstMember, $secondMember, $thirdMember] as $applicant) {
        authAs($applicant);

        $this->postJson("/api/v1/tasks/{$task->id}/apply", [
            'message' => 'I can help with this project.',
        ])->assertCreated();
    }

    expect(collect(daySevenFullFlowNotificationTypes($owner))->filter(fn (string $type): bool => $type === 'application_received'))
        ->toHaveCount(3);

    $applications = Application::query()->where('task_id', $task->id)->get()->keyBy('user_id');

    authAs($owner);

    foreach ([$firstMember, $secondMember] as $member) {
        $this->patchJson("/api/v1/applications/{$applications[$member->id]->id}/accept")->assertOk();

        expect(collect(daySevenFullFlowNotificationTypes($member))->contains('application_accepted'))->toBeTrue();
    }

    expect($task->fresh()->status)->toBe(TaskStatus::Full);

    authAs($secondMember);
    $this->patchJson("/api/v1/applications/{$applications[$secondMember->id]->id}/leave")->assertOk();

    expect($task->fresh()->status)->toBe(TaskStatus::Open)
        ->and(collect(daySevenFullFlowNotificationTypes($owner))->contains('member_left'))->toBeTrue();

    authAs($owner);
    $this->patchJson("/api/v1/applications/{$applications[$thirdMember->id]->id}/accept")->assertOk();

    expect($task->fresh()->status)->toBe(TaskStatus::Full);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")->assertOk();

    foreach ([$firstMember, $thirdMember] as $member) {
        expect(collect(daySevenFullFlowNotificationTypes($member))->contains('project_started'))->toBeTrue();
    }

    expect((int) $owner->fresh()->points_balance)->toBe($bonus - 100)
        ->and((int) $task->fresh()->escrow_amount)->toBe(100);

    authAs($owner);
    $this->patchJson("/api/v1/tasks/{$task->id}/complete")->assertOk();

    expect($task->fresh()->status)->toBe(TaskStatus::Completed)
        ->and((int) $task->fresh()->escrow_amount)->toBe(0);

    foreach ([$firstMember, $thirdMember] as $member) {
        expect((int) $member->fresh()->points_balance)->toBe($bonus + 50)
            ->and(collect(daySevenFullFlowNotificationTypes($member))->contains('project_completed'))->toBeTrue();
    }

    expect((int) $owner->fresh()->points_balance)->toBe($bonus - 100)
        ->and((int) $secondMember->fresh()->points_balance)->toBe($bonus)
        ->and($applications[$secondMember->id]->fresh()->status->value)->toBe('left');

    $participants = [$owner, $firstMember, $thirdMember];

    foreach ($participants as $reviewer) {
        authAs($reviewer);

        foreach ($participants as $reviewee) {
            if ($reviewer->id === $reviewee->id) {
                continue;
            }

            $this->postJson("/api/v1/tasks/{$task->id}/reviews", [
                'reviewee_id' => $reviewee->id,
                'rating' => 5,
                'comment' => 'Excellent collaboration throughout the project.',
            ])->assertCreated();
        }
    }

    expect(Review::query()->where('task_id', $task->id)->count())->toBe(6);

    foreach ($participants as $recipient) {
        $reviewNotifications = collect(daySevenFullFlowNotificationTypes($recipient))
            ->filter(fn (string $type): bool => $type === 'review_received');

        expect($reviewNotifications)->toHaveCount(2);
    }

    expect(collect(daySevenFullFlowNotificationTypes($owner))->contains('member_left'))->toBeTrue()
        ->and(collect(daySevenFullFlowNotificationTypes($owner))->contains('application_accepted'))->toBeFalse();

    foreach ([$firstMember, $thirdMember] as $member) {
        expect(collect(daySevenFullFlowNotificationTypes($member))->contains('application_accepted'))->toBeTrue()
            ->and(collect(daySevenFullFlowNotificationTypes($member))->contains('member_left'))->toBeFalse();
    }
});
