<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Facility;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facility>
 */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    /**
     * Define the model's default state.
     *
     * @var string
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true), // Contoh: 'Free Wifi'
            'icon' => 'path/to/icon.svg',
        ];
    }
}
