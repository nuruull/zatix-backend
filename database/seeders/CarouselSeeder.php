<?php

namespace Database\Seeders;

use App\Models\Carousel;
use Illuminate\Database\Seeder;
use App\Enum\Type\LinkTargetTypeEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CarouselSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Carousel::truncate();

        $carousels=[
            [
                'image' => 'carousels/sample1.jpg', // Path placeholder untuk gambar
                'title' => 'Diskon Spesial Musim Panas',
                'caption' => 'Nikmati potongan harga hingga 50% untuk semua produk fashion.',
                'link_url' => 'https://google.com',
                'link_target' => LinkTargetTypeEnum::SELF->value, // Membuka link di tab yang sama
                'order' => 1,
                'is_active' => true,
            ],
            [
                'image' => 'carousels/sample2.jpg',
                'title' => 'Koleksi Terbaru Telah Tiba',
                'caption' => 'Jelajahi koleksi terbaru kami yang elegan dan modern.',
                'link_url' => 'https://google.com',
                'link_target' => LinkTargetTypeEnum::BLANK->value, // Membuka link di tab baru
                'order' => 2,
                'is_active' => true,
            ],
            [
                'image' => 'carousels/sample3.jpg',
                'title' => 'Acara Komunitas Berikutnya',
                'caption' => 'Segera hadir, jangan sampai ketinggalan!',
                'link_url' => null, // Tidak ada link
                'link_target' => LinkTargetTypeEnum::SELF->value, // Default
                'order' => 3,
                'is_active' => true,
            ],
            [
                'image' => 'carousels/sample4.jpg',
                'title' => 'Carousel Non-Aktif',
                'caption' => 'Ini adalah contoh carousel yang tidak akan ditampilkan.',
                'link_url' => null,
                'link_target' => LinkTargetTypeEnum::SELF->value,
                'order' => 4,
                'is_active' => false, // Carousel ini tidak aktif
            ],
        ];

        foreach ($carousels as $item) {
            Carousel::create($item);
        }
    }
}
