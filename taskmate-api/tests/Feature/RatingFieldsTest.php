<?php

use App\Models\Application;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;

it('exposes the uploader review count on task payloads', function () {
    $uploader = User::factory()->create();
    Review::factory()->count(2)->create(['reviewee_id' => $uploader->id]);
    $task = Task::factory()->create(['uploader_id' => $uploader->id]);

    authAs();

    $response = $this->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.uploader.reviews_count', 2);

    expect($response->json('data.uploader.average_rating'))->not->toBeNull();
});

it('exposes the review count on team members', function () {
    $owner = authAs();
    $member = User::factory()->create();
    $task = Task::factory()->create(['uploader_id' => $owner->id]);

    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    Review::factory()->count(3)->create(['reviewee_id' => $member->id]);

    $this->getJson("/api/v1/tasks/{$task->id}/team")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reviews_count', 3);
});

it('exposes the review count on applicants', function () {
    $owner = authAs();
    $applicant = User::factory()->create();
    Review::factory()->count(2)->create(['reviewee_id' => $applicant->id]);
    $task = Task::factory()->create(['uploader_id' => $owner->id]);

    Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    $this->getJson("/api/v1/tasks/{$task->id}/applications")
        ->assertOk()
        ->assertJsonPath('data.0.applicant.reviews_count', 2);
});

it('tracks the current user pending reviews on my applications', function () {
    $owner = User::factory()->create();
    $member = authAs();
    $task = Task::factory()->completed()->create([
        'uploader_id' => $owner->id,
        'team_size' => 1,
    ]);

    Application::factory()->accepted()->create([
        'task_id' => $task->id,
        'user_id' => $member->id,
    ]);

    $this->getJson('/api/v1/my/applications')
        ->assertOk()
        ->assertJsonPath('data.0.task.pending_reviews_count', 1);

    Review::factory()->create([
        'task_id' => $task->id,
        'reviewer_id' => $member->id,
        'reviewee_id' => $owner->id,
        'rating' => 5,
    ]);

    $this->getJson('/api/v1/my/applications')
        ->assertOk()
        ->assertJsonPath('data.0.task.pending_reviews_count', 0);
});
