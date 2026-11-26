<?php

use App\Models\Application;
use App\Models\Task;
use App\Models\User;
use App\Services\CvService;

function mockCvLink(): void
{
    $service = Mockery::mock(CvService::class);
    $service->shouldReceive('temporaryUrl')->andReturn('https://storage.example.test/cv-link');
    $service->shouldNotReceive('store');
    $service->shouldNotReceive('delete');

    app()->instance(CvService::class, $service);
}

function userWithCv(): User
{
    return User::factory()->create([
        'cv_path' => 'cvs/private/cv.pdf',
        'cv_original_name' => 'cv.pdf',
    ]);
}

it('lets the CV owner request a signed link', function () {
    $owner = authAs();
    $owner->forceFill(['cv_path' => 'cvs/'.$owner->id.'/cv.pdf'])->save();

    mockCvLink();

    $this->getJson('/api/v1/users/'.$owner->id.'/cv')
        ->assertOk()
        ->assertJsonPath('data.url', 'https://storage.example.test/cv-link')
        ->assertJsonPath('data.expires_in', 600)
        ->assertJsonMissingPath('data.cv_path');
});

it('lets a task poster view the CV of an applicant', function () {
    $applicant = userWithCv();
    $viewer = authAs();

    $task = Task::factory()->open()->create(['uploader_id' => $viewer->id]);
    Application::factory()->create([
        'task_id' => $task->id,
        'user_id' => $applicant->id,
    ]);

    mockCvLink();

    $this->getJson('/api/v1/users/'.$applicant->id.'/cv')->assertOk();
});

it('forbids unrelated users from requesting a CV link', function () {
    $owner = userWithCv();

    authAs();

    mockCvLink();

    $this->getJson('/api/v1/users/'.$owner->id.'/cv')->assertForbidden();
});

it('returns 404 when the authorized user has no CV', function () {
    $owner = authAs();

    mockCvLink();

    $this->getJson('/api/v1/users/'.$owner->id.'/cv')->assertNotFound();
});

it('authorizes before checking whether a CV exists', function () {
    $owner = User::factory()->create();

    authAs();

    mockCvLink();

    $this->getJson('/api/v1/users/'.$owner->id.'/cv')->assertForbidden();
});
