<?php

use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContentPages\Pages\CreatePage;
use App\Filament\Resources\NewsPosts\NewsPostResource;
use App\Filament\Resources\NewsPosts\Pages\CreateNewsPost;
use App\Filament\Resources\NewsPosts\Pages\EditNewsPost;
use App\Filament\Resources\NewsPosts\Pages\ListNewsPosts;
use App\Models\ContactMessage;
use App\Models\MediaAsset;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function userWith(string $role): User
{
    return tap(User::factory()->create(), fn (User $u) => $u->assignRole($role));
}

it('lets an editor publish a news post with an image and a scheduled date', function () {
    $cover = MediaAsset::factory()->create();
    actingAs($editor = userWith(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(CreateNewsPost::class)
        ->fillForm([
            'title' => 'Rentrée 2026',
            'excerpt' => 'La rentrée a eu lieu.',
            'body' => '<p>Texte de l\'article.</p>',
            'cover_id' => $cover->id,
            'status' => PublicationStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = NewsPost::firstWhere('title', 'Rentrée 2026');

    expect($post->slug)->toBe('rentree-2026')
        ->and($post->status)->toBe(PublicationStatus::Published)
        ->and($post->isLive())->toBeTrue()
        ->and($post->cover_id)->toBe($cover->id)
        ->and($post->user_id)->toBe($editor->id);
});

it('does not offer the published status to a contributor', function () {
    actingAs(userWith(RolesAndPermissionsSeeder::CONTRIBUTOR));

    Livewire::test(CreateNewsPost::class)
        ->fillForm(['title' => 'Proposition', 'status' => PublicationStatus::Published->value])
        ->call('create')
        ->assertHasFormErrors(['status']);

    Livewire::test(CreateNewsPost::class)
        ->fillForm(['title' => 'Proposition', 'status' => PublicationStatus::Draft->value])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(NewsPost::firstWhere('title', 'Proposition')->status)->toBe(PublicationStatus::Draft);
});

it('forces any contributor save back to draft at model level, whatever the form says', function () {
    actingAs($contributor = userWith(RolesAndPermissionsSeeder::CONTRIBUTOR));

    $post = NewsPost::factory()->create(['status' => PublicationStatus::Published]);

    expect($post->fresh()->status)->toBe(PublicationStatus::Draft)
        ->and($post->fresh()->user_id)->toBe($contributor->id); // l'auteur est renseigné automatiquement
});

it('only lets a contributor edit their own drafts', function () {
    $contributor = userWith(RolesAndPermissionsSeeder::CONTRIBUTOR);
    $mine = NewsPost::factory()->draft()->create(['user_id' => $contributor->id]);
    $theirs = NewsPost::factory()->draft()->create(['user_id' => userWith(RolesAndPermissionsSeeder::EDITOR)->id]);
    $minePublished = NewsPost::factory()->create(['user_id' => $contributor->id]);

    expect($contributor->can('update', $mine))->toBeTrue()
        ->and($contributor->can('update', $theirs))->toBeFalse()
        ->and($contributor->can('update', $minePublished))->toBeFalse()
        ->and($contributor->can('delete', $mine))->toBeFalse();

    actingAs($contributor);
    $this->get(NewsPostResource::getUrl('edit', ['record' => $theirs]))->assertForbidden();
    $this->get(NewsPostResource::getUrl('edit', ['record' => $mine]))->assertOk();
});

it('shows the scheduled status in the news list', function () {
    NewsPost::factory()->scheduled()->create(['title' => 'Bientôt']);
    actingAs(userWith(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(ListNewsPosts::class)->loadTable()->assertSee('Bientôt')->assertSee('Programmé');
});

it('keeps an existing draft editable and publishable by an editor', function () {
    $post = NewsPost::factory()->draft()->create();
    actingAs(userWith(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(EditNewsPost::class, ['record' => $post->getKey()])
        ->fillForm(['status' => PublicationStatus::Published->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->fresh()->isLive())->toBeTrue();
});

it('rejects a reserved top-level page slug and accepts it under a parent', function () {
    actingAs(userWith(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Test', 'slug' => 'actualites'])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    expect(Page::count())->toBe(0);
});

it('protects system pages from deletion', function () {
    $home = Page::factory()->create(['title' => 'Accueil', 'key' => 'home']);
    $free = Page::factory()->create(['title' => 'Libre']);
    $editor = userWith(RolesAndPermissionsSeeder::EDITOR);

    expect($editor->can('delete', $home))->toBeFalse()->and($editor->can('delete', $free))->toBeTrue();
});

it('saves the site settings', function () {
    actingAs(userWith(RolesAndPermissionsSeeder::EDITOR));

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_name' => 'Université Adventiste Zurcher',
            'phone' => '+261 00 00 000 00',
            'social_links' => ['facebook' => 'https://facebook.com/uazsambaina'],
            'key_figures' => [['value' => 200, 'suffix' => '+', 'label' => 'Hectares']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = SiteSetting::current()->fresh();

    expect($settings->phone)->toBe('+261 00 00 000 00')
        ->and($settings->social('facebook'))->toBe('https://facebook.com/uazsambaina')
        ->and($settings->key_figures[0]['label'])->toBe('Hectares');
});

it('denies the settings page to contributors', function () {
    actingAs(userWith(RolesAndPermissionsSeeder::CONTRIBUTOR));

    $this->get('/admin/manage-site-settings')->assertForbidden();
});

it('exports contact messages as CSV and neutralises spreadsheet formulas', function () {
    ContactMessage::factory()->create(['name' => 'Alice', 'subject' => '=HYPERLINK("http://x")', 'message' => 'Bonjour']);
    actingAs(userWith(RolesAndPermissionsSeeder::EDITOR));

    $component = Livewire::test(ListContactMessages::class)->callAction('export');

    $component->assertFileDownloaded();
    expect(ListContactMessages::safeCell('=1+1'))->toBe("'=1+1")
        ->and(ListContactMessages::safeCell('Bonjour'))->toBe('Bonjour')
        ->and(ListContactMessages::safeCell(null))->toBe('');
});

it('does not let contributors read contact messages', function () {
    actingAs(userWith(RolesAndPermissionsSeeder::CONTRIBUTOR));

    $this->get('/admin/contact-messages')->assertForbidden();
});

it('duplicates, publishes and unpublishes contents from the list actions', function (string $listClass, string $model, string $titleField) {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-admin'));
    $record = $model::factory()->create([$titleField => 'Contenu original', 'status' => \App\Enums\PublicationStatus::Draft]);

    Livewire::test($listClass)->loadTable()
        ->callTableAction('replicate', $record)
        ->assertNotified();

    $copy = $model::where($titleField, 'Contenu original (copie)')->first();
    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe(\App\Enums\PublicationStatus::Draft)
        ->and($copy->slug)->not->toBe($record->slug)
        ->and($copy->getKey())->not->toBe($record->getKey());

    Livewire::test($listClass)->loadTable()->callTableAction('publish', $record)->assertNotified();
    expect($record->refresh()->status)->toBe(\App\Enums\PublicationStatus::Published)->and($record->published_at)->not->toBeNull();

    Livewire::test($listClass)->loadTable()->callTableAction('unpublish', $record)->assertNotified();
    expect($record->refresh()->status)->toBe(\App\Enums\PublicationStatus::Draft);

    Livewire::test($listClass)->loadTable()
        ->callTableBulkAction('publishSelected', [$record, $copy])
        ->assertNotified();
    expect($record->refresh()->status)->toBe(\App\Enums\PublicationStatus::Published)
        ->and($copy->refresh()->status)->toBe(\App\Enums\PublicationStatus::Published);
})->with([
    'actualités' => [\App\Filament\Resources\NewsPosts\Pages\ListNewsPosts::class, \App\Models\NewsPost::class, 'title'],
    'événements' => [\App\Filament\Resources\Events\Pages\ListEvents::class, \App\Models\Event::class, 'title'],
    'formations' => [\App\Filament\Resources\Formations\Pages\ListFormations::class, \App\Models\Formation::class, 'title'],
    'pages' => [\App\Filament\Resources\ContentPages\Pages\ListPages::class, \App\Models\Page::class, 'title'],
]);

it('does not let a contributor publish from the quick actions', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('contributor'));
    $post = \App\Models\NewsPost::factory()->draft()->create();

    Livewire::test(\App\Filament\Resources\NewsPosts\Pages\ListNewsPosts::class)->loadTable()
        ->assertTableActionHidden('publish', $post);
});
