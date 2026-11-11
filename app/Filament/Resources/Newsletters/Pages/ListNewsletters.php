<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Models\Newsletter;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsletters extends ListRecords
{
    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle lettre')];
    }

    public function getSubheading(): ?string
    {
        $count = Newsletter::audienceCount();

        return $count === 0
            ? 'Aucun abonné actif pour le moment : une lettre ne partirait à personne.'
            : 'Chaque lettre part à '.$count.' abonné'.($count > 1 ? 's' : '').' actif'.($count > 1 ? 's' : '').' (adresse confirmée, non désinscrit).';
    }
}
