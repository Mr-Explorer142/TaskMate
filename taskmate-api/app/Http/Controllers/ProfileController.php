<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadCvRequest;
use App\Http\Resources\PublicUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        $user = $user->fresh()->load('profileLinks');
        $user->loadAvg('reviewsReceived', 'rating');

        return ApiResponse::success(new UserResource($user), 'Profile updated.');
    }

    /**
     * Show another user's public profile.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('profileLinks');
        $user->loadAvg('reviewsReceived', 'rating');

        return ApiResponse::success(new PublicUserResource($user), 'User profile.');
    }

    /**
     * Upload or replace the authenticated user's CV.
     */
    public function uploadCv(UploadCvRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('cv');

        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs(
            'cvs/'.$user->id,
            Str::random(40).'.'.$extension,
            'local',
        );

        $previousPath = $user->cv_path;

        $user->update([
            'cv_path' => $path,
            'cv_original_name' => $file->getClientOriginalName(),
            'cv_uploaded_at' => now(),
        ]);

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        $user = $user->fresh()->load('profileLinks');

        return ApiResponse::success(new UserResource($user), 'CV uploaded.');
    }

    /**
     * Download or stream a user's CV for authenticated users.
     */
    public function downloadCv(User $user): StreamedResponse
    {
        abort_unless(filled($user->cv_path), 404, 'CV not found.');

        $disk = Storage::disk('local');

        abort_unless($disk->exists($user->cv_path), 404, 'CV not found.');

        $filename = $user->cv_original_name ?: basename($user->cv_path);

        return $disk->download($user->cv_path, $filename);
    }

    /**
     * Delete the authenticated user's CV.
     */
    public function deleteCv(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->cv_path) {
            Storage::disk('local')->delete($user->cv_path);
        }

        $user->update([
            'cv_path' => null,
            'cv_original_name' => null,
            'cv_uploaded_at' => null,
        ]);

        $user = $user->fresh()->load('profileLinks');

        return ApiResponse::success(new UserResource($user), 'CV deleted.');
    }
}
