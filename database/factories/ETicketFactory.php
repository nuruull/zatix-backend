<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\ETicket;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ETicket>
 */
class ETicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ETicket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_code' => Str::upper('ZTX-' . Str::random(12)),
            'order_id' => null,
            'user_id' => null,
            'ticket_id' => null,
            'attendee_name' => $this->faker->name(),
            'checked_in_at' => null,
            'checked_in_by' => null,
        ];
    }
}
