<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Les photos de la page de connexion du back-office : de vraies photos de l'université, déjà publiées sur le site (images d'accueil et de
 * formations choisies dans les réglages, couvertures des pages, puis couvertures des dernières actualités). Rien n'est ajouté ni inventé :
 * seules comptent les photos en paysage, assez grandes pour être belles (et, pour les actualités, de vraies photos d'appareil). Les identifiants sont gardés dix minutes.
 */
final class LoginPhotos
{
    /** @return Collection<int, MediaAsset> */
    public static function pick(int $limit = 6): Collection
    {
        $ids = Cache::remember('login.photos.'.$limit, 600, function () use ($limit) {
            $settings = SiteSetting::current();
            $candidates = collect([$settings->home_hero_image_id, $settings->formations_hero_image_id, $settings->home_cta_image_id])
                ->merge(Page::published()->whereNotNull('cover_id')->orderBy('id')->pluck('cover_id'))
                ->filter()->unique()->values();
            $fromNews = NewsPost::published()->whereNotNull('cover_id')->orderByDesc('published_at')->limit(40)->pluck('cover_id')->filter()->unique()->diff($candidates)->values();
            $candidates = $candidates->merge($fromNews);

            $assets = MediaAsset::query()->with('media')->whereIn('id', $candidates)->get()->keyBy('id');

            // Les couvertures d'actualités viennent de tout : on ne garde que les photos d'appareil (IMG_…, DSC_…, UAZ…), au format 3:2.
            return $candidates->map(fn ($id) => $assets->get($id))
                ->filter(fn (?MediaAsset $asset) => $asset && self::isPhoto($asset) && (! $fromNews->contains($asset->id) || self::isCameraShot($asset)))
                ->take($limit)->pluck('id')->all();
        });

        $assets = MediaAsset::query()->with('media')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $assets->get($id))->filter()->values();
    }

    private static function isCameraShot(MediaAsset $asset): bool
    {
        [$width, $height] = $asset->dimensions();

        return preg_match('/^(UAZ|IMG|DSC)[_-]?\d/i', (string) $asset->file()?->file_name) === 1 && $width / $height >= 1.4 && $width / $height <= 1.6;
    }

    /** Une photo en paysage (3:2 à 16:9), d'au moins 1 600 px de large : ni capture d'écran, ni affiche, ni portrait. */
    private static function isPhoto(MediaAsset $asset): bool
    {
        [$width, $height] = $asset->dimensions();

        return $asset->file() !== null && $width >= 1600 && $height > 0 && $width / $height >= 1.3 && $width / $height <= 1.8;
    }
}
