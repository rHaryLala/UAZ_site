<?php

namespace App\Filament\Support;

use App\Enums\PublicationStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

class PublicationSection
{
    public static function make(string $dateLabel = 'Date de publication'): Section
    {
        return Section::make('Publication')
            ->schema([
                Select::make('status')
                    ->label('Statut')
                    ->options(fn () => auth()->user()?->can('content.publish')
                        ? PublicationStatus::class
                        : [PublicationStatus::Draft->value => PublicationStatus::Draft->getLabel()])
                    ->default(PublicationStatus::Draft)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->helperText(fn () => auth()->user()?->can('content.publish')
                        ? null
                        : 'Seul un éditeur peut publier : votre contenu reste en brouillon jusqu\'à validation.'),
                DateTimePicker::make('published_at')
                    ->label($dateLabel)
                    ->seconds(false)
                    ->native(false)
                    ->helperText('Laissez vide pour publier immédiatement. Une date future programme la publication.'),
            ]);
    }
}
