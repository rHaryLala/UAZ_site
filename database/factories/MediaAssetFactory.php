<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaAsset> */
class MediaAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'alt' => fake()->sentence(6),
            'caption' => null,
            'credit' => null,
        ];
    }

    /** Attache une image de démonstration générée (conversions comprises). */
    public function withImage(int $width = 1600, int $height = 900): static
    {
        return $this->afterCreating(function (MediaAsset $asset) use ($width, $height) {
            $path = PlaceholderImage::make($width, $height, (int) $asset->getKey());
            $asset->addMedia($path)->usingFileName('demo-'.$asset->getKey().'.jpg')
                ->toMediaCollection(MediaAsset::COLLECTION);
        });
    }
}
