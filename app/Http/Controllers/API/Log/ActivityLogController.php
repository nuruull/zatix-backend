<?php

namespace App\Http\Controllers\API\Log;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends BaseController
{
    public function index() {
        $activities = Activity::latest()->paginate(20);
        return $this->sendResponse(
            $activities,
            'List of activities'
        );
    }
}
