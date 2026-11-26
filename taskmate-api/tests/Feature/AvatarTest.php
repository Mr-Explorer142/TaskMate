<?php

use App\Models\User;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @return array{Cloudinary, UploadApi} */
function mockCloudinary(): array
{
    $api = Mockery::mock(UploadApi::class);
    $cloudinary = Mockery::mock(Cloudinary::class);
    $cloudinary->shouldReceive('uploadApi')->andReturn($api);

    app()->instance(Cloudinary::class, $cloudinary);

    return [$cloudinary, $api];
}

function fakeAvatar(string $name = 'avatar.jpg'): UploadedFile
{
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/wFfC+FDAAAAAElFTkSuQmCC'
    );

    return UploadedFile::fake()->createWithContent($name, $png);
}

it('uploads a valid avatar to Cloudinary', function () {
    [, $api] = mockCloudinary();

    $api->shouldReceive('upload')
        ->once()
        ->with(Mockery::type('string'), [
            'folder' => 'taskmate/avatars',
            'resource_type' => 'image',
            'transformation' => [
                'width' => 400,
                'height' => 400,
                'crop' => 'thumb',
                'gravity' => 'face',
            ],
        ])
        ->andReturn(new ApiResponse([
            'public_id' => 'taskmate/avatars/avatar-1',
            'secure_url' => 'https://res.cloudinary.com/demo/image/upload/avatar-1.jpg',
        ], []));

    $user = authAs();

    $this->postJson('/api/v1/profile/avatar', ['avatar' => fakeAvatar()])
        ->assertOk()
        ->assertJsonPath('data.avatar_url', 'https://res.cloudinary.com/demo/image/upload/avatar-1.jpg')
        ->assertJsonMissingPath('data.avatar_public_id')
        ->assertJsonMissingPath('data.cv_path');

    $user->refresh();

    expect($user->avatar_public_id)->toBe('taskmate/avatars/avatar-1')
        ->and($user->avatar_url)->toBe('https://res.cloudinary.com/demo/image/upload/avatar-1.jpg');
});

it('rejects an avatar with an unsupported format', function () {
    mockCloudinary();

    authAs();

    $this->postJson('/api/v1/profile/avatar', [
        'avatar' => UploadedFile::fake()->create('avatar.gif', 100, 'image/gif'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['avatar']);
});

it('rejects an oversized avatar', function () {
    mockCloudinary();

    authAs();

    $this->postJson('/api/v1/profile/avatar', [
        'avatar' => fakeAvatar()->size(3000),
    ])->assertUnprocessable()->assertJsonValidationErrors(['avatar']);
});

it('deletes the previous Cloudinary avatar when replacing it', function () {
    [, $api] = mockCloudinary();

    $user = authAs();
    $user->forceFill([
        'avatar_public_id' => 'taskmate/avatars/avatar-1',
        'avatar_url' => 'https://res.cloudinary.com/demo/image/upload/avatar-1.jpg',
    ])->save();

    $api->shouldReceive('upload')
        ->once()
        ->andReturn(new ApiResponse([
            'public_id' => 'taskmate/avatars/avatar-2',
            'secure_url' => 'https://res.cloudinary.com/demo/image/upload/avatar-2.jpg',
        ], []));

    $api->shouldReceive('destroy')
        ->once()
        ->with('taskmate/avatars/avatar-1', ['invalidate' => true])
        ->andReturn(new ApiResponse(['result' => 'ok'], []));

    $this->postJson('/api/v1/profile/avatar', ['avatar' => fakeAvatar()])
        ->assertOk()
        ->assertJsonPath('data.avatar_url', 'https://res.cloudinary.com/demo/image/upload/avatar-2.jpg');

    expect($user->fresh()->avatar_public_id)->toBe('taskmate/avatars/avatar-2');
});

it('deletes the avatar and clears both columns', function () {
    [, $api] = mockCloudinary();

    $user = authAs();
    $user->forceFill([
        'avatar_public_id' => 'taskmate/avatars/avatar-1',
        'avatar_url' => 'https://res.cloudinary.com/demo/image/upload/avatar-1.jpg',
    ])->save();

    $api->shouldReceive('destroy')
        ->once()
        ->with('taskmate/avatars/avatar-1', ['invalidate' => true])
        ->andReturn(new ApiResponse(['result' => 'ok'], []));

    $this->deleteJson('/api/v1/profile/avatar')
        ->assertOk()
        ->assertJsonMissingPath('data.avatar_public_id');

    $user->refresh();

    expect($user->avatar_public_id)->toBeNull()
        ->and($user->avatar_url)->toBeNull();
});

it('removes stored files when a user account is deleted', function () {
    Storage::fake('r2');

    [, $api] = mockCloudinary();

    $user = User::factory()->create([
        'avatar_public_id' => 'taskmate/avatars/avatar-1',
        'avatar_url' => 'https://res.cloudinary.com/demo/image/upload/avatar-1.jpg',
    ]);
    $user->forceFill(['cv_path' => 'cvs/private/cv.pdf'])->save();

    Storage::disk('r2')->put($user->cv_path, 'pdf-content');

    $api->shouldReceive('destroy')
        ->once()
        ->with('taskmate/avatars/avatar-1', ['invalidate' => true])
        ->andReturn(new ApiResponse(['result' => 'ok'], []));

    $user->delete();

    expect(User::find($user->id))->toBeNull();

    Storage::disk('r2')->assertMissing($user->cv_path);
});
