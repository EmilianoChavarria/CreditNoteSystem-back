<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification as NotificationModel;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $notifications = NotificationModel::query()
            ->where('userId', (int) $authUser->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(ApiResponse::success('Notificaciones', NotificationResource::collection($notifications)));
    }

    public function unread(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $notifications = NotificationModel::query()
            ->where('userId', (int) $authUser->id)
            ->where('isRead', false)
            ->orderByDesc('id')
            ->get();

        return response()->json(ApiResponse::success('Notificaciones no leidas', NotificationResource::collection($notifications)));
    }

    public function markAsRead(Request $request, int $id)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $notification = NotificationModel::query()
            ->where('id', $id)
            ->where('userId', (int) $authUser->id)
            ->first();

        if (!$notification) {
            return response()->json(ApiResponse::error('Notificacion no encontrada', null, 404), 404);
        }

        if (!$notification->isRead) {
            $notification->update([
                'isRead' => true,
                'readAt' => now(),
            ]);
        }

        return response()->json(ApiResponse::success('Notificacion marcada como leida', NotificationResource::make($notification->fresh())));
    }
}