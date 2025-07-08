<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bank>
 */
class BankFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Bank::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $banks = [
            'bca' => ['name' => 'BCA', 'type' => 'bank_transfer'],
            'bni' => ['name' => 'BNI', 'type' => 'bank_transfer'],
            'mandiri' => ['name' => 'Mandiri', 'type' => 'echannel'],
            'permata' => ['name' => 'Permata', 'type' => 'bank_transfer'],
        ];
        $code = $this->faker->unique()->randomElement(array_keys($banks));
        $bank = $banks[$code];

        return [
            'name' => $bank['name'],
            'code' => $code,
            'type' => $bank['type'],
            'main_image' => $this->faker->imageUrl(200, 200, 'business'),
            'secondary_image' => null,
        ];
    }
}
