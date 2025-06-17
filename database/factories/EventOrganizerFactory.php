<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventOrganizer>
 */
class EventOrganizerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'description' => $this->faker->paragraph(),
            'email_eo' => $this->faker->unique()->safeEmail(),
            'phone_no_eo' => $this->faker->phoneNumber(),
            'address_eo' => $this->faker->address(),
            'logo' => null, // Defaultnya null, bisa di-override di test
            'eo_owner_id' => User::factory(), // Secara otomatis membuat User baru sebagai pemilik
        ];
    }
}
