<?php

namespace App\Http\Controllers;

use App\Services\CampusWeather;
use Illuminate\Http\JsonResponse;

/** Météo du campus en JSON, pour que le pied de page se rafraîchisse sans recharger la page (resources/js/weather.js). */
class WeatherController extends Controller
{
    public function __invoke(CampusWeather $weather): JsonResponse
    {
        return response()->json($weather->current())->header('Cache-Control', 'public, max-age=300');
    }
}
