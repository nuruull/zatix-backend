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
            'order_id' => Order::factory(),
            'user_id' => function (array $attributes) {
                return Order::find($attributes['order_id'])->user_id;
            },
            'attendee_name' => function (array $attributes) {
                $order = Order::with('user')->find($attributes['order_id']);
                return $order->user->name;
            },
            'ticket_id' => function (array $attributes) {
                $order = Order::with('orderItems')->find($attributes['order_id']);
                // Pastikan ada orderItems sebelum mengambil yang pertama
                return $order->orderItems->first()?->ticket_id ?? Ticket::factory();
            },
            'checked_in_at' => null,
            'checked_in_by' => null,
        ];
    }
}
