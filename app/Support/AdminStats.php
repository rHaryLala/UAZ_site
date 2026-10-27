<?php

namespace App\Support;

use App\Enums\PublicationStatus;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\NewsPost;
use Illuminate\Support\Facades\Cache;

/**
 * Chiffres du tableau de bord et compteur de la navigation, en cache : sans cela, chaque page du back-office relançait ces requêtes.
 * Le cache est vidé dès qu'une actualité, un événement ou un message change (voir AppServiceProvider) ; il expire de toute façon
 * au bout de 2 minutes, car « événements à venir » et les publications programmées évoluent avec l'heure.
 */
class AdminStats
{
    private const KEY = 'admin.stats';

    /** @return array{news_published: int, news_drafts: int, events_upcoming: int, messages_unread: int} */
    public static function get(): array
    {
        return Cache::remember(self::KEY, 120, fn () => [
            'news_published' => NewsPost::published()->count(),
            'news_drafts' => NewsPost::where('status', PublicationStatus::Draft)->count(),
            'events_upcoming' => Event::published()->upcoming()->count(),
            'messages_unread' => ContactMessage::unread()->count(),
        ]);
    }

    public static function unreadMessages(): int
    {
        return self::get()['messages_unread'];
    }

    public static function flush(): void
    {
        Cache::forget(self::KEY);
    }
}
