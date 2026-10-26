<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListMediaAssets extends ListRecords
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkUpload')
                ->label('Envoi multiple')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Envoyer plusieurs images')
                ->modalSubmitActionLabel('Enregistrer dans la médiathèque')
                ->visible(fn () => auth()->user()?->can('create', MediaAsset::class))
                ->schema([
                    FileUpload::make('files')
                        ->label('Fichiers')
                        ->helperText('Sélectionnez ou déposez plusieurs fichiers à la fois (30 maximum).')
                        ->multiple()
                        ->maxFiles(30)
                        ->disk('local')
                        ->directory('uploads-tmp')
                        ->visibility('private')
                        ->preserveFilenames()
                        ->acceptedFileTypes(config('uaz.media.accepted_mime_types'))
                        ->maxSize(config('uaz.media.max_file_size_kb'))
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth((string) config('uaz.media.original_max_width'))
                        ->imageResizeUpscale(false)
                        ->live()
                        ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                            $previous = collect($get('items') ?? [])->keyBy('file');

                            $set('items', collect($state)->map(function ($file) use ($previous) {
                                $name = is_string($file) ? basename($file) : $file->getClientOriginalName();

                                return [
                                    'file' => $name,
                                    'alt' => $previous[$name]['alt'] ?? '',
                                ];
                            })->values()->all());
                        })
                        ->required(),
                    Repeater::make('items')
                        ->label('Texte alternatif de chaque fichier (obligatoire)')
                        ->schema([
                            Hidden::make('file'),
                            TextInput::make('alt')
                                ->label(fn (Get $get) => $get('file'))
                                ->helperText('Décrivez ce que montre l\'image ; pour un PDF, indiquez son sujet.')
                                ->required()
                                ->maxLength(300),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->visible(fn (Get $get) => filled($get('files'))),
                ])
                ->action(function (array $data): void {
                    $alts = collect($data['items'] ?? [])->pluck('alt', 'file');
                    $disk = Storage::disk('local');
                    $count = 0;

                    foreach ($data['files'] as $path) {
                        $name = basename($path);
                        $asset = MediaAsset::create([
                            'title' => pathinfo($name, PATHINFO_FILENAME),
                            'alt' => (string) $alts->get($name, ''),
                        ]);
                        $asset->addMedia($disk->path($path))->toMediaCollection(MediaAsset::COLLECTION);
                        $count++;
                    }

                    Notification::make()->success()->title("$count fichier(s) ajouté(s) à la médiathèque")->send();
                }),
            CreateAction::make()->label('Envoyer un fichier'),
        ];
    }
}
