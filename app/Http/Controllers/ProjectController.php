<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;

class ProjectController extends Controller
{
    public function show(string $slug): View
    {
        $project = Project::active()->where('slug', $slug)->with('cover.media')->firstOrFail();

        return view('projects.show', [
            'project' => $project,
            'others' => Project::active()->where('id', '!=', $project->id)->with('cover.media')->limit(2)->get(),
        ]);
    }
}
