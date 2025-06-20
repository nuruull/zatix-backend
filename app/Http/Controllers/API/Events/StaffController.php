<?php

namespace App\Http\Controllers\API\Events;

use Throwable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StaffController extends BaseController
{
    public function store(Request $request)
    {
        try {
            $eoOwner = Auth::user();
            $eventOrganizer = $eoOwner->eventOrganizer;

            if (!$eventOrganizer) {
                DB::rollback();
                return $this->sendError(
                    "You must have an Event Organizer profile to add staff.",
                    [],
                    403
                );
            }

            Log::info('Staff creation request received', [
                'eo_owner_id' => $eoOwner->id,
                'event_organizer_id' => $eventOrganizer->id,
                'request_data' => $request->all(),
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => ['required', 'string', Rule::in(['finance', 'crew', 'kasir'])]
            ]);
            

            if ($validator->fails()) {
                Log::error('Staff creation validation failed', ['errors' => $validator->errors()]);
                throw new HttpResponseException(
                    $this->sendError('Validation failed', $validator->errors(), 422)
                );
            }

            $validated = $validator->validated();

            $staffData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ];

            $newStaff = User::create($staffData);

            $newStaff->guard(['api'])->assignRole($validated['role']);
            // dd($newStaff->id);

            $eventOrganizer->members()->attach($newStaff->id);

            DB::commit();

            return $this->sendResponse(
                [
                    'name' => $newStaff->name,
                    'email' => $newStaff->email,
                    'role' => $newStaff->role,
                ],
                'Staff created successfully',
                201
            );
        } catch (HttpResponseException $e) {
            DB::rollBack();
            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create staff member: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return $this->sendError(
                'Failed to create staff member.',
                ['error' => 'An unexpected server error occurred.'],
                500
            );
        }
    }
}
