<?php

use App\Enums\TaskType;
use App\Models\Application;
use App\Models\Task;

it('allows the owner to update a task', function () {
    $user = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $user->id]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'title' => 'Updated Laravel task title',
        'description' => 'An updated description that is long enough to pass validation.',
        'team_size' => 4,
        'skills' => ['Laravel', 'MySQL'],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Laravel task title')
        ->assertJsonPath('data.team_size', 4)
        ->assertJsonPath('data.skills', ['Laravel', 'MySQL'])
        ->assertJsonPath('data.is_owner', true);

    $task->refresh();

    expect($task->title)->toBe('Updated Laravel task title')
        ->and($task->team_size)->toBe(4);
});

it('forbids non-owners from updating a task', function () {
    authAs();
    $task = Task::factory()->open()->create();

    $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'A new long enough title'])
        ->assertForbidden();
});

it('rejects guests from updating a task', function () {
    $task = Task::factory()->open()->create();

    $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'A new long enough title'])
        ->assertUnauthorized();
});

it('rejects a team size below the accepted member count', function () {
    $user = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $user->id, 'team_size' => 4]);

    Application::factory()->accepted()->count(2)->create(['task_id' => $task->id]);

    $this->putJson("/api/v1/tasks/{$task->id}", ['team_size' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team_size']);

    expect($task->fresh()->team_size)->toBe(4);
});

it('moves a full task back to open when the team size is raised', function () {
    $user = authAs();
    $task = Task::factory()->full()->create(['uploader_id' => $user->id, 'team_size' => 2]);

    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->putJson("/api/v1/tasks/{$task->id}", ['team_size' => 3])
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.open_slots', 2);

    expect($task->fresh()->status->value)->toBe('open');
});

it('moves an open task to full when the team size reaches the accepted count', function () {
    $user = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $user->id, 'team_size' => 3]);

    Application::factory()->accepted()->count(2)->create(['task_id' => $task->id]);

    $this->putJson("/api/v1/tasks/{$task->id}", ['team_size' => 2])
        ->assertOk()
        ->assertJsonPath('data.status', 'full')
        ->assertJsonPath('data.open_slots', 0);

    expect($task->fresh()->status->value)->toBe('full');
});

it('locks the type once the task has applications', function () {
    $user = authAs();
    $task = Task::factory()->paid()->create(['uploader_id' => $user->id]);

    Application::factory()->create(['task_id' => $task->id]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'type' => TaskType::Points->value,
        'reward' => 10,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);

    expect($task->fresh()->type)->toBe(TaskType::Paid);
});

it('allows changing the type when there are no applications', function () {
    $user = authAs();
    $task = Task::factory()->paid()->create(['uploader_id' => $user->id]);

    $this->putJson("/api/v1/tasks/{$task->id}", [
        'type' => TaskType::Points->value,
        'reward' => 10,
    ])
        ->assertOk()
        ->assertJsonPath('data.type', 'points');

    expect($task->fresh()->type)->toBe(TaskType::Points);
});

it('rejects updates on tasks that are not open or full', function () {
    $user = authAs();
    $task = Task::factory()->cancelled()->create(['uploader_id' => $user->id]);

    $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'A new long enough title'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('validates the reward against the effective type on update', function () {
    $user = authAs();
    $task = Task::factory()->paid()->create(['uploader_id' => $user->id]);

    $this->putJson("/api/v1/tasks/{$task->id}", ['type' => 'points', 'reward' => 12.5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reward']);
});
