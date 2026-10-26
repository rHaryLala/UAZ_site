<?php

namespace Database\Factories;

use App\Enums\TeamCategory;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamMember> */
class TeamMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'job_title' => fake()->jobTitle(),
            'category' => TeamCategory::Teaching,
            'bio' => fake()->sentence(18),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
