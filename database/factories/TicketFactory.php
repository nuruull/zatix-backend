<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            // Asumsikan Anda punya factory untuk TicketType
            'ticket_type_id' => TicketType::query()->inRandomOrder()->value('id') ?? TicketType::factory(),
            'name' => $this->faker->randomElement(['Regular', 'VIP', 'Early Bird', 'Presale 1']),
            'price' => $this->faker->numberBetween(5, 50) * 10000, // Harga antara 50rb - 500rb
            'stock' => $this->faker->numberBetween(100, 1000),
            'limit' => $this->faker->numberBetween(1, 5), // Maksimal pembelian per transaksi
            'start_date' => now(),
            'end_date' => now()->addWeeks(2),
        ];
    }
}
