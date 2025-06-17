<?php

namespace App\Http\Controllers\API\Auth;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends BaseController
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function store(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email']);

            $key = 'reset-password:' . $request->email;

            if ($this->limiter->tooManyAttempts($key, 3)) {
                $seconds = $this->limiter->availableIn($key);
                return $this->sendError(
                    'Too many attempts.',
                    ['message' => 'Please try again in ' . $seconds . ' seconds.'],
                    429
                );
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return $this->sendError(
                    'Failed to send the reset link.',
                    ['email' => 'We couldn`t find a user with that email address.'],
                    404
                );
            }

            $this->limiter->hit($key, 3600);

            $token = Password::broker()->createToken($user);

            $user->sendPasswordResetNotification($token);

            $testData = [];
            if (app()->isLocal()) {
                $testData['reset_token_for_testing'] = $token;
            }

            return $this->sendResponse(
                $testData,
                'The password reset link has been sent to your email.'
            );
        } catch (ValidationException $e) {
            return $this->sendError(
                'Invalid data.',
                $e->errors(),
                422);
        } catch (Exception $e) {
            Log::error('Error in PasswordResetLinkController: ' . $e->getMessage());
            return $this->sendError(
                'An error occurred on the server.',
                [],
                500);
        }
    }
}

