<?php

namespace App\Http\Controllers\API\Events;

use App\Models\Event;
use Illuminate\Http\Request;
use App\Services\WaitingRoomService;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;

class QueueStatusController extends BaseController
{
    protected $waitingRoom;

    public function __construct(WaitingRoomService $waitingRoom)
    {
        $this->waitingRoom = $waitingRoom;
    }

    public function checkStatus(Event $event)
    {
        $user = Auth::user();

        if ($this->waitingRoom->isAllowedToProceed($event, $user)) {
            return $this->sendResponse(
                ['status' => 'allowed'],
                'You can now proceed to checkout.'
            );
        } else {
            return $this->sendResponse(
                ['status' => 'waiting'],
                'Please continue to wait in the queue.'
            );
        }
    }
}
