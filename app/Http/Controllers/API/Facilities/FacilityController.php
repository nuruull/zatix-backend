<?php

namespace App\Http\Controllers\API\Facilities;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacilityController extends Controller
{
    public function index() {
        $facilities = Facility::all();
        return response()->json($facilities);
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'required|string|max:100',
        ]);

        try {
            $facility = Facility::create($request->only('name', 'icon'));
            return response()->json($facility, 201);
        } catch (\Exception $e) {
            Log::error('Error creating facility: ', $e->getMessage());
            return response()->json(['message' => 'Failed to create facility'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $facility = Facility::find($id);

            if (!$facility) {
                return response()->json(['message' => 'Facility not found'], 404);
            }

            $request->validate([
                'name' => 'string|max:100',
                'icon' => 'string|max:100',
            ]);

            $facility->update($request->only('name', 'icon'));

            return response()->json($facility);
        } catch (\Exception $e) {
            Log::error("Error updating facility {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update facility'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $facility = Facility::find($id);

            if (!$facility) {
                return response()->json(['message' => 'Facility not found'], 404);
            }

            $facility->delete();

            return response()->json(['message' => 'Facility deleted successfully']);
        } catch (\Exception $e) {
            Log::error("Error deleting facility {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete facility'], 500);
        }
    }
}
