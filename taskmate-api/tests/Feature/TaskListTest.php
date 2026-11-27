<?php

use App\Models\Application;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function taskListUrl(array $params = []): string
{
    return '/api/v1/tasks'.($params === [] ? '' : '?'.http_build_query($params));
}

it('returns only open and full tasks by default', function () {
    authAs();

    Task::factory()->open()->create(['title' => 'Open task']);
    Task::factory()->full()->create(['title' => 'Full task']);
    Task::factory()->inProgress()->create(['title' => 'In progress task']);
    Task::factory()->completed()->create(['title' => 'Completed task']);
    Task::factory()->cancelled()->create(['title' => 'Cancelled task']);

    $response = $this->getJson(taskListUrl())->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())
        ->toEqualCanonicalizing(['Open task', 'Full task']);
});

it('shows statuses that were explicitly filtered for', function () {
    authAs();

    Task::factory()->open()->create();
    Task::factory()->cancelled()->create(['title' => 'Cancelled task']);

    $response = $this->getJson(taskListUrl(['filter' => ['status' => 'cancelled']]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Cancelled task');
});

it('searches the title and description', function () {
    authAs();

    Task::factory()->open()->create(['title' => 'Laravel API build', 'description' => 'A long enough description for the task.']);
    Task::factory()->open()->create(['title' => 'React UI build', 'description' => 'Need help with a Laravel integration.']);
    Task::factory()->open()->create(['title' => 'Unrelated task', 'description' => 'Nothing to see in this description.']);

    $response = $this->getJson(taskListUrl(['filter' => ['search' => 'laravel']]))->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('escapes wildcard characters in the search term', function () {
    authAs();

    Task::factory()->open()->create(['title' => '100% Laravel coverage', 'description' => 'A sufficiently long description here.']);
    Task::factory()->open()->create(['title' => 'Laravel basics', 'description' => 'A sufficiently long description here.']);

    $response = $this->getJson(taskListUrl(['filter' => ['search' => '%']]))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('100% Laravel coverage');
});

it('filters by type', function () {
    authAs();

    Task::factory()->paid()->create();
    Task::factory()->points()->create();

    $response = $this->getJson(taskListUrl(['filter' => ['type' => 'points']]))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.type'))->toBe('points');
});

it('filters by skill and matches any comma separated skill', function () {
    authAs();

    Task::factory()->open()->create(['skills' => ['Laravel', 'React']]);
    Task::factory()->open()->create(['skills' => ['Figma', 'UI Design']]);
    Task::factory()->open()->create(['skills' => ['Python']]);

    $single = $this->getJson(taskListUrl(['filter' => ['skill' => 'Laravel']]))->assertOk();
    expect($single->json('data'))->toHaveCount(1);

    $multiple = $this->getJson(taskListUrl(['filter' => ['skill' => 'Laravel,Figma']]))->assertOk();
    expect($multiple->json('data'))->toHaveCount(2);
});

it('filters by reward range', function () {
    authAs();

    Task::factory()->paid()->create(['reward' => 50]);
    Task::factory()->paid()->create(['reward' => 100]);
    Task::factory()->paid()->create(['reward' => 200]);

    $response = $this->getJson(taskListUrl([
        'filter' => ['min_reward' => 80, 'max_reward' => 150],
    ]))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.reward'))->toBe('100.00');
});

it('filters by open slots', function () {
    authAs();

    $withSlots = Task::factory()->open()->create(['team_size' => 3]);
    Application::factory()->accepted()->create(['task_id' => $withSlots->id]);

    $withoutSlots = Task::factory()->open()->create(['team_size' => 1]);
    Application::factory()->accepted()->create(['task_id' => $withoutSlots->id]);

    $response = $this->getJson(taskListUrl(['filter' => ['has_open_slots' => '1']]))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($withSlots->id);
});

it('filters by uploader', function () {
    authAs();

    $uploader = User::factory()->create();
    Task::factory()->open()->create(['uploader_id' => $uploader->id]);
    Task::factory()->open()->create();

    $response = $this->getJson(taskListUrl(['filter' => ['uploader_id' => $uploader->id]]))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.uploader.id'))->toBe($uploader->id)
        ->and($response->json('data.0.uploader.country'))->toBe($uploader->country);
});

it('sorts by allowed fields and defaults to newest first', function () {
    authAs();

    $cheap = Task::factory()->paid()->create(['title' => 'Cheap task', 'reward' => 10]);
    $expensive = Task::factory()->paid()->create(['title' => 'Expensive task', 'reward' => 300]);
    $middle = Task::factory()->paid()->create(['title' => 'Middle task', 'reward' => 150]);

    $cheap->created_at = now()->subDays(3);
    $cheap->save();
    $middle->created_at = now()->subDays(2);
    $middle->save();

    $ascending = $this->getJson(taskListUrl(['sort' => 'reward']))->assertOk();
    expect(collect($ascending->json('data'))->pluck('reward')->all())
        ->toBe(['10.00', '150.00', '300.00']);

    $descending = $this->getJson(taskListUrl(['sort' => '-reward']))->assertOk();
    expect(collect($descending->json('data'))->pluck('reward')->all())
        ->toBe(['300.00', '150.00', '10.00']);

    $default = $this->getJson(taskListUrl())->assertOk();
    expect(collect($default->json('data'))->pluck('title')->all())
        ->toBe(['Expensive task', 'Middle task', 'Cheap task']);
});

it('paginates with the standard resource shape', function () {
    authAs();

    Task::factory()->open()->count(5)->create();

    $response = $this->getJson(taskListUrl(['per_page' => 2, 'page' => 2]))->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.current_page'))->toBe(2)
        ->and($response->json('meta.per_page'))->toBe(2)
        ->and($response->json('meta.total'))->toBe(5)
        ->and($response->json('meta.last_page'))->toBe(3)
        ->and($response->json('links'))->toHaveKey('next');
});

it('caps per_page at fifty', function () {
    authAs();

    Task::factory()->open()->count(2)->create();

    $response = $this->getJson(taskListUrl(['per_page' => 500]))->assertOk();

    expect($response->json('meta.per_page'))->toBe(50);
});

it('rejects unknown filters and sorts', function () {
    authAs();

    $this->getJson(taskListUrl(['filter' => ['bogus' => '1']]))->assertStatus(400);
    $this->getJson(taskListUrl(['sort' => 'bogus']))->assertStatus(400);
});

it('keeps the list query count bounded', function () {
    authAs();

    Task::factory()->open()->count(15)->create()
        ->each(fn (Task $task) => Application::factory()->accepted()->create(['task_id' => $task->id]));

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->getJson(taskListUrl())->assertOk();

    expect($queries)->toBeLessThanOrEqual(8);
});
