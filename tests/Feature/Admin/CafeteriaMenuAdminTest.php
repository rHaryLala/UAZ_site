<?php

use App\Filament\Resources\CafeteriaMenus\CafeteriaMenuResource;
use App\Filament\Resources\CafeteriaMenus\Pages\CreateCafeteriaMenu;
use App\Filament\Resources\CafeteriaMenus\Pages\EditCafeteriaMenu;
use App\Filament\Resources\CafeteriaMenus\Pages\ListCafeteriaMenus;
use App\Models\CafeteriaMenu;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 09:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::EDITOR));
    CafeteriaMenu::query()->delete(); // les trois semaines installées par la migration ne servent pas ici
});

afterEach(fn () => Carbon::setTestNow());

it('lists the weeks with their period and number of dishes', function () {
    $current = CafeteriaMenu::factory()->startingOn('2026-11-08')->create();
    $past = CafeteriaMenu::factory()->startingOn('2026-11-01')->create();
    $next = CafeteriaMenu::factory()->startingOn('2026-11-15')->create();

    $list = Livewire::test(ListCafeteriaMenus::class)->loadTable()->assertOk()
        ->assertCanSeeTableRecords([$next, $current, $past])
        ->assertSee('Semaine du 8 au 14 novembre 2026')->assertSee('En cours')->assertSee('À venir')->assertSee('Passée');

    // La semaine la plus récente en premier.
    expect($list->instance()->getTableRecords()->pluck('id')->all())->toBe([$next->id, $current->id, $past->id]);
});

it('creates a week from the form: one dish per line, saved as a list', function () {
    Livewire::test(CreateCafeteriaMenu::class)
        ->fillForm([
            'starts_on' => '2026-12-06',
            'is_active' => true,
            'note' => 'Menu susceptible de changer.',
            'meals.0.morning' => "Fried eggs\n  Rice with leafy greens  \n\nSoy milk",
            'meals.0.noon' => 'Chinese fried rice',
            'meals.6.evening' => "Omelette au poireau\nBanane",
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $menu = CafeteriaMenu::whereDate('starts_on', '2026-12-06')->firstOrFail();
    $days = $menu->days();

    expect($menu->is_active)->toBeTrue()->and($menu->note)->toBe('Menu susceptible de changer.')
        ->and($days[0]['meals']['morning'])->toBe(['Fried eggs', 'Rice with leafy greens', 'Soy milk'])
        ->and($days[0]['meals']['noon'])->toBe(['Chinese fried rice'])
        ->and($days[6]['meals']['evening'])->toBe(['Omelette au poireau', 'Banane']);
});

it('proposes the week after the last saved menu for a new one', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-22')->create();

    expect(CafeteriaMenuResource::nextWeekStart())->toBe('2026-11-29');

    CafeteriaMenu::query()->delete();
    expect(CafeteriaMenuResource::nextWeekStart())->toBe('2026-11-15'); // le prochain dimanche
});

it('refuses a second menu for the same week and requires a first day', function () {
    CafeteriaMenu::factory()->startingOn('2026-12-06')->create();

    Livewire::test(CreateCafeteriaMenu::class)->fillForm(['starts_on' => '2026-12-06'])->call('create')->assertHasFormErrors(['starts_on' => 'unique']);
    Livewire::test(CreateCafeteriaMenu::class)->fillForm(['starts_on' => null])->call('create')->assertHasFormErrors(['starts_on' => 'required']);

    expect(CafeteriaMenu::count())->toBe(1);
});

it('shows the dishes of a week one per line and saves the changes', function () {
    $menu = CafeteriaMenu::factory()->startingOn('2026-11-08')->create(['meals' => [['morning' => ['Riz', 'Thé'], 'noon' => ['Soupe'], 'evening' => []]]]);

    Livewire::test(EditCafeteriaMenu::class, ['record' => $menu->getKey()])
        ->assertFormSet(['meals.0.morning' => "Riz\nThé", 'meals.0.noon' => 'Soupe'])
        ->fillForm(['meals.0.morning' => "Riz\nThé\nCafé", 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $menu->refresh();
    expect($menu->days()[0]['meals']['morning'])->toBe(['Riz', 'Thé', 'Café'])->and($menu->is_active)->toBeFalse();
});

it('labels each tab with the day and date of the chosen week', function () {
    expect(CafeteriaMenuResource::dayLabel('2026-11-08', 0))->toBe('dimanche 8 nov.')
        ->and(CafeteriaMenuResource::dayLabel('2026-11-08', 6))->toBe('samedi 14 nov.')
        ->and(CafeteriaMenuResource::dayLabel(null, 2))->toBe('Jour 3');
});

it('duplicates a week to another date as an unpublished copy', function () {
    $menu = CafeteriaMenu::factory()->startingOn('2026-11-08')->create(['note' => 'À relire']);

    Livewire::test(ListCafeteriaMenus::class)
        ->callAction(TestAction::make('duplicate')->table($menu), ['starts_on' => '2026-12-13'])
        ->assertHasNoFormErrors();

    $copy = CafeteriaMenu::whereDate('starts_on', '2026-12-13')->firstOrFail();
    expect($copy->is_active)->toBeFalse()->and($copy->meals)->toBe($menu->meals)->and($copy->note)->toBe('À relire')
        ->and($menu->fresh()->is_active)->toBeTrue();
});

it('refuses to duplicate onto a week that already has a menu', function () {
    $menu = CafeteriaMenu::factory()->startingOn('2026-11-08')->create();
    CafeteriaMenu::factory()->startingOn('2026-11-15')->create();

    Livewire::test(ListCafeteriaMenus::class)
        ->callAction(TestAction::make('duplicate')->table($menu), ['starts_on' => '2026-11-15'])
        ->assertHasActionErrors(['starts_on' => 'unique']);

    expect(CafeteriaMenu::count())->toBe(2);
});

it('lets an editor manage the menus but not a contributor delete them', function () {
    $menu = CafeteriaMenu::factory()->create();

    Livewire::test(ListCafeteriaMenus::class)->assertTableActionVisible('delete', $menu);

    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::CONTRIBUTOR));
    Livewire::test(ListCafeteriaMenus::class)->assertTableActionHidden('delete', $menu);
});
