<?php

namespace App\Http\Controllers\API\Events;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\EventResource;
use App\Http\Controllers\BaseController;

class RecommendationController extends BaseController
{
    public function getEventRecommendations()
    {
        try {
            $user = Auth::user();
            $bookmarkedEventIds = $user->bookmarkedEvents()->pluck('events.id');

            $purchasedEventIds = DB::table('orders')
                ->where('user_id', $user->id)
                ->where('status', 'paid')
                ->distinct()
                ->pluck('event_id');

            $interactedEventIds = $bookmarkedEventIds->merge($purchasedEventIds)->unique();

            $recommendations = collect();

            if ($interactedEventIds->isNotEmpty()) {
                $favoriteCategoryIds = Event::whereIn('id', $interactedEventIds)
                    ->pluck('category_id')
                    ->unique()
                    ->filter();

                if ($favoriteCategoryIds->isNotEmpty()) {
                    $recommendations = Event::query()
                        ->whereIn('category_id', $favoriteCategoryIds)
                        ->whereNotIn('id', $interactedEventIds)
                        ->where('is_published', true)
                        ->where('start_date', '>=', now()->toDateString())
                        ->with('category')
                        ->inRandomOrder()
                        ->take(10)
                        ->get();
                }
            }

            if ($recommendations->isEmpty()) {
                $recommendations = Event::query()
                    ->where('is_published', true)
                    ->where('start_date', '>=', now()->toDateString())
                    ->whereNotIn('id', $interactedEventIds)
                    ->orderBy('created_at', 'desc')
                    ->take(10)
                    ->get();
            }

            return $this->sendResponse(EventResource::collection($recommendations), 'Event recommendations retrieved successfully.');

        } catch (\Throwable $th) {
            return $this->sendError('Failed to retrieve recommendations.', ['error' => $th->getMessage()], 500);
        }
    }
}
