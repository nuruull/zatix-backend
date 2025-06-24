<?php

namespace Tests\Feature\API\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\CustomResetPasswordNotification;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test #1: Pengguna berhasil meminta link reset password.
     * Menguji: PasswordResetLinkController->store()
     */
    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/forgot-password', ['email' => $user->email]);

        $response
            ->assertStatus(200)
            ->assertJson(['message' => 'The password reset link has been sent to your email.']);

        Notification::assertSentTo(
            [$user],
            CustomResetPasswordNotification::class
        );

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    /**
     * Test #2: Pengguna berhasil mereset password dengan token yang valid.
     * Menguji: NewPasswordController->store()
     */
    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create();

        $token = Password::broker()->createToken($user);

        $resetData = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'password_baru_123',
            'password_confirmation' => 'password_baru_123',
        ];

        $response = $this->postJson('/api/reset-password', $resetData);

        $response
            ->assertStatus(200)
            ->assertJson(['message' => 'Password berhasil direset! Silakan login.']);

        $updatedUser = $user->fresh();
        $this->assertTrue(Hash::check('password_baru_123', $updatedUser->password));

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    /**
     * Test #3: Reset password gagal dengan token yang tidak valid.
     * Menguji: NewPasswordController->store()
     */
    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $resetData = [
            'email' => $user->email,
            'token' => 'token-yang-salah-dan-tidak-valid',
            'password' => 'password_baru_123',
            'password_confirmation' => 'password_baru_123',
        ];

        $response = $this->postJson('/api/reset-password', $resetData);

        $response
            ->assertStatus(400) // Sesuai dengan kode Anda
            ->assertJson(['message' => 'Token tidak valid atau sudah kadaluarsa.']);
    }
}
