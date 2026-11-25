<?php

use App\Enums\ApplicationStatus;
use App\Enums\PointTransactionType;
use App\Models\Application;
use App\Models\PointTransaction;
use App\Models\Task;
use App\Services\PointsService;

it('cancels a task and rejects pending applications', function () {
    $user = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $user->id]);

    $pending = Application::factory()->create(['task_id' => $task->id]);
    $accepted = Application::factory()->accepted()->create(['task_id' => $task->id]);
    $rejected = Application::factory()->rejected()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($task->fresh()->status->value)->toBe('cancelled')
        ->and($pending->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and($accepted->fresh()->status)->toBe(ApplicationStatus::Accepted)
        ->and($rejected->fresh()->status)->toBe(ApplicationStatus::Rejected);
});

it('cancels a full task', function () {
    $user = authAs();
    $task = Task::factory()->full()->create(['uploader_id' => $user->id, 'team_size' => 1]);

    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/cancel")->assertOk();

    expect($task->fresh()->status->value)->toBe('cancelled');
});

it('cannot cancel a task twice', function () {
    $user = authAs();
    $task = Task::factory()->cancelled()->create(['uploader_id' => $user->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('forbids non-owners from cancelling a task', function () {
    authAs();
    $task = Task::factory()->open()->create();

    $this->patchJson("/api/v1/tasks/{$task->id}/cancel")->assertForbidden();
});

it('refunds escrow in full when cancelling an in-progress project', function () {
    $owner = authAs();
    app(PointsService::class)->grantBonus($owner, 500, 'Welcome bonus');

    $task = Task::factory()->points()->inProgress()->create([
        'uploader_id' => $owner->id,
        'team_size' => 2,
        'reward' => 60,
    ]);

    $members = Application::factory()->accepted()->count(2)->create(['task_id' => $task->id]);

    app(PointsService::class)->escrow($owner, $task, 120);

    $this->patchJson("/api/v1/tasks/{$task->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($task->fresh()->status->value)->toBe('cancelled')
        ->and($task->fresh()->escrow_amount)->toBe(0)
        ->and($owner->fresh()->points_balance)->toBe(500);

    foreach ($members as $member) {
        expect($member->user->fresh()->points_balance)->toBe(0);
    }

    $this->assertDatabaseHas('point_transactions', [
        'user_id' => $owner->id,
        'task_id' => $task->id,
        'amount' => 120,
        'type' => PointTransactionType::Refund->value,
    ]);

    expect(PointTransaction::query()
        ->where('task_id', $task->id)
        ->where('type', PointTransactionType::Release->value)
        ->count()
    )->toBe(0);
});

it('cannot cancel a completed project', function () {
    $owner = authAs();
    $task = Task::factory()->completed()->create(['uploader_id' => $owner->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/cancel")->assertStatus(409);

    expect($task->fresh()->status->value)->toBe('completed');
});

it('deletes an open task without accepted members', function () {
    $user = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $user->id]);

    Application::factory()->create(['task_id' => $task->id]);

    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertOk();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

it('deletes a cancelled task without accepted members', function () {
    $user = authAs();
    $task = Task::factory()->cancelled()->create(['uploader_id' => $user->id]);

    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertOk();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

it('blocks deletion when a member has been accepted', function () {
    $user = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $user->id]);

    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This task has accepted members. Cancel the task instead of deleting it.');

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

it('blocks deletion of tasks that are not open or cancelled', function () {
    $user = authAs();
    $task = Task::factory()->inProgress()->create(['uploader_id' => $user->id]);

    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertStatus(409);

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

it('forbids non-owners from deleting a task', function () {
    authAs();
    $task = Task::factory()->open()->create();

    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});
