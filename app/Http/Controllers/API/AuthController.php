<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SendOtpEmail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $otpService;
    protected $limiter;

    public function __construct(OtpService $otpService, RateLimiter $limiter)
    {
        $this->otpService = $otpService;
        $this->limiter = $limiter;
    }

    protected function sendOtpEmail(User $user, string $otpCode)
    {
        try {
            SendOtpEmail::dispatch($user, $otpCode);
            Log::info("OTP email dispatched for user {$user->id}");
            return true;
        } catch (\Exception $e) {
            Log::error('Error queueing OTP email: ' . $e->getMessage());
            return false;
        }
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|confirmed|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'email_verified_at' => null,
        ]);

        $user->assignRole('customer');

        $otp = $this->otpService->generateOtp($user);

        $emailSent = $this->sendOtpEmail($user, $otp->code);

        if (!$emailSent) {
            return response()->json([
                'message' => 'Registration successful but failed to send OTP email',
                'user_id' => $user->id,
                'otp_code' => env('APP_ENV') === 'local' ? $otp->code : null,
            ], 202);
        }

        return response()->json([
            'message' => $emailSent
                ? 'Registration successful. Please check your email for OTP.'
                : 'Registration successful but failed to send OTP email. Please contact support.',
            'user_id' => $user->id,
            'otp_code' => env('APP_ENV') === 'local' ? $otp->code : null,
        ], $emailSent ? 200 : 202);
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp_code' => 'required|string|size:6',
        ]);

        $user = User::where('id', $validated['user_id'])
            ->whereNull('email_verified_at')
            ->firstOrFail();

        // Case insensitive OTP check
        $otpCode = strtoupper($validated['otp_code']);

        if (!$this->otpService->verifyOtp($user, $otpCode)) {
            Log::warning("Failed OTP attempt for user {$user->id}");
            return response()->json([
                'message' => 'Invalid or expired OTP',
            ], 422);
        }

        $user->update(['email_verified_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info("User {$user->id} successfully verified OTP");

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->makeHidden('password'),
            'message' => 'OTP verified successfully',
        ]);
    }

    public function resendOtp(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::where('id', $validated['user_id'])
            ->whereNull('email_verified_at')
            ->firstOrFail();

        $key = 'otp_resend:' . $user->user_id;

        if ($this->limiter->tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again in ' . $this->limiter->availableIn($key) . ' seconds.',
            ], 429);
        }

        $this->limiter->hit($key, 60);

        $this->otpService->revokeAllOtps($user);

        $user = User::findOrFail($request->user_id);

        $otp = $this->otpService->generateOtp($user);

        $emailSent = $this->sendOtpEmail($user, $otp->code);

        if (!$emailSent) {
            return response()->json([
                'message' => 'Failed to resend OTP email. Please contact support.',
                'user_id' => $user->id,
                'otp_code' => env('APP_ENV') === 'local' ? $otp->code : null,
            ], 500);
        }

        return response()->json([
            'message' => 'New OTP has been sent to your email.',
            'user_id' => $user->id,
            'otp_code' => env('APP_ENV') === 'local' ? $otp->code : null,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah']
            ]);
        }

        $token = $user->createToken('auth_token ' . $user->email)->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
