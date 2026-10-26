<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exporter en CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('messages.export'))
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /** Exporte les messages correspondant aux filtres et à la recherche en cours. */
    private function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : Excel affiche correctement les accents
            fputcsv($out, ['Date', 'Nom', 'E-mail', 'Téléphone', 'Sujet', 'Message', 'Lu'], ';');

            $query->clone()->chunk(200, function ($messages) use ($out): void {
                /** @var ContactMessage $m */
                foreach ($messages as $m) {
                    fputcsv($out, array_map(self::safeCell(...), [
                        $m->created_at->format('d/m/Y H:i'),
                        $m->name,
                        $m->email,
                        $m->phone,
                        $m->subject,
                        $m->message,
                        $m->read_at ? 'oui' : 'non',
                    ]), ';');
                }
            });

            fclose($out);
        }, 'messages-contact-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise l'injection de formules (=, +, -, @) à l'ouverture dans un tableur. */
    public static function safeCell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
