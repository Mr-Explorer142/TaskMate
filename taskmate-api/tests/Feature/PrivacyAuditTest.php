<?php

use App\Models\Application;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;

function daySevenAssertNoPrivateUserData($response, User $other): void
{
    $body = json_encode($response->json());

    expect($body)->not->toContain($other->email)
        ->and($body)->not->toContain($other->cv_path)
        ->and($body)->not->toContain($other->cv_original_name)
        ->and($body)->not->toContain((string) $other->points_balance)
        ->and($body)->not->toContain('"password"')
        ->and($body)->not->toContain('"cv_path"')
        ->and($body)->not->toContain('"avatar_public_id"')
        ->and($body)->not->toContain('"points_balance"');
}

it('keeps private fields out of applicant cards, team members and reviews', function () {
    $viewer = authAs();

    $other = User::factory()->create([
        'email' => 'private-audit@example.com',
        'password' => 'private-password',
        'points_balance' => 987654321,
        'cv_path' => 'cvs/private/secret-resume.pdf',
        'cv_original_name' => 'secret-resume.pdf',
    ]);

    $task = Task::factory()->open()->create([
        'uploader_id' => $viewer->id,
        'team_size' => 2,
    ]);

    Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $other->id,
    ]);

    $teamTask = Task::factory()->open()->create([
        'uploader_id' => $viewer->id,
        'team_size' => 2,
    ]);

    Application::factory()->accepted()->create([
        'task_id' => $teamTask->id,
        'user_id' => $other->id,
    ]);

    $completedTask = Task::factory()->completed()->create([
        'uploader_id' => $viewer->id,
        'team_size' => 2,
    ]);

    Application::factory()->accepted()->create([
        'task_id' => $completedTask->id,
        'user_id' => $other->id,
    ]);

    Review::factory()->create([
        'task_id' => $completedTask->id,
        'reviewer_id' => $viewer->id,
        'reviewee_id' => $other->id,
        'rating' => 5,
    ]);

    $applicants = $this->getJson("/api/v1/tasks/{$task->id}/applications");
    $applicants->assertOk()
        ->assertJsonPath('data.0.applicant.id', $other->id)
        ->assertJsonMissingPath('data.0.applicant.email')
        ->assertJsonMissingPath('data.0.applicant.cv_path')
        ->assertJsonMissingPath('data.0.applicant.points_balance');
    daySevenAssertNoPrivateUserData($applicants, $other);

    $team = $this->getJson("/api/v1/tasks/{$teamTask->id}/team");
    $team->assertOk()
        ->assertJsonPath('data.0.id', $other->id)
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.cv_path')
        ->assertJsonMissingPath('data.0.points_balance');
    daySevenAssertNoPrivateUserData($team, $other);

    $reviews = $this->getJson("/api/v1/users/{$other->id}/reviews");
    $reviews->assertOk()
        ->assertJsonPath('data.0.reviewee_id', $other->id)
        ->assertJsonMissingPath('data.0.reviewer.email')
        ->assertJsonMissingPath('data.0.reviewer.cv_path')
        ->assertJsonMissingPath('data.0.reviewer.points_balance');
    daySevenAssertNoPrivateUserData($reviews, $other);

    $profile = $this->getJson("/api/v1/users/{$other->id}");
    $profile->assertOk()->assertJsonPath('data.id', $other->id);
    daySevenAssertNoPrivateUserData($profile, $other);
});
