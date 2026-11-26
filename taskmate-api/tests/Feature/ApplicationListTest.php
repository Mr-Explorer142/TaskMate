<?php

use App\Models\Application;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists only the authenticated user applications with task summaries', function () {
    $applicant = authAs();
    $task = Task::factory()->open()->create(['team_size' => 3]);
    $own = Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);
    Application::factory()->create();

    $response = $this->getJson('/api/v1/my/applications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id)
        ->assertJsonPath('data.0.task.id', $task->id)
        ->assertJsonPath('data.0.task.title', $task->title)
        ->assertJsonPath('data.0.task.team_size', 3)
        ->assertJsonPath('data.0.task.accepted_count', 0)
        ->assertJsonPath('meta.per_page', 12);

    expect($response->json('data.0.task'))->toHaveKeys([
        'id', 'title', 'type', 'reward', 'status', 'team_size', 'accepted_count',
    ]);
});

it('filters the authenticated user applications by status', function () {
    $applicant = authAs();
    $pending = Application::factory()->create(['user_id' => $applicant->id]);
    $accepted = Application::factory()->accepted()->create(['user_id' => $applicant->id]);
    Application::factory()->rejected()->create(['user_id' => $applicant->id]);

    $response = $this->getJson('/api/v1/my/applications?filter[status]=pending')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($pending->id);

    $acceptedResponse = $this->getJson('/api/v1/my/applications?filter[status]=accepted')->assertOk();
    expect($acceptedResponse->json('data.0.id'))->toBe($accepted->id);
});

it('orders the authenticated user applications newest first', function () {
    $applicant = authAs();
    $older = Application::factory()->create(['user_id' => $applicant->id]);
    $newer = Application::factory()->create(['user_id' => $applicant->id]);

    $older->created_at = now()->subDay();
    $older->save();

    $response = $this->getJson('/api/v1/my/applications')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it('paginates the authenticated user applications at twelve per page', function () {
    $applicant = authAs();
    $tasks = Task::factory()->open()->count(13)->create();

    foreach ($tasks as $task) {
        Application::factory()->create([
            'task_id' => $task->id,
            'user_id' => $applicant->id,
        ]);
    }

    $response = $this->getJson('/api/v1/my/applications')->assertOk();

    expect($response->json('data'))->toHaveCount(12)
        ->and($response->json('meta.per_page'))->toBe(12)
        ->and($response->json('meta.total'))->toBe(13);
});

it('shows task applications to the owner with pending first', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);

    $rejected = Application::factory()->rejected()->create([
        'task_id' => $task->id,
        'created_at' => now(),
    ]);
    $olderPending = Application::factory()->create([
        'task_id' => $task->id,
        'created_at' => now()->subDay(),
    ]);
    $newerPending = Application::factory()->create([
        'task_id' => $task->id,
        'created_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/tasks/{$task->id}/applications")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 20);

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$newerPending->id, $olderPending->id, $rejected->id]);
});

it('filters task applications by status', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 3]);
    $accepted = Application::factory()->accepted()->create(['task_id' => $task->id]);
    Application::factory()->create(['task_id' => $task->id]);

    $response = $this->getJson("/api/v1/tasks/{$task->id}/applications?filter[status]=accepted")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($accepted->id);
});

it('forbids non-owners from listing task applications', function () {
    authAs();
    $task = Task::factory()->open()->create();
    Application::factory()->create(['task_id' => $task->id]);

    $this->getJson("/api/v1/tasks/{$task->id}/applications")->assertForbidden();
});

it('requires authentication for application lists', function () {
    $task = Task::factory()->open()->create();

    $this->getJson('/api/v1/my/applications')->assertUnauthorized();
    $this->getJson("/api/v1/tasks/{$task->id}/applications")->assertUnauthorized();
});

it('does not leak private applicant fields', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);
    $applicant = User::factory()->create([
        'email' => 'private@example.com',
        'cv_path' => 'cvs/1/secret.pdf',
        'points_balance' => 999,
    ]);
    Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $response = $this->getJson("/api/v1/tasks/{$task->id}/applications")
        ->assertOk()
        ->assertJsonPath('data.0.applicant.id', $applicant->id)
        ->assertJsonPath('data.0.applicant.has_cv', true);

    $applicantPayload = $response->json('data.0.applicant');

    expect($applicantPayload)->toHaveKeys([
        'id', 'name', 'avatar_url', 'bio', 'skills', 'average_rating', 'has_cv', 'profile_links',
    ])
        ->not->toHaveKey('email')
        ->not->toHaveKey('cv_path')
        ->not->toHaveKey('points_balance');
});

it('keeps the my applications query count bounded', function () {
    $applicant = authAs();
    $tasks = Task::factory()->open()->count(10)->create();

    foreach ($tasks as $task) {
        Application::factory()->create([
            'task_id' => $task->id,
            'user_id' => $applicant->id,
        ]);
    }

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->getJson('/api/v1/my/applications')->assertOk();

    expect($queries)->toBeLessThanOrEqual(8);
});

it('keeps the task applications query count bounded', function () {
    $owner = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id, 'team_size' => 20]);

    Application::factory()->count(15)->create(['task_id' => $task->id]);

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->getJson("/api/v1/tasks/{$task->id}/applications")->assertOk();

    expect($queries)->toBeLessThanOrEqual(8);
});
