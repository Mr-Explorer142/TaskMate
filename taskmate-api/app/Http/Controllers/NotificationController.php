<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * List the authenticated user's notifications, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()->notifications()
            ->when(
                $request->boolean('filter.unread'),
                fn ($query) => $query->whereNull('read_at'),
            )
            ->latest('created_at')
            ->paginate(15);

        return NotificationResource::collection($notifications);
    }

    /**
     * Count the authenticated user's unread notifications.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one of the authenticated user's notifications as read.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return ApiResponse::success(new NotificationResource($notification), 'Notification marked as read.');
    }

    /**
     * Mark all of the authenticated user's notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(['updated' => $updated], 'All notifications marked as read.');
    }
}
