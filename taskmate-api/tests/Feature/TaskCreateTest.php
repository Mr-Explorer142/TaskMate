<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

function validTaskPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Build a Laravel dashboard',
        'description' => 'We need a small team to build an admin dashboard for our SaaS.',
        'team_size' => 3,
        'type' => 'paid',
        'reward' => 250.50,
        'deadline' => now()->addWeek()->toDateString(),
        'skills' => ['Laravel', 'React'],
    ], $overrides);
}

it('creates a task owned by the authenticated user', function () {
    $user = authAs();
    $other = User::factory()->create();

    $response = $this->postJson('/api/v1/tasks', validTaskPayload([
        'uploader_id' => $other->id,
        'skills' => ['Laravel', ' laravel ', 'React', ''],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.title', 'Build a Laravel dashboard')
        ->assertJsonPath('data.status', TaskStatus::Open->value)
        ->assertJsonPath('data.type', 'paid')
        ->assertJsonPath('data.skills', ['Laravel', 'React'])
        ->assertJsonPath('data.uploader.id', $user->id)
        ->assertJsonPath('data.is_owner', true)
        ->assertJsonPath('data.accepted_count', 0)
        ->assertJsonPath('data.open_slots', 3)
        ->assertJsonPath('data.applications_count', 0);

    expect($response->json('data.uploader'))->not->toHaveKey('email');

    $task = Task::query()->firstOrFail();

    expect($task->uploader_id)->toBe($user->id)
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and($task->skills)->toBe(['Laravel', 'React']);
});

it('creates a points task with an integer reward', function () {
    authAs();

    $this->postJson('/api/v1/tasks', validTaskPayload([
        'type' => 'points',
        'reward' => 50,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.type', 'points')
        ->assertJsonPath('data.reward', '50.00');

    expect(Task::query()->count())->toBe(1);
});

it('rejects guests', function () {
    $this->postJson('/api/v1/tasks', validTaskPayload())->assertUnauthorized();
});

it('rejects invalid task fields', function (array $overrides, string $field) {
    authAs();

    $this->postJson('/api/v1/tasks', validTaskPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Task::query()->count())->toBe(0);
})->with([
    'title too short' => [['title' => 'abcd'], 'title'],
    'title too long' => [['title' => str_repeat('a', 121)], 'title'],
    'description too short' => [['description' => 'Too short'], 'description'],
    'description too long' => [['description' => str_repeat('a', 5001)], 'description'],
    'team size too small' => [['team_size' => 0], 'team_size'],
    'team size too large' => [['team_size' => 21], 'team_size'],
    'invalid type' => [['type' => 'volunteer'], 'type'],
    'paid reward zero' => [['type' => 'paid', 'reward' => 0], 'reward'],
    'paid reward negative' => [['type' => 'paid', 'reward' => -5], 'reward'],
    'paid reward too many decimals' => [['type' => 'paid', 'reward' => 10.123], 'reward'],
    'paid reward too large' => [['type' => 'paid', 'reward' => 1000000.01], 'reward'],
    'points reward decimal' => [['type' => 'points', 'reward' => 10.5], 'reward'],
    'points reward zero' => [['type' => 'points', 'reward' => 0], 'reward'],
    'points reward negative' => [['type' => 'points', 'reward' => -2], 'reward'],
    'past deadline' => [['deadline' => now()->subDay()->toDateString()], 'deadline'],
    'too many skills' => [['skills' => array_map(fn (int $i) => 'Skill '.$i, range(1, 16))], 'skills'],
    'skill too short' => [['skills' => ['L']], 'skills.0'],
    'skill too long' => [['skills' => [str_repeat('a', 41)]], 'skills.0'],
]);

it('accepts a deadline of today', function () {
    authAs();

    $this->postJson('/api/v1/tasks', validTaskPayload([
        'deadline' => now()->toDateString(),
    ]))->assertCreated();
});
