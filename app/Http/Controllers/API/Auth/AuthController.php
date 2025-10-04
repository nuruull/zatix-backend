<?php

namespace App\Http\Controllers\API\Auth;

use Exception;
use App\Models\User;
use App\Models\TncStatus;
use App\Jobs\SendOtpEmail;
use App\Models\TermAndCon;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
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
        } catch (Exception $e) {
            Log::error('Error queueing OTP email: ' . $e->getMessage());
            return false;
        }
    }

    public function register(Request $request)
    {
        try {
            DB::beginTransaction();
            $validatedData = $request->validate([
                'name' => 'required|string|max:100',
                'email' => 'required|string|email',
                'password' => 'required|string|confirmed|min:8',
                'is_tnc_accepted' => 'required|boolean|accepted'
            ]);

            $tnc = TermAndCon::where('type', 'general')->latest()->first();
            // dd($tnc);
            if (!$tnc) {
                throw new Exception('Terms and Conditions not configured for registration.');
            }

            $user = User::where('email', $request->email)->first();

            if ($user == null) {

                $user = User::create([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'password' => bcrypt($validatedData['password']),
                    'email_verified_at' => null,
                ]);
                $user->assignRole('customer');

                TncStatus::create([
                    'tnc_id' => $tnc->id,
                    'user_id' => $user->id,
                    'accepted_at' => \now(),
                ]);
            }

            if ($user->email_verified_at == null) {
                $otp = $this->otpService->generateOtp($user);

                $emailSent = $this->sendOtpEmail($user, $otp->code);

                if (!$emailSent) {
                    return $this->sendError(
                        'Registration successful but failed to send OTP email. Please try again later or contact support.',
                        [],
                        500
                    );
                }
                DB::commit();

                $user->load('roles');

                // --- BLOK KODE UNTUK TESTING ---
                $responseData = [
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->getRoleNames(), // BARU: Menambahkan roles ke response
                    ]
                ];
                if (app()->isLocal()) {
                    $responseData['otp_code_for_testing'] = $otp->code;
                }
                // ------------------------------------

                return $this->sendResponse(
                    $responseData,
                    'Registration successful. Please check your email for OTP.',
                    200
                );
            }
            return $this->sendError(
                'This email is already registered and verified. Please login',
                [],
                409
            );
        } catch (ValidationException $exception) {
            DB::rollBack();
            return $this->sendError(
                'Validation Exception',
                $exception->errors(),
                422
            );
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->sendError(
                'An unexpected error occurred.',
                [],
                500
            );
        }
    }

    public function verifyOtp(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'email' => 'required|exists:users,email',
                'otp_code' => 'required|string|size:6',
            ]);

            $user = User::where('email', $validated['email'])
                ->whereNull('email_verified_at')
                ->firstOrFail();

            // Case insensitive OTP check
            $otpCode = strtoupper(
                $validated['otp_code']
            );

            if (!$this->otpService->verifyOtp($user, $otpCode)) {
                DB::rollBack();
                return $this->sendError(
                    'Invalid or expired OTP'
                );
            }

            $user->email_verified_at = now();
            $user->save();
            $token = $user->createToken('auth_token')->plainTextToken;
            $user->assignRole('eo-owner');

            DB::commit();

            return $this->sendResponse(
                [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => new UserResource($user),
                ],
                'OTP verified successfully'
            );
        } catch (ValidationException $exception) {
            DB::rollBack();
            return $this->sendError(
                'Validation Exception',
                $exception->errors(),
                422
            );
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->sendError(
                'Failed',
                $exception->getMessage(),
                500
            );
        }
    }

    public function resendOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|exists:users,email',
            ]);

            $user = User::where('email', $validated['email'])
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

            $otp = $this->otpService->generateOtp($user);

            $emailSent = $this->sendOtpEmail($user, $otp->code);

            if (!$emailSent) {
                return $this->sendError(
                    'Failed to resend OTP email. Please contact support.'
                );
            }

            // --- BLOK KODE UNTUK TESTING ---
            $responseData = ['email' => $user->email];
            if (app()->isLocal()) {
                $responseData['otp_code_for_testing'] = $otp->code;
            }
            // ------------------------------------

            return $this->sendResponse(
                $responseData,
                'New OTP has been sent to your email.'
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

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return $this->sendError(
                    'Email not registered',
                    [],
                    404
                );
            }

            if ($user->email_verified_at == null) {
                return $this->sendError(
                    'Email not verified'
                );
            }

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->sendError(
                    'Incorrect email or password'
                );
            }

            $token = $user->createToken('auth_token ' . $user->email)->plainTextToken;
            $responseData = [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'roles' => $user->getRoleNames(),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ],
            ];

            return $this->sendResponse(
                $responseData,
                'Login successfully'
            );
        } catch (ValidationException $exception) {
            return $this->sendError(
                'Validation Exception',
                $exception->getMessage(),
                422
            );
        } catch (Exception $exception) {
            return $this->sendError(
                'Failed',
                $exception->getMessage(),
                500
            );
        }
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
