<?php

use App\Enums\PointTransactionType;
use App\Enums\TaskStatus;
use App\Models\Application;
use App\Models\PointTransaction;
use App\Models\Task;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Assert that a user's cached balance matches their whole ledger.
 */
function expectCompletionBalanceMatchesLedger(User $user): void
{
    $user->refresh();

    $ledgerTotal = (int) PointTransaction::query()
        ->where('user_id', $user->id)
        ->sum('amount');

    expect((int) $user->points_balance)->toBe($ledgerTotal);
}

/**
 * Create a started points task that already holds its escrow.
 *
 * @return array{Task, Collection<int, Application>}
 */
function startedPointsTask(User $owner, int $reward, int $memberCount): array
{
    $service = app(PointsService::class);
    $service->grantBonus($owner, 1000, 'Welcome bonus');

    $task = Task::factory()->points()->inProgress()->create([
        'uploader_id' => $owner->id,
        'team_size' => $memberCount,
        'reward' => $reward,
    ]);

    $applications = Application::factory()
        ->accepted()
        ->count($memberCount)
        ->create(['task_id' => $task->id]);

    $service->escrow($owner, $task, $reward * $memberCount);

    return [$task, $applications];
}

it('completes a points project and releases escrow to every accepted member', function () {
    $owner = authAs();
    [$task, $applications] = startedPointsTask($owner, 50, 2);

    $response = $this->patchJson("/api/v1/tasks/{$task->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::Completed->value)
        ->assertJsonPath('data.can_complete', false)
        ->assertJsonPath('data.is_participant', true);

    expect($response->json('data.completed_at'))->not->toBeNull()
        ->and($task->fresh()->status)->toBe(TaskStatus::Completed)
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($task->fresh()->escrow_amount)->toBe(0)
        ->and($owner->fresh()->points_balance)->toBe(900);

    foreach ($applications as $application) {
        $member = $application->user;

        expect($member->fresh()->points_balance)->toBe(50);

        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $member->id,
            'task_id' => $task->id,
            'amount' => 50,
            'type' => PointTransactionType::Release->value,
        ]);
    }

    $this->assertDatabaseHas('point_transactions', [
        'user_id' => $owner->id,
        'task_id' => $task->id,
        'amount' => -100,
        'type' => PointTransactionType::Escrow->value,
    ]);

    expect(PointTransaction::query()
        ->where('task_id', $task->id)
        ->where('type', PointTransactionType::Release->value)
        ->count()
    )->toBe(2)
        ->and(PointTransaction::query()
            ->where('task_id', $task->id)
            ->where('type', PointTransactionType::Refund->value)
            ->count()
        )->toBe(0);

    $participantIds = $applications->pluck('user_id')->push($owner->id)->all();

    expect((int) PointTransaction::query()
        ->where('task_id', $task->id)
        ->whereIn('user_id', $participantIds)
        ->sum('amount')
    )->toBe(0);
});

it('completes a paid project without creating ledger rows', function () {
    $owner = authAs();
    $task = Task::factory()->paid()->inProgress()->create(['uploader_id' => $owner->id]);

    Application::factory()->accepted()->create(['task_id' => $task->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::Completed->value);

    expect($task->fresh()->status)->toBe(TaskStatus::Completed)
        ->and($task->fresh()->escrow_amount)->toBe(0)
        ->and(PointTransaction::query()->count())->toBe(0);
});

it('forbids a non-owner from completing a project', function () {
    $task = Task::factory()->inProgress()->create();

    authAs();

    $this->patchJson("/api/v1/tasks/{$task->id}/complete")->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('refuses to complete a project that is not in progress', function (string $state) {
    $owner = authAs();
    $task = Task::factory()->{$state}()->create(['uploader_id' => $owner->id]);

    $this->patchJson("/api/v1/tasks/{$task->id}/complete")->assertStatus(409);

    expect($task->fresh()->status->value)->toBe($state);
})->with(['open', 'full', 'completed', 'cancelled']);

it('rejects a second completion without releasing points twice', function () {
    $owner = authAs();
    [$task] = startedPointsTask($owner, 40, 2);

    $this->patchJson("/api/v1/tasks/{$task->id}/complete")->assertOk();
    $this->patchJson("/api/v1/tasks/{$task->id}/complete")->assertStatus(409);

    expect(PointTransaction::query()
        ->where('task_id', $task->id)
        ->where('type', PointTransactionType::Release->value)
        ->count()
    )->toBe(2)
        ->and($task->fresh()->escrow_amount)->toBe(0)
        ->and($owner->fresh()->points_balance)->toBe(920);
});

it('keeps every participant balance equal to the ledger sum after completion', function () {
    $owner = authAs();
    [$task, $applications] = startedPointsTask($owner, 25, 3);
    $outsider = User::factory()->create(['points_balance' => 0]);

    $this->patchJson("/api/v1/tasks/{$task->id}/complete")->assertOk();

    expectCompletionBalanceMatchesLedger($owner);

    foreach ($applications as $application) {
        expectCompletionBalanceMatchesLedger($application->user);
    }

    expectCompletionBalanceMatchesLedger($outsider);
});
