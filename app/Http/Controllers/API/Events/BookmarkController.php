<?php

namespace App\Http\Controllers\API\Events;

use App\Http\Controllers\BaseController;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Throwable;

class BookmarkController extends BaseController
{
    public function index()
    {
        try {
            $user = Auth::user();
            $bookmarkedEvents = $user->bookmarkedEvents()->latest('bookmarked_events.created_at')->paginate(15);

            return $this->sendResponse(EventResource::collection($bookmarkedEvents), 'Bookmarked events retrieved successfully.');
        } catch (Throwable $e) {
            return $this->sendError('Failed to retrieve bookmarks.', ['error' => $e->getMessage()], 500);
        }
    }

    public function toggle(Event $event)
    {
        try {
            $user = Auth::user();

            $result = $user->bookmarkedEvents()->toggle($event->id);

            $status = 'bookmarked';
            $message = 'Event added to bookmarks.';

            if (!empty($result['detached'])) {
                $status = 'not_bookmarked';
                $message = 'Event removed from bookmarks.';
            }

            return $this->sendResponse(['status' => $status], $message);

        } catch (Throwable $e) {
            return $this->sendError('Failed to update bookmark status.', ['error' => $e->getMessage()], 500);
        }
    }
}
