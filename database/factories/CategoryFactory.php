<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Facebook', 'TikTok', 'Instagram', 'YouTube', 'Telegram', 'Threads', 'Shopee', 'Twitter', 'Zalo', 'Website']).' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'badge' => mb_substr($name, 0, 2),
            'color' => '#1f47e6',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
