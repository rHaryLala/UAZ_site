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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListMediaAssets extends ListRecords
{
    protected static string $resource = MediaAssetResource::class;

    /** @var array<int, TemporaryUploadedFile> Fichiers glissés-déposés sur la zone de dépôt (resources/views/filament/media/dropzone.blade.php). */
    public array $dropped = [];

    /** Nombre de fichiers ajoutés à la grille à chaque « Charger plus ». */
    public const GRID_STEP = 36;

    /** Mode grille : on ajoute des fichiers à la suite au lieu de changer de page (comme la médiathèque de WordPress). */
    public function loadMoreMedia(): void
    {
        $this->tableRecordsPerPage = min((int) $this->tableRecordsPerPage + self::GRID_STEP, 1000);
    }

    /** @return array{shown: int, total: int} */
    public function mediaCounts(): array
    {
        $records = $this->getTableRecords();

        return ['shown' => $records->count(), 'total' => $records->total()];
    }

    // Une nouvelle recherche ou un nouveau filtre repart du début de la médiathèque.
    public function updatedTableSearch(): void
    {
        parent::updatedTableSearch();
        $this->tableRecordsPerPage = self::GRID_STEP;
    }

    public function updatedTableFilters(): void
    {
        parent::updatedTableFilters();
        $this->tableRecordsPerPage = self::GRID_STEP;
    }

    /**
     * Fichiers déposés : on les contrôle, on les range dans le dossier temporaire de l'envoi multiple, puis on ouvre ce formulaire déjà rempli
     * (le texte alternatif de chaque fichier reste à saisir : il est obligatoire).
     */
    public function updatedDropped(): void
    {
        $this->validate([
            'dropped' => ['max:30'],
            'dropped.*' => ['file', 'mimetypes:'.implode(',', config('uaz.media.accepted_mime_types')), 'max:'.config('uaz.media.max_file_size_kb')],
        ], [], ['dropped.*' => 'fichier']);

        $paths = collect($this->dropped)
            ->map(fn (TemporaryUploadedFile $file) => $file->storeAs('uploads-tmp', str($file->getClientOriginalName())->afterLast('/')->toString(), 'local'))
            ->all();
        $this->dropped = [];

        $this->mountAction('bulkUpload', ['files' => $paths]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Bascule liste / grille (choix conservé en session) : la grille montre de grandes vignettes, plus pratique pour retrouver une photo.
            Action::make('toggleLayout')
                ->label(fn () => MediaAssetResource::isGrid() ? 'Vue liste' : 'Vue grille')
                ->icon(fn () => MediaAssetResource::isGrid() ? Heroicon::OutlinedListBullet : Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->action(function () {
                    session(['media_layout' => MediaAssetResource::isGrid() ? 'list' : 'grid']);

                    $this->redirect(MediaAssetResource::getUrl(), navigate: true);
                }),
            Action::make('bulkUpload')
                ->label('Envoi multiple')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Envoyer plusieurs images')
                ->modalSubmitActionLabel('Enregistrer dans la médiathèque')
                ->visible(fn () => auth()->user()?->can('create', MediaAsset::class))
                ->fillForm(fn (array $arguments): array => isset($arguments['files']) ? [
                    'files' => $arguments['files'],
                    'items' => collect($arguments['files'])->map(fn (string $path) => ['file' => basename($path), 'alt' => ''])->all(),
                ] : [])
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
