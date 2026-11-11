<?php

use App\Models\Faculty;
use App\Models\Formation;
use App\Models\Page;
use App\Models\Project;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Refonte des traductions (5 novembre 2026) : applique aux textes déjà enregistrés en base (colonne « translations ») les formulations
 * améliorées du seeder de traductions (anglais américain, « major », « track », « Class schedules »…).
 *
 * Prudence : seule une valeur qui est EXACTEMENT l'ancienne valeur du seeder est remplacée. Une traduction modifiée à la main dans le
 * back-office n'est jamais touchée. Réversible : `down()` remet les anciennes formulations, avec la même précaution.
 */
return new class extends Migration
{
    /** Ancien texte => nouveau texte (anglais). */
    private const CHANGES = [
        'Research centre (ZRC)' => 'Research Center (ZRC)',
        'The research centre of Zurcher Adventist University.' => 'The research center of Zurcher Adventist University.',
        'Timetables' => 'Class schedules',
        'Admission after the baccalaureate, depending on the field of study and the criteria of each programme.' => 'Admission after the baccalaureate, depending on the major and the requirements of each program.',
        'Bachelor\'s fields of study' => 'Bachelor\'s majors',
        '7:30 am – 12 noon and 1:30 pm – 5:30 pm' => '7:30 am – 12 pm and 1:30 pm – 5:30 pm',
        '7:30 am – 12 noon' => '7:30 am – 12 pm',
        'Preparing today the leaders of tomorrow' => 'Preparing tomorrow\'s leaders today',
        'Academic excellence, a lively student life and personal development: UAZ supports you in a modern, green setting that supports well-being.' => 'Academic excellence, a vibrant student life and personal growth: UAZ is with you every step of the way, in a modern, green setting that supports well-being.',
    ];

    public function up(): void
    {
        $this->apply(self::CHANGES, 'Pathways:', 'Tracks:');
    }

    public function down(): void
    {
        $this->apply(array_flip(self::CHANGES), 'Tracks:', 'Pathways:');
    }

    /** @param  array<string, string>  $changes */
    private function apply(array $changes, string $summaryFrom, string $summaryTo): void
    {
        foreach ([Page::class, Formation::class, Faculty::class, Project::class, SiteSetting::class] as $model) {
            foreach ($model::query()->get() as $record) {
                $translations = $record->translations;

                if (! is_array($translations) || ! isset($translations['en'])) {
                    continue;
                }

                $english = $this->replace($translations['en'], $changes);

                // « Pathways: A · B. » devient « Tracks: A · B. » dans le résumé des formations.
                if ($model === Formation::class && is_string($english['summary'] ?? null) && str_starts_with($english['summary'], $summaryFrom)) {
                    $english['summary'] = $summaryTo.substr($english['summary'], strlen($summaryFrom));
                }

                if ($english !== $translations['en']) {
                    $translations['en'] = $english;
                    $record->translations = $translations;
                    $record->saveQuietly();
                }
            }
        }

        // Nom du site dans les titres d'onglet et le référencement : traduit seulement s'il est encore vide.
        if ($changes === self::CHANGES) {
            foreach (SiteSetting::query()->get() as $setting) {
                $translations = $setting->translations ?? [];
                foreach (['en' => 'Zurcher Adventist University', 'mg' => 'Oniversite Advantista Zurcher'] as $locale => $name) {
                    if (blank($translations[$locale]['site_name'] ?? null)) {
                        $translations[$locale]['site_name'] = $name;
                    }
                }
                $setting->translations = $translations;
                $setting->saveQuietly();
            }
        }

        // Les réglages du site sont gardés en cache une heure : on les relit tout de suite.
        Cache::forget('site-settings');
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @param  array<string, string>  $changes
     * @return array<int|string, mixed>
     */
    private function replace(array $value, array $changes): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->replace($item, $changes);
            } elseif (is_string($item) && isset($changes[$item])) {
                $value[$key] = $changes[$item];
            }
        }

        return $value;
    }
};
