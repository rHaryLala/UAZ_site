<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
    public function show(string $slug): View|RedirectResponse
    {
        $project = Project::active()->where('slug', $slug)->with('cover.media')->firstOrFail();

        // Les dons se font sur le site dédié, s'il est défini : « Soutenir ce projet » y mène directement.
        if (filled(config('uaz.donation_url'))) {
            return redirect()->away(config('uaz.donation_url'));
        }

        return view('projects.show', [
            'project' => $project,
            'others' => Project::active()->where('id', '!=', $project->id)->with('cover.media')->limit(2)->get(),
        ]);
    }
}
