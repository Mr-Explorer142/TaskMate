<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Requests\UploadCvRequest;
use App\Http\Resources\PublicUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\CvService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return ApiResponse::success(
            new UserResource($this->freshUser($user)),
            'Profile updated.',
        );
    }

    /**
     * Show another user's public profile.
     */
    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(
            new PublicUserResource($this->freshUser($user)),
            'User profile.',
        );
    }

    /**
     * Upload or replace the authenticated user's Cloudinary avatar.
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $avatarService = app(AvatarService::class);
        $previousPublicId = $user->avatar_public_id;

        try {
            $avatar = $avatarService->upload($request->file('avatar'));
        } catch (Throwable) {
            return ApiResponse::error('The avatar could not be uploaded. Please try again.', 502);
        }

        $user->forceFill([
            'avatar_public_id' => $avatar['public_id'],
            'avatar_url' => $avatar['url'],
        ])->save();

        if ($previousPublicId && $previousPublicId !== $avatar['public_id']) {
            try {
                $avatarService->delete($previousPublicId);
            } catch (Throwable) {
                // The new avatar is already saved; a stale asset can be cleaned up later.
            }
        }

        return ApiResponse::success(
            new UserResource($this->freshUser($user)),
            'Avatar uploaded.',
        );
    }

    /**
     * Delete the authenticated user's Cloudinary avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (filled($user->avatar_public_id)) {
            try {
                app(AvatarService::class)->delete($user->avatar_public_id);
            } catch (Throwable) {
                return ApiResponse::error('The avatar could not be deleted. Please try again.', 502);
            }
        }

        $user->forceFill([
            'avatar_public_id' => null,
            'avatar_url' => null,
        ])->save();

        return ApiResponse::success(
            new UserResource($this->freshUser($user)),
            'Avatar deleted.',
        );
    }

    /**
     * Upload or replace the authenticated user's private CV on R2.
     */
    public function uploadCv(UploadCvRequest $request): JsonResponse
    {
        $user = $request->user();
        $cvService = app(CvService::class);
        $previousPath = $user->cv_path;

        try {
            $cv = $cvService->store($request->file('cv'), (int) $user->id);
        } catch (Throwable) {
            return ApiResponse::error('The CV could not be uploaded. Please try again.', 502);
        }

        $user->forceFill([
            'cv_path' => $cv['path'],
            'cv_original_name' => $cv['original_name'],
            'cv_uploaded_at' => now(),
        ])->save();

        if ($previousPath && $previousPath !== $cv['path']) {
            try {
                $cvService->delete($previousPath);
            } catch (Throwable) {
                // The new CV is already saved; a stale object can be cleaned up later.
            }
        }

        return ApiResponse::success(
            new UserResource($this->freshUser($user)),
            'CV uploaded.',
        );
    }

    /**
     * Issue a fresh signed URL for a user's CV.
     */
    public function viewCv(User $user): JsonResponse
    {
        Gate::authorize('viewCv', $user);

        abort_unless(filled($user->cv_path), 404, 'CV not found.');

        try {
            $url = app(CvService::class)->temporaryUrl($user->cv_path);
        } catch (Throwable) {
            return ApiResponse::error('A CV link could not be generated. Please try again.', 502);
        }

        return ApiResponse::success([
            'url' => $url,
            'expires_in' => 600,
        ], 'CV link generated.');
    }

    /**
     * Delete the authenticated user's CV from R2.
     */
    public function deleteCv(Request $request): JsonResponse
    {
        $user = $request->user();

        if (filled($user->cv_path)) {
            try {
                app(CvService::class)->delete($user->cv_path);
            } catch (Throwable) {
                return ApiResponse::error('The CV could not be deleted. Please try again.', 502);
            }
        }

        $user->forceFill([
            'cv_path' => null,
            'cv_original_name' => null,
            'cv_uploaded_at' => null,
        ])->save();

        return ApiResponse::success(
            new UserResource($this->freshUser($user)),
            'CV deleted.',
        );
    }

    /**
     * Load the relationships used by the user resources.
     */
    private function freshUser(User $user): User
    {
        $user = $user->fresh()->load('profileLinks');
        $user->loadAvg('reviewsReceived', 'rating');
        $user->loadCount('reviewsReceived');

        return $user;
    }
}
