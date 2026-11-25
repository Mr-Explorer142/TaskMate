<?php

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use App\Models\Application;
use App\Models\PointTransaction;
use App\Models\Task;
use App\Services\PointsService;

it('escrows reward times accepted count when a points task starts', function () {
    $owner = authAs();
    app(PointsService::class)->grantBonus($owner, 500, 'Welcome bonus');

    $task = Task::factory()->points()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 3,
        'reward' => 100,
    ]);

    Application::factory()->accepted()->count(2)->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::InProgress->value);

    expect($task->fresh()->escrow_amount)->toBe(200)
        ->and($owner->fresh()->points_balance)->toBe(300);

    $this->assertDatabaseHas('point_transactions', [
        'user_id' => $owner->id,
        'task_id' => $task->id,
        'amount' => -200,
        'type' => 'escrow',
    ]);
});

it('refuses to start a points task when the uploader cannot fund the escrow', function (string $state) {
    $owner = authAs();
    app(PointsService::class)->grantBonus($owner, 100, 'Welcome bonus');

    $task = Task::factory()->points()->{$state}()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
        'reward' => 100,
    ]);

    Application::factory()->accepted()->count(2)->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Insufficient points: you need 200 and have 100');

    expect($task->fresh()->status)->toBe($state === 'open' ? TaskStatus::Open : TaskStatus::Full)
        ->and($task->fresh()->started_at)->toBeNull()
        ->and($task->fresh()->escrow_amount)->toBe(0)
        ->and($owner->fresh()->points_balance)->toBe(100)
        ->and(PointTransaction::query()->count())->toBe(1);
})->with(['open', 'full']);

it('starts a paid task without moving points', function () {
    $owner = authAs();

    $task = Task::factory()->paid()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
        'reward' => 250,
    ]);

    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::InProgress->value);

    expect($task->fresh()->escrow_amount)->toBe(0)
        ->and(PointTransaction::query()->count())->toBe(0)
        ->and($owner->fresh()->points_balance)->toBe(0);
});

it('rejects a points start when one member has not been accepted', function () {
    $owner = authAs();
    app(PointsService::class)->grantBonus($owner, 500, 'Welcome bonus');

    $task = Task::factory()->points()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
        'reward' => 100,
    ]);

    Application::factory()->create([
        'task_id' => $task->id,
        'status' => ApplicationStatus::Pending,
    ]);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Accept at least one member before starting');

    expect($task->fresh()->status)->toBe(TaskStatus::Open)
        ->and($task->fresh()->escrow_amount)->toBe(0)
        ->and($owner->fresh()->points_balance)->toBe(500);
});
