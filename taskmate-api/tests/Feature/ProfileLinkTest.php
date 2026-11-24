<?php

use App\Models\ProfileLink;

it('adds more than ten links without a cap', function () {
    $user = authAs();

    for ($i = 1; $i <= 12; $i++) {
        $this->postJson('/api/v1/profile/links', [
            'label' => 'Link '.$i,
            'url' => 'https://example.com/profile/'.$i,
        ])->assertCreated();
    }

    $links = $user->profileLinks()->orderBy('sort_order')->get();

    expect($links)->toHaveCount(12)
        ->and($links->pluck('sort_order')->all())->toBe(range(0, 11));
});

it('rejects duplicate URLs for the same user', function () {
    $user = authAs();

    $this->postJson('/api/v1/profile/links', [
        'url' => 'https://github.com/duplicate',
    ])->assertCreated();

    $this->postJson('/api/v1/profile/links', [
        'url' => '  https://github.com/duplicate  ',
    ])->assertUnprocessable()->assertJsonValidationErrors(['url']);

    expect($user->profileLinks()->count())->toBe(1);
});

it('rejects invalid and javascript URLs', function (string $url) {
    authAs();

    $this->postJson('/api/v1/profile/links', ['url' => $url])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
})->with([
    'javascript:alert(1)',
    'ftp://example.com/file',
    'not a url',
    'http://',
    '//example.com/no-scheme',
]);

it('auto-detects the platform from the URL', function (string $url, string $platform) {
    authAs();

    $response = $this->postJson('/api/v1/profile/links', ['url' => $url])
        ->assertCreated();

    expect($response->json('data.platform'))->toBe($platform);
})->with([
    ['https://github.com/example', 'github'],
    ['https://www.linkedin.com/in/example', 'linkedin'],
    ['https://www.behance.net/example', 'behance'],
    ['https://dribbble.com/example', 'dribbble'],
    ['https://x.com/example', 'twitter'],
    ['https://twitter.com/example', 'twitter'],
    ['https://www.youtube.com/@example', 'youtube'],
    ['https://youtu.be/abc123', 'youtube'],
    ['https://example.com/portfolio', 'website'],
]);

it('respects an explicitly provided platform', function () {
    authAs();

    $response = $this->postJson('/api/v1/profile/links', [
        'platform' => 'portfolio',
        'url' => 'https://github.com/example',
    ])->assertCreated();

    expect($response->json('data.platform'))->toBe('portfolio');
});

it('updates and deletes an owned link', function () {
    $user = authAs();
    $link = ProfileLink::factory()->create(['user_id' => $user->id]);

    $this->putJson('/api/v1/profile/links/'.$link->id, [
        'label' => 'Renamed',
    ])->assertOk()->assertJsonPath('data.label', 'Renamed');

    $this->deleteJson('/api/v1/profile/links/'.$link->id)->assertOk();

    $this->assertDatabaseMissing('profile_links', ['id' => $link->id]);
});

it('prevents editing or deleting another user\'s link', function () {
    $link = ProfileLink::factory()->create();

    authAs();

    $this->putJson('/api/v1/profile/links/'.$link->id, ['label' => 'Hijacked'])
        ->assertForbidden();

    $this->deleteJson('/api/v1/profile/links/'.$link->id)
        ->assertForbidden();

    $this->assertDatabaseHas('profile_links', [
        'id' => $link->id,
        'label' => $link->label,
    ]);
});

it('reorders the authenticated user\'s links', function () {
    $user = authAs();

    $first = ProfileLink::factory()->create(['user_id' => $user->id, 'sort_order' => 0]);
    $second = ProfileLink::factory()->create(['user_id' => $user->id, 'sort_order' => 1]);
    $third = ProfileLink::factory()->create(['user_id' => $user->id, 'sort_order' => 2]);

    $response = $this->patchJson('/api/v1/profile/links/reorder', [
        'ids' => [$third->id, $first->id, $second->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.id', $third->id)
        ->assertJsonPath('data.1.id', $first->id)
        ->assertJsonPath('data.2.id', $second->id);

    expect($third->fresh()->sort_order)->toBe(0)
        ->and($first->fresh()->sort_order)->toBe(1)
        ->and($second->fresh()->sort_order)->toBe(2);
});

it('rejects reordering ids that belong to another user', function () {
    $user = authAs();
    $own = ProfileLink::factory()->create(['user_id' => $user->id]);
    $foreign = ProfileLink::factory()->create();

    $this->patchJson('/api/v1/profile/links/reorder', [
        'ids' => [$own->id, $foreign->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['ids']);

    expect($own->fresh()->sort_order)->toBe(0)
        ->and($foreign->fresh()->sort_order)->toBe(0);
});

it('rejects labels longer than sixty characters', function () {
    authAs();

    $this->postJson('/api/v1/profile/links', [
        'label' => str_repeat('a', 61),
        'url' => 'https://example.com/label-test',
    ])->assertUnprocessable()->assertJsonValidationErrors(['label']);
});

it('requires authentication for the link endpoints', function () {
    $this->getJson('/api/v1/profile/links')->assertUnauthorized();
    $this->postJson('/api/v1/profile/links', ['url' => 'https://example.com'])->assertUnauthorized();
});
