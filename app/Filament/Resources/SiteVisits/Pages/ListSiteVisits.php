<?php

namespace App\Filament\Resources\SiteVisits\Pages;

use App\Filament\Resources\SiteVisits\SiteVisitResource;
use Filament\Resources\Pages\ListRecords;

class ListSiteVisits extends ListRecords
{
    protected static string $resource = SiteVisitResource::class;

    protected static ?string $title = 'Visiteurs du site';

    public function getSubheading(): ?string
    {
        return 'Un visiteur par jour : le numéro change chaque jour, personne ne peut être suivi d\'un jour à l\'autre. Le lieu est déduit de l\'adresse IP (qui n\'est jamais conservée) : c\'est celui du fournisseur d\'accès, au mieux la ville, jamais l\'adresse exacte.';
    }
}
