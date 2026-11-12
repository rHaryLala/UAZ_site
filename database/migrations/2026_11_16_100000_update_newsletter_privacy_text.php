<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * L'abonnement à la lettre d'information est désormais immédiat (plus d'e-mail de confirmation) : la page « Confidentialité » ne doit plus affirmer le contraire.
 * Seules les phrases d'origine (français et anglais) sont remplacées ; un texte réécrit dans le back-office n'est jamais modifié.
 */
return new class extends Migration
{
    private const REPLACEMENTS = [
        "</strong>, la date de votre demande et celle de votre confirmation. Nous vous envoyons d'abord un e-mail de confirmation : sans clic sur le lien, vous ne recevez rien et personne ne peut vous inscrire à votre place." => "</strong> et la date de votre abonnement. L'abonnement est immédiat : nous ne vous envoyons pas d'e-mail de confirmation.",
        '</strong>, the date of your request and the date of your confirmation. We first send you a confirmation email: without a click on the link, you receive nothing and nobody can sign you up in your place.' => '</strong> and the date of your subscription. Your subscription is immediate: we do not send you a confirmation email.',
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
        // Le texte d'avant décrivait un fonctionnement qui n'existe plus : on ne le rétablit pas.
    }
};
