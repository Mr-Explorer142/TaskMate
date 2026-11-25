<?php

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('paginates reviews received by a user', function () {
    authAs();

    $user = User::factory()->create();
    Review::factory()->count(12)->create(['reviewee_id' => $user->id]);

    $this->getJson("/api/v1/users/{$user->id}/reviews")
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.reviews_count', 12)
        ->assertJsonPath('meta.total', 12);

    $this->getJson("/api/v1/users/{$user->id}/reviews?page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.reviews_count', 12);
});

it('returns the average, count, distribution, and newest reviews first', function () {
    authAs();

    $user = User::factory()->create();

    foreach ([5, 5, 4, 2, 1] as $index => $rating) {
        Review::factory()->create([
            'reviewee_id' => $user->id,
            'rating' => $rating,
            'comment' => "Review {$index}",
            'created_at' => now()->subDays(5 - $index),
            'updated_at' => now()->subDays(5 - $index),
        ]);
    }

    $response = $this->getJson("/api/v1/users/{$user->id}/reviews")
        ->assertOk()
        ->assertJsonPath('meta.average_rating', 3.4)
        ->assertJsonPath('meta.reviews_count', 5)
        ->assertJsonPath('meta.distribution.1', 1)
        ->assertJsonPath('meta.distribution.2', 1)
        ->assertJsonPath('meta.distribution.3', 0)
        ->assertJsonPath('meta.distribution.4', 1)
        ->assertJsonPath('meta.distribution.5', 2)
        ->assertJsonPath('data.0.comment', 'Review 4')
        ->assertJsonPath('data.4.comment', 'Review 0');

    expect($response->json('data.0'))->toHaveKeys([
        'id', 'reviewee_id', 'rating', 'comment', 'created_at', 'reviewer', 'task',
    ]);
});

it('returns an empty summary for a user with no reviews', function () {
    authAs();

    $user = User::factory()->create();

    $this->getJson("/api/v1/users/{$user->id}/reviews")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.average_rating', null)
        ->assertJsonPath('meta.reviews_count', 0)
        ->assertJsonPath('meta.distribution.1', 0)
        ->assertJsonPath('meta.distribution.5', 0);
});

it('does not expose private reviewer or task fields', function () {
    authAs();

    $user = User::factory()->create();
    $reviewer = User::factory()->create([
        'email' => 'private-reviewer@example.com',
        'points_balance' => 999,
        'cv_path' => 'cvs/private.pdf',
    ]);

    Review::factory()->create([
        'reviewee_id' => $user->id,
        'reviewer_id' => $reviewer->id,
        'rating' => 5,
    ]);

    $this->getJson("/api/v1/users/{$user->id}/reviews")
        ->assertOk()
        ->assertJsonPath('data.0.reviewer.id', $reviewer->id)
        ->assertJsonPath('data.0.reviewer.name', $reviewer->name)
        ->assertJsonMissingPath('data.0.reviewer.email')
        ->assertJsonMissingPath('data.0.reviewer.points_balance')
        ->assertJsonMissingPath('data.0.reviewer.cv_path')
        ->assertJsonMissingPath('data.0.task.description');
});

it('uses a bounded number of queries to list reviews', function () {
    authAs();

    $user = User::factory()->create();
    Review::factory()->count(5)->create(['reviewee_id' => $user->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson("/api/v1/users/{$user->id}/reviews")->assertOk()->assertJsonCount(5, 'data');

    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(6);
});

it('requires authentication to read another user\'s reviews', function () {
    $user = User::factory()->create();

    $this->getJson("/api/v1/users/{$user->id}/reviews")->assertUnauthorized();
});
