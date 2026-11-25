<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function daySevenEndpointNotification(User $user, bool $read, int $minutesAgo, string $type = 'project_started'): string
{
    $id = (string) Str::uuid();
    $createdAt = now()->subMinutes($minutesAgo);

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => $type,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => (string) json_encode([
            'type' => $type,
            'title' => 'Test notification',
            'body' => "Notification {$minutesAgo} minutes ago.",
            'task_id' => 123,
        ]),
        'read_at' => $read ? $createdAt->copy()->addMinute() : null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    return $id;
}

it('lists fifteen notifications per page, newest first', function () {
    $user = authAs();

    $ids = collect(range(1, 18))
        ->map(fn (int $minutes): string => daySevenEndpointNotification($user, true, $minutes))
        ->values();

    $response = $this->getJson('/api/v1/notifications');

    $response->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('data.0.id', $ids->first())
        ->assertJsonPath('meta.total', 18)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at'],
            ],
        ]);
});

it('filters the notification list to unread rows', function () {
    $user = authAs();

    $unreadId = daySevenEndpointNotification($user, false, 1);
    daySevenEndpointNotification($user, true, 2);
    daySevenEndpointNotification($user, true, 3);

    $response = $this->getJson('/api/v1/notifications?filter[unread]=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $unreadId)
        ->assertJsonPath('data.0.type', 'project_started')
        ->assertJsonPath('data.0.data.task_id', 123);
});

it('returns the exact unread count payload', function () {
    $user = authAs();

    daySevenEndpointNotification($user, false, 1);
    daySevenEndpointNotification($user, false, 2);
    daySevenEndpointNotification($user, true, 3);

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertExactJson(['count' => 2]);
});

it('marks one of the authenticated user\'s notifications as read', function () {
    $user = authAs();
    $id = daySevenEndpointNotification($user, false, 1, 'review_received');

    $this->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('message', 'Notification marked as read.')
        ->assertJsonPath('data.id', $id);

    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->not->toBeNull();

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertExactJson(['count' => 0]);
});

it('marks all of the authenticated user\'s notifications as read without touching other users', function () {
    $user = authAs();
    $other = User::factory()->create();

    daySevenEndpointNotification($user, false, 1);
    daySevenEndpointNotification($user, false, 2);
    $otherId = daySevenEndpointNotification($other, false, 3);

    $this->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.updated', 2);

    expect(DB::table('notifications')->where('notifiable_id', $user->id)->whereNull('read_at')->count())->toBe(0)
        ->and(DB::table('notifications')->where('id', $otherId)->value('read_at'))->toBeNull();
});

it('does not let a user read another user\'s notification', function () {
    authAs();
    $other = User::factory()->create();
    $id = daySevenEndpointNotification($other, false, 1);

    $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
});

it('requires authentication for every notification endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/notifications'],
    ['GET', '/api/v1/notifications/unread-count'],
    ['POST', '/api/v1/notifications/read-all'],
    ['POST', '/api/v1/notifications/00000000-0000-0000-0000-000000000000/read'],
]);
