<?php

namespace App\Http\Controllers\API;

use App\Models\TermAndCon;
use Illuminate\Http\Request;
use App\Enum\Status\TncTypeEnum;
use Carbon\Exceptions\Exception;
use Illuminate\Validation\Rules\Enum;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class TermAndConController extends BaseController
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
        try {
            $validated = $request->validate([
                'type' => ['required', new Enum(TncTypeEnum::class)],
                'content' => 'required|string|min:10',
            ]);

            $term = TermAndCon::create($validated);

            return $this->sendResponse(
                [
                    'data' => $term,
                ],
                'TnC created successfully'
            );
        } catch (ValidationException $exception) {
            return $this->sendError(
                'Validation Exception',
                $exception->getMessage(),
                202
            );
        } catch (Exception $exception) {
            return $this->sendError(
                'Failed',
                $exception->getMessage(),
                202
            );
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $term = TermAndCon::findOrFail($id);

            $validated = $request->validate([
                'type' => ['sometimes', new Enum(TncTypeEnum::class)],
                'content' => 'sometimes|string|min:10',
            ]);

            $term->update($validated);

            return $this->sendResponse(
                [
                    'data' => $term,
                ],
                'TnC updated successfully'
            );
        } catch (ValidationException $exception) {
            return $this->sendError(
                'Validation Exception',
                $exception->getMessage(),
                202
            );
        } catch (Exception $exception) {
            return $this->sendError(
                'Failed',
                $exception->getMessage(),
                202
            );
        }
    }

    public function destroy($id)
    {
        try {
            $term = TermAndCon::findOrFail($id);
            $term->delete();

            return $this->sendResponse(
                null,
                'TnC deleted successfully'
            );

        } catch (ModelNotFoundException $e) {
            return $this->sendError('TnC not found', [], 404);
        } catch (\Throwable $th) {
            return $this->sendError('Failed to delete TnC', $th->getMessage(), 500);
        }
    }
}
