<?php

namespace App\Http\Controllers\API\Events;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\Category;

class CategoryController extends BaseController
{
    public function index()
    {
        try {
            $categories = Category::select('id', 'name', 'slug')->get();
            return $this->sendResponse($categories, 'Categories retrieved successfully.');
        } catch (\Throwable $th) {
            return $this->sendError('Failed to retrieve categories.', [], 500);
        }
    }

}
