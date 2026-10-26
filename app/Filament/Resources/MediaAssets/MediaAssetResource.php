<?php

namespace App\Filament\Resources\MediaAssets;

use App\Filament\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Médias';

    protected static ?string $navigationLabel = 'Médiathèque';

    protected static ?string $modelLabel = 'média';

    protected static ?string $pluralModelLabel = 'médias';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                SpatieMediaLibraryFileUpload::make('file')
                    ->label('Fichier')
                    ->collection(MediaAsset::COLLECTION)
                    ->acceptedFileTypes(config('uaz.media.accepted_mime_types'))
                    ->maxSize(config('uaz.media.max_file_size_kb'))
                    ->helperText('Images (JPEG, PNG, WebP) ou PDF. Les grandes images sont réduites et compressées automatiquement.')
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth((string) config('uaz.media.original_max_width'))
                    ->imageResizeUpscale(false)
                    ->imageEditor()
                    ->downloadable()
                    ->openable()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('title')->label('Titre')->maxLength(255),
                TextInput::make('alt')
                    ->label('Texte alternatif')
                    ->helperText('Obligatoire. Décrivez ce que montre l\'image ; pour un PDF, indiquez son sujet.')
                    ->required()
                    ->maxLength(300),
                TextInput::make('caption')->label('Légende')->maxLength(500),
                TextInput::make('credit')->label('Crédit photo')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('file')
                    ->label('Aperçu')
                    ->collection(MediaAsset::COLLECTION)
                    ->conversion('thumb')
                    ->square()
                    ->size(56),
                TextColumn::make('title')->label('Titre')->searchable()->placeholder('—')->limit(40),
                TextColumn::make('alt')->label('Texte alternatif')->searchable()->limit(50)->placeholder('Manquant')->color('warning'),
                TextColumn::make('file_name')->label('Fichier')
                    ->state(fn (MediaAsset $record) => $record->file()?->file_name)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'media', fn (Builder $m) => $m->where('file_name', 'like', "%$search%"),
                    ))
                    ->toggleable(),
                TextColumn::make('size')->label('Poids')
                    ->state(fn (MediaAsset $record) => $record->file()?->human_readable_size)
                    ->toggleable(),
                TextColumn::make('created_at')->label('Ajouté le')->dateTime('d/m/Y')->sortable()->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Type')
                    ->options(['image' => 'Images', 'pdf' => 'PDF'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'image' => $query->whereHas('media', fn ($m) => $m->where('mime_type', 'like', 'image/%')),
                        'pdf' => $query->whereHas('media', fn ($m) => $m->where('mime_type', 'application/pdf')),
                        default => $query,
                    }),
                Filter::make('missing_alt')->label('Sans texte alternatif')
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('La médiathèque est vide')
            ->emptyStateDescription('Envoyez vos premières images avec « Envoi multiple ».');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'create' => CreateMediaAsset::route('/create'),
            'edit' => EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
