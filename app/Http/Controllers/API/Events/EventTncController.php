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

        $hasAcceptedGenerally = auth()->user()->tncStatuses()
            ->where('tnc_id', $eventTnc->id)
            ->whereNull('event_id') // Hanya cari persetujuan yang belum terikat ke event
            ->exists();

        // Struktur response yang lebih baik
        $response = [
            'tnc' => $eventTnc,
            'already_accepted' => $hasAcceptedGenerally,
        ];

        return $this->sendResponse(
            $response,
            'Terms and conditions data retrieved successfully'
        );
    }

    public function agree(Request $request)
    {
        $eventTnc = TermAndCon::where('type', TncTypeEnum::EVENT->value)->latest()->first();

        if (!$eventTnc) {
            return $this->sendError(
                'TNC event not found.'
            );
        }

        if (auth()->user()->tncStatuses()->where('tnc_id', $eventTnc->id)->exists()) {
            return $this->sendResponse([], 'TNC already accepted and ready to use.');
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
