<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\NewsPost;
use App\Models\PhotoAlbum;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:purge {--force : Ne pas demander de confirmation}')]
#[Description('Supprime les contenus de démonstration « (Démo) » créés par le seeder local')]
class PurgeDemoContent extends Command
{
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Supprimer tous les contenus « (Démo) » ?')) {
            return self::FAILURE;
        }

        $prefix = DemoContentSeeder::PREFIX.'%';
        $count = 0;

        foreach ([
            [NewsPost::class, 'title'], [Event::class, 'title'], [PhotoAlbum::class, 'title'],
            [TeamMember::class, 'name'], [Testimonial::class, 'author_name'], [MediaAsset::class, 'title'],
        ] as [$model, $column]) {
            $count += $model::where($column, 'like', $prefix)->get()->each->delete()->count();
        }

        $this->info("$count élément(s) de démonstration supprimé(s).");

        return self::SUCCESS;
    }
}
