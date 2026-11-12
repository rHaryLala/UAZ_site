<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Le site dit « newsletter » et non « lettre d'information » : la page « Confidentialité » déjà en base est mise au même vocabulaire.
 * Seules les phrases d'origine sont remplacées ; un texte réécrit dans le back-office n'est jamais modifié.
 */
return new class extends Migration
{
    private const REPLACEMENTS = [
        "la lettre d'information" => 'la newsletter',
        "La lettre d'information" => 'La newsletter',
        "n'est utilisée que pour cette lettre." => "n'est utilisée que pour cette newsletter.",
    ];

    public function up(): void
    {
        $replace = function (mixed $value) use (&$replace): mixed {
            if (is_array($value)) {
                return array_map($replace, $value);
            }

            return is_string($value) ? strtr($value, self::REPLACEMENTS) : $value;
        };

        Page::query()->get()->each(function (Page $page) use ($replace) {
            $changed = false;

            foreach (['blocks', 'draft', 'translations'] as $attribute) {
                $current = $page->getAttribute($attribute);

                if (! is_array($current)) {
                    continue;
                }

                $updated = $replace($current);

                if ($updated !== $current) {
                    $page->setAttribute($attribute, $updated);
                    $changed = true;
                }
            }

            if ($changed) {
                $page->save();
            }
        });
    }

    public function down(): void
    {
        // Le vocabulaire d'avant n'est pas rétabli.
    }
};
