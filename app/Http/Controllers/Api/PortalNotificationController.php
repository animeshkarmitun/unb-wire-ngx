<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ClientUser $user */
        $user = $request->attributes->get('clientUser');
        $filter = $request->query('filter', 'all');

        $unreadCount = $user->unreadNotifications()->count();

        $query = $filter === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        $paginated = $query->latest()->paginate(20);

        $items = $paginated->getCollection()->map(function ($notif) {
            $data = is_string($notif->data) ? json_decode($notif->data, true) : $notif->data;

            return [
                'id' => (string) $notif->id,
                'event' => $data['event'] ?? 'notification',
                'title' => $data['title'] ?? 'Notification',
                'message' => $data['message'] ?? '',
                'story_id' => $data['story_id'] ?? null,
                'public_id' => $data['public_id'] ?? null,
                'category' => $data['category'] ?? null,
                'urgency' => $data['urgency'] ?? 'regular',
                'read_at' => $notif->read_at?->toISOString(),
                'created_at' => $notif->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'data' => $items,
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        /** @var ClientUser $user */
        $user = $request->attributes->get('clientUser');

        $notification = $user->notifications()->where('id', $id)->first();
        if (! $notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var ClientUser $user */
        $user = $request->attributes->get('clientUser');

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var ClientUser $user */
        $user = $request->attributes->get('clientUser');

        $notification = $user->notifications()->where('id', $id)->first();
        if (! $notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        /** @var ClientUser $user */
        $user = $request->attributes->get('clientUser');
        $scope = $request->query('scope', 'all');

        if ($scope === 'read') {
            $user->readNotifications()->delete();
        } else {
            $user->notifications()->delete();
        }

        return response()->json([
            'message' => 'Notifications cleared',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
}
