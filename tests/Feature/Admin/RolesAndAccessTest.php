<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function userWithRole(?string $role): User
{
    $user = User::factory()->create();

    if ($role) {
        $user->assignRole($role);
    }

    return $user;
}

it('redirects guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('renders the admin login page in French', function () {
    $this->get('/admin/login')->assertOk()->assertSee('Adresse e-mail', false);
});

it('lets every role enter the back-office', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertOk();
})->with([
    RolesAndPermissionsSeeder::SUPER_ADMIN,
    RolesAndPermissionsSeeder::EDITOR,
    RolesAndPermissionsSeeder::CONTRIBUTOR,
]);

it('forbids accounts without a role', function () {
    $this->actingAs(userWithRole(null))->get('/admin')->assertForbidden();
});

it('gives the super-admin every ability through Gate::before', function () {
    $admin = userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

    expect($admin->can('users.manage'))->toBeTrue()
        ->and($admin->can('anything.at.all'))->toBeTrue();
});

it('limits the editor to content, media, settings and messages', function () {
    $editor = userWithRole(RolesAndPermissionsSeeder::EDITOR);

    expect($editor->can('content.publish'))->toBeTrue()
        ->and($editor->can('media.manage'))->toBeTrue()
        ->and($editor->can('settings.manage'))->toBeTrue()
        ->and($editor->can('users.manage'))->toBeFalse();
});

it('only lets the contributor create drafts', function () {
    $contributor = userWithRole(RolesAndPermissionsSeeder::CONTRIBUTOR);

    expect($contributor->can('content.create'))->toBeTrue()
        ->and($contributor->can('content.publish'))->toBeFalse()
        ->and($contributor->can('content.delete'))->toBeFalse()
        ->and($contributor->can('media.manage'))->toBeFalse();
});
