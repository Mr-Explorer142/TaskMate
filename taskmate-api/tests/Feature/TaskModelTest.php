<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Task;
use Illuminate\Database\QueryException;

it('calculates open slots and fullness from accepted applications', function () {
    $task = Task::factory()->create(['team_size' => 3]);

    Application::factory()->accepted()->count(2)->create(['task_id' => $task->id]);

    expect($task->acceptedCount())->toBe(2)
        ->and($task->openSlots())->toBe(1)
        ->and($task->isFull())->toBeFalse();

    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $task->refresh();

    expect($task->acceptedCount())->toBe(3)
        ->and($task->openSlots())->toBe(0)
        ->and($task->isFull())->toBeTrue();
});

it('ignores applications that are not accepted', function () {
    $task = Task::factory()->create(['team_size' => 2]);

    Application::factory()->create([
        'task_id' => $task->id,
        'status' => ApplicationStatus::Pending,
    ]);

    Application::factory()->rejected()->create(['task_id' => $task->id]);
    Application::factory()->left()->create(['task_id' => $task->id]);

    expect($task->acceptedCount())->toBe(0)
        ->and($task->openSlots())->toBe(2)
        ->and($task->isFull())->toBeFalse();
});

it('never reports a negative number of open slots', function () {
    $task = Task::factory()->create(['team_size' => 2]);

    Application::factory()->accepted()->count(3)->create(['task_id' => $task->id]);

    expect($task->openSlots())->toBe(0)
        ->and($task->isFull())->toBeTrue();
});

it('blocks duplicate applications for the same task and user', function () {
    $task = Task::factory()->create();
    $application = Application::factory()->create(['task_id' => $task->id]);

    expect(function () use ($task, $application) {
        Application::factory()->create([
            'task_id' => $task->id,
            'user_id' => $application->user_id,
        ]);
    })->toThrow(QueryException::class);
});
