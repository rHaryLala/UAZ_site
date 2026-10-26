<?php

namespace Database\Factories;

use App\Enums\FacultyType;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Faculty> */
class FacultyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'type' => FacultyType::Department,
            'summary' => fake()->sentence(16),
            'description' => '<p>'.fake()->paragraph(4).'</p>',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
