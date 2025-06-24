<?php

namespace Database\Factories;

use App\Enum\Status\DocumentStatusEnum;
use App\Models\EventOrganizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
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
            'file' => 'documents/' . $this->faker->uuid() . '.pdf',
            'status' => $this->faker->randomElement(DocumentStatusEnum::cases()),
            'reason_rejected' => null,
        ];
    }

    /**
     * Configure the model factory.
     * Logika ini akan berjalan SETELAH model dibuat.
     */
    public function configure(): static
    {
        return $this->afterMaking(function ($document) {
            // Jika tidak ada nama/alamat, ambil dari induknya (EO)
            if (!$document->name && $document->documentable) {
                $document->name = $document->documentable->name;
            }
            if (!$document->address && $document->documentable) {
                $document->address = $document->documentable->address_eo;
            }
        });
    }

    /**
     * State untuk dokumen KTP.
     */
    public function forKtp(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'ktp',
                'number' => $this->faker->nik(),
            ];
        });
    }

    /**
     * State untuk dokumen NPWP.
     */
    public function forNpwp(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'npwp',
                'number' => $this->faker->numerify('##.###.###.#-###.###'),
            ];
        });
    }

    /**
     * State untuk dokumen NIB.
     */
    public function forNib(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'nib',
                'number' => $this->faker->numerify('##############'),
            ];
        });
    }
}
