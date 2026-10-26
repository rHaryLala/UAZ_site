<?php

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\NewsPost;
use App\Models\Page;

it('only exposes published content whose date is not in the future', function () {
    NewsPost::factory()->create(['title' => 'Visible']);
    NewsPost::factory()->draft()->create(['title' => 'Brouillon']);
    NewsPost::factory()->scheduled()->create(['title' => 'Programmé']);

    expect(NewsPost::published()->pluck('title')->all())->toBe(['Visible']);
});

it('labels content as draft, scheduled or published', function () {
    expect(NewsPost::factory()->make()->publicationLabel())->toBe('Publié')
        ->and(NewsPost::factory()->draft()->make()->publicationLabel())->toBe('Brouillon')
        ->and(NewsPost::factory()->scheduled()->make()->publicationLabel())->toBe('Programmé');
});

it('sets the publication date when publishing without one', function () {
    $post = NewsPost::factory()->create(['status' => PublicationStatus::Published, 'published_at' => null]);

    expect($post->fresh()->published_at)->not->toBeNull();
});

it('builds unique slugs from titles without overwriting a manual slug', function () {
    $a = NewsPost::factory()->create(['title' => 'Rentrée à l\'UAZ']);
    $b = NewsPost::factory()->create(['title' => 'Rentrée à l\'UAZ']);
    $c = NewsPost::factory()->create(['title' => 'Autre', 'slug' => 'mon-slug']);

    expect($a->slug)->toBe('rentree-a-luaz')
        ->and($b->slug)->toBe('rentree-a-luaz-1')
        ->and($c->slug)->toBe('mon-slug');
});

it('computes page paths from parents and cascades renames to children', function () {
    $admissions = Page::factory()->create(['title' => 'Admissions']);
    $licence = Page::factory()->create(['title' => 'Licence', 'parent_id' => $admissions->id]);

    expect($licence->path)->toBe('admissions/licence');

    $admissions->update(['slug' => 'inscriptions']);

    expect($licence->fresh()->path)->toBe('inscriptions/licence');
});

it('allows the same slug under different parents', function () {
    $a = Page::factory()->create(['title' => 'A']);
    $b = Page::factory()->create(['title' => 'B']);
    $one = Page::factory()->create(['title' => 'Documents', 'parent_id' => $a->id]);
    $two = Page::factory()->create(['title' => 'Documents', 'parent_id' => $b->id]);

    expect($one->slug)->toBe('documents')->and($two->slug)->toBe('documents')
        ->and($one->path)->not->toBe($two->path);
});

it('separates upcoming and past events', function () {
    Event::factory()->create(['title' => 'Futur']);
    Event::factory()->past()->create(['title' => 'Passé']);

    expect(Event::upcoming()->pluck('title')->all())->toBe(['Futur'])
        ->and(Event::past()->pluck('title')->all())->toBe(['Passé']);
});
