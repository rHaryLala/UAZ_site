<?php

namespace Database\Seeders;

use App\Enums\TeamCategory;
use App\Models\Event;
use App\Models\Faculty;
use App\Models\MediaAsset;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\PhotoAlbum;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Contenus de DÉMONSTRATION (texte factice, images générées) pour développer et tester le thème.
 * Réservé à l'environnement local : tous les titres commencent par « (Démo) » pour ne jamais passer
 * pour de vrais contenus. À supprimer avant la mise en ligne (`php artisan demo:purge`).
 */
class DemoContentSeeder extends Seeder
{
    public const PREFIX = '(Démo) ';

    public function run(): void
    {
        if (NewsPost::where('title', 'like', self::PREFIX.'%')->exists()) {
            return; // déjà semé
        }

        // L'encodage AVIF est lent (≈ 2 min pour toute la démo) : le WebP suffit pour développer.
        config(['uaz.media.avif' => false]);

        $landscapes = $this->images(10, 1600, 900, 'Photographie de démonstration');
        $portraits = $this->images(8, 800, 1000, 'Portrait de démonstration');

        $this->news($landscapes);
        $this->events($landscapes);
        $this->team($portraits);
        $this->testimonials($portraits);
        $this->albums($landscapes);
    }

    /** @return Collection<int, MediaAsset> */
    private function images(int $count, int $width, int $height, string $alt): Collection
    {
        return collect(range(1, $count))->map(fn (int $i) => MediaAsset::factory()
            ->withImage($width, $height)
            ->create(['title' => self::PREFIX."Image $i", 'alt' => "$alt $i"]));
    }

    /** @param  Collection<int, MediaAsset>  $images */
    private function news(Collection $images): void
    {
        $categories = collect(['Communauté', 'Formation', 'Vie du campus'])
            ->map(fn (string $name) => NewsCategory::firstOrCreate(['name' => $name]));

        foreach (range(0, 5) as $i) {
            NewsPost::factory()->create([
                'title' => self::PREFIX.rtrim(fake()->sentence(7), '.'),
                'news_category_id' => $categories[$i % 3]->id,
                'cover_id' => $images[$i]->id,
                'is_featured' => $i === 0,
                'published_at' => now()->subDays(3 + $i * 9),
            ]);
        }
    }

    /** @param  Collection<int, MediaAsset>  $images */
    private function events(Collection $images): void
    {
        foreach (range(0, 3) as $i) {
            Event::factory()->create([
                'title' => self::PREFIX.rtrim(fake()->sentence(5), '.'),
                'cover_id' => $images[$i + 2]->id,
                'starts_at' => now()->addDays(10 + $i * 14)->setTime(9, 0),
                'ends_at' => now()->addDays(10 + $i * 14)->setTime(12, 0),
            ]);
        }

        foreach (range(0, 1) as $i) {
            Event::factory()->past()->create([
                'title' => self::PREFIX.rtrim(fake()->sentence(5), '.'),
                'cover_id' => $images[$i + 6]->id,
            ]);
        }
    }

    /** @param  Collection<int, MediaAsset>  $portraits */
    private function team(Collection $portraits): void
    {
        $faculties = Faculty::query()->pluck('id');

        foreach ($portraits as $i => $portrait) {
            TeamMember::factory()->create([
                'name' => self::PREFIX.fake()->name(),
                'category' => $i < 3 ? TeamCategory::Administration : TeamCategory::Teaching,
                'faculty_id' => $i < 3 ? null : $faculties->random(),
                'photo_id' => $portrait->id,
                'sort_order' => $i,
            ]);
        }
    }

    /** @param  Collection<int, MediaAsset>  $portraits */
    private function testimonials(Collection $portraits): void
    {
        foreach (range(0, 2) as $i) {
            Testimonial::factory()->create([
                'author_name' => self::PREFIX.fake()->name(),
                'photo_id' => $portraits[$i]->id,
                'sort_order' => $i,
            ]);
        }
    }

    /** @param  Collection<int, MediaAsset>  $images */
    private function albums(Collection $images): void
    {
        foreach (range(0, 1) as $a) {
            $album = PhotoAlbum::factory()->create([
                'title' => self::PREFIX.'Album '.($a + 1),
                'cover_id' => $images[$a]->id,
            ]);

            $album->photos()->sync(
                $images->slice($a * 3, 6)->values()->mapWithKeys(fn ($image, $pos) => [$image->id => ['position' => $pos]])->all(),
            );
        }
    }
}
