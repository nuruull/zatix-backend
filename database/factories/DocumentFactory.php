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
            // Secara default, setiap dokumen akan dibuat untuk sebuah Event Organizer baru.
            'documentable_type' => EventOrganizer::class,
            'documentable_id' => EventOrganizer::factory(),

            // --- PERBAIKAN DI SINI ---
            // Menambahkan nilai default untuk kolom-kolom yang required
            // agar factory bisa dipanggil langsung tanpa state.
            'type' => $this->faker->randomElement(['ktp', 'npwp', 'nib']),
            'number' => $this->faker->numerify('################'),
            'name' => $this->faker->name(),
            'address' => $this->faker->address(),

            'file' => 'documents/' . $this->faker->uuid() . '.pdf',
            'status' => $this->faker->randomElement(DocumentStatusEnum::cases()),
            'reason_rejected' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function ($document) {
            // Logika ini akan menimpa 'name' dan 'address' default jika
            // dokumen dibuat untuk EO yang sudah ada, membuatnya lebih akurat.
            if ($document->documentable) {
                // Untuk KTP, ambil nama pemilik. Untuk lainnya, nama perusahaan.
                $document->name = ($document->type === 'ktp' && $document->documentable->eo_owner)
                    ? $document->documentable->eo_owner->name
                    : $document->documentable->name;

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
                'number' => $this->faker->numerify('################'),
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
