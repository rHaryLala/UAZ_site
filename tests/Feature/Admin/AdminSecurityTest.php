<?php

use App\Filament\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
});

function adminWithRole(string $role): User
{
    return tap(User::factory()->create(), fn (User $u) => $u->assignRole($role));
}

it('offers two-factor authentication (TOTP) to back-office users', function () {
    $providers = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders();

    expect($providers)->not->toBeEmpty()
        ->and(collect($providers)->first())->toBeInstanceOf(AppAuthentication::class);
});

it('stores the TOTP secret encrypted and hidden from serialization', function () {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $raw = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

    expect($raw)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->toArray())->not->toHaveKey('app_authentication_secret')
        ->and($user->fresh()->toArray())->not->toHaveKey('password');
});

it('rejects uploads that are not images or PDFs', function (string $name, string $mime) {
    actingAs(adminWithRole(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(CreateMediaAsset::class)
        ->fillForm(['file' => UploadedFile::fake()->create($name, 50, $mime), 'alt' => 'Fichier'])
        ->call('create')
        ->assertHasFormErrors(['file']);

    expect(MediaAsset::count())->toBe(0);
})->with([
    ['malware.exe', 'application/x-msdownload'],
    ['page.html', 'text/html'],
    ['script.php', 'application/x-php'],
    ['document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['image.svg', 'image/svg+xml'],
]);

it('rejects files larger than the configured maximum', function () {
    config(['uaz.media.max_file_size_kb' => 2048]);
    actingAs(adminWithRole(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(CreateMediaAsset::class)
        ->fillForm(['file' => UploadedFile::fake()->create('lourd.pdf', 5000, 'application/pdf'), 'alt' => 'Trop lourd'])
        ->call('create')
        ->assertHasFormErrors(['file']);

    expect(MediaAsset::count())->toBe(0);
});

it('accepts a PDF and an image', function () {
    actingAs(adminWithRole(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(CreateMediaAsset::class)
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('calendrier.pdf', '%PDF-1.4
1 0 obj<</Type/Catalog>>endobj
trailer<</Root 1 0 R>>
%%EOF'), 'alt' => 'Calendrier académique'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MediaAsset::first()->isPdf())->toBeTrue();
});

it('blocks the user management screen for editors and contributors', function (string $role) {
    actingAs(adminWithRole($role));

    $this->get('/admin/users')->assertForbidden();
})->with([RolesAndPermissionsSeeder::EDITOR, RolesAndPermissionsSeeder::CONTRIBUTOR]);

it('lets only the super-admin manage users, and never delete themselves', function () {
    $admin = adminWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
    $other = adminWithRole(RolesAndPermissionsSeeder::EDITOR);
    $second = adminWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

    actingAs($admin);
    $this->get('/admin/users')->assertOk();

    expect($admin->can('delete', $admin))->toBeFalse()
        ->and($admin->can('delete', $other))->toBeTrue()
        ->and($admin->can('delete', $second))->toBeTrue();

    // Le dernier super-admin ne peut être supprimé par personne.
    $second->delete();
    $lone = $admin->fresh();
    expect($lone->can('delete', $lone))->toBeFalse();
});

it('rate-limits back-office login attempts', function () {
    $user = User::factory()->create(['password' => 'bon-mot-de-passe']);

    foreach (range(1, 6) as $i) {
        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'mauvais')
            ->call('authenticate');
    }

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'bon-mot-de-passe')
        ->call('authenticate')
        ->assertNotified();

    $this->assertGuest();
});

it('does not expose the password hash or remember token on the user model', function () {
    expect(User::factory()->create()->toArray())->not->toHaveKeys(['password', 'remember_token']);
});
