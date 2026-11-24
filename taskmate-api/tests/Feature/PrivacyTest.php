<?php

use App\Models\User;

it('never exposes private fields on another user\'s profile', function () {
    $owner = User::factory()->create([
        'email' => 'private@example.com',
        'points_balance' => 500,
        'cv_path' => 'cvs/1/secret.pdf',
        'cv_original_name' => 'secret.pdf',
    ]);

    $owner->profileLinks()->create([
        'platform' => 'github',
        'url' => 'https://github.com/private',
    ]);

    authAs();

    $response = $this->getJson('/api/v1/users/'.$owner->id);

    $response->assertOk()
        ->assertJsonPath('data.name', $owner->name)
        ->assertJsonCount(1, 'data.profile_links')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.cv_path')
        ->assertJsonMissingPath('data.cv_original_name')
        ->assertJsonMissingPath('data.points_balance')
        ->assertJsonMissingPath('data.updated_at');

    $payload = $response->json('data');

    expect($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('cv_path')
        ->and($payload)->not->toHaveKey('points_balance')
        ->and($payload)->toHaveKeys([
            'id', 'name', 'bio', 'skills', 'avatar', 'average_rating', 'has_cv', 'profile_links', 'created_at',
        ]);
});

it('includes private fields on the authenticated user', function () {
    $user = authAs();

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.points_balance', $user->points_balance)
        ->assertJsonMissingPath('data.cv_path');
});

it('requires authentication to view a user profile', function () {
    $user = User::factory()->create();

    $this->getJson('/api/v1/users/'.$user->id)->assertUnauthorized();
});
