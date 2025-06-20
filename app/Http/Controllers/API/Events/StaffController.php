<?php

namespace App\Http\Controllers\API\Events;

use Throwable;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StaffController extends BaseController
{
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

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

            $allowedRoles = Role::where('guard_name', 'api')
                ->whereIn('name', ['finance', 'crew', 'cashier'])
                ->pluck('name')
                ->all();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'role' => [
                    'required',
                    'string',
                    Rule::in($allowedRoles)
                ]
            ]);


            if ($validator->fails()) {
                Log::error('Staff creation validation failed', ['errors' => $validator->errors()]);
                throw new HttpResponseException(
                    $this->sendError('Validation failed', $validator->errors(), 422)
                );
            }

            $validated = $validator->validated();

            $temporaryPassword = Str::random(40);

            $staffData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'email_verified_at' => now(),
            ];

            $newStaff = User::create($staffData);

            $role = Role::where('name', $validated['role'])
                ->where('guard_name', 'api')
                ->first();

            if (!$role) {
                DB::rollback();
                Log::error('Role not found with api guard', [
                    'role_name' => $validated['role'],
                    'available_roles' => $allowedRoles
                ]);
                return $this->sendError(
                    'Role configuration error.',
                    ['error' => 'The specified role is not properly configured for API guard.'],
                    500
                );
            }

            $newStaff->assignRole($role);

            // Verifikasi guard setelah assign role
            $assignedRole = $newStaff->roles()
                ->where('name', $validated['role'])
                ->first();

            if (!$assignedRole || $assignedRole->guard_name !== 'api') {
                DB::rollback();
                Log::error('Role assignment failed - incorrect guard', [
                    'user_id' => $newStaff->id,
                    'expected_guard' => 'api',
                    'actual_guard' => $assignedRole ? $assignedRole->guard_name : 'null',
                    'role_name' => $validated['role']
                ]);
                return $this->sendError(
                    'Role assignment failed.',
                    ['error' => 'Role was not assigned with the correct guard permissions.'],
                    500
                );
            }

            $eventOrganizer->members()->attach($newStaff->id);

            Password::broker()->sendResetLink(
                ['email' => $newStaff->email]
            );

            DB::commit();

            return $this->sendResponse(
                [
                    'name' => $newStaff->name,
                    'email' => $newStaff->email,
                    'role' => $newStaff->getRoleNames()->first(),
                ],
                'Staff member created successfully. An email has been sent to them to set up their password.',
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
