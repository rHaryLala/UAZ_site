<?php

namespace App\Filament\Widgets;

use App\Enums\PublicationStatus;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\NewsPost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Aperçu du site';

    protected function getStats(): array
    {
        $stats = [
            Stat::make('Actualités publiées', NewsPost::published()->count())
                ->description(NewsPost::where('status', PublicationStatus::Draft)->count().' brouillon(s)')
                ->icon('heroicon-o-newspaper'),
            Stat::make('Événements à venir', Event::published()->upcoming()->count())
                ->icon('heroicon-o-calendar-days'),
        ];

        if (auth()->user()?->can('messages.view')) {
            $stats[] = Stat::make('Messages non lus', ContactMessage::unread()->count())
                ->icon('heroicon-o-envelope')
                ->color(ContactMessage::unread()->exists() ? 'warning' : 'gray');
        }

        return $stats;
    }
}
