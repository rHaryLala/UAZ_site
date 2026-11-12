<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Models\Newsletter;
use App\Support\NewsletterTemplates;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletter extends CreateRecord
{
    protected static string $resource = NewsletterResource::class;

    /** Une newsletter neuve reprend le modèle choisi (blocs, couleurs, police ; photos de la médiathèque posées d'office). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $built = NewsletterTemplates::build((string) ($data['template'] ?? '')) ?? NewsletterTemplates::build('simple');
        unset($data['template']);

        return $data + [
            'blocks' => $built['blocks'],
            'theme' => $built['theme'],
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
        return 'Newsletter créée : composez-la sur le plan de travail.';
    }
}
