<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Sản phẩm '.fake()->unique()->word();

        return [
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'unit' => 'kg',
            'price' => fake()->numberBetween(10, 200) * 1000,
            'description' => null,
            'is_active' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
