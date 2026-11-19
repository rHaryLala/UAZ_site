<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retrait de l'ancien WordPress (D-088) : les quatre téléchargements que le nouveau site citait encore sur « /wp-content/uploads/… » (trois
 * formulaires Word d'admission et le PDF du Yearbook) vivent désormais dans storage/app/public/documents/ (adresse /storage/documents/…).
 * Les liens des pages (blocs, traductions, brouillon) sont réécrits ici, dans le JSON tel qu'il est stocké (barres normales ou échappées).
 */
return new class extends Migration
{
    /** @var array<string, string> ancien nom de fichier => nouveau nom */
    private const FILES = [
        'Formulaire-de-demande-dadmission-new.docx' => 'Formulaire-de-demande-dadmission-new.docx',
        'Formulaire-de-recommandation-new-1.doc' => 'Formulaire-de-recommandation-new-1.doc',
        'Formulaire-de-demande-dadmission-Master-2025.docx' => 'Formulaire-de-demande-dadmission-Master-2025.docx',
        'Yearbook-2025-.pdf' => 'Yearbook-2025.pdf',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $columns = array_values(array_filter(['blocks', 'draft', 'translations'], fn (string $column) => Schema::hasColumn('pages', $column)));

        foreach (DB::table('pages')->get() as $page) {
            $changes = [];

            foreach ($columns as $column) {
                $value = $page->{$column};

                if (! is_string($value) || ! str_contains($value, 'wp-content')) {
                    continue;
                }

                $new = $this->rewrite($value);

                if ($new !== $value) {
                    $changes[$column] = $new;
                }
            }

            if ($changes !== []) {
                DB::table('pages')->where('id', $page->id)->update($changes);
            }
        }
    }

    public function down(): void
    {
        // Les liens ne reviennent pas à l'ancien WordPress.
    }

    private function rewrite(string $json): string
    {
        foreach (self::FILES as $old => $new) {
            $name = preg_quote($old, '~');
            $json = preg_replace_callback(
                '~https?:(?<s>\x5c?/)\k<s>(?:www\.)?zurcher\.edu\.mg\k<s>wp-content\k<s>uploads\k<s>\d{4}\k<s>\d{2}\k<s>'.$name.'~',
                fn (array $m) => $m['s'].'storage'.$m['s'].'documents'.$m['s'].$new,
                $json,
            );
        }

        return $json;
    }
};
