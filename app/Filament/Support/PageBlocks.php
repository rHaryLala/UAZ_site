<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Blocs flexibles des pages : texte riche, image, galerie, citation, appel à l'action,
 * chiffres clés, liste de cartes. Stockés en JSON : [{type, data}].
 * Les types sont rendus par resources/views/components/blocks/{type}.blade.php.
 */
class PageBlocks
{
    public const TYPES = ['rich_text', 'image', 'gallery', 'quote', 'cta', 'key_figures', 'card_list', 'download_list'];

    public static function builder(string $name = 'blocks', string $label = 'Contenu de la page'): Builder
    {
        return Builder::make($name)
            ->label($label)
            ->addActionLabel('Ajouter un bloc')
            ->collapsible()
            ->cloneable()
            ->blockNumbers(false)
            ->blocks([
                Block::make('rich_text')->label('Texte')->icon('heroicon-o-document-text')->schema([
                    TextInput::make('heading')->label('Titre (facultatif)')->maxLength(200),
                    Rich::editor('content', 'Texte')->required(),
                    Select::make('width')->label('Largeur')->options(['normal' => 'Colonne de lecture', 'wide' => 'Large'])
                        ->default('normal')->selectablePlaceholder(false),
                ]),
                Block::make('image')->label('Image')->icon('heroicon-o-photo')->schema([
                    MediaPicker::make('image_id', 'Image')->required(),
                    TextInput::make('caption')->label('Légende (facultatif)')->maxLength(300),
                    Select::make('size')->label('Taille')->options(['normal' => 'Normale', 'wide' => 'Large', 'full' => 'Pleine largeur'])
                        ->default('wide')->selectablePlaceholder(false),
                ]),
                Block::make('gallery')->label('Galerie')->icon('heroicon-o-squares-2x2')->schema([
                    TextInput::make('title')->label('Titre (facultatif)')->maxLength(200),
                    Repeater::make('images')->label('Photos')->schema([
                        MediaPicker::make('image_id', 'Photo')->required(),
                        TextInput::make('caption')->label('Légende (facultatif)')->maxLength(300),
                    ])->addActionLabel('Ajouter une photo')->minItems(1)->reorderableWithButtons()->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['caption'] ?? null),
                ]),
                Block::make('quote')->label('Citation')->icon('heroicon-o-chat-bubble-bottom-center-text')->schema([
                    Textarea::make('text')->label('Citation')->rows(3)->required()->maxLength(600),
                    TextInput::make('author')->label('Auteur')->maxLength(150),
                    TextInput::make('role')->label('Fonction')->maxLength(150),
                ]),
                Block::make('cta')->label('Appel à l\'action')->icon('heroicon-o-megaphone')->schema([
                    TextInput::make('title')->label('Titre')->required()->maxLength(200),
                    Textarea::make('text')->label('Texte')->rows(2)->maxLength(400),
                    TextInput::make('button_label')->label('Texte du bouton')->required()->maxLength(60),
                    TextInput::make('button_url')->label('Lien du bouton')->required()
                        ->helperText('Adresse complète (https://…) ou chemin du site (/contact).')->maxLength(500),
                ]),
                Block::make('key_figures')->label('Chiffres clés')->icon('heroicon-o-chart-bar')->schema([
                    TextInput::make('title')->label('Titre (facultatif)')->maxLength(200),
                    Repeater::make('items')->label('Chiffres')->schema([
                        TextInput::make('value')->label('Valeur')->required()->numeric()->maxValue(1_000_000_000),
                        TextInput::make('suffix')->label('Suffixe')->placeholder('+, %, ha…')->maxLength(10),
                        TextInput::make('label')->label('Libellé')->required()->maxLength(80),
                    ])->columns(3)->minItems(1)->maxItems(6)->addActionLabel('Ajouter un chiffre')
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                ]),
                Block::make('download_list')->label('Liste de téléchargements')->icon('heroicon-o-arrow-down-tray')->schema([
                    TextInput::make('title')->label('Titre (facultatif)')->maxLength(200),
                    Textarea::make('intro')->label('Introduction (facultatif)')->rows(2)->maxLength(400),
                    Repeater::make('items')->label('Documents')->schema([
                        TextInput::make('title')->label('Titre du document')->required()->maxLength(160),
                        Textarea::make('text')->label('Description (facultatif)')->rows(2)->maxLength(400),
                        MediaPicker::make('file_id', 'Fichier de la médiathèque', false)
                            ->helperText('Choisissez un PDF envoyé dans la médiathèque… ou indiquez une adresse ci-dessous.'),
                        TextInput::make('url')->label('Adresse du fichier (si hors médiathèque)')->maxLength(500)
                            ->helperText('Adresse complète (https://…) : ancien site, Google Drive, etc.'),
                    ])->minItems(1)->addActionLabel('Ajouter un document')->collapsible()->reorderableWithButtons()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                ]),
                Block::make('card_list')->label('Liste de cartes')->icon('heroicon-o-rectangle-group')->schema([
                    TextInput::make('title')->label('Titre (facultatif)')->maxLength(200),
                    Textarea::make('intro')->label('Introduction (facultatif)')->rows(2)->maxLength(400),
                    Repeater::make('cards')->label('Cartes')->schema([
                        TextInput::make('title')->label('Titre')->required()->maxLength(120),
                        Textarea::make('text')->label('Texte')->rows(2)->maxLength(400),
                        MediaPicker::make('image_id', 'Image (facultatif)'),
                        TextInput::make('url')->label('Lien (facultatif)')->maxLength(500),
                        TextInput::make('link_label')->label('Texte du lien')->maxLength(60),
                    ])->minItems(1)->addActionLabel('Ajouter une carte')->collapsible()->reorderableWithButtons()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                ]),
            ]);
    }
}
