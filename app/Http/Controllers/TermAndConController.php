<?php

namespace App\Http\Controllers;

use App\Models\TermAndCon;
use Illuminate\Http\Request;

class TermAndConController extends Controller
{
    public function index()
    {
        $terms = TermAndCon::latest()->get();
        return response()->json($terms);
    }

    public function latestByType($type)
    {
        $term = TermAndCon::where('type', $type)->latest()->first();

        if (!$term) {
            return response()->json(['message' => 'TnC not found for type: ' . $type], 404);
        }

        return response()->json($term);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:general,event',
            'content' => 'required|string|min:10',
        ]);

        $term = TermAndCon::create($validated);

        return response()->json([
            'message' => 'TnC created successfully',
            'data' => $term,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $term = TermAndCon::findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|in:general,event',
            'content' => 'sometimes|string|min:10',
        ]);

        $term->update($validated);

        return response()->json([
            'message' => 'TnC updated successfully',
            'data' => $term,
        ]);
    }

    public function destroy($id)
    {
        $term = TermAndCon::findOrFail($id);
        $term->delete();

        return response()->json(['message' => 'TnC deleted successfully']);
    }
}
