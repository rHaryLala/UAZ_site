<?php

use App\Enums\FormationLevel;
use App\Models\Formation;
use App\Models\TeamMember;
use App\Support\FormationAdmission;
use Illuminate\Database\Migrations\Migration;

/**
 * Remplace les conditions d'admission encore en « TODO-CONTENU » des licences par celles du site zurcher.edu.mg (matières du test et
 * séries de Baccalauréat par mention), et ajoute au recteur la phrase de son « Mot du recteur ». Une saisie déjà faite dans le
 * back-office n'est jamais écrasée.
 */
return new class extends Migration
{
    private const RECTOR_EMAIL = 'rector@zurcher.edu.mg';

    private const RECTOR_QUOTE = 'Préparer les leaders de demain est une noble et importante mission.';

    public function up(): void
    {
        Formation::query()->where('level', FormationLevel::Licence)->get()->each(function (Formation $formation) {
            $html = FormationAdmission::html((string) $formation->mention);

            if ($html && str_contains((string) $formation->admission_requirements, 'TODO-CONTENU')) {
                $formation->update(['admission_requirements' => $html]);
            }
        });

        TeamMember::query()->where('email', self::RECTOR_EMAIL)->whereNull('quote')->get()
            ->each(fn (TeamMember $member) => $member->update(['quote' => self::RECTOR_QUOTE]));
    }

    public function down(): void
    {
        TeamMember::query()->where('email', self::RECTOR_EMAIL)->where('quote', self::RECTOR_QUOTE)->get()
            ->each(fn (TeamMember $member) => $member->update(['quote' => null]));
    }
};
