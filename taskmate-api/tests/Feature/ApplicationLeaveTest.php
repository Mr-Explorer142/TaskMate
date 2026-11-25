<?php

use App\Enums\ApplicationStatus;
use App\Enums\TaskStatus;
use App\Models\Application;
use App\Models\Task;
use App\Models\User;

it('lets an accepted member leave a full team and reopens the slot', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $teammate = User::factory()->create();
    $task = Task::factory()->full()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);
    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $teammate->id,
    ]);
    $application = Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    authAs($member);

    $response = $this->patchJson("/api/v1/applications/{$application->id}/leave")
        ->assertOk()
        ->assertJsonPath('data.status', 'left')
        ->assertJsonPath('data.left_at', fn (?string $value) => $value !== null);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Left)
        ->and($application->fresh()->left_at)->not->toBeNull()
        ->and($task->fresh()->status)->toBe(TaskStatus::Open)
        ->and($task->fresh()->acceptedCount())->toBe(1)
        ->and($response->json('data.left_at'))->not->toBeNull();
});

it('keeps an open team open when a member leaves', function () {
    $member = authAs();
    $task = Task::factory()->open()->create(['team_size' => 3]);
    $application = Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    $this->patchJson("/api/v1/applications/{$application->id}/leave")->assertOk();

    expect($task->fresh()->status)->toBe(TaskStatus::Open)
        ->and($task->fresh()->acceptedCount())->toBe(0);
});

it('blocks leaving unless the application is accepted', function (string $state) {
    $task = Task::factory()->open()->create(['team_size' => 3]);
    $application = (match ($state) {
        'rejected' => Application::factory()->rejected(),
        'left' => Application::factory()->left(),
        default => Application::factory(),
    })->create(['task_id' => $task->id]);

    authAs($application->user);

    $this->patchJson("/api/v1/applications/{$application->id}/leave")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only accepted members can leave the team.');

    expect($application->fresh()->status->value)->toBe($state);
})->with(['pending', 'rejected', 'left']);

it('forbids leaving another user application', function () {
    $task = Task::factory()->open()->create(['team_size' => 3]);
    $application = Application::factory()->accepted()->create(['task_id' => $task->id]);

    authAs(User::factory()->create());

    $this->patchJson("/api/v1/applications/{$application->id}/leave")->assertForbidden();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Accepted);
});

it('blocks leaving after the project has started', function () {
    $member = authAs();
    $task = Task::factory()->inProgress()->create(['team_size' => 2]);
    $application = Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    $this->patchJson("/api/v1/applications/{$application->id}/leave")
        ->assertStatus(409)
        ->assertJsonPath('message', 'The project has already started, you cannot leave');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Accepted)
        ->and($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('refills the reopened slot with a pending applicant', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $pendingApplicant = User::factory()->create();

    $task = Task::factory()->full()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);

    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $first->id,
    ]);
    $leaving = Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $second->id,
    ]);
    $pending = Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $pendingApplicant->id,
    ]);

    authAs($second);

    $this->patchJson("/api/v1/applications/{$leaving->id}/leave")->assertOk();

    expect($task->fresh()->status)->toBe(TaskStatus::Open)
        ->and($task->fresh()->acceptedCount())->toBe(1);

    authAs($owner);

    $this->patchJson("/api/v1/applications/{$pending->id}/accept")->assertOk();

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Full)
        ->and($task->acceptedCount())->toBe(2)
        ->and($task->acceptedCount())->toBeLessThanOrEqual($task->team_size)
        ->and($pending->fresh()->status)->toBe(ApplicationStatus::Accepted);
});

it('blocks a user who left from applying again', function () {
    $applicant = authAs();
    $task = Task::factory()->open()->create(['team_size' => 2]);

    Application::factory()->left()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertStatus(409)
        ->assertJsonPath('message', 'You left this team and cannot re-apply.');

    expect(Application::query()->where('task_id', $task->id)->count())->toBe(1);
});

it('exposes can_leave until the member leaves the team', function () {
    $member = authAs();
    $task = Task::factory()->open()->create(['team_size' => 3]);
    $application = Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    $this->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.can_leave', true);

    $this->patchJson("/api/v1/applications/{$application->id}/leave")->assertOk();

    $this->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.can_leave', false)
        ->assertJsonPath('data.my_application_status', 'left');
});

it('keeps the task status consistent when leaves and accepts interleave', function () {
    $owner = User::factory()->create();

    $assertConsistent = function (Task $task): void {
        $task->refresh();
        $accepted = $task->acceptedCount();

        expect($accepted)->toBeLessThanOrEqual($task->team_size);

        if ($accepted >= $task->team_size) {
            expect($task->status)->toBe(TaskStatus::Full);

            return;
        }

        expect($task->status)->toBe(TaskStatus::Open);
    };

    $leaveFirstTask = Task::factory()->full()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);
    $leaveFirstMember = User::factory()->create();
    $leaveFirstApplication = Application::factory()->accepted()->create([
        'task_id' => $leaveFirstTask->id,
        'user_id' => $leaveFirstMember->id,
    ]);
    Application::factory()->accepted()->create(['task_id' => $leaveFirstTask->id]);
    $leaveFirstPending = Application::factory()->create(['task_id' => $leaveFirstTask->id]);

    authAs($leaveFirstMember);
    $this->patchJson("/api/v1/applications/{$leaveFirstApplication->id}/leave")->assertOk();
    $assertConsistent($leaveFirstTask);

    authAs($owner);
    $this->patchJson("/api/v1/applications/{$leaveFirstPending->id}/accept")->assertOk();
    $assertConsistent($leaveFirstTask);
    expect($leaveFirstTask->fresh()->status)->toBe(TaskStatus::Full);

    $acceptFirstTask = Task::factory()->full()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
    ]);
    $acceptFirstMember = User::factory()->create();
    $acceptFirstApplication = Application::factory()->accepted()->create([
        'task_id' => $acceptFirstTask->id,
        'user_id' => $acceptFirstMember->id,
    ]);
    Application::factory()->accepted()->create(['task_id' => $acceptFirstTask->id]);
    $acceptFirstPending = Application::factory()->create(['task_id' => $acceptFirstTask->id]);

    authAs($owner);
    $this->patchJson("/api/v1/applications/{$acceptFirstPending->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Team is already full.');
    $assertConsistent($acceptFirstTask);

    authAs($acceptFirstMember);
    $this->patchJson("/api/v1/applications/{$acceptFirstApplication->id}/leave")->assertOk();
    $assertConsistent($acceptFirstTask);
    expect($acceptFirstTask->fresh()->status)->toBe(TaskStatus::Open);
});

it('requires authentication to leave a team', function () {
    $task = Task::factory()->open()->create(['team_size' => 2]);
    $application = Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/leave")->assertUnauthorized();
});
