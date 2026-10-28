<?php

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                    fputcsv($out, [\App\Filament\Resources\ContactMessages\Pages\ListContactMessages::safeCell($subscriber->email), $subscriber->confirmed_at->format('d/m/Y H:i')], ';');
                }
            });

            fclose($out);
        }, 'abonnes-lettre-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
