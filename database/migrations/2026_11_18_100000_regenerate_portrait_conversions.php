<?php

use App\Models\MediaAsset;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Les portraits (équipe, recherche, témoignages) ont une conversion plus fine, « portrait » (WebP qualité 94, 1200 px) : la qualité 80 des cartes
 * leur retirait un cinquième du détail. Les photos déjà en ligne la reçoivent ici ; en attendant, la page garde la version « card ».
 * La génération passe par la file d'attente (le site n'attend pas) ; un échec ici ne doit jamais bloquer un déploiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $assetIds = TeamMember::query()->whereNotNull('photo_id')->pluck('photo_id')
                ->merge(Testimonial::query()->whereNotNull('photo_id')->pluck('photo_id'))->unique()->values();

            $mediaIds = MediaAsset::query()->whereIn('id', $assetIds)->get()->map(fn (MediaAsset $asset) => $asset->file()?->getKey())->filter()->values()->all();

            if ($mediaIds === []) {
                return;
            }

            Artisan::call('media-library:regenerate', ['--ids' => $mediaIds, '--only' => ['portrait'], '--with-responsive-images' => true, '--force' => true]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        // Les conversions restent en place.
    }
};
