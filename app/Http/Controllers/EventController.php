<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $upcoming = Event::visible()->upcoming()->with('cover.media')->orderBy('starts_at')->get();

        return view('events.index', [
            'upcoming' => $upcoming,
            // « Prochain rendez-vous » : le premier événement à venir, celui du compte à rebours.
            'next' => $upcoming->first(),
            'past' => Event::visible()->past()->with('cover.media')->orderByDesc('starts_at')->paginate(9),
        ]);
    }

    public function show(Event $event): View
    {
        $event->load(['cover.media', 'ogImage.media']);

        return view('events.show', ['event' => $event]);
    }
}
