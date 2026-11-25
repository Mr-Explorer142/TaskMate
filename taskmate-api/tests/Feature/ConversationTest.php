<?php

use App\Models\Application;
use App\Models\Conversation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function conversationBetween(User $first, User $second, Task $task): Conversation
{
    [$oneId, $twoId] = $first->id < $second->id
        ? [$first->id, $second->id]
        : [$second->id, $first->id];

    return Conversation::query()->create([
        'task_id' => $task->id,
        'user_one_id' => $oneId,
        'user_two_id' => $twoId,
    ]);
}

it('lets a non-owner start a conversation with the task uploader', function () {
    $owner = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);
    $viewer = authAs();

    $response = $this->postJson('/api/v1/conversations', [
        'task_id' => $task->id,
        'user_id' => $owner->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.task.id', $task->id)
        ->assertJsonPath('data.other_user.id', $owner->id)
        ->assertJsonPath('data.unread_count', 0);

    $this->assertDatabaseHas('conversations', [
        'task_id' => $task->id,
        'user_one_id' => min($viewer->id, $owner->id),
        'user_two_id' => max($viewer->id, $owner->id),
    ]);
});

it('lets the uploader start a conversation with an applicant of any status', function (string $status) {
    $owner = authAs();
    $applicant = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);

    Application::factory()->{$status}()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $this->postJson('/api/v1/conversations', [
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.other_user.id', $applicant->id);
})->with(['accepted', 'rejected', 'left']);

it('forbids the uploader from messaging a user who never applied', function () {
    $owner = authAs();
    $stranger = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);

    $this->postJson('/api/v1/conversations', [
        'task_id' => $task->id,
        'user_id' => $stranger->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'You can only message users who applied to this task.');

    $this->assertDatabaseCount('conversations', 0);
});

it('forbids messaging someone other than the uploader as a non-owner', function () {
    authAs();
    $task = Task::factory()->open()->create();
    $stranger = User::factory()->create();

    $this->postJson('/api/v1/conversations', [
        'task_id' => $task->id,
        'user_id' => $stranger->id,
    ])->assertForbidden();
});

it('rejects messaging yourself', function () {
    $user = authAs();
    $task = Task::factory()->open()->create();

    $this->postJson('/api/v1/conversations', [
        'task_id' => $task->id,
        'user_id' => $user->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You cannot message yourself.');
});

it('returns the same conversation when ids are reversed', function () {
    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);

    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    authAs($applicant);

    $first = $this->postJson('/api/v1/conversations', [
        'task_id' => $task->id,
        'user_id' => $owner->id,
    ])->assertCreated();

    authAs($owner);

    $second = $this->postJson('/api/v1/conversations', [
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Conversation::query()->count())->toBe(1);
});

it('forbids outsiders from reading or sending messages', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $first->id]);
    $conversation = conversationBetween($first, $second, $task);
    $conversation->messages()->create([
        'sender_id' => $first->id,
        'body' => 'Private message',
    ]);

    authAs(User::factory()->create());

    $this->getJson("/api/v1/conversations/{$conversation->id}/messages")
        ->assertForbidden();

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'Sneaky message',
    ])->assertForbidden();

    $this->postJson("/api/v1/conversations/{$conversation->id}/read")
        ->assertForbidden();
});

it('orders conversations by last message and reports unread counts', function () {
    $viewer = authAs();
    $firstPartner = User::factory()->create();
    $secondPartner = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);

    $firstConversation = conversationBetween($viewer, $firstPartner, $task);
    $secondConversation = conversationBetween($viewer, $secondPartner, $task);

    $firstConversation->messages()->create([
        'sender_id' => $firstPartner->id,
        'body' => 'Older message',
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    $firstConversation->messages()->create([
        'sender_id' => $viewer->id,
        'body' => 'Newer reply',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    $secondConversation->messages()->create([
        'sender_id' => $secondPartner->id,
        'body' => 'Newest message',
        'read_at' => now(),
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $response = $this->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $secondConversation->id)
        ->assertJsonPath('data.0.last_message.body', 'Newest message')
        ->assertJsonPath('data.0.unread_count', 0)
        ->assertJsonPath('data.1.id', $firstConversation->id)
        ->assertJsonPath('data.1.unread_count', 1)
        ->assertJsonPath('data.1.other_user.id', $firstPartner->id);

    expect($response->json('data.1.other_user'))->toHaveKeys(['id', 'name', 'avatar'])
        ->and($response->json('data.1.other_user'))->not->toHaveKey('email')
        ->and($response->json('data.1.other_user'))->not->toHaveKey('points_balance');
});

it('paginates conversations to twenty and keeps query counts bounded', function () {
    $viewer = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);

    foreach (range(1, 23) as $index) {
        $partner = User::factory()->create();
        $conversation = conversationBetween($viewer, $partner, $task);
        $conversation->messages()->create([
            'sender_id' => $partner->id,
            'body' => "Message {$index}",
        ]);
    }

    $other = User::factory()->create();
    conversationBetween(User::factory()->create(), $other, $task);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 23);

    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(10)
        ->and(collect($response->json('data'))->pluck('id'))->not->toContain($other->id);
});
