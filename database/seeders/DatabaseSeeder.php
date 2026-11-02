<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            StructureSeeder::class,
            ContentTranslationsSeeder::class,
        ]);

        // Contenus factices uniquement en développement local.
        if (app()->environment('local')) {
            $this->call(DemoContentSeeder::class);
        }
    }
}
