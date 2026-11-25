<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Task;
use App\Models\User;

it('rejects a pending application', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);
    $application = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and($application->fresh()->accepted_at)->toBeNull();
});

it('forbids non-owners from rejecting applications', function () {
    authAs();
    $task = Task::factory()->open()->create();
    $application = Application::factory()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/reject")->assertForbidden();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Pending);
});

it('does not reject an accepted application', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 3]);
    $application = Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/reject")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only pending applications can be rejected.');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Accepted);
});

it('does not reject an application twice', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);
    $application = Application::factory()->rejected()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/applications/{$application->id}/reject")->assertStatus(409);
});

it('prevents a rejected applicant from re-applying', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);
    $applicant = User::factory()->create();
    $application = Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $this->patchJson("/api/v1/applications/{$application->id}/reject")->assertOk();

    authAs($applicant);

    $this->postJson("/api/v1/tasks/{$task->id}/apply")
        ->assertStatus(409)
        ->assertJsonPath('message', 'You cannot re-apply to this task.');
});

it('lets an applicant withdraw a pending application and apply again', function () {
    $applicant = authAs();
    $task = Task::factory()->open()->create();
    $application = Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $this->deleteJson("/api/v1/applications/{$application->id}")->assertOk();

    $this->assertDatabaseMissing('applications', ['id' => $application->id]);

    $this->postJson("/api/v1/tasks/{$task->id}/apply")->assertCreated();

    expect($task->applications()->where('user_id', $applicant->id)->exists())->toBeTrue();
});

it('does not withdraw an accepted or rejected application', function (string $state) {
    $applicant = authAs();
    $task = Task::factory()->open()->create(['team_size' => 3]);
    $application = Application::factory()->{$state}()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $this->deleteJson("/api/v1/applications/{$application->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only pending applications can be withdrawn.');

    $this->assertDatabaseHas('applications', ['id' => $application->id]);
})->with(['accepted', 'rejected']);

it('forbids withdrawing another user application', function () {
    authAs();
    $task = Task::factory()->open()->create();
    $application = Application::factory()->create(['task_id' => $task->id]);

    $this->deleteJson("/api/v1/applications/{$application->id}")->assertForbidden();

    $this->assertDatabaseHas('applications', ['id' => $application->id]);
});

it('requires authentication to withdraw', function () {
    $task = Task::factory()->open()->create();
    $application = Application::factory()->create(['task_id' => $task->id]);

    $this->deleteJson("/api/v1/applications/{$application->id}")->assertUnauthorized();
});
