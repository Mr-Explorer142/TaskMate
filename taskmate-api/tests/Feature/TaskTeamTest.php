<?php

use App\Models\Application;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists only accepted team members with public fields and meta', function () {
    $owner = User::factory()->create();
    $task = Task::factory()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 3,
    ]);

    $first = User::factory()->create([
        'name' => 'First Member',
        'email' => 'first@example.com',
        'cv_path' => 'cvs/1/secret.pdf',
        'points_balance' => 999,
        'bio' => 'First bio',
        'skills' => ['Laravel', 'React'],
    ]);
    $second = User::factory()->create(['name' => 'Second Member']);

    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $first->id,
        'accepted_at' => now()->subDay(),
    ]);
    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $second->id,
        'accepted_at' => now(),
    ]);
    Application::factory()->create(['task_id' => $task->id]);
    Application::factory()->rejected()->create(['task_id' => $task->id]);
    Application::factory()->left()->create(['task_id' => $task->id]);

    authAs(User::factory()->create());

    $response = $this->getJson("/api/v1/tasks/{$task->id}/team")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.team_size', 3)
        ->assertJsonPath('meta.accepted_count', 2)
        ->assertJsonPath('meta.open_slots', 1)
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.name', 'First Member')
        ->assertJsonPath('data.0.bio', 'First bio')
        ->assertJsonPath('data.0.skills', ['Laravel', 'React'])
        ->assertJsonPath('data.1.id', $second->id);

    expect($response->json('data.0'))->toHaveKeys([
        'id', 'name', 'avatar', 'bio', 'skills', 'average_rating', 'accepted_at',
    ])
        ->not->toHaveKey('email')
        ->not->toHaveKey('cv_path')
        ->not->toHaveKey('points_balance')
        ->and($response->json('data.0.accepted_at'))->not->toBeNull();
});

it('allows any authenticated user to view the team', function () {
    $task = Task::factory()->open()->create(['team_size' => 2]);
    Application::factory()->accepted()->create(['task_id' => $task->id]);

    authAs();

    $this->getJson("/api/v1/tasks/{$task->id}/team")->assertOk();
});

it('keeps the team query count bounded', function () {
    $task = Task::factory()->open()->create(['team_size' => 20]);

    Application::factory()
        ->accepted()
        ->count(10)
        ->create(['task_id' => $task->id]);

    authAs();

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->getJson("/api/v1/tasks/{$task->id}/team")
        ->assertOk()
        ->assertJsonCount(10, 'data');

    expect($queries)->toBeLessThanOrEqual(5);
});

it('allows guests to view a team', function () {
    $task = Task::factory()->open()->create(['team_size' => 3]);
    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->getJson("/api/v1/tasks/{$task->id}/team")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.team_size', 3)
        ->assertJsonPath('meta.accepted_count', 1);
});
