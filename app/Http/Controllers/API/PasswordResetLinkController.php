<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function store(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $key = 'reset-password:' . $request->email;

        if ($this->limiter->tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again in ' . $this->limiter->availableIn($key) . ' seconds.'
            ], 429);
        }

        $this->limiter->hit($key, 3600);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Link reset password telah dikirim ke email Anda.'])
            : response()->json(['message' => 'Gagal mengirim link reset.'], 400);
    }
}
