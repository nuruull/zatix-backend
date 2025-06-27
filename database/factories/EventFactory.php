<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TermAndCon;
use App\Models\EventOrganizer;
use App\Enum\Status\EventStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+1 week', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, $startDate->format('Y-m-d H:i:s') . ' +8 hours');

        return [
            // Hubungkan ke model lain menggunakan factory mereka
            'eo_id' => EventOrganizer::factory(),
            'tnc_id' => TermAndCon::factory(),

            // Isi data event menggunakan faker
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(3),
            'start_date' => $startDate->format('Y-m-d'),
            'start_time' => $startDate->format('H:i:s'),
            'end_date' => $endDate->format('Y-m-d'),
            'end_time' => $endDate->format('H:i:s'),
            'location' => $this->faker->address(),
            'contact_phone' => $this->faker->phoneNumber(),

            // Atur status default
            'status' => EventStatusEnum::DRAFT,
            'is_published' => false,
            'is_public' => false,
        ];
    }
}
