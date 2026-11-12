<?php

use App\Models\Formation;
use App\Support\FormationContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Remplace les « présentation » et « débouchés » encore en TODO-CONTENU des licences par ceux de la fiche du site zurcher.edu.mg,
 * avec leurs versions anglaise et malagasy. Une saisie déjà faite dans le back-office n'est jamais écrasée.
 */
return new class extends Migration
{
    private const FIELDS = ['description' => 'description', 'career_prospects' => 'careers'];

    public function up(): void
    {
        Formation::query()->get()->each(function (Formation $formation) {
            $mention = (string) $formation->mention;

            if (! FormationContent::has($mention)) {
                return;
            }

            $translations = $formation->translations ?? [];

            foreach (self::FIELDS as $field => $method) {
                $current = (string) $formation->getRawOriginal($field);
                $french = FormationContent::$method($mention);

                // Texte d'attente, ou déjà le nôtre (migration rejouée) : on remplit aussi les traductions encore vides.
                if (! str_contains($current, 'TODO-CONTENU') && $current !== $french) {
                    continue;
                }

                $formation->forceFill([$field => $french]);

                foreach (['en', 'mg'] as $locale) {
                    if (blank($translations[$locale][$field] ?? null)) {
                        $translations[$locale][$field] = FormationContent::$method($mention, $locale);
                    }
                }
            }

            $formation->translations = $translations;
            $formation->save();
        });
    }

    public function down(): void
    {
        // Le contenu rempli est conservé : revenir à du texte d'attente n'aurait aucun intérêt.
    }
};
