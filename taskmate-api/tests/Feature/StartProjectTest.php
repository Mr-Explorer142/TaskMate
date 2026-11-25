<?php

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use App\Models\Application;
use App\Models\Task;
use App\Models\User;

it('lets the owner start an open task and rejects pending applications', function () {
    $owner = authAs();
    $task = Task::factory()->paid()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);
    $accepted = Application::factory()->accepted()->create(['task_id' => $task->id]);
    $pending = Application::factory()->create(['task_id' => $task->id]);

    $response = $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.started_at', fn (?string $value) => $value !== null)
        ->assertJsonPath('data.can_start', false);

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::InProgress)
        ->and($task->started_at)->not->toBeNull()
        ->and($accepted->fresh()->status)->toBe(ApplicationStatus::Accepted)
        ->and($pending->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and($response->json('data.started_at'))->not->toBeNull();
});

it('allows starting a full task', function () {
    $owner = authAs();
    $task = Task::factory()->paid()->full()->create([
        'uploader_id' => $owner->id,
        'team_size' => 1,
    ]);
    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');
});

it('forbids non-owners from starting a task', function () {
    $task = Task::factory()->paid()->open()->create(['team_size' => 2]);
    Application::factory()->accepted()->create(['task_id' => $task->id]);

    authAs(User::factory()->create());

    $this->patchJson("/api/v1/tasks/{$task->id}/start")->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::Open);
});

it('requires at least one accepted member before starting', function () {
    $owner = authAs();
    $task = Task::factory()->paid()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 3,
    ]);
    Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Accept at least one member before starting');

    expect($task->fresh()->status)->toBe(TaskStatus::Open)
        ->and($task->fresh()->started_at)->toBeNull();
});

it('refuses to start a task that is not open or full', function (string $state) {
    $owner = authAs();
    $task = Task::factory()->{$state}()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);
    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only open or full tasks can be started.');
})->with(['inProgress', 'completed', 'cancelled']);

it('blocks apply, accept, leave and edit after the project starts', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $task = Task::factory()->inProgress()->create([
        'uploader_id' => $owner->id,
        'team_size' => 3,
    ]);
    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);
    $pending = Application::factory()->create(['task_id' => $task->id]);

    authAs(User::factory()->create());

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This task is already in progress.');

    authAs($owner);

    $this->patchJson("/api/v1/applications/{$pending->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only open tasks can accept applications.');

    authAs($member);

    $acceptedApplication = $task->acceptedApplications()->firstOrFail();

    $this->patchJson("/api/v1/applications/{$acceptedApplication->id}/leave")
        ->assertStatus(409)
        ->assertJsonPath('message', 'The project has already started, you cannot leave');

    authAs($owner);

    $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Updated title'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This task is already in progress and can no longer be edited.');
});

it('exposes can_start to the owner only when a member is accepted', function () {
    $owner = authAs();
    $emptyTask = Task::factory()->paid()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);

    $this->getJson("/api/v1/tasks/{$emptyTask->id}")
        ->assertOk()
        ->assertJsonPath('data.can_start', false);

    $readyTask = Task::factory()->paid()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);
    Application::factory()->accepted()->create(['task_id' => $readyTask->id]);

    $this->getJson("/api/v1/tasks/{$readyTask->id}")
        ->assertOk()
        ->assertJsonPath('data.can_start', true);

    authAs(User::factory()->create());

    $this->getJson("/api/v1/tasks/{$readyTask->id}")
        ->assertOk()
        ->assertJsonPath('data.can_start', false);
});

it('requires authentication to start a task', function () {
    $task = Task::factory()->paid()->open()->create(['team_size' => 2]);
    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")->assertUnauthorized();
});
