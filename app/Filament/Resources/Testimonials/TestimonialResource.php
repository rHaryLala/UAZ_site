<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Support\MediaPicker;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Communauté';

    protected static ?string $modelLabel = 'témoignage';

    protected static ?string $pluralModelLabel = 'témoignages';

    protected static ?string $recordTitleAttribute = 'author_name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('author_name')->label('Nom')->required()->maxLength(255),
            TextInput::make('author_role')->label('Qualité')->placeholder('Ex. : Étudiant en Gestion, promotion 2025')
                ->maxLength(255),
            Textarea::make('quote')->label('Témoignage')->rows(4)->maxLength(800)->columnSpanFull()
                ->helperText('Texte du témoignage. Laissez vide si seule une vidéo est fournie.'),
            TextInput::make('video_url')->label('Vidéo YouTube (facultatif)')->url()->maxLength(500)->columnSpanFull(),
            MediaPicker::make('photo_id', 'Photo'),
            TextInput::make('sort_order')->label('Ordre d\'affichage')->numeric()->default(0)->minValue(0),
            Toggle::make('is_active')->label('Affiché sur le site')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_thumb')->label('Photo')->circular()->size(44)
                    ->state(fn (Testimonial $record) => $record->photo?->url('thumb')),
                TextColumn::make('author_name')->label('Nom')->searchable()->weight('medium'),
                TextColumn::make('author_role')->label('Qualité')->toggleable(),
                TextColumn::make('quote')->label('Témoignage')->limit(70)->placeholder('Vidéo uniquement'),
                ToggleColumn::make('is_active')->label('Affiché'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTestimonials::route('/')];
    }
}
