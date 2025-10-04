<?php

namespace App\Http\Controllers\API\Events;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\Format;

class FormatController extends BaseController
{
    public function index()
    {
        try {
            $formats = Format::select('id', 'name', 'slug')->get();
            return $this->sendResponse($formats, 'Formats retrieved successfully.');
        } catch (\Throwable $th) {
            return $this->sendError('Failed to retrieve formats.', [], 500);
        }
    }

}
