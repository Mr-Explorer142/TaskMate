<?php

use App\Enums\TaskStatus;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PointTransaction;
use App\Models\ProfileLink;
use App\Models\Review;
use App\Models\SocialAccount;
use App\Models\Task;
use App\Models\User;

it('does not mark sensitive columns as fillable', function (string $modelClass, array $sensitiveFields) {
    $fillable = (new $modelClass)->getFillable();

    foreach ($sensitiveFields as $field) {
        expect($fillable)->not->toContain($field);
    }
})->with([
    'user' => [User::class, ['email_verified_at', 'cv_path', 'cv_original_name', 'cv_uploaded_at', 'points_balance', 'remember_token']],
    'task' => [Task::class, ['uploader_id', 'status', 'escrow_amount', 'started_at', 'completed_at']],
    'application' => [Application::class, ['task_id', 'user_id', 'status', 'accepted_at', 'left_at']],
    'review' => [Review::class, ['task_id', 'reviewer_id', 'reviewee_id']],
    'point transaction' => [PointTransaction::class, ['user_id', 'task_id', 'amount', 'type']],
    'conversation' => [Conversation::class, ['task_id', 'user_one_id', 'user_two_id']],
    'message' => [Message::class, ['conversation_id', 'sender_id', 'read_at']],
    'profile link' => [ProfileLink::class, ['user_id']],
    'social account' => [SocialAccount::class, ['user_id']],
]);

it('ignores sensitive fields sent when creating a task', function () {
    $owner = authAs();
    $other = User::factory()->create();

    $response = $this->postJson('/api/v1/tasks', [
        'title' => 'A clean task title',
        'description' => 'A sufficiently long description for this task.',
        'team_size' => 2,
        'type' => 'paid',
        'reward' => 100,
        'uploader_id' => $other->id,
        'status' => TaskStatus::Completed->value,
        'escrow_amount' => 9999,
    ]);

    $response->assertCreated();

    $task = Task::query()->where('title', 'A clean task title')->firstOrFail();

    expect((int) $task->uploader_id)->toBe($owner->id)
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and((int) $task->escrow_amount)->toBe(0);
});

it('ignores sensitive fields sent when updating a profile', function () {
    $user = authAs();
    $user->forceFill(['points_balance' => 50, 'cv_path' => 'cvs/1/original.pdf'])->save();

    $this->putJson('/api/v1/profile', [
        'bio' => 'Updated bio.',
        'points_balance' => 9999,
        'cv_path' => 'cvs/1/hacked.pdf',
        'email_verified_at' => null,
    ])->assertOk();

    $user->refresh();

    expect((int) $user->points_balance)->toBe(50)
        ->and($user->cv_path)->toBe('cvs/1/original.pdf')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('ignores sensitive fields sent when applying to a task', function () {
    $applicant = authAs();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);

    $this->postJson("/api/v1/tasks/{$task->id}/apply", [
        'message' => 'I would like to help.',
        'user_id' => $other->id,
        'status' => 'accepted',
        'accepted_at' => now()->toIso8601String(),
    ])->assertCreated();

    $application = Application::query()->where('task_id', $task->id)->firstOrFail();

    expect((int) $application->user_id)->toBe($applicant->id)
        ->and($application->status->value)->toBe('pending')
        ->and($application->accepted_at)->toBeNull();
});
