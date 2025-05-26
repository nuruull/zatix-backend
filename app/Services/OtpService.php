<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use Carbon\Carbon;

class OtpService
{
    public function generateOtp(User $user)
    {
        OtpCode::where('user_id', $user->id)
            ->where('is_used', false)
            ->delete();

        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return OtpCode::create([
            'user_id' => $user->id,
            'code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);
    }

    public function verifyOtp(User $user, string $code)
    {
        $otp = OtpCode::where('user_id', $user->id)
            ->where('code', $code)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return false;
        }

        $otp->update(['is_used' => true]);

        return true;
    }

    public function revokeAllOtps(User $user)
    {
        return OtpCode::where('user_id', $user->id)
            ->where('is_used', false)
            ->delete();
    }
}
