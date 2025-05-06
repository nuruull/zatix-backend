<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification</title>
</head>
<body>
    <h2>Your OTP Code</h2>
    <p>Hello {{ $user->name }},</p>
    <p>Your OTP code is: <strong>{{ $otpCode }}</strong></p>
    <p>This code will expire in 15 minutes.</p>
    <p>If you didn't request this, please ignore this email.</p>
    <br>
    <p>Regards,<br>{{ config('app.name') }}</p>
</body>
</html>
