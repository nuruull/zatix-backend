<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;

class DocumentController extends BaseController
{
    public function store(Request $request) {
        try {
            $validated = Validator::make(request()->all(), [
                
            ]);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
