<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Task;

it('submits a pending application to an open task', function () {
    $applicant = authAs();
    $task = Task::factory()->open()->create();

    $response = $this->postJson("/api/v1/tasks/{$task->id}/apply", [
        'message' => 'I would love to help with this.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.message', 'I would love to help with this.')
        ->assertJsonPath('data.applicant.id', $applicant->id)
        ->assertJsonPath('data.task.id', $task->id);

    expect($response->json('data.applicant'))->not->toHaveKey('email');

    $this->assertDatabaseHas('applications', [
        'task_id' => $task->id,
        'user_id' => $applicant->id,
        'status' => ApplicationStatus::Pending->value,
    ]);
});

it('accepts an application without a message', function () {
    authAs();
    $task = Task::factory()->open()->create();

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertCreated()
        ->assertJsonPath('data.message', null);
});

it('blocks the task owner from applying to their own task', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertForbidden();

    $this->assertDatabaseCount('applications', 0);
});

it('blocks a duplicate application', function () {
    $applicant = authAs();
    $task = Task::factory()->open()->create();
    Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertStatus(409)
        ->assertJsonPath('message', 'You have already applied to this task.');

    $this->assertDatabaseCount('applications', 1);
});

it('blocks re-applying after a rejection', function () {
    $applicant = authAs();
    $task = Task::factory()->open()->create();
    Application::factory()->rejected()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertStatus(409)
        ->assertJsonPath('message', 'You cannot re-apply to this task.');
});

it('blocks applications to tasks that are not open', function (string $state) {
    authAs();
    $task = Task::factory()->{$state}()->create();

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertStatus(409);

    $this->assertDatabaseCount('applications', 0);
})->with(['full', 'inProgress', 'completed', 'cancelled']);

it('explains why a full task is closed', function () {
    authAs();
    $task = Task::factory()->full()->create();

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertStatus(409)
        ->assertJsonPath('message', "This task's team is already full.");
});

it('requires authentication to apply', function () {
    $task = Task::factory()->open()->create();

    $this->postJson("/api/v1/tasks/{$task->id}/apply")->assertUnauthorized();
});

it('validates the apply message length', function () {
    authAs();
    $task = Task::factory()->open()->create();

    $this->postJson("/api/v1/tasks/{$task->id}/apply", [
        'message' => str_repeat('a', 1001),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('allows a user to apply to many tasks', function () {
    $applicant = authAs();
    $tasks = Task::factory()->open()->count(3)->create();

    foreach ($tasks as $task) {
        $this->postJson("/api/v1/tasks/{$task->id}/apply")->assertCreated();
    }

    expect(Application::query()->where('user_id', $applicant->id)->count())->toBe(3);
});

it('rate limits applications to thirty per minute', function () {
    authAs();
    $tasks = Task::factory()->open()->count(31)->create();

    foreach ($tasks->take(30) as $task) {
        $this->postJson("/api/v1/tasks/{$task->id}/apply")->assertCreated();
    }

    $this->postJson("/api/v1/tasks/{$tasks->last()->id}/apply")->assertStatus(429);
});
