<?php

namespace App\Http\Controllers\API\General;

use App\Models\Carousel;
use Illuminate\Http\Request;
use App\Traits\ManageFileTrait;
use Illuminate\Support\Facades\Log;
use App\Enum\Type\LinkTargetTypeEnum;
use Illuminate\Validation\Rules\Enum;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;

class CarouselController extends BaseController
{
    use ManageFileTrait;

    //PUBLIC
    public function index()
    {
        try {
            $carousels = Carousel::where('is_active', true)->get();

            if ($carousels->isEmpty()) {
                return $this->sendError(
                    'No carousel data found',
                );
            }

            return $this->sendResponse(
                $carousels,
                'Carousel data retrieved successfully'
            );
        } catch (\Throwable $th) {
            Log::error('Error fetching carousels: ' . $th->getMessage());
            return $this->sendError(
                'An error occurred while fetching carousel data.',
                $th->getMessage(),
                500
            );
        }
    }

    public function getCarouselList()
    {
        try {
            $carousels = Carousel::all();

            if ($carousels->isEmpty()) {
                return $this->sendError(
                    'No carousel data found',
                );
            }

            return $this->sendResponse(
                $carousels,
                'Carousel data retrieved successfully'
            );
        } catch (\Throwable $th) {
            Log::error('Error fetching carousels: ' . $th->getMessage());
            return $this->sendError(
                'An error occurred while fetching carousel data.',
                $th->getMessage(),
                500
            );
        }
    }

    public function show($id)
    {
        try {
            $carousel = Carousel::find($id);

            if (!$carousel) {
                return $this->sendError(
                    'Carousel item not found.',
                );
            }

            return $this->sendResponse(
                $carousel,
                'Carousel item retrieved successfully.',
                200
            );

        } catch (\Throwable $th) {
            Log::error("Error fetching carousel item with id {$id}: " . $th->getMessage());

            return $this->sendError(
                'An error occurred while fetching the carousel item.',
                $th->getMessage(),
                500
            );
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:10240',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'link_url' => 'nullable|url|max:255',
            'link_target' => ['nullable', new Enum(LinkTargetTypeEnum::class)],
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);


        if ($validator->fails()) {
            return $this->sendError(
                'Validation errors',
                $validator->errors(),
                422
            );
        }

        $imagePath = null;

        try {
            if ($request->hasFile('image')) {
                $imagePath = $this->storeFile($request->file('image'), 'carousels');
            }

            $carousel = Carousel::create([
                'image' => $imagePath,
                'title' => $request->input('title'),
                'caption' => $request->input('caption'),
                'link_url' => $request->input('link_url'),
                'link_target' => $request->input('link_target', LinkTargetTypeEnum::SELF->value),
                'order' => $request->input('order', 0),
                'is_active' => $request->input('is_active', true),
            ]);

            return $this->sendResponse(
                $carousel,
                'Carousel item created successfully',
                201
            );

        } catch (\Throwable $th) {
            Log::error('Error creating carousel: ' . $th->getMessage());
            if ($imagePath) {
                $this->deleteFile($imagePath);
            }

            return $this->sendError(
                'An error occurred while creating the carousel item.',
                $th->getMessage(),
                500
            );
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'nullable|image|max:10240',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'link_url' => 'nullable|url|max:255',
            'link_target' => ['nullable', new Enum(LinkTargetTypeEnum::class)],
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation errors',
                $validator->errors(),
                422
            );
        }

        $newImagePath = null;
        try {
            $carousel = Carousel::find($id);

            if (!$carousel) {
                return $this->sendError(
                    'Carousel item not found.',
                );
            }

            $dataToUpdate = $request->except('image');

            if ($request->hasFile('image')) {
                $newImagePath = $this->updateFile($request->file('image'), 'carousels', $carousel->image);
                $dataToUpdate['image'] = $newImagePath;
            }

            $carousel->update($dataToUpdate);

            return $this->sendResponse(
                $carousel,
                'Carousel item updated successfully',
                200
            );

        } catch (\Throwable $th) {
            Log::error("Error updating carousel item with id {$id}: " . $th->getMessage() . ' Trace: ' . $th->getTraceAsString());
            if ($newImagePath) {
                $this->deleteFile($newImagePath);
            }

            return $this->sendError(
                'An error occurred while updating the carousel item.',
                $th->getMessage(),
                500
            );
        }
    }

    public function destroy($id)
    {
        try {
            $carousel = Carousel::find($id);

            if (!$carousel) {
                return $this->sendError(
                    'Carousel item not found.',
                );
            }

            $oldImagePath = $carousel->image;
            $carousel->delete();

            if ($oldImagePath) {
                $this->deleteFile($oldImagePath);
            }

            return $this->sendResponse(
                $carousel,
                'Carousel item deleted successfully',
                200
            );


        } catch (\Throwable $th) {
            Log::error("Error deleting carousel item with id {$id}: " . $th->getMessage());
            return $this->sendError(
                'An error occurred while deleting the carousel item.',
                $th->getMessage(),
                500
            );
        }
    }
}
