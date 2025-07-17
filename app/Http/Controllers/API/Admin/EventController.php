<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EventController extends BaseController
{
    public function index()
    {
        $events = Event::query()
            ->where('is_published', true) // Sesuai US4: hanya yang sudah dipublikasi/aktif
            ->with('eventOrganizer:id,name') // Eager load untuk efisiensi
            ->latest()
            ->paginate(20);

        return $this->sendResponse($events,'Published events retrieved successfully for admin.');
    }
}
