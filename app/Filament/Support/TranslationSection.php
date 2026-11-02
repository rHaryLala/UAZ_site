<?php

namespace App\Filament\Support;

use App\Support\Locales;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Onglets « Traductions » (anglais, malagasy) d'un formulaire de contenu. Les champs écrivent dans la colonne JSON « translations »
 * du modèle (App\Models\Concerns\HasTranslations) ; un champ laissé vide s'affiche en français sur le site.
 *
 * Champs : ['title' => ['text', 'Titre'], 'summary' => ['textarea', 'Résumé'], 'body' => ['rich', 'Texte'],
 *           'blocks' => ['blocks', 'Contenu de la page'], 'key_figures' => ['labels', 'Chiffres clés'],
 *           'opening_hours' => ['hours', 'Horaires']]
 */
class TranslationSection
{
    /**
     * @param  array<string, array{0: string, 1: string}>  $fields
     * @param  (Closure(): ?Model)|null  $source  Modèle dont on affiche le texte français en aide (par défaut : l'enregistrement du formulaire).
     */
    public static function make(array $fields, ?Closure $source = null): Section
    {
        $tabs = collect(Locales::prefixed())->map(fn (string $code) => Tab::make(Locales::all()[$code]['name'])
            ->schema(collect($fields)->map(fn (array $spec, string $attribute) => self::field($code, $attribute, $spec[0], $spec[1], $source))->values()->all())
            ->columns(1))->all();

        return Section::make('Traductions')
            ->description('Anglais et malagasy. Un champ laissé vide s\'affiche en français sur le site.')
            ->icon(Heroicon::Language)
            ->collapsible()
            ->collapsed()
            ->schema([Tabs::make('translations')->tabs($tabs)->columnSpanFull()])
            ->columnSpanFull();
    }

    private static function field(string $code, string $attribute, string $type, string $label, ?Closure $source): mixed
    {
        $path = "translations.$code.$attribute";
        $hint = fn (?Model $record): ?string => ($model = $source ? $source() : $record) && is_scalar($model->getRawOriginal($attribute))
            ? 'Français : '.Str::limit(trim(strip_tags((string) $model->getRawOriginal($attribute))), 160)
            : null;

        return match ($type) {
            'textarea' => Textarea::make($path)->label($label)->rows(3)->maxLength(5000)->helperText($hint),
            'rich' => Rich::editor($path, $label)->helperText($hint),
            'blocks' => PageBlocks::builder($path, $label)
                ->helperText('Reprenez la même structure que le contenu français : traduisez chaque bloc, dans le même ordre.'),
            'labels' => Repeater::make($path)->label($label)->schema([
                TextInput::make('label')->label('Libellé traduit')->maxLength(200),
            ])->addActionLabel('Ajouter un libellé')
                ->helperText('Un libellé par chiffre, dans le même ordre que le français. Les valeurs ne changent pas.'),
            'hours' => Repeater::make($path)->label($label)->schema([
                TextInput::make('days')->label('Jours')->maxLength(80),
                TextInput::make('hours')->label('Horaires')->maxLength(80),
            ])->columns(2)->addActionLabel('Ajouter une ligne')
                ->helperText('Une ligne par ligne d\'horaires, dans le même ordre que le français.'),
            default => TextInput::make($path)->label($label)->maxLength(500)->helperText($hint),
        };
    }
}
