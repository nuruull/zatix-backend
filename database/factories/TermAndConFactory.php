<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TermAndCon>
 */
class TermAndConFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'general', // Sesuai dengan yang dibutuhkan di AuthController Anda
            // 'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraph(5),
            // 'version' => '1.0',
        ];
    }
}
