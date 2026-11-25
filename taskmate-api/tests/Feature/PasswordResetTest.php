<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a frontend reset link without revealing whether the account exists', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset@example.com']);

    $response = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => $user->email,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'If the account exists, a reset link has been sent.');

    Notification::assertSentTo($user, ResetPasswordNotification::class);

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();

    $token = null;
    $mailNotification = null;

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use (&$token, &$mailNotification): bool {
            $token = $notification->token;
            $mailNotification = $notification;

            return true;
        },
    );

    $html = (string) $mailNotification->toMail($user)->render();

    expect($html)->toContain(rtrim((string) config('taskmate.frontend_url'), '/').'/reset-password?token=')
        ->and($token)->toBeString();
});

it('returns the same response for an unknown account', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'missing@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'If the account exists, a reset link has been sent.');

    Notification::assertNothingSent();
});

it('resets a password with a valid token and invalidates the token', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset-me@example.com']);

    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();

    $token = null;

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        },
    );

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Password reset successfully.');

    expect(Hash::check('new-password123', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'new-password123',
    ])->assertOk();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create(['email' => 'bad-token@example.com']);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => $user->email,
        'token' => 'not-a-valid-token',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates password reset input', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->postJson('/api/v1/auth/password/reset', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'token', 'password']);
});

it('rate limits login, register and password routes to ten requests per minute', function (string $uri, string $kind) {
    Cache::flush();

    $payloadFor = function () use ($kind): array {
        return match ($kind) {
            'login' => ['email' => 'missing@example.com', 'password' => 'wrong'],
            'register' => [
                'name' => 'Throttled User',
                'email' => fake()->unique()->safeEmail(),
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
            'forgot' => ['email' => 'missing@example.com'],
            default => [
                'email' => 'missing@example.com',
                'token' => 'invalid',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        };
    };

    foreach (range(1, 10) as $index) {
        $response = $this->postJson($uri, $payloadFor());

        expect($response->status())->toBeIn([200, 201, 422]);
    }

    $this->postJson($uri, $payloadFor())->assertStatus(429);
})->with([
    'login' => ['/api/v1/auth/login', 'login'],
    'register' => ['/api/v1/auth/register', 'register'],
    'forgot password' => ['/api/v1/auth/password/forgot', 'forgot'],
    'reset password' => ['/api/v1/auth/password/reset', 'reset'],
]);
