<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class TopicEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topics = [
            // Technology
            'Software / Programming',
            'AI & Machine Learning',
            'Cloud',
            'Cybersecurity',
            'Web Development',
            'Mobile Development',
            'IoT / Hardware',
            'Blockchain / Web3 / Crypto',

            // Business
            'Entrepreneurship',
            'Business Strategy',
            'Marketing / Digital Marketing',
            'Finance',
            'Leadership',
            'Sales & Growth',

            // Art & Creativity
            'Photography',
            'Arts',
            'Film',
            'Music',
            'Design',
            'Fashion',

            // Education & Health
            'Learning & Development',
            'Language & Communication',
            'Teaching',
            'Research',

            // Lifestyle & Community
            'Sports',
            'Culinary / Food',
            'Health & Wellness',
            'Travel & Culture',
            'Laundry & Home Services',

            // Social & Public
            'Government',
            'Volunteering',
            'Religious / Spiritual',
        ];

        foreach ($topics as $topicName) {
            Topic::create([
                'name' => $topicName,
                'slug' => Str::slug($topicName)
            ]);
        }
    }
}
