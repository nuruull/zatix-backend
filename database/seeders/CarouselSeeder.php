<?php

namespace Database\Seeders;

use App\Models\Carousel;
use Illuminate\Database\Seeder;
use App\Enum\Type\LinkTargetTypeEnum;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CarouselSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Carousel::truncate();
        Storage::disk('public')->deleteDirectory('carousels');
        Storage::disk('public')->makeDirectory('carousels');

        $carousels = [
            [
                'title' => 'Diskon Spesial Musim Panas',
                'caption' => 'Nikmati potongan harga hingga 50% untuk semua produk fashion.',
                'link_url' => 'https://google.com',
                'link_target' => LinkTargetTypeEnum::SELF->value, // Membuka link di tab yang sama
                'order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Koleksi Terbaru Telah Tiba',
                'caption' => 'Jelajahi koleksi terbaru kami yang elegan dan modern.',
                'link_url' => 'https://google.com',
                'link_target' => LinkTargetTypeEnum::BLANK->value, // Membuka link di tab baru
                'order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Acara Komunitas Berikutnya',
                'caption' => 'Segera hadir, jangan sampai ketinggalan!',
                'link_url' => null, // Tidak ada link
                'link_target' => LinkTargetTypeEnum::SELF->value, // Default
                'order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Carousel Non-Aktif',
                'caption' => 'Ini adalah contoh carousel yang tidak akan ditampilkan.',
                'link_url' => null,
                'link_target' => LinkTargetTypeEnum::SELF->value,
                'order' => 4,
                'is_active' => false, // Carousel ini tidak aktif
            ],
        ];

        foreach ($carousels as $index => $item) {
            // 2. Buat nama file dan path tujuan
            $filename = 'sample-' . ($index + 1) . '.jpg';
            $path = 'carousels/' . $filename;

            try {
                // 3. Ambil konten gambar dari URL (menggunakan picsum.photos untuk gambar acak)
                $imageUrl = 'https://picsum.photos/2048/1152?random=' . ($index + 1);
                $imageContent = file_get_contents($imageUrl);
                
                // 4. Simpan gambar ke storage publik Anda
                Storage::disk('public')->put($path, $imageContent);

                // 5. Buat entri database dengan path gambar yang sudah disimpan
                Carousel::create(array_merge($item, [
                    'image' => $path, // Simpan path relatif: 'carousels/sample-1.jpg'
                ]));

            } catch (\Exception $e) {
                // Jika gagal mengunduh, log error dan lanjutkan tanpa membuat record
                $this->command->error("Gagal mengunduh gambar untuk carousel: " . $item['title'] . ". Error: " . $e->getMessage());
            }
        }

    }
}
