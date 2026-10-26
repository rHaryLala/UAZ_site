<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+4 months');

        return [
            'title' => rtrim(fake()->unique()->sentence(5), '.'),
            'summary' => fake()->sentence(16),
            'description' => '<p>'.fake()->paragraph(4).'</p>',
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+3 hours'),
            'is_all_day' => false,
            'location' => 'Campus de l\'UAZ',
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
        ];
    }

    public function past(): static
    {
        return $this->state(function () {
            $start = fake()->dateTimeBetween('-5 months', '-1 week');

            return ['starts_at' => $start, 'ends_at' => (clone $start)->modify('+3 hours')];
        });
    }
}
