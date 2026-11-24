<?php

use App\Models\Task;
use App\Models\User;

it('returns only the authenticated user tasks including cancelled ones', function () {
    $user = authAs();

    Task::factory()->open()->create(['uploader_id' => $user->id, 'title' => 'My open task']);
    Task::factory()->cancelled()->create(['uploader_id' => $user->id, 'title' => 'My cancelled task']);
    Task::factory()->completed()->create(['uploader_id' => $user->id, 'title' => 'My completed task']);
    Task::factory()->open()->create(['title' => 'Someone else task']);

    $response = $this->getJson('/api/v1/my/tasks')->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())
        ->toEqualCanonicalizing(['My open task', 'My cancelled task', 'My completed task']);
});

it('applies filters and pagination to my tasks', function () {
    $user = authAs();

    Task::factory()->open()->create([
        'uploader_id' => $user->id,
        'title' => 'My Laravel task',
        'skills' => ['Laravel'],
    ]);
    Task::factory()->open()->create([
        'uploader_id' => $user->id,
        'title' => 'My React task',
        'skills' => ['React'],
    ]);
    Task::factory()->open()->create([
        'uploader_id' => $user->id,
        'title' => 'My other React task',
        'skills' => ['React'],
    ]);

    $filtered = $this->getJson('/api/v1/my/tasks?'.http_build_query([
        'filter' => ['skill' => 'Laravel'],
    ]))->assertOk();

    expect($filtered->json('data'))->toHaveCount(1)
        ->and($filtered->json('data.0.title'))->toBe('My Laravel task');

    $paginated = $this->getJson('/api/v1/my/tasks?'.http_build_query([
        'filter' => ['skill' => 'React'],
        'per_page' => 1,
        'page' => 2,
    ]))->assertOk();

    expect($paginated->json('data'))->toHaveCount(1)
        ->and($paginated->json('meta.per_page'))->toBe(1)
        ->and($paginated->json('meta.total'))->toBe(2);
});

it('requires authentication for my tasks', function () {
    $this->getJson('/api/v1/my/tasks')->assertUnauthorized();
});

it('does not expose application counts or other users tasks', function () {
    $user = authAs();
    $other = User::factory()->create();

    Task::factory()->open()->create(['uploader_id' => $other->id]);

    $response = $this->getJson('/api/v1/my/tasks')->assertOk();

    expect($response->json('data'))->toHaveCount(0);
    expect($user->postedTasks()->count())->toBe(0);
});
