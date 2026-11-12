<?php

use App\Models\CafeteriaMenu;

// Pas de nettoyage ici : on vérifie ce que la migration a installé dans la base de test.
it('installed the three menus supplied by the cafeteria, active, one per week from Sunday to Saturday', function () {
    $weeks = CafeteriaMenu::orderBy('starts_on')->get();

    expect($weeks)->toHaveCount(3)
        ->and($weeks->map(fn ($menu) => $menu->starts_on->toDateString())->all())->toBe(['2026-11-08', '2026-11-15', '2026-11-22'])
        ->and($weeks->every(fn ($menu) => $menu->starts_on->isSunday() && $menu->is_active && ! $menu->isEmpty()))->toBeTrue()
        ->and($weeks[0]->days()[2]['meals']['morning'])->toContain('Potatoes and boiled leafy greens'); // le mardi du menu 1
});
