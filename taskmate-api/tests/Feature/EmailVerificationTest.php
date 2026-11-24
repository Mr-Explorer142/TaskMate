<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

function captureVerificationCode(User $user): string
{
    $code = null;

    Notification::assertSentTo(
        $user,
        VerifyEmailNotification::class,
        function (VerifyEmailNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        },
    );

    return $code;
}

it('emails a six character mixed verification code on registration', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Alice Example',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::where('email', 'alice@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmailNotification::class);

    $code = captureVerificationCode($user);

    expect($code)->toMatch('/^(?=.*[A-Z])(?=.*\d)[A-Z0-9]{6}$/');
    expect($user->email_verification_code)->not->toBeNull();
    expect($user->email_verification_expires_at?->isFuture())->toBeTrue();
});

it('verifies the email with the emailed code', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Alice Example',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::where('email', 'alice@example.com')->firstOrFail();
    $code = captureVerificationCode($user);

    $response = $this->postJson('/api/v1/auth/email/verify', [
        'email' => 'alice@example.com',
        'code' => strtolower($code),
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Email verified successfully.')
        ->assertJsonPath('data.email_verified', true);

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull();
    expect($user->email_verification_code)->toBeNull();
    expect($user->email_verification_expires_at)->toBeNull();
});

it('rejects an incorrect verification code', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Alice Example',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $response = $this->postJson('/api/v1/auth/email/verify', [
        'email' => 'alice@example.com',
        'code' => 'ZZZZZZ',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('rejects an expired verification code', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Alice Example',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::where('email', 'alice@example.com')->firstOrFail();
    $code = captureVerificationCode($user);

    $user->forceFill(['email_verification_expires_at' => now()->subMinute()])->save();

    $response = $this->postJson('/api/v1/auth/email/verify', [
        'email' => 'alice@example.com',
        'code' => $code,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('resends a fresh verification code to an unverified user', function () {
    Notification::fake();

    $user = authAs(User::factory()->unverified()->create());

    $this->postJson('/api/v1/auth/email/resend')
        ->assertOk()
        ->assertJsonPath('message', 'Verification code sent.');

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('does not resend a code to an already verified user', function () {
    Notification::fake();

    $user = authAs();

    $this->postJson('/api/v1/auth/email/resend')
        ->assertOk()
        ->assertJsonPath('message', 'Your email is already verified.');

    Notification::assertNothingSentTo($user);
});

it('requires authentication to resend a verification code', function () {
    $this->postJson('/api/v1/auth/email/resend')
        ->assertUnauthorized();
});
