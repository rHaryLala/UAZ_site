<?php

use App\Models\MediaAsset;
use App\Models\Page;
use App\Support\FeeCatalog;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));

it('keeps every transcribed column equal to the total printed on the official tables', function () {
    $years = array_keys(config('fees.licence.years'));
    $checked = 0;

    foreach (config('fees.licence.departments') as $department => $data) {
        foreach ($data['semesters'] as $number => $semester) {
            foreach ($years as $yearIndex => $year) {
                foreach (['interne', 'externe'] as $status) {
                    $sum = FeeCatalog::sum(FeeCatalog::licenceLines($semester, $yearIndex, $status));

                    expect($sum)->toBe(FeeCatalog::licencePublishedTotal($semester, $yearIndex, $status), "$department S$number $year $status");
                    $checked++;
                }
            }
        }
    }

    foreach (config('fees.master.tracks') as $track => $data) {
        foreach ($data['semesters'] as $number => $semester) {
            expect(array_sum(array_column($semester['rows'], 'amount')))->toBe($semester['total'], "master $track S$number");
            $checked++;
        }
    }

    expect($checked)->toBe(35);
});

it('builds the catalogue the simulator reads, with totals that are the sum of the lines', function () {
    $catalog = FeeCatalog::catalog();

    expect($catalog['licence']['sciences-infirmieres']['l1']['interne'][1]['total'])->toBe(2354000)
        ->and($catalog['licence']['sciences-infirmieres']['l1']['externe'][2]['total'])->toBe(954000)
        ->and($catalog['licence']['autres']['l3']['interne'][2]['total'])->toBe(2725000)
        ->and($catalog['licence']['autres']['l3']['externe'][1]['total'])->toBe(853000)
        ->and($catalog['master']['m1'][1]['total'])->toBe(957000)
        ->and($catalog['master']['m1'][2]['total'])->toBe(900000)
        ->and($catalog['master']['m2'][2]['total'])->toBe(1600000)
        ->and($catalog['master']['m2'])->not->toHaveKey(1);

    // Un externe ne paie ni le dortoir ni la cantine.
    $labels = array_column($catalog['licence']['sciences-infirmieres']['l1']['externe'][1]['lines'], 'label');
    expect($labels)->not->toContain('Dortoir')->not->toContain('Cantine');
});

it('formats amounts the French way, with non-breaking spaces', function () {
    expect(FeeCatalog::format(2354000))->toBe("2\u{00A0}354\u{00A0}000")->and(FeeCatalog::format(null))->toBe('–');
});

it('serves the fees page with the simulator, the payment plans, the official tables and the finance contact', function () {
    $html = $this->get('/admissions/frais-academiques')->assertOk()
        ->assertSee('data-fee-hero', false)
        ->assertSee('data-fee-sim', false)
        ->assertSee('data-fee-pay', false)
        ->assertSee('data-fee-tables', false)
        ->assertSee('data-fee-contact', false)
        ->assertSee('chiefaccount@zurcher.edu.mg')
        ->assertSee('034 13 859 75')
        ->getContent();

    expect(substr_count($html, 'data-fee-detail>'))->toBe(6)
        ->and(substr_count($html, '<table class="fee-grid'))->toBe(6)
        ->and($html)->toContain("2\u{00A0}354\u{00A0}000")->toContain("1\u{00A0}600\u{00A0}000")->toContain("1\u{00A0}188\u{00A0}000")
        ->and($html)->toContain('data-fee-catalog')->toContain('"sciences-infirmieres"');
});

it('shows the payment plans and methods of the Master poster', function () {
    $html = $this->get('/admissions/frais-academiques')->assertOk()->getContent();

    expect(substr_count($html, 'data-fee-part'))->toBe(7)
        ->and($html)->toContain('50 % en octobre, 50 % avant l&#039;examen final')
        ->toContain('038 53 849 49')->toContain('00004 00006 070112 001 28 32')
        ->and(substr_count($html, 'data-fee-copy='))->toBe(2);
});

it('keeps the simulator hidden until JavaScript starts it, and the tables readable without it', function () {
    $html = $this->get('/admissions/frais-academiques')->assertOk()->getContent();

    expect($html)->toMatch('/<section id="fee-sim"[^>]*\\bhidden\\b/')
        ->and($html)->toContain('<details class="fee-detail"');
});

it('lists the official table images in the order of the imported text, and tolerates their absence', function () {
    $assets = collect(range(1, 6))->map(fn ($i) => MediaAsset::create(['title' => "Tableau $i", 'alt' => '']));
    Page::where('key', 'admissions-fees')->firstOrFail()->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => $assets->map(fn ($a) => '<figure data-media="'.$a->id.'"></figure>')->implode('')]]],
    ]);

    // Les médias de test n'ont pas de fichier : la page reste servie.
    $this->get('/admissions/frais-academiques')->assertOk()->assertSee('fee-posters', false);

    Page::where('key', 'admissions-fees')->firstOrFail()->update(['blocks' => [['type' => 'rich_text', 'data' => ['content' => '<p>Texte</p>']]]]);
    $this->get('/admissions/frais-academiques')->assertOk()->assertDontSee('fee-posters', false)->assertSee('data-fee-sim', false);
});

it('does not use the fees layout for the other admissions pages', function () {
    $this->get('/admissions/licence')->assertOk()->assertDontSee('data-fees', false);
    $this->get('/admissions/telechargements')->assertOk()->assertDontSee('data-fee-sim', false);
});
