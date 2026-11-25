<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;

function messageTestConversation(User $first, User $second, Task $task): Conversation
{
    [$oneId, $twoId] = $first->id < $second->id
        ? [$first->id, $second->id]
        : [$second->id, $first->id];

    return Conversation::factory()->create([
        'task_id' => $task->id,
        'user_one_id' => $oneId,
        'user_two_id' => $twoId,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function messageTestMessage(Conversation $conversation, array $attributes): Message
{
    return Message::factory()->create(array_merge([
        'conversation_id' => $conversation->id,
    ], $attributes));
}

it('returns the latest thirty messages oldest first by default', function () {
    $viewer = authAs();
    $partner = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);
    $conversation = messageTestConversation($viewer, $partner, $task);

    $ids = [];

    foreach (range(1, 35) as $index) {
        $ids[] = messageTestMessage($conversation, [
            'sender_id' => $index % 2 === 0 ? $viewer->id : $partner->id,
            'body' => "Message {$index}",
        ])->id;
    }

    $response = $this->getJson("/api/v1/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonCount(30, 'data');

    expect($response->json('data.0.id'))->toBe($ids[5])
        ->and($response->json('data.29.id'))->toBe($ids[34]);
});

it('loads older messages with before_id', function () {
    $viewer = authAs();
    $partner = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);
    $conversation = messageTestConversation($viewer, $partner, $task);

    $ids = [];

    foreach (range(1, 35) as $index) {
        $ids[] = messageTestMessage($conversation, [
            'sender_id' => $partner->id,
            'body' => "Message {$index}",
        ])->id;
    }

    $response = $this->getJson("/api/v1/conversations/{$conversation->id}/messages?before_id={$ids[30]}")
        ->assertOk()
        ->assertJsonCount(30, 'data');

    expect($response->json('data.0.id'))->toBe($ids[0])
        ->and($response->json('data.29.id'))->toBe($ids[29]);
});

it('returns only newer messages with after_id', function () {
    $viewer = authAs();
    $partner = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);
    $conversation = messageTestConversation($viewer, $partner, $task);

    $ids = [];

    foreach (range(1, 5) as $index) {
        $ids[] = messageTestMessage($conversation, [
            'sender_id' => $partner->id,
            'body' => "Message {$index}",
        ])->id;
    }

    $response = $this->getJson("/api/v1/conversations/{$conversation->id}/messages?after_id={$ids[2]}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$ids[3], $ids[4]]);
});

it('validates and normalizes the message body', function () {
    $viewer = authAs();
    $partner = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);
    $conversation = messageTestConversation($viewer, $partner, $task);
    $url = "/api/v1/conversations/{$conversation->id}/messages";

    $this->postJson($url, ['body' => '   '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    $this->postJson($url, ['body' => str_repeat('a', 2001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    $this->postJson($url, ['body' => " \0Hello\r\nWorld\r "])
        ->assertCreated()
        ->assertJsonPath('data.body', "Hello\nWorld");

    $this->postJson($url, ['body' => str_repeat('a', 2000)])
        ->assertCreated();
});

it('marks only the other participant messages as read', function () {
    $viewer = authAs();
    $partner = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);
    $conversation = messageTestConversation($viewer, $partner, $task);

    $firstFromPartner = messageTestMessage($conversation, [
        'sender_id' => $partner->id,
        'body' => 'First from partner',
    ]);

    $secondFromPartner = messageTestMessage($conversation, [
        'sender_id' => $partner->id,
        'body' => 'Second from partner',
    ]);

    $fromViewer = messageTestMessage($conversation, [
        'sender_id' => $viewer->id,
        'body' => 'From viewer',
    ]);

    $this->getJson('/api/v1/conversations/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 2);

    $this->postJson("/api/v1/conversations/{$conversation->id}/read")
        ->assertOk()
        ->assertJsonPath('data.updated', 2);

    expect($firstFromPartner->fresh()->read_at)->not->toBeNull()
        ->and($secondFromPartner->fresh()->read_at)->not->toBeNull()
        ->and($fromViewer->fresh()->read_at)->toBeNull();

    $this->getJson('/api/v1/conversations/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 0);

    authAs($partner);

    $this->getJson('/api/v1/conversations/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);

    $this->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.unread_count', 1);
});

it('rate limits sending messages to sixty per minute', function () {
    $viewer = authAs();
    $partner = User::factory()->create();
    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);
    $conversation = messageTestConversation($viewer, $partner, $task);
    $url = "/api/v1/conversations/{$conversation->id}/messages";

    foreach (range(1, 60) as $index) {
        $this->postJson($url, ['body' => "Message {$index}"])->assertCreated();
    }

    $this->postJson($url, ['body' => 'One too many'])->assertStatus(429);
});
