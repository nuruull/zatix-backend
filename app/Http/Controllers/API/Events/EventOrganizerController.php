<?php

namespace App\Http\Controllers\API\Events;

use Illuminate\Http\Request;
use App\Models\EventOrganizer;
use Workbench\App\Models\User;
use App\Traits\ManageFileTrait;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Enum\Type\OrganizerTypeEnum;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewVerificationRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EventOrganizerController extends BaseController
{
    use ManageFileTrait;
    public function index(Request $request)
    {
        $organizers = EventOrganizer::with('eo_owner')->latest()->get();

        return $this->sendResponse(
            $organizers,
            'List of Event Organizers'
        );
    }

    public function show($id)
    {
        try {
            $organizer = EventOrganizer::with('eo_owner')->findOrFail($id);

            return $this->sendResponse(
                $organizer,
                'Event Organizer found'
            );
        } catch (ModelNotFoundException $e) {
            return $this->sendError(
                'Event Organizer not found'
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'organizer_type' => ['required', 'string', Rule::in(['individual', 'company'])],
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'description' => 'nullable|string',
                'email_eo' => 'nullable|email',
                'phone_no_eo' => 'required|string|max:20',
                'address_eo' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed', ['errors' => $validator->errors()]);
                throw new HttpResponseException(
                    $this->sendError(
                        'Validation failed',
                        $validator->errors(),
                        422
                    )
                );
            }

            $user = Auth::user();
            if ($user->eventOrganizer()->exists()) {
                return $this->sendError('You already have an Event Organizer profile.', [], 409);
            }

            DB::beginTransaction();

            $data = $validator->validated();
            $data['eo_owner_id'] = auth()->id();

            // Debug file upload extensively
            if ($request->hasFile('logo')) {
                $data['logo'] = $this->storeFile($request->file('logo'), 'event-organizers/logo');
            }

            $organizer = EventOrganizer::create($data);

            DB::commit();

            return $this->sendResponse(
                $organizer,
                'Event Organizer created successfully',
                201
            );
        } catch (HttpResponseException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create event organizer: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return $this->sendError(
                'Failed to create Event Organizer',
                ['error' => 'Server error occurred: ' . $e->getMessage()],
                500
            );
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $organizer = EventOrganizer::findOrFail($id);

            if (Auth::id() !== $organizer->eo_owner_id) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'description' => 'nullable|string',
                'email_eo' => ['nullable', 'email', Rule::unique('event_organizers')->ignore($organizer->id)],
                'phone_no_eo' => 'nullable|string|max:20',
                'address_eo' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError(
                    'Validation failed',
                    $validator->errors(),
                    422
                );
            }

            DB::beginTransaction();

            $data = $validator->validated();

            if ($request->hasFile('logo')) {
                $data['logo'] = $this->updateFile(
                    $request->file('logo'),
                    'event-organizers/logo',
                    $organizer->logo
                );
            }

            $organizer->update($data);

            DB::commit();

            return $this->sendResponse(
                $organizer,
                'Event Organizer updated successfully'
            );

        } catch (HttpResponseException $e) {
            DB::rollBack();
            throw $e;
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->sendError(
                'Event Organizer not found',
                [],
                404
            );
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Failed to update event organizer: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return $this->sendError(
                'Failed to update Event Organizer',
                ['error' => 'Server error occurred: ' . $e->getMessage()],
                500
            );
        }
    }

    // public function destroy($id)
    // {
    //     try {
    //         $organizer = EventOrganizer::findOrFail($id);
    //         $organizer->delete();

    //         return $this->sendResponse(
    //             null,
    //             'Event Organizer deleted successfully'
    //         );
    //     } catch (ModelNotFoundException $e) {
    //         return $this->sendError(
    //             'Event Organizer not found',
    //             [],
    //             404
    //         );
    //     }
    // }
}
