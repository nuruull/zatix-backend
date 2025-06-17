<?php

namespace Tests\Feature\API\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Jobs\SendOtpEmail;
use App\Models\TermAndCon;
use App\Services\OtpService;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Metode ini berjalan sebelum setiap test.
     * Kita gunakan untuk membuat role dan T&C yang dibutuhkan.
     */
    public function setUp(): void
    {
        parent::setUp();
        // Buat role yang dibutuhkan
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'eo-owner', 'guard_name' => 'api']);

        // Buat T&C karena ini adalah prasyarat untuk registrasi
        TermAndCon::factory()->create(['type' => 'general']);
    }

    /**
     * Test #1: Pengguna berhasil mendaftar dan menerima permintaan OTP.
     * Menguji method: register()
     */
    public function test_user_can_register_successfully_and_receives_otp_request(): void
    {
        // 1. "Palsukan" antrian (Queue) agar email tidak benar-benar dikirim
        Queue::fake();

        // 2. Siapkan data pendaftaran yang valid
        $userData = [
            'name' => 'Budi Customer',
            'email' => 'budi.customer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_tnc_accepted' => true,
        ];

        // 3. Panggil endpoint registrasi
        $response = $this->postJson('/api/register', $userData);

        // 4. Lakukan assertions
        $response
            ->assertStatus(200) // Sesuai dengan respons sukses Anda
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful. Please check your email for OTP.',
            ]);

        // 5. Pastikan user dibuat di database, tapi belum terverifikasi
        $this->assertDatabaseHas('users', [
            'email' => 'budi.customer@example.com',
            'email_verified_at' => null,
        ]);

        // 6. Pastikan job untuk mengirim email OTP telah dimasukkan ke antrian
        Queue::assertPushed(SendOtpEmail::class);
    }

    /**
     * Test #2: Pengguna berhasil memverifikasi OTP dan mendapatkan token.
     * Menguji method: verifyOtp()
     */
    public function test_user_can_verify_otp_and_get_token(): void
    {
        // 1. Buat user yang belum terverifikasi
        $user = User::factory()->create(['email_verified_at' => null]);

        // 2. Gunakan OtpService untuk membuat OTP yang valid untuk user ini
        $otpService = $this->app->make(OtpService::class);
        $otp = $otpService->generateOtp($user);

        // 3. Siapkan data untuk verifikasi
        $verificationData = [
            'email' => $user->email,
            'otp_code' => $otp->code,
        ];

        // 4. Panggil endpoint verifikasi OTP
        $response = $this->postJson('/api/verify-otp', $verificationData);

        // 5. Lakukan assertions
        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([ // Pastikan ada token dan data user
                'data' => [
                    'access_token',
                    'token_type',
                    'user',
                ]
            ]);

        // 6. Pastikan kolom email_verified_at di database sudah terisi
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    /**
     * Test #3: Login gagal jika email belum terverifikasi.
     * Menguji method: login()
     */
    public function test_login_fails_if_email_is_not_verified(): void
    {
        // 1. Buat user yang belum terverifikasi
        $user = User::factory()->create(['email_verified_at' => null]);

        // 2. Siapkan data login
        $loginData = [
            'email' => $user->email,
            'password' => 'password', // Password default dari factory
        ];

        // 3. Panggil endpoint login
        $response = $this->postJson('/api/login', $loginData);

        // 4. Lakukan assertions
        $response
            ->assertStatus(404) // Sesuai dengan kode Anda
            ->assertJson([
                'success' => false,
                'message' => 'Email not verified',
            ]);
    }

    /**
     * Test #4: Pengguna yang terverifikasi berhasil login.
     * Menguji method: login()
     */
    public function test_verified_user_can_login_successfully(): void
    {
        // 1. Buat user yang sudah terverifikasi
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => Hash::make('passwordyangbenar'),
        ]);

        // 2. Siapkan data login
        $loginData = [
            'email' => $user->email,
            'password' => 'passwordyangbenar',
        ];

        // 3. Panggil endpoint login
        $response = $this->postJson('/api/login', $loginData);

        // 4. Lakukan assertions
        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token']]);
    }

    /**
     * Test #5: Pengguna yang sudah login bisa logout.
     * Menguji method: logout()
     */
    public function test_authenticated_user_can_logout(): void
    {
        // 1. Buat dan "login"-kan user.
        $user = User::factory()->create();

        // 2. Buat token untuk user tersebut. Ini adalah langkah kuncinya.
        $token = $user->createToken('test-logout-token')->plainTextToken;

        // 3. Panggil endpoint logout dengan menyertakan token di header
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        // 4. Lakukan assertions
        $response
            ->assertStatus(200)
            ->assertJson(['message' => 'Logged out']);

        // 5. Pastikan token user tersebut sudah dihapus dari database
        // $user->fresh()->tokens akan mengambil data token terbaru dari database.
        $this->assertCount(0, $user->fresh()->tokens);
    }
}
