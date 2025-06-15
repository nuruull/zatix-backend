<?php

namespace App\Http\Controllers\API\General;

use App\Http\Controllers\BaseController;

class NotificationController extends BaseController
{
    public function index(){
        $user = auth()->user();
        $data = [
            'all' => $user->notifications,
            'unread' => $user->unreadNotifications,
        ];

        return $this->sendResponse(
            $data,
            'Notification data retrieved successfully',
        );
    }

    public function markAsRead($id){
        $user = auth()->user();
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return $this->sendError(
                'Notification not found.',
            );
        }

        $notification->markAsRead();

        return $this->sendResponse(
            [],
            'Notification marked as read',
        );
    }

}
