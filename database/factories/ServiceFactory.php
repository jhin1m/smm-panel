<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => 'Like bài viết '.fake()->numberBetween(1, 9999),
            'description' => null,
            'unit' => 'per_1000',
            'price' => 16_098,
            'min_quantity' => 50,
            'max_quantity' => 50_000,
            'speed' => '10.000/ngày',
            'start_time' => '5 phút',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
