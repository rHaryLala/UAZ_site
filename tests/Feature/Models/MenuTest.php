<?php

use App\Models\Menu;
use App\Models\Page;

it('resolves page links, skips unpublished pages and keeps external urls', function () {
    $live = Page::factory()->create(['title' => 'Admissions']);
    $draft = Page::factory()->draft()->create(['title' => 'Secret']);

    Menu::create(['name' => 'Principal', 'handle' => 'header', 'items' => [
        ['label' => 'Admissions', 'type' => 'page', 'page_id' => $live->id, 'children' => [
            ['label' => 'Cachée', 'type' => 'page', 'page_id' => $draft->id],
        ]],
        ['label' => 'Facebook', 'type' => 'url', 'url' => 'https://facebook.com/uazsambaina', 'new_tab' => true],
        ['label' => 'Accueil', 'type' => 'url', 'url' => '/'],
    ]]);

    $tree = Menu::resolved('header');

    expect($tree)->toHaveCount(3)
        ->and($tree[0]['url'])->toBe(url('/admissions'))
        ->and($tree[0]['children'])->toBe([])
        ->and($tree[1]['new_tab'])->toBeTrue()
        ->and($tree[2]['url'])->toBe(url('/'));
});

it('returns an empty tree for an unknown menu', function () {
    expect(Menu::resolved('inconnu'))->toBe([]);
});
