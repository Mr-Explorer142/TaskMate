<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderProfileLinksRequest;
use App\Http\Requests\StoreProfileLinkRequest;
use App\Http\Requests\UpdateProfileLinkRequest;
use App\Http\Resources\ProfileLinkResource;
use App\Models\ProfileLink;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProfileLinkController extends Controller
{
    /**
     * List the authenticated user's links.
     */
    public function index(Request $request): JsonResponse
    {
        $links = $request->user()
            ->profileLinks()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            ProfileLinkResource::collection($links),
            'Profile links.',
        );
    }

    /**
     * Add a link to the authenticated user's profile.
     */
    public function store(StoreProfileLinkRequest $request): JsonResponse
    {
        $user = $request->user();
        $maxSortOrder = $user->profileLinks()->max('sort_order');

        $link = $user->profileLinks()->create([
            ...$request->validated(),
            'sort_order' => $maxSortOrder === null ? 0 : ((int) $maxSortOrder) + 1,
        ]);

        return ApiResponse::success(new ProfileLinkResource($link), 'Profile link added.', 201);
    }

    /**
     * Update one of the authenticated user's links.
     */
    public function update(UpdateProfileLinkRequest $request, ProfileLink $link): JsonResponse
    {
        Gate::authorize('update', $link);

        $link->update($request->validated());

        return ApiResponse::success(new ProfileLinkResource($link->fresh()), 'Profile link updated.');
    }

    /**
     * Delete one of the authenticated user's links.
     */
    public function destroy(ProfileLink $link): JsonResponse
    {
        Gate::authorize('delete', $link);

        $link->delete();

        return ApiResponse::success(null, 'Profile link deleted.');
    }

    /**
     * Reorder the authenticated user's links.
     */
    public function reorder(ReorderProfileLinksRequest $request): JsonResponse
    {
        $user = $request->user();
        $ids = $request->validated('ids');

        DB::transaction(function () use ($user, $ids): void {
            foreach ($ids as $index => $id) {
                ProfileLink::query()
                    ->where('user_id', $user->id)
                    ->whereKey($id)
                    ->update(['sort_order' => $index]);
            }
        });

        $links = $user->profileLinks()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            ProfileLinkResource::collection($links),
            'Profile links reordered.',
        );
    }
}
