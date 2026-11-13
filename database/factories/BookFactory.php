<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->numerify('978##########'),
            'published_date' => fake()->dateTimeBetween('-10 years', '-1 day')->format('Y-m-d'),
            'description' => fake()->paragraph(),
            'image_url' => fake()->url(),
        ];
    }
}
