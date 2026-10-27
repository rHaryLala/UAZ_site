<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/** La page de recherche est un composant Livewire (App\Livewire\Search) : ce contrôleur ne fait que l'héberger. */
class SearchController extends Controller
{
    public function __invoke(): View
    {
        return view('search.index');
    }
}
