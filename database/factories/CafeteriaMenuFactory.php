<?php

namespace Database\Factories;

use App\Models\CafeteriaMenu;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<CafeteriaMenu> */
class CafeteriaMenuFactory extends Factory
{
    protected $model = CafeteriaMenu::class;

    public function definition(): array
    {
        static $week = 0;

        return [
            'starts_on' => Carbon::today()->addWeeks(++$week + 100)->toDateString(),
            'meals' => collect(range(0, 6))->map(fn (int $day) => [
                'morning' => ["Petit-déjeuner {$day}A", "Petit-déjeuner {$day}B"],
                'noon' => ["Déjeuner {$day}A", "Déjeuner {$day}B", "Déjeuner {$day}C"],
                'evening' => ["Dîner {$day}A"],
            ])->all(),
            'note' => null,
            'is_active' => true,
        ];
    }

    public function startingOn(string $date): static
    {
        return $this->state(['starts_on' => $date]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
