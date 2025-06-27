<?php

namespace Database\Factories;

use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketType>
 */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;
    /**
     * Define the model's default state.
     *
     * @var string
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Regular', 'VIP', 'Early Bird', 'Student']),
            'description' => $this->faker->sentence(),
        ];
    }
}
