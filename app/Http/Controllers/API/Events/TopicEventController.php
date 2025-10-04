<?php

namespace App\Http\Controllers\API\Events;

use App\Http\Controllers\BaseController;
use App\Models\Topic;

class TopicEventController extends BaseController
{
    public function index()
    {
        try {
            $topics = Topic::select('id', 'name', 'slug')->get();
            return $this->sendResponse($topics, 'Topics retrieved successfully.');
        } catch (\Throwable $th) {
            return $this->sendError('Failed to retrieve topics.', [], 500);
        }
    }
}
