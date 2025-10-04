<?php

namespace App\Http\Controllers\API\Events;

use Throwable;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\EventResource;
use App\Http\Controllers\BaseController;

class EventPublicController extends BaseController
{
    public function index(Request $request)
    {
        $query = Event::query()
            ->with(['format', 'topics', 'eventOrganizer'])
            ->where('is_published', true)
            ->where('is_public', true);

        //filter by keyword
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        //filter by price
        if ($request->filled('price')) {
            $price = $request->input('price');
            if ($price === 'free') {
                $query->whereDoesntHave('tickets', function ($ticketQuery) {
                    $ticketQuery->where('price', '>', 0);
                })->whereHas('tickets'); // Memastikan event punya tiket (yang harganya 0)
            } elseif ($price === 'paid') {
                $query->whereHas('tickets', function ($ticketQuery) {
                    $ticketQuery->where('price', '>', 0);
                });
            }
        }

        if ($request->filled('timeframe')) {
            $timeframe = $request->input('timeframe');
            $now = now();

            match ($timeframe) {
                'today' => $query->whereDate('start_date', $now->toDateString()),
                'this_week' => $query->whereBetween('start_date', [$now->startOfWeek(), $now->endOfWeek()]),
                'this_month' => $query->whereBetween('start_date', [$now->startOfMonth(), $now->endOfMonth()]),
                'upcoming' => $query->where('start_date', '>=', $now->toDateString()),
                default => null,
            };
        }

        if ($request->filled('location')) {
            $location = $request->input('location');
            $query->where('location', 'like', '%' . $location . '%');
        }

        if ($request->filled('format')) {
            $query->whereHas('format', fn($q) => $q->where('slug', $request->input('format')));
        }

        if ($request->filled('topic')) {
            $topicSlugs = explode(',', $request->input('topic'));
            $query->whereHas('topics', fn($q) => $q->whereIn('slug', $topicSlugs));
        }

        $events = $query->latest()->paginate($request->input('per_page', 15));

        return EventResource::collection($events);
    }

    public function show(Event $event)
    {
        if ($event->is_published && $event->is_public) {

            $event->load(['facilities', 'tickets', 'format', 'topics', 'eventOrganizer']);

            return $this->sendResponse($event, 'Event retrieved successfully.');
        }
        return $this->sendError('Event not found.', [], 404);
    }


    public function getPopularEvents()
    {
        try {
            $query = Event::query()
                ->where('is_published', true)
                ->where('start_date', '>=', now()->toDateString());

            $query->withCount('bookmarkedByUsers');

            $query->orderBy('bookmarked_by_users_count', 'desc');

            $popularEvents = $query->take(8)->get();

            if ($popularEvents->isEmpty() || $popularEvents->first()->bookmarked_by_users_count === 0) {
                Log::info('No bookmarked events found, falling back to latest events.');
                $popularEvents = Event::query()
                    ->where('is_published', true)
                    ->where('start_date', '>=', now()->toDateString())
                    ->latest()
                    ->take(8)
                    ->get();
            }

            return $this->sendResponse(EventResource::collection($popularEvents), 'Popular events retrieved successfully.');

        } catch (Throwable $e) {
            return $this->sendError('Failed to retrieve popular events.', ['error' => $e->getMessage()], 500);
        }
    }

    public function getTopSellingEvents()
    {
        try {
            $query = Event::query()
                ->where('is_published', true)
                ->where('is_public', true);

            $fallbackQuery = clone $query;

            $topSellingEvents = $query
                ->withCount('tickets')
                ->orderBy('tickets_count', 'desc')
                ->take(8)
                ->get();

            if ($topSellingEvents->isEmpty() || $topSellingEvents->first()->tickets_count === 0) {
                Log::info('No selling events found, falling back to latest events.');
                $topSellingEvents = $fallbackQuery
                    ->latest()
                    ->take(8)
                    ->get();
            }

            return $this->sendResponse(EventResource::collection($topSellingEvents), 'Top selling events retrieved successfully.');

        } catch (Throwable $e) {
            return $this->sendError('Failed to retrieve top selling events.', ['error' => $e->getMessage()], 500);
        }
    }

    public function getLearningEvents()
    {
        try {
            $learningFormatSlugs = [
                'workshop',
                'seminar',
                'training-bootcamp',
                'webinar-online',
                'talkshow-panel-discussion'
            ];

            $events = Event::query()
                ->where('is_published', true)
                ->where('is_public', true)
                ->where('start_date', '>=', now()->toDateString()) // Hanya tampilkan yang akan datang
                ->whereHas('format', function ($query) use ($learningFormatSlugs) {
                    $query->whereIn('slug', $learningFormatSlugs);
                })
                ->with(['format', 'topics', 'eventOrganizer'])
                ->latest()
                ->take(10)
                ->get();

            return $this->sendResponse(EventResource::collection($events), 'Learning events retrieved successfully.');
        } catch (Throwable $e) {
            return $this->sendError('Failed to retrieve learning events.', ['error' => $e->getMessage()], 500);
        }
    }
}
