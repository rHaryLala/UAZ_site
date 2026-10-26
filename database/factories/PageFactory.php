<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Page> */
class PageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'summary' => fake()->sentence(14),
            'blocks' => [
                ['type' => 'rich_text', 'data' => ['heading' => fake()->sentence(3), 'content' => '<p>'.fake()->paragraph(4).'</p>', 'width' => 'normal']],
            ],
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
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
