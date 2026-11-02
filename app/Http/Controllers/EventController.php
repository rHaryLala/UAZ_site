<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\EventCalendar;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

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

        return view('events.show', [
            'event' => $event,
            // « Autres rendez-vous » : les trois prochains événements, sans celui-ci.
            'others' => Event::visible()->upcoming()->with('cover.media')->where('id', '!=', $event->id)->orderBy('starts_at')->limit(3)->get(),
        ]);
    }

    /** « Ajouter à mon agenda » : le fichier iCalendar de l'événement. */
    public function calendar(Event $event): Response
    {
        return response(EventCalendar::ics($event), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$event->slug.'.ics"',
        ]);
    }
}
