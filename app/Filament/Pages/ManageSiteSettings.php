<?php

namespace App\Filament\Pages;

use App\Filament\Support\MediaPicker;
use App\Filament\Support\TranslationSection;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Réglages globaux : identité, coordonnées, horaires, réseaux sociaux, accueil, pied de page.
 * Une seule ligne en base (SiteSetting::current()).
 */
class ManageSiteSettings extends Page
{
    protected string $view = 'filament.pages.manage-site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Site & médias';

    protected static ?string $navigationLabel = 'Réglages du site';

    protected static ?string $title = 'Réglages du site';

    protected static ?int $navigationSort = 4;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Réglages')->persistTabInQueryString()->tabs([
                    Tab::make('Identité')->icon(Heroicon::OutlinedIdentification)->schema([
                        TextInput::make('site_name')->label('Nom du site')->required()->maxLength(255),
                        TextInput::make('tagline')->label('Slogan')->maxLength(255),
                        MediaPicker::make('logo_id', 'Logo'),
                        MediaPicker::make('default_og_image_id', 'Image de partage par défaut')
                            ->helperText('Utilisée sur les réseaux sociaux quand une page n\'a pas d\'image.'),
                    ])->columns(2),
                    Tab::make('Coordonnées')->icon(Heroicon::OutlinedMapPin)->schema([
                        TextInput::make('address')->label('Adresse')->maxLength(255)->columnSpanFull(),
                        TextInput::make('phone')->label('Téléphone')->tel()->maxLength(100),
                        TextInput::make('email')->label('E-mail de contact')->email()->maxLength(255),
                        TextInput::make('latitude')->label('Latitude (carte)')->numeric()->minValue(-90)->maxValue(90),
                        TextInput::make('longitude')->label('Longitude (carte)')->numeric()->minValue(-180)->maxValue(180),
                        Repeater::make('opening_hours')->label('Horaires d\'ouverture')->schema([
                            TextInput::make('days')->label('Jours')->required()->placeholder('Lundi – vendredi')->maxLength(80),
                            TextInput::make('hours')->label('Horaires')->required()->placeholder('8 h – 16 h')->maxLength(80),
                        ])->columns(2)->addActionLabel('Ajouter une ligne')->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string => $state['days'] ?? null),
                    ])->columns(2),
                    Tab::make('Réseaux sociaux')->icon(Heroicon::OutlinedShare)->schema([
                        TextInput::make('social_links.facebook')->label('Facebook')->url()->maxLength(255),
                        TextInput::make('social_links.instagram')->label('Instagram')->url()->maxLength(255),
                        TextInput::make('social_links.linkedin')->label('LinkedIn')->url()->maxLength(255),
                        TextInput::make('social_links.youtube')->label('YouTube')->url()->maxLength(255),
                        TextInput::make('social_links.tiktok')->label('TikTok')->url()->maxLength(255)
                            ->placeholder('https://www.tiktok.com/@…'),
                    ])->columns(2),
                    Tab::make('Accueil')->icon(Heroicon::OutlinedHome)->schema([
                        TextInput::make('home_hero_title')->label('Titre principal')->maxLength(160)
                            ->helperText('Titre affiché en grand en haut de l\'accueil.'),
                        Textarea::make('home_hero_subtitle')->label('Sous-titre')->rows(2)->maxLength(300),
                        MediaPicker::make('home_hero_image_id', 'Image d\'en-tête'),
                        MediaPicker::make('home_cta_image_id', 'Image du bandeau final (« Votre avenir se dessine »)')
                            ->helperText('Photo en fond du dernier bandeau de l\'accueil (laisser vide pour le fond marine).'),
                        Repeater::make('key_figures')->label('Chiffres clés')->schema([
                            TextInput::make('value')->label('Valeur')->required()->numeric()->maxValue(1_000_000_000),
                            TextInput::make('suffix')->label('Suffixe')->placeholder('+, %, ha…')->maxLength(10),
                            TextInput::make('label')->label('Libellé')->required()->maxLength(80),
                        ])->columns(3)->maxItems(6)->addActionLabel('Ajouter un chiffre')->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->helperText('Ne saisissez que des chiffres officiels et vérifiés.'),
                    ])->columns(2),
                    Tab::make('Pages intérieures')->icon(Heroicon::OutlinedPhoto)->schema([
                        MediaPicker::make('formations_hero_image_id', 'Image d\'en-tête — page Formations')
                            ->helperText('Photo affichée derrière le titre de la page Formations (laisser vide pour le fond marine).'),
                    ]),
                    Tab::make('Pied de page')->icon(Heroicon::OutlinedRectangleGroup)->schema([
                        Textarea::make('footer_text')->label('Texte de présentation')->rows(3)->maxLength(500)->columnSpanFull(),
                        TextInput::make('footer_legal')->label('Mention légale')->maxLength(255)
                            ->placeholder('© Université Adventiste Zurcher')->columnSpanFull(),
                    ]),
                    Tab::make('Traductions')->icon(Heroicon::OutlinedLanguage)->schema([
                        TranslationSection::make([
                            'tagline' => ['text', 'Slogan'],
                            'home_hero_title' => ['text', 'Accueil : titre principal'],
                            'home_hero_subtitle' => ['textarea', 'Accueil : sous-titre'],
                            'key_figures' => ['labels', 'Accueil : libellés des chiffres clés'],
                            'opening_hours' => ['hours', 'Horaires d\'ouverture'],
                            'footer_text' => ['textarea', 'Pied de page : texte de présentation'],
                            'footer_legal' => ['text', 'Pied de page : mention légale'],
                        ], fn () => SiteSetting::current())->collapsed(false),
                    ]),
                ])->columnSpanFull(),
            ]);
    }

    /** @return list<Action> */
    public function getFormActions(): array
    {
        return [
            Action::make('save')->label('Enregistrer les réglages')->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::current()->update($data);

        Notification::make()->success()->title('Réglages enregistrés')->send();
    }
}
