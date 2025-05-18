<?php

namespace App\Http\Controllers\API;

use App\Models\DemoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;
use App\Notifications\DemoRequestUpdated;
use App\Notifications\RoleUpgradedNotification;

class   DemoRequestController extends BaseController
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
    public function store(Request $request)
    {
        try {
            $request->validate([
                'eo_name' => 'required|string|max:255',
                'eo_email' => 'required|email',
                'eo_description' => 'required|string',
                'event_name' => 'required|string|max:255',
                'event_description' => 'required|string',
                'audience_target' => 'required|string|max:255',
            ]);

            $demoRequest = DemoRequest::create([
                'user_id' => Auth::id(),
                'eo_name' => $request->eo_name,
                'eo_email' => $request->eo_email,
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
            $demoRequest = DemoRequest::findOrFail($id);

            // Validasi kepemilikan atau role
            if (!Auth::user()->hasRole('super-admin') && $demoRequest->user_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

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

    public function getCurrentStep($id)
    {
        try {
            $demoRequest = DemoRequest::findOrFail($id);
            return response()->json(['current_step' => $demoRequest->current_step]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to get current step', 'error' => $e->getMessage()], 500);
        }
    }

    public function submitPitchingSchedule(Request $request, $id)
    {
        try {
            $request->validate([
                'pitching_schedule' => 'required|date',
            ]);

            $demoRequest = DemoRequest::findOrFail($id);

            if (!$demoRequest->canProceedTo(2)) {
                return response()->json(['message' => 'Pitching schedule cannot be submitted unless the request is approved.'], 403);
            }

            $demoRequest->pitching_schedule = $request->pitching_schedule;
            $demoRequest->current_step = 2; // update step
            $demoRequest->save();

            $user = $demoRequest->user;
            $message = 'Jadwal pitching Anda telah diajukan. Menunggu konfirmasi admin.';
            $user->notify(new DemoRequestUpdated($demoRequest, $message));

            return response()->json(['message' => 'Pitching schedule submitted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to submit pitching schedule', 'error' => $e->getMessage()], 500);
        }
    }

    public function approvePitching(Request $request, $id)
    {
        try {
            $request->validate([
                'pitching_link' => 'required|url',
            ]);

            $demoRequest = DemoRequest::findOrFail($id);

            $demoRequest->pitching_link = $request->pitching_link;
            $demoRequest->current_step = 3;
            $demoRequest->save();

            $user = $demoRequest->user;
            $message = 'Jadwal pitching Anda telah disetujui.';
            $user->notify(new DemoRequestUpdated($demoRequest, $message));

            return response()->json([
                'message' => 'Pitching approved successfully',
                'data' => $demoRequest->pitching_link
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to approve pitching', 'error' => $e->getMessage()], 500);
        }
    }

    public function provideDemoAccount(Request $request, $id)
    {
        try {
            $request->validate([
                'expiry_date' => 'required|date',
            ]);

            $demoRequest = DemoRequest::findOrFail($id);
            $user = $demoRequest->user;

            $permissions = [
                'event.store',
                'event.update',
                'event.destroy',
                'facility.store',
                'facility.update',
                'facility.destroy',
                'ticket.store',
                'ticket.update',
                'ticket.destroy',
            ];
            $user->givePermissionTo($permissions);

            $demoRequest->demo_access_expiry = $request->expiry_date;
            $demoRequest->current_step = 4;
            $demoRequest->save();

            $user = $demoRequest->user;
            $message = 'Akses demo telah diberikan kepada Anda hingga ' . $request->expiry_date . '.';
            $user->notify(new DemoRequestUpdated($demoRequest, $message));

            return response()->json(['message' => 'Demo account provided successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to provide demo account', 'error' => $e->getMessage()], 500);
        }
    }

    public function confirmContinuation(Request $request, $id)
    {
        try {
            $request->validate([
                'is_continue' => 'required|boolean',
            ]);

            $demoRequest = DemoRequest::findOrFail($id);
            $demoRequest->is_continue = $request->is_continue;
            $demoRequest->save();

            $message = $request->is_continue
                ? "Anda memilih untuk melanjutkan."
                : "Anda memilih untuk tidak melanjutkan.";

            $demoRequest->user->notify(new DemoRequestUpdated($demoRequest, $message));

            return response()->json(['message' => 'Continuation confirmed successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to confirm continuation', 'error' => $e->getMessage()], 500);
        }
    }

    public function upgradeToEoOwner($id)
    {
        try {
            $demoRequest = DemoRequest::findOrFail($id);

            // Validasi
            if ($demoRequest->current_step !== 4 || !$demoRequest->is_continue) {
                return response()->json(['message' => 'Cannot upgrade role at this stage'], 400);
            }

            // Update role user
            $user = $demoRequest->user;
            $user->syncRoles('eo-owner');
            $user->save();

            // Update demo request
            $demoRequest->current_step = 5;
            $demoRequest->role_updated = true;
            $demoRequest->save();

            $user->notify(new RoleUpgradedNotification('eo-owner'));

            return response()->json(['message' => 'Role upgraded to EO Owner successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upgrade role', 'error' => $e->getMessage()], 500);
        }
    }
}
