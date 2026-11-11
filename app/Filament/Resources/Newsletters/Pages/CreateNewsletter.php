<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Models\Newsletter;
use App\Support\NewsletterBlocks;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletter extends CreateRecord
{
    protected static string $resource = NewsletterResource::class;

    /** Une lettre neuve démarre avec un titre, un texte et un bouton d'exemple, et le thème aux couleurs de l'UAZ. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + [
            'blocks' => Newsletter::startingBlocks(),
            'theme' => NewsletterBlocks::defaultTheme(),
            'status' => Newsletter::DRAFT,
            'created_by' => auth()->id(),
        ];
    }

    /** Après la création, on ouvre directement le plan de travail. */
    protected function getRedirectUrl(): string
    {
        return route('admin.newsletter-editor.show', $this->getRecord());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Lettre créée : composez-la sur le plan de travail.';
    }
}
