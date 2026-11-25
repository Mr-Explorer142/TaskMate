<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function fakeCv(string $name = 'resume.pdf', int $kilobytes = 100): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

it('uploads a valid CV to the private disk', function () {
    Storage::fake('local');

    $user = authAs();

    $response = $this->post('/api/v1/profile/cv', [
        'cv' => fakeCv(),
    ], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('data.has_cv', true)
        ->assertJsonPath('data.cv_original_name', 'resume.pdf');

    $user->refresh();

    expect($user->cv_path)->not->toBeNull()
        ->and($user->cv_path)->toStartWith('cvs/'.$user->id.'/')
        ->and($user->cv_uploaded_at)->not->toBeNull();

    Storage::disk('local')->assertExists($user->cv_path);
});

it('rejects a CV with the wrong file type', function () {
    Storage::fake('local');

    authAs();

    $response = $this->post('/api/v1/profile/cv', [
        'cv' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
    ], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['cv']);
});

it('rejects an oversized CV', function () {
    Storage::fake('local');

    authAs();

    $response = $this->post('/api/v1/profile/cv', [
        'cv' => fakeCv('resume.pdf', 6000),
    ], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['cv']);
});

it('deletes the previous CV when replacing it', function () {
    Storage::fake('local');

    $user = authAs();

    $this->post('/api/v1/profile/cv', ['cv' => fakeCv('first.pdf')], ['Accept' => 'application/json'])
        ->assertOk();

    $oldPath = $user->fresh()->cv_path;

    $this->post('/api/v1/profile/cv', ['cv' => fakeCv('second.pdf')], ['Accept' => 'application/json'])
        ->assertOk();

    $newPath = $user->fresh()->cv_path;

    expect($newPath)->not->toBe($oldPath);

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($newPath);
});

it('lets another authenticated user download a CV', function () {
    Storage::fake('local');

    $owner = authAs();
    $this->post('/api/v1/profile/cv', ['cv' => fakeCv('owner-cv.pdf')], ['Accept' => 'application/json'])
        ->assertOk();

    authAs();

    $response = $this->get('/api/v1/users/'.$owner->id.'/cv', ['Accept' => 'application/json']);

    $response->assertOk()->assertDownload('owner-cv.pdf');
});

it('returns 404 when a user has no CV', function () {
    $owner = User::factory()->create();

    authAs();

    $this->getJson('/api/v1/users/'.$owner->id.'/cv')->assertNotFound();
});

it('requires authentication to download a CV', function () {
    $owner = User::factory()->create(['cv_path' => 'cvs/1/test.pdf']);

    $this->get('/api/v1/users/'.$owner->id.'/cv', ['Accept' => 'application/json'])
        ->assertUnauthorized();
});

it('deletes the authenticated user CV', function () {
    Storage::fake('local');

    $user = authAs();
    $this->post('/api/v1/profile/cv', ['cv' => fakeCv()], ['Accept' => 'application/json'])
        ->assertOk();

    $path = $user->fresh()->cv_path;

    $this->deleteJson('/api/v1/profile/cv')
        ->assertOk()
        ->assertJsonPath('data.has_cv', false);

    Storage::disk('local')->assertMissing($path);

    $user->refresh();

    expect($user->cv_path)->toBeNull()
        ->and($user->cv_original_name)->toBeNull()
        ->and($user->cv_uploaded_at)->toBeNull();
});

it('rate limits CV uploads to twenty requests per minute', function () {
    Storage::fake('local');
    Cache::flush();

    authAs();

    foreach (range(1, 20) as $index) {
        $this->post('/api/v1/profile/cv', [
            'cv' => fakeCv(),
        ], ['Accept' => 'application/json'])->assertOk();
    }

    $this->post('/api/v1/profile/cv', [
        'cv' => fakeCv(),
    ], ['Accept' => 'application/json'])->assertStatus(429);
});
