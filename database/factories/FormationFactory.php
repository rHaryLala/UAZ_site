<?php

namespace Database\Factories;

use App\Enums\FormationLevel;
use App\Enums\PublicationStatus;
use App\Models\Faculty;
use App\Models\Formation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Formation> */
class FormationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'mention' => ucfirst(fake()->word()),
            'level' => FormationLevel::Licence,
            'duration' => '3 ans',
            'credits' => 180,
            'summary' => fake()->sentence(18),
            'description' => '<p>'.fake()->paragraph(4).'</p>',
            'career_prospects' => '<ul><li>'.fake()->sentence(4).'</li><li>'.fake()->sentence(4).'</li></ul>',
            'admission_requirements' => '<p>'.fake()->paragraph(2).'</p>',
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
        ];
    }
}
