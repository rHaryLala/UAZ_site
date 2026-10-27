<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use Illuminate\Contracts\View\View;

class TeamController extends Controller
{
    /** Une seule liste, sans séparation entre administration et enseignants. */
    public function __invoke(): View
    {
        return view('team.index', [
            'members' => TeamMember::active()->with(['photo.media', 'faculty'])->get(),
        ]);
    }
}
