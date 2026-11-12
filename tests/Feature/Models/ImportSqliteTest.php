<?php

use App\Models\Formation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Fabrique une base SQLite « de poste de développement » (migrations jouées) et renvoie son chemin. */
function devSqlite(): string
{
    $path = tempnam(sys_get_temp_dir(), 'uazdev').'.sqlite';
    touch($path);
    config(['database.connections.dev_copy' => ['driver' => 'sqlite', 'database' => $path, 'foreign_key_constraints' => false]]);
    DB::purge('dev_copy');
    Artisan::call('migrate', ['--database' => 'dev_copy', '--force' => true]);

    return $path;
}

it('copies the content of a SQLite file into the configured database', function () {
    $path = devSqlite();
    DB::connection('dev_copy')->table('formations')->insert([
        'id' => 41, 'title' => 'Licence en Test', 'slug' => 'licence-en-test', 'level' => 'licence', 'status' => 'published',
        'description' => '<p>Présentation</p>', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('db:import-sqlite', ['path' => $path])->assertSuccessful();

    expect(Formation::find(41)?->title)->toBe('Licence en Test')->and(Formation::find(41)->getRawOriginal('description'))->toBe('<p>Présentation</p>');
});

it('leaves out sessions, test messages, subscribers and letters', function () {
    $path = devSqlite();
    $source = DB::connection('dev_copy');
    $source->table('contact_messages')->insert(['name' => 'Essai', 'email' => 'essai@exemple.mg', 'subject' => 'Test', 'message' => 'Bonjour', 'created_at' => now(), 'updated_at' => now()]);
    $source->table('newsletter_subscribers')->insert(['email' => 'abonne@exemple.mg', 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('db:import-sqlite', ['path' => $path])->assertSuccessful();

    expect(DB::table('contact_messages')->count())->toBe(0)->and(DB::table('newsletter_subscribers')->count())->toBe(0);
});

it('replaces the passwords of the accounts unless asked to keep them', function () {
    $path = devSqlite();
    DB::connection('dev_copy')->table('users')->insert(['id' => 7, 'name' => 'Dev', 'email' => 'dev@uaz.test', 'password' => Hash::make('mot-de-passe-local'), 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('db:import-sqlite', ['path' => $path])->assertSuccessful();
    expect(Hash::check('mot-de-passe-local', User::find(7)->password))->toBeFalse();

    $this->artisan('db:import-sqlite', ['path' => $path, '--keep-passwords' => true])->assertSuccessful();
    expect(Hash::check('mot-de-passe-local', User::find(7)->password))->toBeTrue();
});

it('fails clearly when the file does not exist', function () {
    $this->artisan('db:import-sqlite', ['path' => '/nulle/part.sqlite'])->assertFailed();
});
