<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 3);
        $price = $this->faker->randomElement([50000, 75000, 100000, 150000]);
        $discount = 0; // Anda bisa ubah logikanya jika perlu

        return [
            // 'order_id' => Order::factory(), // <-- DIHAPUS untuk memutus loop tak terbatas
            'ticket_id' => Ticket::factory(),
            'quantity' => $quantity,
            'price' => $price,
            'discount' => $discount,
            'subtotal' => ($quantity * $price) - $discount,
        ];
    }
}
