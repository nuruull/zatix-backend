<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RolesAndPermissionsSeeder::class,
            FacilitySeeder::class,
            TicketTypeSeeder::class,
            TermAndConSeeder::class,
            EventOrganizerSeeder::class,
            DocumentSeeder::class,
            CategorySeeder::class,
            EventSeeder::class,
            CarouselSeeder::class,
            StaffSeeder::class,
            PaymentMethodSeeder::class,
            VoucherSeeder::class,
            ETicketSeeder::class,
        ]);
    }
}
