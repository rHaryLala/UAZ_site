<?php

namespace App\Filament\Resources\Newsletters;

use App\Filament\Resources\Newsletters\Pages\CreateNewsletter;
use App\Filament\Resources\Newsletters\Pages\ListNewsletters;
use App\Models\Newsletter;
use App\Support\NewsletterTemplates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Newsletters : la liste des newsletters (brouillons, en cours d'envoi, envoyées) et leur suivi. La rédaction se fait dans l'éditeur
 * visuel (plan de travail), ouvert depuis chaque ligne : voir App\Http\Controllers\Admin\NewsletterEditorController.
 */
class NewsletterResource extends Resource
{
    protected static ?string $model = Newsletter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|\UnitEnum|null $navigationGroup = 'Personnes & messages';

    protected static ?string $modelLabel = 'newsletter';

    protected static ?string $pluralModelLabel = 'newsletters';

    protected static ?string $navigationLabel = 'Newsletter';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 1;

    /** Création rapide : l'objet et le texte d'aperçu ; tout le reste se compose sur le plan de travail. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nouvelle newsletter')
                ->description('Donnez un objet : vous composerez ensuite la newsletter directement sur le plan de travail.')
                ->schema([
                    TextInput::make('subject')->label('Objet du courrier')->required()->maxLength(190)
                        ->placeholder('Ex. : Les nouvelles de l\'UAZ, novembre 2026')
                        ->helperText('C\'est ce que les abonnés lisent en premier dans leur boîte de réception.'),
                    TextInput::make('preheader')->label('Texte d\'aperçu (facultatif)')->maxLength(190)
                        ->helperText('Une ligne affichée à côté de l\'objet dans la liste des messages.'),
                ]),
            Section::make('Modèle')->columnSpanFull()
                ->description('Une mise en page moderne déjà composée : vous changerez tout (textes, photos, couleurs, colonnes) sur le plan de travail.')
                ->schema([
                    ViewField::make('template')->hiddenLabel()->view('filament.forms.newsletter-template-picker')->default('news')->live()
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                            // L'objet est proposé d'après le modèle tant qu'on n'a rien écrit soi-même.
                            $built = $state ? NewsletterTemplates::build($state) : null;
                            $typed = trim((string) $get('subject'));

                            if ($built && ($typed === '' || in_array($typed, NewsletterTemplates::subjects(), true))) {
                                $set('subject', $built['subject']);
                            }
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'deliveries as sent_count' => fn ($q) => $q->where('status', 'sent'),
                'deliveries as failed_count' => fn ($q) => $q->where('status', 'failed'),
            ]))
            ->columns([
                TextColumn::make('subject')->label('Objet')->searchable()->weight('medium')->limit(70),
                TextColumn::make('status')->label('État')->badge()
                    ->formatStateUsing(fn (string $state) => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Newsletter::SENT => 'success',
                        Newsletter::SENDING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('recipients')->label('Envoyée à')
                    ->state(fn (Newsletter $record) => $record->isDraft() ? '—' : $record->sent_count.' / '.$record->recipients_count)
                    ->description(fn (Newsletter $record) => $record->failed_count > 0 ? $record->failed_count.' échec(s)' : null)
                    ->color(fn (Newsletter $record) => $record->failed_count > 0 ? 'danger' : null),
                TextColumn::make('sent_at')->label('Envoyée le')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('updated_at')->label('Modifiée le')->dateTime('d/m/Y H:i')->sortable()->toggleable(),
                TextColumn::make('creator.name')->label('Auteur')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Newsletter $record) => route('admin.newsletter-editor.show', $record))
            ->recordActions([
                Action::make('open')->label(fn (Newsletter $record) => $record->isDraft() ? 'Modifier' : 'Voir')
                    ->icon(Heroicon::OutlinedPencilSquare)->url(fn (Newsletter $record) => route('admin.newsletter-editor.show', $record)),
                ActionGroup::make([
                    Action::make('duplicate')->label('Dupliquer')->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->action(function (Newsletter $record): void {
                            $copy = $record->replicate(['status', 'sent_at', 'recipients_count']);
                            $copy->forceFill(['subject' => 'Copie de '.$record->subject, 'status' => Newsletter::DRAFT, 'created_by' => auth()->id()])->save();
                            Notification::make()->title('Newsletter dupliquée : c\'est un nouveau brouillon.')->success()->send();
                        }),
                    DeleteAction::make()->visible(fn (Newsletter $record) => $record->isDraft()),
                ])->icon(Heroicon::OutlinedEllipsisVertical)->tooltip('Actions'),
            ])
            ->emptyStateHeading('Aucune newsletter pour le moment')
            ->emptyStateDescription('Créez votre première newsletter : elle sera envoyée à tous les abonnés qui ont confirmé leur adresse.');
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [Newsletter::DRAFT => 'Brouillon', Newsletter::SENDING => 'Envoi en cours', Newsletter::SENT => 'Envoyée'];
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletters::route('/'), 'create' => CreateNewsletter::route('/create')];
    }
}
