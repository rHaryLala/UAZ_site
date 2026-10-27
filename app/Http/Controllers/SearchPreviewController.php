<?php

namespace App\Http\Controllers;

use App\Services\SiteSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Fragment HTML des résultats pour la fenêtre de recherche de l'en-tête (appelé en JavaScript pendant la saisie). */
class SearchPreviewController extends Controller
{
    public function __invoke(Request $request, SiteSearch $search): View
    {
        $term = trim(mb_substr((string) $request->query('q', ''), 0, 100));

        return view('search.preview', ['term' => $term, 'results' => $search->search($term, compact: true)]);
    }
}
