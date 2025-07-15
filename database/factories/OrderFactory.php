<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\OrderItem;
use App\Enum\Status\OrderStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'gross_amount' => 0, // Akan dihitung ulang di afterCreating
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => 0, // Akan dihitung ulang di afterCreating
            'status' => $this->faker->randomElement(OrderStatusEnum::cases()),
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure()
    {
        return $this->afterCreating(function (Order $order) {
            // Ambil tiket yang sudah ada untuk event ini
            $ticket = Ticket::where('event_id', $order->event_id)->inRandomOrder()->first();

            // Jika tidak ada tiket sama sekali untuk event ini, baru buat satu
            if (!$ticket) {
                $ticket = Ticket::factory()->create(['event_id' => $order->event_id]);
            }

            $orderItems = OrderItem::factory()
                ->count($this->faker->numberBetween(1, 2))
                ->create([
                    'order_id' => $order->id,
                    'ticket_id' => $ticket->id, // Gunakan ID tiket yang sudah ada/baru dibuat
                ]);

            // Hitung ulang total berdasarkan item yang baru dibuat
            $grossAmount = $orderItems->sum('subtotal');
            $netAmount = $grossAmount - $order->discount_amount;

            // Update order dengan total yang benar
            $order->update([
                'gross_amount' => $grossAmount,
                'net_amount' => $netAmount,
            ]);
        });
    }
}
