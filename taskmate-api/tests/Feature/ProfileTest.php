<?php

it('updates the authenticated profile', function () {
    $user = authAs();

    $response = $this->putJson('/api/v1/profile', [
        'name' => 'Updated Name',
        'country' => 'jp',
        'bio' => 'A short bio.',
        'skills' => ['PHP', 'React', 'MySQL'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.country', 'JP')
        ->assertJsonPath('data.bio', 'A short bio.')
        ->assertJsonPath('data.skills.0', 'PHP');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'country' => 'JP',
        'bio' => 'A short bio.',
    ]);
});

it('limits skills to thirty items of fifty characters', function () {
    $user = authAs();

    $this->putJson('/api/v1/profile', [
        'skills' => array_map(fn (int $i) => 'Skill '.$i, range(1, 31)),
    ])->assertUnprocessable()->assertJsonValidationErrors(['skills']);

    $this->putJson('/api/v1/profile', [
        'skills' => [str_repeat('a', 51)],
    ])->assertUnprocessable()->assertJsonValidationErrors(['skills.0']);

    expect($user->fresh()->skills)->toBeNull();
});

it('requires authentication to update the profile', function () {
    $this->putJson('/api/v1/profile', ['name' => 'Nope'])
        ->assertUnauthorized();
});
