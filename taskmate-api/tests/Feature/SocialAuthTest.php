<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function makeSocialiteUser(array $attributes = []): SocialiteUser
{
    return (new SocialiteUser)->map(array_merge([
        'id' => '123456789',
        'nickname' => 'jane',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'avatar' => 'https://example.com/avatar.png',
    ], $attributes));
}

function mockSocialiteProvider(SocialiteUser $socialiteUser): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
}

beforeEach(function () {
    Config::set('taskmate.frontend_url', 'http://localhost:5173');
});

it('creates a user from a social login and redirects with a token', function () {
    mockSocialiteProvider(makeSocialiteUser());

    $response = $this->get('/api/v1/auth/github/callback');

    $response->assertRedirect();

    expect($response->headers->get('Location'))
        ->toStartWith('http://localhost:5173/auth/callback?token=');

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->name)->toBe('Jane Doe')
        ->and($user->avatar)->toBe('https://example.com/avatar.png')
        ->and($user->avatar_url)->toBe('https://example.com/avatar.png')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->points_balance)->toBe((int) config('points.signup_bonus'))
        ->and($user->pointTransactions()->count())->toBe(1)
        ->and($user->socialAccounts)->toHaveCount(1);

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => '123456789',
    ]);
});

it('links a social login to an existing user with the same email', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'country' => 'DE',
    ]);

    mockSocialiteProvider(makeSocialiteUser());

    $this->get('/api/v1/auth/github/callback')->assertRedirect();

    expect(User::query()->count())->toBe(1)
        ->and($user->fresh()->country)->toBe('DE')
        ->and($user->fresh()->points_balance)->toBe(0)
        ->and($user->pointTransactions()->count())->toBe(0)
        ->and($user->socialAccounts()->count())->toBe(1);
});

it('reuses an existing social account', function () {
    $user = User::factory()->create();

    SocialAccount::factory()->create([
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => '123456789',
    ]);

    mockSocialiteProvider(makeSocialiteUser(['email' => null]));

    $this->get('/api/v1/auth/github/callback')->assertRedirect();

    expect(User::query()->count())->toBe(1)
        ->and($user->socialAccounts()->count())->toBe(1);
});

it('redirects with an error when the provider does not share an email', function () {
    mockSocialiteProvider(makeSocialiteUser(['email' => null]));

    $response = $this->get('/api/v1/auth/github/callback');

    expect($response->headers->get('Location'))
        ->toContain('social_error=');

    expect(User::query()->count())->toBe(0);
});

it('redirects with an error when a provider is not configured', function () {
    config()->set('services.github', [
        'client_id' => null,
        'client_secret' => null,
        'redirect' => '/api/v1/auth/github/callback',
    ]);

    $response = $this->get('/api/v1/auth/github/redirect');

    expect($response->headers->get('Location'))
        ->toContain('social_error=');
});

it('rejects unsupported providers', function () {
    $this->get('/api/v1/auth/facebook/callback')->assertNotFound();
    $this->get('/api/v1/auth/facebook/redirect')->assertNotFound();
});
