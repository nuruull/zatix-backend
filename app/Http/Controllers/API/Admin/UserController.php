<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Http\Resources\UserResource;
use App\Http\Controllers\BaseController;

class UserController extends BaseController
{
    public function index()
    {
        $users = User::query()
            // Eager load relasi 'roles' untuk efisiensi, ambil nama perannya saja
            ->with('roles:name')
            ->latest()
            ->paginate(20);

        // Gunakan API Resource untuk memformat output
        return UserResource::collection($users)
            ->additional([
                'success' => true,
                'message' => 'Users retrieved successfully.'
            ]);
    }

    public function getRoles()
    {
        // Ambil semua peran, hanya kolom id dan name yang kita butuhkan
        $roles = Role::all(['id', 'name']);

        return $this->sendResponse($roles, 'Available roles retrieved successfully.');
    }

    public function updateRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'api')
            ],
        ]);

        $user->syncRoles($validated['roles']);

        $user->load('roles:name');
        return $this->sendResponse(
            new UserResource($user),
            'User roles updated successfully.'
        );
    }
}
