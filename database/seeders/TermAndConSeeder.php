<?php

namespace Database\Seeders;

use App\Models\TermAndCon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TermAndConSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TermAndCon::insert([
            [
                'content' => ''
            ]
        ]);
    }
}
