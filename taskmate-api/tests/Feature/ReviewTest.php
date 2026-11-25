<?php

use App\Models\Application;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Create a completed task with an uploader, two accepted members, and an outsider.
 *
 * @return array{Task, User, Collection<int, User>, User}
 */
function reviewTaskWithTeam(): array
{
    $owner = User::factory()->create();
    $members = User::factory()->count(2)->create();
    $outsider = User::factory()->create();

    $task = Task::factory()->completed()->create([
        'uploader_id' => $owner->id,
        'team_size' => 3,
    ]);

    foreach ($members as $member) {
        Application::factory()->accepted()->create([
            'task_id' => $task->id,
            'user_id' => $member->id,
        ]);
    }

    return [$task, $owner, $members, $outsider];
}

/**
 * Submit a review as a given user.
 */
function submitReview(
    TestCase $test,
    Task $task,
    User $reviewer,
    User $reviewee,
    array $overrides = [],
): TestResponse {
    authAs($reviewer);

    return $test->postJson("/api/v1/tasks/{$task->id}/reviews", array_merge([
        'reviewee_id' => $reviewee->id,
        'rating' => 5,
        'comment' => ' solid work ',
    ], $overrides));
}

it('lets an uploader and a member review each other', function () {
    [$task, $owner, $members] = reviewTaskWithTeam();

    submitReview($this, $task, $owner, $members[0])
        ->assertCreated()
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.comment', 'solid work')
        ->assertJsonPath('data.reviewee_id', $members[0]->id)
        ->assertJsonPath('data.reviewer.id', $owner->id)
        ->assertJsonPath('data.task.id', $task->id);

    submitReview($this, $task, $members[0], $owner, ['rating' => 4, 'comment' => null])
        ->assertCreated()
        ->assertJsonPath('data.reviewer.id', $members[0]->id)
        ->assertJsonPath('data.reviewee_id', $owner->id)
        ->assertJsonPath('data.comment', null);

    expect(Review::query()->where('task_id', $task->id)->count())->toBe(2);
});

it('lets members review other members', function () {
    [$task, $owner, $members] = reviewTaskWithTeam();

    submitReview($this, $task, $members[0], $members[1], ['rating' => 3])
        ->assertCreated()
        ->assertJsonPath('data.reviewer.id', $members[0]->id)
        ->assertJsonPath('data.reviewee_id', $members[1]->id)
        ->assertJsonPath('data.rating', 3);
});

it('rejects reviews before a project is completed', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $task = Task::factory()->inProgress()->create(['uploader_id' => $owner->id]);

    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    submitReview($this, $task, $owner, $member)->assertStatus(409);

    expect(Review::query()->count())->toBe(0);
});

it('rejects a reviewer who is not a participant', function () {
    [$task, $owner, $members, $outsider] = reviewTaskWithTeam();

    submitReview($this, $task, $outsider, $members[0])->assertUnprocessable();

    expect(Review::query()->count())->toBe(0);
});

it('rejects a reviewee who is not a participant', function () {
    [$task, $owner, $members, $outsider] = reviewTaskWithTeam();

    submitReview($this, $task, $owner, $outsider)->assertUnprocessable();

    expect(Review::query()->count())->toBe(0);
});

it('rejects self reviews', function () {
    [$task, $owner] = reviewTaskWithTeam();

    submitReview($this, $task, $owner, $owner)->assertUnprocessable();
});

it('rejects a repeated review for the same pair', function () {
    [$task, $owner, $members] = reviewTaskWithTeam();

    submitReview($this, $task, $owner, $members[0])->assertCreated();
    submitReview($this, $task, $owner, $members[0], ['rating' => 2])->assertStatus(409);

    expect(Review::query()->where('task_id', $task->id)->count())->toBe(1)
        ->and(Review::query()->where('task_id', $task->id)->first()->rating)->toBe(5);
});

it('validates rating and comment fields', function (array $payload) {
    [$task, $owner, $members] = reviewTaskWithTeam();

    submitReview($this, $task, $owner, $members[0], $payload)->assertUnprocessable();
})->with([
    'rating too low' => [['rating' => 0]],
    'rating too high' => [['rating' => 6]],
    'rating missing' => [['rating' => null]],
    'comment too long' => [['comment' => str_repeat('a', 1001)]],
]);

it('excludes members who left from reviewing and being reviewed', function () {
    [$task, $owner, $members] = reviewTaskWithTeam();

    $leftMember = User::factory()->create();
    Application::factory()->left()->create([
        'task_id' => $task->id,
        'user_id' => $leftMember->id,
    ]);

    submitReview($this, $task, $leftMember, $owner)->assertUnprocessable();
    submitReview($this, $task, $owner, $leftMember)->assertUnprocessable();

    authAs($owner);

    $response = $this->getJson("/api/v1/tasks/{$task->id}/reviews/pending")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($leftMember->id);
});

it('lists the participants a reviewer still needs to review', function () {
    [$task, $owner, $members] = reviewTaskWithTeam();

    authAs($owner);

    $this->getJson("/api/v1/tasks/{$task->id}/reviews/pending")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $members[0]->id)
        ->assertJsonPath('data.0.name', $members[0]->name)
        ->assertJsonPath('data.0.avatar', $members[0]->avatar)
        ->assertJsonPath('data.1.id', $members[1]->id);

    submitReview($this, $task, $owner, $members[0])->assertCreated();

    authAs($owner);

    $this->getJson("/api/v1/tasks/{$task->id}/reviews/pending")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $members[1]->id);

    submitReview($this, $task, $owner, $members[1])->assertCreated();

    authAs($owner);

    $this->getJson("/api/v1/tasks/{$task->id}/reviews/pending")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('forbids outsiders from viewing pending reviews', function () {
    [$task, $owner, $members, $outsider] = reviewTaskWithTeam();

    authAs($outsider);

    $this->getJson("/api/v1/tasks/{$task->id}/reviews/pending")->assertForbidden();
});

it('does not list pending reviews before a project is completed', function () {
    $owner = User::factory()->create();
    $task = Task::factory()->inProgress()->create(['uploader_id' => $owner->id]);

    authAs($owner);

    $this->getJson("/api/v1/tasks/{$task->id}/reviews/pending")->assertStatus(409);
});
