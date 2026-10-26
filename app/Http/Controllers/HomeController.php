<?php

namespace App\Http\Controllers;

use App\Enums\FormationLevel;
use App\Models\Event;
use App\Models\Faculty;
use App\Models\Formation;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\Partner;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $site = SiteSetting::current();

        // Domaines de formation avec leurs mentions publiées (cartes de la section Formations).
        $domains = Faculty::active()
            ->with(['cover.media', 'formations' => fn ($q) => $q->visible()->orderBy('sort_order')->orderBy('title')])
            ->get()
            ->filter(fn (Faculty $faculty) => $faculty->formations->isNotEmpty())
            ->values();

        // Un résumé par niveau (Licence : 3 ans · 180 ECTS…), tiré des formations publiées.
        $levels = Formation::visible()->get()
            ->groupBy(fn (Formation $f) => $f->level->value)
            ->map(fn ($group) => $group->first())
            ->sortBy(fn (Formation $f) => array_search($f->level, FormationLevel::cases(), true))
            ->values();

        return view('home', [
            'site' => $site,
            'domains' => $domains,
            'levels' => $levels,
            'news' => NewsPost::visible()->with(['category', 'cover.media'])
                ->orderByDesc('is_featured')->orderByDesc('published_at')->limit(3)->get(),
            'events' => Event::visible()->upcoming()->with('cover.media')->orderBy('starts_at')->limit(3)->get(),
            'testimonials' => Testimonial::active()->with('photo.media')->get(),
            'partners' => Partner::active()->with('logo.media')->get(),
            'studentLife' => Page::byKey('student-life')?->children()->visible()->with('cover.media')->get() ?? collect(),
            'homePage' => Page::byKey('home'),
        ]);
    }
}
