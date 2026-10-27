<?php

namespace App\Filament\Support;

use App\Models\MediaAsset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Sélecteur d'image de la médiathèque : recherche par titre / texte alternatif / nom de fichier,
 * aperçu en miniature, et envoi d'une nouvelle image sans quitter le formulaire.
 */
class MediaPicker
{
    public static function make(string $name, string $label = 'Image', bool $imagesOnly = true): Select
    {
        return Select::make($name)
            ->label($label)
            ->searchable()
            ->allowHtml()
            ->placeholder('Choisir dans la médiathèque…')
            ->noSearchResultsMessage('Aucune image trouvée. Utilisez « + » pour en envoyer une.')
            ->options(fn () => static::search('', $imagesOnly))
            ->getSearchResultsUsing(fn (string $search) => static::search($search, $imagesOnly))
            ->getOptionLabelUsing(fn ($value) => static::optionLabel(MediaAsset::find($value)))
            ->createOptionForm(static::uploadForm())
            ->createOptionModalHeading('Envoyer une image')
            ->createOptionUsing(fn (array $data): int => static::createFromUpload($data)->getKey());
    }

    /** @return array<int|string, HtmlString> */
    public static function search(string $term, bool $imagesOnly = true, int $limit = 30): array
    {
        return MediaAsset::query()
            ->with('media')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%$term%")
                ->orWhere('alt', 'like', "%$term%")
                ->orWhereHas('media', fn ($m) => $m->where('file_name', 'like', "%$term%"))))
            ->when($imagesOnly, fn ($q) => $q->whereHas('media', fn ($m) => $m->where('mime_type', 'like', 'image/%')))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset) => [$asset->getKey() => static::optionLabel($asset)])
            ->all();
    }

    public static function optionLabel(?MediaAsset $asset): HtmlString
    {
        if (! $asset) {
            return new HtmlString('');
        }

        // Styles en ligne : le CSS de Filament ne contient pas les classes Tailwind du site.
        $box = 'width:2.5rem;height:2.5rem;flex:none;border-radius:.375rem;';
        $thumb = $asset->isImage()
            ? '<img src="'.e($asset->url('thumb')).'" alt="" loading="lazy" style="'.$box.'object-fit:cover">'
            : '<span style="'.$box.'display:flex;align-items:center;justify-content:center;background:#f3f4f6;color:#4b5563;font-size:.7rem;font-weight:600">PDF</span>';

        return new HtmlString(
            '<span style="display:flex;align-items:center;gap:.75rem">'.$thumb.'<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'.e($asset->label()).'</span></span>',
        );
    }

    /** @return list<mixed> */
    public static function uploadForm(): array
    {
        return [
            FileUpload::make('file')
                ->label('Fichier')
                ->disk('local')
                ->directory('uploads-tmp')
                ->visibility('private')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(config('uaz.media.max_file_size_kb'))
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth((string) config('uaz.media.original_max_width'))
                ->imageResizeUpscale(false)
                ->required(),
            TextInput::make('alt')
                ->label('Texte alternatif')
                ->helperText('Décrivez l\'image pour les personnes qui ne la voient pas (ex. : « Étudiants devant la bibliothèque »).')
                ->required()
                ->maxLength(300),
            TextInput::make('title')->label('Titre (facultatif)')->maxLength(255),
        ];
    }

    /** Crée un élément de médiathèque à partir des données du formulaire d'envoi. */
    public static function createFromUpload(array $data): MediaAsset
    {
        $asset = MediaAsset::create([
            'title' => $data['title'] ?? null,
            'alt' => $data['alt'],
        ]);

        $disk = Storage::disk('local');
        $asset->addMedia($disk->path($data['file']))->toMediaCollection(MediaAsset::COLLECTION);

        return $asset;
    }
}
