<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\NewsPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NewsPost> */
class NewsPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->unique()->sentence(7), '.'),
            'excerpt' => fake()->sentence(20),
            'body' => collect(range(1, 4))->map(fn () => '<p>'.fake()->paragraph(5).'</p>')->implode(''),
            'is_featured' => false,
            'status' => PublicationStatus::Published,
            'published_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PublicationStatus::Draft, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => PublicationStatus::Published, 'published_at' => now()->addWeek()]);
    }
}
