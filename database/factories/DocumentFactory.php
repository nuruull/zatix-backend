<?php

namespace Database\Factories;

use App\Models\EventOrganizer;
use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventOrganizer>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'documentable_type' => EventOrganizer::class,
            'documentable_id' => EventOrganizer::factory(),
            'type' => $this->faker->randomElement(['ktp', 'npwp', 'nib']),
            'file' => 'documents/' . $this->faker->uuid() . '.pdf',
            'number' => $this->faker->numerify('################'),
            'name' => $this->faker->name(),
            'address' => $this->faker->address(),
            'status' => DocumentStatusEnum::PENDING,
            'reason_rejected' => null,
        ];

    }
}
