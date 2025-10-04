<?php

namespace Database\Seeders;

use App\Models\Format;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class FormatEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formats = [
            'Workshop',
            'Seminar',
            'Conference',
            'Meetup / Gathering',
            'Exhibition / Pameran',
            'Concert / Music Show',
            'Festival',
            'Webinar (Online)',
            'Competition / Hackathon / Lomba',
            'Training / Bootcamp',
            'Talkshow / Panel Discussion',
            'Networking Event',
            'Charity / Fundraising Event'
        ];

        foreach ($formats as $format) {
            Format::create([
                'name' => $format,
                'slug' => Str::slug($format)
            ]);
        }
    }
}
