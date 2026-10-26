<?php

namespace App\Http\Controllers;

use App\Enums\FormationLevel;
use App\Models\Faculty;
use App\Models\Formation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    /** Liste filtrable par niveau et par faculté/département (filtres en liens : fonctionne sans JavaScript). */
    public function index(Request $request): View
    {
        $level = FormationLevel::tryFrom((string) $request->query('niveau'));
        $faculty = Faculty::active()->where('slug', $request->query('faculte'))->first();

        $formations = Formation::visible()
            ->with(['faculty', 'cover.media'])
            ->when($level, fn ($q) => $q->where('level', $level->value))
            ->when($faculty, fn ($q) => $q->where('faculty_id', $faculty->id))
            ->orderBy('sort_order')->orderBy('title')
            ->get();

        return view('formations.index', [
            'formations' => $formations,
            'level' => $level,
            'faculty' => $faculty,
            'levels' => Formation::visible()->pluck('level')->unique()->values(),
            'faculties' => Faculty::active()->whereHas('formations', fn ($q) => $q->visible())->get(),
        ]);
    }

    public function show(Formation $formation): View
    {
        $formation->load(['faculty', 'cover.media', 'ogImage.media']);

        return view('formations.show', [
            'formation' => $formation,
            'related' => Formation::visible()->with('cover.media', 'faculty')
                ->where('id', '!=', $formation->id)
                ->when($formation->faculty_id, fn ($q) => $q->where('faculty_id', $formation->faculty_id))
                ->orderBy('sort_order')->limit(3)->get(),
        ]);
    }
}
