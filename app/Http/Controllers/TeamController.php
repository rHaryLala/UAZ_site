<?php

namespace App\Http\Controllers;

use App\Enums\TeamCategory;
use App\Models\TeamMember;
use Illuminate\Contracts\View\View;

class TeamController extends Controller
{
    public function __invoke(): View
    {
        $members = TeamMember::active()->with(['photo.media', 'faculty'])->get()->groupBy(fn (TeamMember $m) => $m->category->value);

        return view('team.index', [
            'groups' => collect(TeamCategory::cases())
                ->mapWithKeys(fn (TeamCategory $category) => [$category->getLabel() => $members->get($category->value, collect())])
                ->filter(fn ($group) => $group->isNotEmpty()),
        ]);
    }
}
