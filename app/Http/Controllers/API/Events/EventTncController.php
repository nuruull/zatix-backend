<?php

namespace App\Http\Controllers\API\Events;

use App\Enum\Type\TncTypeEnum;
use App\Http\Controllers\BaseController;
use App\Models\TermAndCon;
use App\Models\TncStatus;
use Illuminate\Http\Request;

class EventTncController extends BaseController
{
    public function show()
    {
        $eventTnc = TermAndCon::where('type', TncTypeEnum::EVENT->value)->latest()->first();

        if (!$eventTnc) {
            return $this->sendError(
                'TNC event not found.'
            );
        }

        return $this->sendResponse(
            [
                $eventTnc,
                'already_accepted' => auth()->user()->tncStatuses()
                    ->where('tnc_id', $eventTnc->id)
                    ->exists()
            ],
            'Terms and conditions data retrieved successfully'
        );
    }

    public function agree(Request $request) {
        $eventTnc = TermAndCon::where('type', TncTypeEnum::EVENT->value)->latest()->first();

        if (!$eventTnc) {
            return $this->sendError(
                'TNC event not found.'
            );
        }

        if (auth()->user()->tncStatuses()->where('tnc_id', $eventTnc->id)->exists()) {
            return $this->sendError(
                'You have agreed to this TNC',
                [],
                409
            );
        }

        TncStatus::create([
            'tnc_id' => $eventTnc->id,
            'user_id' => auth()->id(),
            'accepted_at' => now()
        ]);

        return $this->sendResponse(
            [],
            'TNC event successfully approved'
        );
    }
}
