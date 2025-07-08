<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PaymentMethod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Asumsikan Anda punya factory untuk PaymentMethodCategory
            'payment_method_category_id' => PaymentMethodCategory::factory(),
            'bank_id' => Bank::factory(),
            'is_active' => true,
            'is_maintenance' => false,
            'priority' => $this->faker->numberBetween(1, 10),
        ];
    }
}
