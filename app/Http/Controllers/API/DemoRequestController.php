<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoRequestController extends Controller
{
    //Super Admin
    public function index()
    {
        try {
            $demoRequests = DemoRequest::with('user')->get();

            return response()->json($demoRequests);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch demo requests', 'error' => $e->getMessage()], 500);
        }
    }

    //Eo Owner
    public function store(Request $request) {
        try {
            $request->validate([
                'eo_name' => 'required|string|max:255',
                'email' => 'required|email',
                'eo_description' => 'required|string',
                'event_name' => 'required|string|max:255',
                'event_description' => 'required|string',
                'audience_target' => 'required|string|max:255',
            ]);

            $demoRequest = DemoRequest::create([
                'user_id' => Auth::id(),
                'eo_name' => $request->eo_name,
                'email' => $request->email,
                'eo_description' => $request->eo_description,
                'event_name' => $request->event_name,
                'event_description' => $request->event_description,
                'audience_target' => $request->audience_target,
                'status' => 'pending',
            ]);

            return response()->json(['message' => 'Demo request submitted successfully', 'data' => $demoRequest], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to submit demo request', 'error' => $e->getMessage()], 500);
        }
    }

    // Super Admin
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:approved,rejected',
                'note' => 'nullable|string',
            ]);

            $demoRequest = DemoRequest::findOrFail($id);
            $demoRequest->status = $request->status;
            $demoRequest->note = $request->note;
            $demoRequest->save();

            return response()->json(['message' => 'Demo request updated successfully', 'data' => $demoRequest]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update demo request', 'error' => $e->getMessage()], 500);
        }
    }
}
