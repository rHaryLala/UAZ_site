<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        return view('events.index', [
            'upcoming' => Event::visible()->upcoming()->with('cover.media')->orderBy('starts_at')->get(),
            'past' => Event::visible()->past()->with('cover.media')->orderByDesc('starts_at')->paginate(6),
        ]);
    }

    public function show(Event $event): View
    {
        $event->load(['cover.media', 'ogImage.media']);

        return view('events.show', ['event' => $event]);
    }
}
