<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function fakeCv(string $name = 'resume.pdf', int $kilobytes = 100): UploadedFile
{
    $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    if ($kilobytes > 5) {
        $pdf .= str_repeat(' ', $kilobytes * 1024 - strlen($pdf));
    }

    return UploadedFile::fake()->createWithContent($name, $pdf);
}

beforeEach(function () {
    Storage::fake('r2');
});

it('uploads a valid CV to the private R2 disk', function () {
    $user = authAs();

    $response = $this->postJson('/api/v1/profile/cv', [
        'cv' => fakeCv(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.has_cv', true)
        ->assertJsonPath('data.cv_original_name', 'resume.pdf')
        ->assertJsonMissingPath('data.cv_path');

    $user->refresh();

    expect($user->cv_path)->not->toBeNull()
        ->and($user->cv_path)->toStartWith('cvs/'.$user->id.'/')
        ->and($user->cv_path)->toEndWith('.pdf')
        ->and($user->cv_path)->not->toContain('resume.pdf')
        ->and($user->cv_uploaded_at)->not->toBeNull();

    Storage::disk('r2')->assertExists($user->cv_path);
});

it('rejects a CV with the wrong file type', function () {
    authAs();

    $this->postJson('/api/v1/profile/cv', [
        'cv' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['cv']);
});

it('rejects a file that is not really a PDF', function () {
    authAs();

    $this->postJson('/api/v1/profile/cv', [
        'cv' => UploadedFile::fake()->createWithContent('resume.pdf', 'This is not a PDF.'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['cv']);
});

it('rejects an oversized CV', function () {
    authAs();

    $this->postJson('/api/v1/profile/cv', [
        'cv' => fakeCv('resume.pdf', 6000),
    ])->assertUnprocessable()->assertJsonValidationErrors(['cv']);
});

it('deletes the previous CV when replacing it', function () {
    $user = authAs();

    $this->postJson('/api/v1/profile/cv', ['cv' => fakeCv('first.pdf')])->assertOk();
    $oldPath = $user->fresh()->cv_path;

    $this->postJson('/api/v1/profile/cv', ['cv' => fakeCv('second.pdf')])->assertOk();
    $newPath = $user->fresh()->cv_path;

    expect($newPath)->not->toBe($oldPath);

    Storage::disk('r2')->assertMissing($oldPath);
    Storage::disk('r2')->assertExists($newPath);
});

it('deletes the authenticated user CV', function () {
    $user = authAs();

    $this->postJson('/api/v1/profile/cv', ['cv' => fakeCv()])->assertOk();
    $path = $user->fresh()->cv_path;

    $this->deleteJson('/api/v1/profile/cv')
        ->assertOk()
        ->assertJsonPath('data.has_cv', false);

    Storage::disk('r2')->assertMissing($path);

    $user->refresh();

    expect($user->cv_path)->toBeNull()
        ->and($user->cv_original_name)->toBeNull()
        ->and($user->cv_uploaded_at)->toBeNull();
});

it('returns 404 when the owner has no CV', function () {
    $owner = authAs();

    $this->getJson('/api/v1/users/'.$owner->id.'/cv')->assertNotFound();
});

it('requires authentication to request a CV link', function () {
    $owner = User::factory()->create(['cv_path' => 'cvs/1/test.pdf']);

    $this->getJson('/api/v1/users/'.$owner->id.'/cv')->assertUnauthorized();
});

it('rate limits CV uploads to twenty requests per minute', function () {
    Cache::flush();

    authAs();

    foreach (range(1, 20) as $index) {
        $this->postJson('/api/v1/profile/cv', [
            'cv' => fakeCv(),
        ])->assertOk();
    }

    $this->postJson('/api/v1/profile/cv', [
        'cv' => fakeCv(),
    ])->assertStatus(429);
});
