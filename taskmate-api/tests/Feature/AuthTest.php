<?php

use App\Models\User;

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Alice Example',
        'email' => 'alice@example.com',
        'country' => 'bd',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Registration successful.')
        ->assertJsonPath('data.user.email', 'alice@example.com')
        ->assertJsonPath('data.user.country', 'BD')
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'has_cv', 'points_balance'],
                'token',
            ],
            'message',
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('users', [
        'email' => 'alice@example.com',
        'country' => 'BD',
    ]);
});

it('grants the signup bonus and records it in the ledger', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Bonus User',
        'email' => 'bonus@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.points_balance', (int) config('points.signup_bonus'));

    $user = User::query()->where('email', 'bonus@example.com')->firstOrFail();

    expect($user->pointTransactions()->sum('amount'))->toBe((int) config('points.signup_bonus'))
        ->and($user->pointTransactions()->first()->type->value)->toBe('bonus');

    $this->assertDatabaseHas('point_transactions', [
        'user_id' => $user->id,
        'amount' => (int) config('points.signup_bonus'),
        'type' => 'bonus',
    ]);
});

it('validates registration input', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'bob@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Login successful.')
        ->assertJsonPath('data.user.id', $user->id);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects a wrong password', function () {
    User::factory()->create([
        'email' => 'bob@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns the authenticated user with their links', function () {
    $user = authAs();
    $user->profileLinks()->create([
        'platform' => 'github',
        'url' => 'https://github.com/example',
    ]);

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonCount(1, 'data.profile_links')
        ->assertJsonMissingPath('data.cv_path');
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth-token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Logged out.');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});

it('requires authentication for protected auth routes', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/auth/me'],
    ['POST', '/api/v1/auth/logout'],
]);

it('returns json for unauthenticated requests without an accept header', function () {
    $this->get('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});
