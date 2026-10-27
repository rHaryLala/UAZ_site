<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(12),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
