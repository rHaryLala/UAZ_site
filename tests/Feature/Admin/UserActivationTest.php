<?php

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

// Activer et désactiver un compte depuis la liste des utilisateurs (interrupteur), sauf les super-administrateurs.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->boss = User::factory()->create(['name' => 'Hary Lala', 'email' => 'hary@exemple.test'])->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
    $this->other = User::factory()->create(['name' => 'Mireille', 'email' => 'mireille@exemple.test'])->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
    $this->editor = User::factory()->create(['name' => 'Éditeur Test', 'email' => 'editeur@exemple.test'])->assignRole(RolesAndPermissionsSeeder::EDITOR);
    $this->contributor = User::factory()->create(['name' => 'Contributeur Test', 'email' => 'contributeur@exemple.test'])->assignRole(RolesAndPermissionsSeeder::CONTRIBUTOR);
});

function toggleAccount(User $record, bool $state): void
{
    Livewire::test(ManageUsers::class)->loadTable()->call('updateTableColumnState', 'is_active', (string) $record->getKey(), $state);
}

it('starts with every account active, including one just created without the column loaded', function () {
    expect(User::query()->where('is_active', false)->count())->toBe(0)
        ->and($this->editor->isActive())->toBeTrue()
        ->and(new User)->isActive()->toBeTrue();
});

it('lets the super-administrator deactivate and reactivate an editor or a contributor', function () {
    $this->actingAs($this->boss);

    toggleAccount($this->editor, false);
    expect($this->editor->refresh()->is_active)->toBeFalse()->and($this->contributor->refresh()->is_active)->toBeTrue();

    toggleAccount($this->contributor, false);
    expect($this->contributor->refresh()->is_active)->toBeFalse();

    toggleAccount($this->editor, true);
    expect($this->editor->refresh()->is_active)->toBeTrue();
});

it('shows a toggle for every account, switched off and greyed out for the super-administrators', function () {
    $this->actingAs($this->boss);

    $page = Livewire::test(ManageUsers::class)->loadTable()->assertSee('Actif')->assertSee('Hary Lala')->assertSee('Mireille')->assertSee('Éditeur Test');
    $html = $page->html();

    // Un interrupteur par compte ; ceux des deux super-administrateurs sont grisés (disabled), pas ceux de l'éditeur et du contributeur.
    expect(substr_count($html, 'role="switch"'))->toBe(4)
        ->and(substr_count($html, 'aria-disabled="true"'))->toBe(2);
});

it('never deactivates a super-administrator, nor oneself, even with a crafted request', function () {
    $this->actingAs($this->boss);

    toggleAccount($this->other, false);
    toggleAccount($this->boss, false);

    expect($this->other->refresh()->is_active)->toBeTrue()->and($this->boss->refresh()->is_active)->toBeTrue()
        ->and($this->other->canBeDeactivated())->toBeFalse()->and($this->editor->canBeDeactivated())->toBeTrue();
});

it('cuts the access of a deactivated account at once, even with a session already open, and gives it back when reactivated', function () {
    $this->actingAs($this->editor)->get('/admin')->assertOk();

    $this->editor->forceFill(['is_active' => false])->save();
    $this->actingAs($this->editor->refresh())->get('/admin')->assertForbidden();
    $this->actingAs($this->editor)->get('/admin/news-posts')->assertForbidden();

    $this->editor->forceFill(['is_active' => true])->save();
    $this->actingAs($this->editor->refresh())->get('/admin')->assertOk();
});

it('refuses to sign in a deactivated account, even with the right password', function () {
    $this->contributor->forceFill(['is_active' => false, 'password' => 'un-mot-de-passe-solide'])->save();

    Livewire::test(Login::class)
        ->fillForm(['email' => 'contributeur@exemple.test', 'password' => 'un-mot-de-passe-solide'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth()->check())->toBeFalse();

    $this->contributor->forceFill(['is_active' => true])->save();

    Livewire::test(Login::class)
        ->fillForm(['email' => 'contributeur@exemple.test', 'password' => 'un-mot-de-passe-solide'])
        ->call('authenticate')
        ->assertHasNoFormErrors();
});

it('filters the list on active and deactivated accounts, and keeps the deactivated ones listed', function () {
    $this->actingAs($this->boss);
    $this->contributor->forceFill(['is_active' => false])->save();

    Livewire::test(ManageUsers::class)->loadTable()->resetTableFilters()->assertSee('Contributeur Test')
        ->filterTable('is_active', false)->assertSee('Contributeur Test')->assertDontSee('Éditeur Test')
        ->filterTable('is_active', true)->assertSee('Éditeur Test')->assertDontSee('Contributeur Test');
});

it('keeps the user screen for the super-administrator only', function () {
    $this->actingAs($this->editor)->get('/admin/users')->assertForbidden();
    $this->actingAs($this->boss)->get('/admin/users')->assertOk();
});
