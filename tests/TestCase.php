<?php

namespace Tests;

use App\Support\Locales;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Une requête de test réutilise l'application de la précédente : le préfixe de langue forcé par une requête /en
     * se retrouverait devant l'adresse de la suivante. En production, chaque requête repart d'un état neuf.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        Locales::reset();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
