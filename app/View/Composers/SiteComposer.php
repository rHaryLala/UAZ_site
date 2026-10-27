<?php

namespace App\View\Composers;

use App\Models\Menu;
use App\Models\SiteSetting;
use Illuminate\View\View;

/** Données communes à toutes les pages publiques : réglages du site et menus de navigation. */
class SiteComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'site' => SiteSetting::current(),
            'weather' => app(\App\Services\CampusWeather::class)->current(),
            'topbarMenu' => Menu::resolved('topbar'),
            'headerMenu' => Menu::resolved('header'),
            'footerMenu' => Menu::resolved('footer'),
        ]);
    }
}
