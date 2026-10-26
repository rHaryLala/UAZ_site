<?php

namespace Database\Factories;

/**
 * Génère des images de démonstration (dégradé bleu marine, triangle et filet or) avec GD.
 * Utilisé par les seeders et les tests : aucune image externe n'est téléchargée.
 */
class PlaceholderImage
{
    /** Couleurs provisoires du sceau ; les valeurs exactes sont définies en phase 3 (docs/DESIGN-SYSTEM.md). */
    private const NAVY = [27, 36, 95];

    private const NAVY_DARK = [10, 14, 44];

    private const GOLD = [160, 128, 70];

    /** Écrit une image JPEG dans le dossier temporaire et renvoie son chemin. */
    public static function make(int $width = 1600, int $height = 900, int $seed = 1, string $extension = 'jpg'): string
    {
        $image = imagecreatetruecolor($width, $height);

        // Dégradé vertical bleu marine.
        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $color = imagecolorallocate(
                $image,
                (int) (self::NAVY[0] + (self::NAVY_DARK[0] - self::NAVY[0]) * $t),
                (int) (self::NAVY[1] + (self::NAVY_DARK[1] - self::NAVY[1]) * $t),
                (int) (self::NAVY[2] + (self::NAVY_DARK[2] - self::NAVY[2]) * $t),
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        // Triangle décentré (motif du sceau), position variable selon la graine.
        $gold = imagecolorallocatealpha($image, self::GOLD[0], self::GOLD[1], self::GOLD[2], 70);
        $size = (int) ($height * (0.55 + ($seed % 4) * 0.08));
        $cx = (int) ($width * (0.25 + (($seed * 37) % 50) / 100));
        $cy = (int) ($height * 0.5);
        imagesetthickness($image, max(2, (int) ($height / 90)));
        imagepolygon($image, [
            $cx - $size / 2, $cy - $size / 3,
            $cx + $size / 2, $cy - $size / 3,
            $cx, $cy + $size * 0.55,
        ], $gold);

        $path = tempnam(sys_get_temp_dir(), 'uaz').'.'.$extension;
        match ($extension) {
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 85),
            default => imagejpeg($image, $path, 85),
        };

        return $path;
    }
}
