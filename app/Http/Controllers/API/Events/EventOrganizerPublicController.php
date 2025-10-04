<?php

namespace App\Http\Controllers\API\Events;

use App\Http\Controllers\BaseController;
use Throwable;
use Illuminate\Http\Request;
use App\Models\EventOrganizer;

class EventOrganizerPublicController extends BaseController
{
    public function getTopOrganizers()
    {
        try {
            $topOrganizers = EventOrganizer::query()
                ->withCount('tickets')
                ->orderBy('tickets_count', 'desc')
                ->take(5)
                ->get();

            // Kamu bisa membuat EventOrganizerResource jika perlu
            return $this->sendResponse($topOrganizers, 'Top event organizers retrieved successfully.');
        } catch (Throwable $e) {
            return $this->sendError('Failed to retrieve top organizers.', ['error' => $e->getMessage()], 500);
        }
    }
}
