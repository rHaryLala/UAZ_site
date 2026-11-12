<?php

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use App\Support\MailpoetImport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Importer un export MailPoet')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                // Ajoute des personnes à une liste d'envoi : réservé aux administrateurs.
                ->visible(fn () => auth()->user()?->can('users.manage'))
                ->modalHeading('Importer les abonnés depuis MailPoet')
                ->modalDescription('Déposez le fichier CSV exporté de MailPoet. Les abonnés (« subscribed ») deviennent actifs ; les adresses non confirmées (« unconfirmed ») sont abonnées aussi, sans attendre de confirmation ; les désinscrits sont conservés comme tels ; les rebonds et adresses invalides sont ignorés. Une personne déjà désinscrite sur ce site n\'est jamais réinscrite, et l\'import peut être refait sans créer de doublons.')
                ->modalSubmitActionLabel('Importer')
                ->schema([
                    FileUpload::make('file')
                        ->label('Fichier CSV exporté de MailPoet')
                        ->required()
                        ->storeFiles(false) // le fichier (adresses de personnes) n'est conservé nulle part : il est lu puis oublié
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'])
                        ->maxSize(5120),
                    Toggle::make('dry_run')->label('Simulation : afficher le résultat sans rien enregistrer')->default(false),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'];
                    $file = is_array($file) ? reset($file) : $file;
                    $dryRun = (bool) ($data['dry_run'] ?? false);

                    try {
                        $result = MailpoetImport::run($file->getRealPath(), $dryRun);
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()->danger()->title('Import impossible')->body($e->getMessage())->persistent()->send();

                        return;
                    }

                    Notification::make()->success()->persistent()
                        ->title($dryRun ? 'Simulation terminée' : 'Import terminé')
                        ->body(implode("\n", MailpoetImport::summary($result, $dryRun)))
                        ->send();
                }),
            Action::make('export')
                ->label('Exporter les abonnés (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('messages.export'))
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /** Exporte les abonnés actifs (confirmés et non désinscrits), prêts à être importés dans un outil d'envoi. */
    private function exportCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : Excel affiche correctement les accents
            fputcsv($out, ['E-mail', 'Confirmé le'], ';');

            NewsletterSubscriber::query()->active()->orderBy('id')->chunk(500, function ($subscribers) use ($out): void {
                foreach ($subscribers as $subscriber) {
                    fputcsv($out, [ListContactMessages::safeCell($subscriber->email), $subscriber->confirmed_at->format('d/m/Y H:i')], ';');
                }
            });

            fclose($out);
        }, 'abonnes-newsletter-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
