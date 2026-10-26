<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\PhotoAlbum;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PhotoAlbum> */
class PhotoAlbumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(14),
            'taken_on' => fake()->dateTimeBetween('-1 year', 'now'),
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
        ];
    }
}
