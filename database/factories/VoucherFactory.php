<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Voucher::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discountType = $this->faker->randomElement(['percentage', 'fixed']);
        $discountValue = ($discountType === 'fixed')
            ? $this->faker->randomElement([10000, 25000, 50000]) // Nilai diskon tetap
            : $this->faker->numberBetween(10, 50); // Persentase diskon

        return [
            'user_id' => User::factory(), // <-- Ditambahkan untuk mengatasi error
            'name' => 'Diskon ' . $this->faker->words(2, true),
            'code' => Str::upper('VCR-' . Str::random(8)),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'max_amount' => ($discountType === 'percentage') ? $this->faker->randomElement([50000, 100000]) : 0,
            'usage_limit' => $this->faker->numberBetween(50, 200),
            'valid_until' => now()->addMonths(3),
            'is_active' => true,
        ];
    }
}
