<?php

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use App\Models\Application;
use App\Models\Task;
use App\Models\User;

it('accepts a pending application and records the timestamp', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 2]);
    $application = Application::factory()->create(['task_id' => $task->id]);

    $response = $this->patchJson("/api/v1/applications/{$application->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($response->json('data.accepted_at'))->not->toBeNull()
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Accepted)
        ->and($application->fresh()->accepted_at)->not->toBeNull()
        ->and($task->fresh()->status)->toBe(TaskStatus::Open);
});

it('flips the task to full when the last slot is filled', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 1]);
    $application = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/accept")->assertOk();

    expect($task->fresh()->status)->toBe(TaskStatus::Full)
        ->and($task->fresh()->acceptedCount())->toBe(1);
});

it('rejects accepting an application after the team is full', function () {
    $owner = authAs();
    $task = Task::factory()->full()->create(['uploader_id' => $owner->id, 'team_size' => 1]);
    Application::factory()->accepted()->create(['task_id' => $task->id]);
    $pending = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$pending->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Team is already full.');

    expect($pending->fresh()->status)->toBe(ApplicationStatus::Pending)
        ->and($task->fresh()->acceptedCount())->toBe(1);
});

it('forbids non-owners from accepting applications', function () {
    authAs();
    $task = Task::factory()->open()->create(['team_size' => 2]);
    $application = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/accept")->assertForbidden();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Pending);
});

it('only accepts pending applications', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 3]);
    $rejected = Application::factory()->rejected()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$rejected->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Application is not pending.');

    expect($rejected->fresh()->status)->toBe(ApplicationStatus::Rejected);
});

it('leaves other pending applications pending', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 2]);
    $accepted = Application::factory()->create(['task_id' => $task->id]);
    $other = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$accepted->id}/accept")->assertOk();

    expect($other->fresh()->status)->toBe(ApplicationStatus::Pending)
        ->and($task->fresh()->acceptedCount())->toBe(1);
});

it('never accepts more applicants than the team has slots', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 1]);
    $first = Application::factory()->create(['task_id' => $task->id]);
    $second = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$first->id}/accept")->assertOk();

    $this->patchJson("/api/v1/applications/{$second->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Team is already full.');

    expect($task->fresh()->acceptedCount())->toBe(1)
        ->and($first->fresh()->status)->toBe(ApplicationStatus::Accepted)
        ->and($second->fresh()->status)->toBe(ApplicationStatus::Pending);
});

it('forbids accepting applications for a task that is not open', function () {
    $owner = authAs();
    $task = Task::factory()->inProgress()->create(['uploader_id' => $owner->id, 'team_size' => 3]);
    $application = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only open tasks can accept applications.');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Pending);
});

it('requires authentication to accept applications', function () {
    $task = Task::factory()->open()->create();
    $application = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/accept")->assertUnauthorized();
});

it('can accept a pending application after a slot reopens manually', function () {
    $owner = authAs();
    $task = Task::factory()->full()->create(['uploader_id' => $owner->id, 'team_size' => 2]);
    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $pending = Application::factory()->create(['task_id' => $task->id]);

    $task->forceFill(['status' => TaskStatus::Open, 'team_size' => 2])->save();

    $this->patchJson("/api/v1/applications/{$pending->id}/accept")->assertOk();

    expect($task->fresh()->acceptedCount())->toBe(2)
        ->and($task->fresh()->status)->toBe(TaskStatus::Full);
});

it('does not accept a user other than the uploader', function () {
    $task = Task::factory()->open()->create(['team_size' => 2]);
    $application = Application::factory()->create(['task_id' => $task->id]);
    authAs(User::factory()->create());

    $this->patchJson("/api/v1/applications/{$application->id}/accept")->assertForbidden();
});
