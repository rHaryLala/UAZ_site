<?php

use App\Http\Controllers\DesignSystemController;
use Illuminate\Support\Facades\Route;

// Catalogue du design system : jamais exposé en production (config/uaz.php).
if (config('uaz.design_system_enabled')) {
    Route::get('/design-system', DesignSystemController::class)->name('design-system');
}

Route::get('/', function () {
    return view('welcome');
});
