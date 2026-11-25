<?php

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\Task;
use App\Models\User;

it('returns the current balance and escrow held on in-progress tasks', function () {
    $user = authAs();
    $user->forceFill(['points_balance' => 500])->save();

    Task::factory()->points()->inProgress()->create([
        'uploader_id' => $user->id,
        'escrow_amount' => 200,
    ]);

    Task::factory()->points()->open()->create([
        'uploader_id' => $user->id,
        'escrow_amount' => 100,
    ]);

    Task::factory()->points()->inProgress()->create([
        'uploader_id' => User::factory()->create()->id,
        'escrow_amount' => 300,
    ]);

    $this->getJson('/api/v1/points')
        ->assertOk()
        ->assertJsonPath('data.balance', 500)
        ->assertJsonPath('data.escrowed', 200);
});

it('paginates only the current user transactions newest first', function () {
    $user = authAs();
    $task = Task::factory()->create(['title' => 'Ledger task']);
    $other = User::factory()->create();

    for ($index = 0; $index < 25; $index++) {
        PointTransaction::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'amount' => $index + 1,
            'type' => PointTransactionType::Bonus,
            'note' => "Transaction {$index}",
            'created_at' => now()->subMinutes(25 - $index),
            'updated_at' => now()->subMinutes(25 - $index),
        ]);
    }

    PointTransaction::factory()->create([
        'user_id' => $other->id,
        'amount' => 999,
        'type' => PointTransactionType::Bonus,
    ]);

    $firstPage = $this->getJson('/api/v1/points/transactions')
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('data.0.amount', 25)
        ->assertJsonPath('data.0.note', 'Transaction 24')
        ->assertJsonPath('data.0.task.id', $task->id)
        ->assertJsonPath('data.0.task.title', 'Ledger task')
        ->assertJsonPath('data.0.type', 'bonus');

    expect($firstPage->json('data.19.amount'))->toBe(6);

    $secondPage = $this->getJson('/api/v1/points/transactions?page=2')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.amount', 5)
        ->assertJsonPath('data.4.amount', 1);

    expect(collect($firstPage->json('data'))->pluck('amount'))
        ->not->toContain(999)
        ->and($secondPage->json('data'))->not->toContain(999);
});

it('requires authentication for points endpoints', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    '/api/v1/points',
    '/api/v1/points/transactions',
]);
