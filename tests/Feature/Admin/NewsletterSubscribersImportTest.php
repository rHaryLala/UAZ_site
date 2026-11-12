<?php

use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

const IMPORT_HEADER = 'E-mail,Prénom,Nom,"Temps d’abonnement","Temps de confirmation","État de la liste","État global",Liste';

function importCsv(array $emails = ['nouveau@exemple.test']): UploadedFile
{
    $rows = collect($emails)->map(fn (string $email) => $email.',Prénom,Nom,"2026-07-02 05:27:33","",subscribed,subscribed,"Liste"')->implode("\n");

    return UploadedFile::fake()->createWithContent('MailPoet_export.csv', IMPORT_HEADER."\n".$rows."\n");
}

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('lets an administrator import a MailPoet export from the subscribers list', function () {
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));

    Livewire::test(ListNewsletterSubscribers::class)
        ->assertActionVisible('import')
        ->callAction('import', ['file' => importCsv(['un@exemple.test', 'deux@exemple.test']), 'dry_run' => false])
        ->assertHasNoActionErrors()
        ->assertNotified('Import terminé');

    expect(NewsletterSubscriber::active()->pluck('email')->sort()->values()->all())->toBe(['deux@exemple.test', 'un@exemple.test']);
});

it('only simulates when asked to, and writes nothing', function () {
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));

    Livewire::test(ListNewsletterSubscribers::class)
        ->callAction('import', ['file' => importCsv(), 'dry_run' => true])
        ->assertNotified('Simulation terminée');

    expect(NewsletterSubscriber::count())->toBe(0);
});

it('explains the problem when the file is not a MailPoet export', function () {
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));

    Livewire::test(ListNewsletterSubscribers::class)
        ->callAction('import', ['file' => UploadedFile::fake()->createWithContent('autre.csv', "Colonne A,Colonne B\n1,2\n"), 'dry_run' => false])
        ->assertNotified('Import impossible');

    expect(NewsletterSubscriber::count())->toBe(0);
});

it('requires a file', function () {
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));

    Livewire::test(ListNewsletterSubscribers::class)->callAction('import', ['file' => null])->assertHasActionErrors(['file' => 'required']);
});

it('keeps the import out of reach of editors, who can still read and export the list', function () {
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(ListNewsletterSubscribers::class)->assertOk()->assertActionHidden('import')->assertActionVisible('export');
});

it('does not let a contributor into the subscribers list at all', function () {
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::CONTRIBUTOR));

    Livewire::test(ListNewsletterSubscribers::class)->assertForbidden();
});
