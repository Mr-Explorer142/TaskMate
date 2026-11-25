<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;

function daySevenAssertErrorEnvelope($response, int $status, bool $expectErrors = false): void
{
    $payload = $response->json();

    $response->assertStatus($status);

    expect($payload)->toHaveKey('message')
        ->and($payload['message'])->toBeString()
        ->and($payload)->not->toHaveKeys(['exception', 'file', 'line', 'trace']);

    if ($expectErrors) {
        expect($payload)->toHaveKey('errors')
            ->and($payload['errors'])->toBeArray();
    }
}

beforeEach(function () {
    config(['app.debug' => false]);
    Cache::flush();
});

it('returns a consistent envelope for 401 responses', function () {
    daySevenAssertErrorEnvelope($this->getJson('/api/v1/tasks'), 401);
});

it('returns a consistent envelope for 403 responses', function () {
    $owner = User::factory()->create();
    $outsider = authAs();
    $task = Task::factory()->open()->create(['uploader_id' => $owner->id]);

    expect($outsider->id)->not->toBe($owner->id);

    daySevenAssertErrorEnvelope($this->patchJson("/api/v1/tasks/{$task->id}/cancel"), 403);
});

it('returns a consistent envelope for 404 responses', function () {
    authAs();

    daySevenAssertErrorEnvelope($this->getJson('/api/v1/tasks/999999'), 404);
});

it('returns a consistent envelope for 405 responses', function () {
    daySevenAssertErrorEnvelope($this->postJson('/api/v1/health'), 405);
});

it('returns a consistent envelope for 409 responses', function () {
    authAs();
    $owner = User::factory()->create();
    $task = Task::factory()->full()->create(['uploader_id' => $owner->id]);

    daySevenAssertErrorEnvelope($this->postJson("/api/v1/tasks/{$task->id}/apply"), 409);
});

it('returns a consistent envelope with errors for validation failures', function () {
    $response = $this->postJson('/api/v1/auth/register', []);

    daySevenAssertErrorEnvelope($response, 422, true);

    $response->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('returns a consistent envelope for 429 responses', function () {
    foreach (range(1, 10) as $index) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ]);
    }

    daySevenAssertErrorEnvelope($this->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'wrong-password',
    ]), 429);
});

it('returns a clean json envelope for csrf token mismatches', function () {
    $request = Request::create('/api/v1/tasks', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

    expect($response->getStatusCode())->toBe(419)
        ->and(json_decode($response->getContent(), true))->toMatchArray([
            'message' => 'CSRF token mismatch.',
        ]);
});

it('does not leak exception details or traces when debug is disabled', function () {
    $request = Request::create('/api/v1/tasks', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = app(ExceptionHandler::class)->render(
        $request,
        new RuntimeException('SECRET_EXCEPTION_DETAIL'),
    );
    $body = $response->getContent();

    expect($response->getStatusCode())->toBe(500)
        ->and($body)->not->toContain('SECRET_EXCEPTION_DETAIL')
        ->and($body)->not->toContain('trace')
        ->and($body)->not->toContain('RuntimeException');
});
