<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Testimonial> */
class TestimonialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'author_role' => 'Étudiant(e)',
            'quote' => fake()->sentence(22),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
