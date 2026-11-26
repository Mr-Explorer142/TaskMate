<?php

use App\Models\Application;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function publicTaskListUrl(array $params = []): string
{
    return '/api/v1/tasks'.($params === [] ? '' : '?'.http_build_query($params));
}

function publicTaskTokenFor(User $user): string
{
    return $user->createToken('public-task-test')->plainTextToken;
}

it('lets guests list, filter, sort and paginate tasks', function () {
    $cheap = Task::factory()->paid()->create(['title' => 'Cheap task', 'reward' => 10]);
    $expensive = Task::factory()->paid()->create(['title' => 'Expensive task', 'reward' => 300]);
    Task::factory()->points()->create(['title' => 'Points task', 'reward' => 50]);
    Task::factory()->completed()->create(['title' => 'Completed task']);
    Task::factory()->cancelled()->create(['title' => 'Cancelled task']);

    $cheap->forceFill(['created_at' => now()->subDays(2)])->save();
    $expensive->forceFill(['created_at' => now()->subDay()])->save();

    $default = $this->getJson(publicTaskListUrl())->assertOk();
    expect(collect($default->json('data'))->pluck('title')->all())
        ->not->toContain('Completed task')
        ->not->toContain('Cancelled task')
        ->toHaveCount(3);

    $points = $this->getJson(publicTaskListUrl(['filter' => ['type' => 'points']]))->assertOk();
    expect($points->json('data'))->toHaveCount(1)
        ->and($points->json('data.0.title'))->toBe('Points task');

    $sorted = $this->getJson(publicTaskListUrl(['sort' => '-reward']))->assertOk();
    expect(collect($sorted->json('data'))->pluck('reward')->take(2)->all())
        ->toBe(['300.00', '50.00']);

    $paged = $this->getJson(publicTaskListUrl(['per_page' => 2, 'page' => 2]))->assertOk();
    expect($paged->json('data'))->toHaveCount(1)
        ->and($paged->json('meta.current_page'))->toBe(2)
        ->and($paged->json('meta.per_page'))->toBe(2)
        ->and($paged->json('meta.total'))->toBe(3)
        ->and($paged->json('links'))->toHaveKey('next');
});

it('lets guests open a task and its team', function () {
    $uploader = User::factory()->create(['name' => 'Task Owner']);
    $member = User::factory()->create(['name' => 'Team Member']);
    $task = Task::factory()->open()->create([
        'uploader_id' => $uploader->id,
        'title' => 'Public task',
        'team_size' => 3,
    ]);

    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    $this->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.title', 'Public task')
        ->assertJsonPath('data.uploader.id', $uploader->id)
        ->assertJsonPath('data.uploader.name', 'Task Owner');

    $this->getJson("/api/v1/tasks/{$task->id}/team")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $member->id)
        ->assertJsonPath('meta.team_size', 3)
        ->assertJsonPath('meta.accepted_count', 1)
        ->assertJsonPath('meta.open_slots', 2);
});

it('never exposes private fields or user-specific data to guests', function () {
    $uploader = User::factory()->create([
        'email' => 'private-uploader@example.com',
        'cv_path' => 'cvs/private/uploader.pdf',
        'cv_original_name' => 'uploader.pdf',
        'points_balance' => 7654321,
    ]);

    $applicant = User::factory()->create([
        'email' => 'private-applicant@example.com',
        'cv_path' => 'cvs/private/applicant.pdf',
        'points_balance' => 1234567,
    ]);

    $task = Task::factory()->open()->create([
        'uploader_id' => $uploader->id,
        'team_size' => 3,
    ]);

    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    Application::factory()->create([
        'task_id' => $task->id,
    ]);

    $list = $this->getJson(publicTaskListUrl())->assertOk();
    $show = $this->getJson("/api/v1/tasks/{$task->id}")->assertOk();

    foreach ([$list, $show] as $response) {
        $payload = $response->json();
        $body = json_encode($payload);

        expect($body)->not->toContain('private-uploader@example.com')
            ->and($body)->not->toContain('private-applicant@example.com')
            ->and($body)->not->toContain('cvs/private')
            ->and($body)->not->toContain('"cv_path"')
            ->and($body)->not->toContain('"points_balance"')
            ->and($body)->not->toContain('"applications"');
    }

    $guestTask = $show->json('data');

    expect($guestTask['is_owner'])->toBeFalse()
        ->and($guestTask['can_start'])->toBeFalse()
        ->and($guestTask['can_complete'])->toBeFalse()
        ->and($guestTask['can_leave'])->toBeFalse()
        ->and($guestTask['is_participant'])->toBeFalse()
        ->and($guestTask['my_application_id'])->toBeNull()
        ->and($guestTask['my_application_status'])->toBeNull()
        ->and($guestTask['pending_reviews_count'])->toBeNull()
        ->and($guestTask)->not->toHaveKey('applications_count')
        ->and($guestTask)->not->toHaveKey('pending_count')
        ->and($guestTask['uploader'])->not->toHaveKey('email')
        ->and($guestTask['uploader'])->not->toHaveKey('cv_path')
        ->and($guestTask['uploader'])->not->toHaveKey('points_balance');

    $first = $list->json('data.0');

    expect($first['is_owner'])->toBeFalse()
        ->and($first['is_participant'])->toBeFalse()
        ->and($first['my_application_id'])->toBeNull()
        ->and($first)->not->toHaveKey('applications_count')
        ->and($first)->not->toHaveKey('pending_count');
});

it('still resolves the viewer from a bearer token on the public routes', function () {
    $owner = User::factory()->create();
    $applicant = User::factory()->create();

    $task = Task::factory()->open()->create([
        'uploader_id' => $owner->id,
        'team_size' => 3,
    ]);

    Application::factory()->accepted()->create(['task_id' => $task->id]);
    Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $ownerView = $this->withHeader(
        'Authorization',
        'Bearer '.publicTaskTokenFor($owner),
    )->getJson("/api/v1/tasks/{$task->id}")->assertOk();

    expect($ownerView->json('data.is_owner'))->toBeTrue()
        ->and($ownerView->json('data.can_start'))->toBeTrue()
        ->and($ownerView->json('data.my_application_id'))->toBeNull()
        ->and($ownerView->json('data.applications_count'))->toBe(2)
        ->and($ownerView->json('data.pending_count'))->toBe(1)
        ->and($ownerView->json('data.pending_reviews_count'))->toBe(0);

    $this->app['auth']->forgetGuards();

    $applicantView = $this->withHeader(
        'Authorization',
        'Bearer '.publicTaskTokenFor($applicant),
    )->getJson("/api/v1/tasks/{$task->id}")->assertOk();

    expect($applicantView->json('data.is_owner'))->toBeFalse()
        ->and($applicantView->json('data.is_participant'))->toBeFalse()
        ->and($applicantView->json('data.my_application_status'))->toBe('pending')
        ->and($applicantView->json('data.my_application_id'))->not->toBeNull()
        ->and($applicantView->json('data.can_start'))->toBeFalse()
        ->and($applicantView)->not->toHaveKey('applications_count')
        ->and($applicantView)->not->toHaveKey('pending_count');
});

it('keeps the guest list query count bounded', function () {
    Task::factory()->open()->count(15)->create()
        ->each(fn (Task $task) => Application::factory()->accepted()->create(['task_id' => $task->id]));

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->getJson(publicTaskListUrl())->assertOk();

    expect($queries)->toBeLessThanOrEqual(8);
});

it('rate limits the public task routes', function () {
    $uris = ['api/v1/tasks', 'api/v1/tasks/{task}', 'api/v1/tasks/{task}/team'];

    foreach ($uris as $uri) {
        $route = collect(Route::getRoutes())->first(
            fn ($route): bool => $route->uri() === $uri
                && in_array('GET', $route->methods(), true),
        );

        expect($route)->not->toBeNull();

        $middleware = $route->gatherMiddleware();

        expect(collect($middleware)->contains(
            fn (mixed $value): bool => is_string($value) && $value === 'throttle:60,1',
        ))->toBeTrue("Missing public rate limit on {$uri}.");
    }
});

it('keeps every other api route protected from guests', function () {
    $this->getJson('/api/v1/my/tasks')->assertUnauthorized();
    $this->getJson('/api/v1/my/applications')->assertUnauthorized();
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->getJson('/api/v1/points')->assertUnauthorized();
    $this->postJson('/api/v1/tasks')->assertUnauthorized();
});
