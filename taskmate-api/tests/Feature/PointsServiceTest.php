<?php

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\Task;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Validation\ValidationException;

function expectPointsLedgerToMatchBalance(User $user): void
{
    $user->refresh();

    $ledgerTotal = (int) PointTransaction::query()
        ->where('user_id', $user->id)
        ->sum('amount');

    expect((int) $user->points_balance)->toBe($ledgerTotal);
}

it('grants a bonus and records a ledger row', function () {
    $user = User::factory()->create(['points_balance' => 0]);

    $transaction = app(PointsService::class)->grantBonus($user, 100, 'Welcome bonus');

    expect($user->fresh()->points_balance)->toBe(100)
        ->and($transaction->type)->toBe(PointTransactionType::Bonus)
        ->and($transaction->amount)->toBe(100)
        ->and($transaction->note)->toBe('Welcome bonus');

    $this->assertDatabaseHas('point_transactions', [
        'user_id' => $user->id,
        'amount' => 100,
        'type' => PointTransactionType::Bonus->value,
        'note' => 'Welcome bonus',
    ]);
});

it('escrows points from the uploader', function () {
    $service = app(PointsService::class);
    $owner = User::factory()->create(['points_balance' => 0]);
    $service->grantBonus($owner, 300, 'Welcome bonus');

    $task = Task::factory()->points()->create([
        'uploader_id' => $owner->id,
        'reward' => 100,
    ]);

    $transaction = $service->escrow($owner, $task, 200);

    expect($transaction->type)->toBe(PointTransactionType::Escrow)
        ->and($transaction->amount)->toBe(-200)
        ->and($transaction->task_id)->toBe($task->id)
        ->and($owner->fresh()->points_balance)->toBe(100)
        ->and($task->fresh()->escrow_amount)->toBe(200);

    expectPointsLedgerToMatchBalance($owner);
});

it('refuses escrow when the uploader lacks points and leaves the balance unchanged', function () {
    $service = app(PointsService::class);
    $owner = User::factory()->create(['points_balance' => 0]);
    $service->grantBonus($owner, 50, 'Welcome bonus');

    $task = Task::factory()->points()->create([
        'uploader_id' => $owner->id,
        'reward' => 100,
    ]);

    expect(fn () => $service->escrow($owner, $task, 100))
        ->toThrow(ValidationException::class, 'Insufficient points: you need 100 and have 50');

    expect($owner->fresh()->points_balance)->toBe(50)
        ->and($task->fresh()->escrow_amount)->toBe(0)
        ->and(PointTransaction::query()->where('type', PointTransactionType::Escrow->value)->exists())->toBeFalse();

    expectPointsLedgerToMatchBalance($owner);
});

it('releases points to a member and refunds the remaining escrow', function () {
    $service = app(PointsService::class);
    $owner = User::factory()->create(['points_balance' => 0]);
    $member = User::factory()->create(['points_balance' => 0]);
    $service->grantBonus($owner, 500, 'Welcome bonus');

    $task = Task::factory()->points()->create([
        'uploader_id' => $owner->id,
        'reward' => 50,
    ]);

    $service->escrow($owner, $task, 200);
    $release = $service->release($task, $member, 120);

    expect($release->type)->toBe(PointTransactionType::Release)
        ->and($release->amount)->toBe(120)
        ->and($member->fresh()->points_balance)->toBe(120)
        ->and($task->fresh()->escrow_amount)->toBe(80);

    $refund = $service->refund($task);

    expect($refund)->not->toBeNull()
        ->and($refund->type)->toBe(PointTransactionType::Refund)
        ->and($refund->amount)->toBe(80)
        ->and($owner->fresh()->points_balance)->toBe(380)
        ->and($task->fresh()->escrow_amount)->toBe(0);

    expectPointsLedgerToMatchBalance($owner);
    expectPointsLedgerToMatchBalance($member);
});

it('keeps the cached balance equal to the ledger after a mixed series of operations', function () {
    $service = app(PointsService::class);
    $owner = User::factory()->create(['points_balance' => 0]);
    $member = User::factory()->create(['points_balance' => 0]);

    $service->grantBonus($owner, 1000, 'Welcome bonus');
    $task = Task::factory()->points()->create([
        'uploader_id' => $owner->id,
        'reward' => 100,
    ]);

    $service->escrow($owner, $task, 400);
    $service->release($task, $member, 150);
    $service->refund($task);
    $service->grantBonus($member, 25, 'Referral bonus');
    $service->escrow($owner, $task, 50);

    expectPointsLedgerToMatchBalance($owner);
    expectPointsLedgerToMatchBalance($member);
});
