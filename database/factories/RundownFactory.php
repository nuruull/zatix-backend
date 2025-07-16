<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rundown>
 */
class RundownFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('+1 day', '+1 month');

        $endTime = (clone $startTime)->modify('+' . $this->faker->numberBetween(15, 90) . ' minutes');

        return [
            // Kolom relasi tidak didefinisikan di sini, harus disediakan saat factory dipanggil
            'event_id' => null,

            // Data dummy untuk kolom-kolom lain
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(2),
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'order' => $this->faker->unique()->numberBetween(1, 100), // urutan unik untuk menghindari duplikasi
        ];
    }
}
