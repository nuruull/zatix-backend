<?php

namespace App\Http\Controllers\API\Log;

use App\Http\Controllers\BaseController;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends BaseController
{
    //create activity log
    public function index() {
        $activities = Activity::latest()->paginate(20);
        return $this->sendResponse(
            $activities,
            'List of activities'
        );
    }
}
