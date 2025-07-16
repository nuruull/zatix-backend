<?php

namespace Database\Factories;

use App\Models\User;
use App\Enum\Type\FinancialTransactionTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Kolom relasi WAJIB disediakan oleh pemanggil (test/seeder)
            'event_id' => null,
            'recorded_by_user_id' => User::factory(),
            'type' => $this->faker->randomElement(FinancialTransactionTypeEnum::cases()),
            'category' => $this->faker->randomElement(['Sponsorship', 'Venue Rent', 'Marketing', 'Logistics', 'Talent Fee']),
            'description' => $this->faker->sentence(),
            'amount' => $this->faker->numberBetween(100000, 10000000),
            'transaction_date' => $this->faker->date(),
            'proof_trans_url' => null,
        ];
    }
}
