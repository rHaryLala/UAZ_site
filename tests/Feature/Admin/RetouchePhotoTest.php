<?php

use App\Models\MediaAsset;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

// La Retouche photo du back-office : la page (l'atelier tourne dans le navigateur) et la recherche / le remplacement de la photo d'une personne.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR);
    $this->plain = User::factory()->create();
});

it('opens the workshop for people who can add media, and only them', function () {
    $this->get(route('admin.retouche.index'))->assertRedirect('/admin/login');
    $this->actingAs($this->plain)->get(route('admin.retouche.index'))->assertForbidden();

    $this->actingAs($this->editor)->get(route('admin.retouche.index'))->assertOk()
        ->assertSee('id="rpApp"', false)->assertSee('Retouche photo')->assertSee('rpNetteteSortie', false)
        ->assertSee('js/admin/retouche/atelier.js', false);
});

it('looks up team members and testimonials by name or role', function () {
    TeamMember::factory()->create(['name' => 'Alice Rakoto', 'job_title' => 'Doyenne']);
    TeamMember::factory()->create(['name' => 'Bob Rabe', 'job_title' => 'Bibliothécaire']);
    Testimonial::factory()->create(['author_name' => 'Clara Randria', 'author_role' => 'Alumni']);

    $found = $this->actingAs($this->editor)->getJson(route('admin.retouche.people', ['action' => 'chercher', 'type' => 'equipe', 'q' => 'rakoto']))->assertOk()->json('resultats');
    expect($found)->toHaveCount(1)->and($found[0])->toMatchArray(['type' => 'equipe', 'nom' => 'Alice Rakoto', 'detail' => 'Doyenne']);

    $byRole = $this->actingAs($this->editor)->getJson(route('admin.retouche.people', ['action' => 'chercher', 'type' => 'equipe', 'q' => 'biblio']))->json('resultats');
    expect($byRole)->toHaveCount(1)->and($byRole[0]['nom'])->toBe('Bob Rabe');

    $testimonial = $this->actingAs($this->editor)->getJson(route('admin.retouche.people', ['action' => 'chercher', 'type' => 'temoignage', 'q' => 'randria']))->json('resultats');
    expect($testimonial)->toHaveCount(1)->and($testimonial[0]['type'])->toBe('temoignage');

    // Trop court : rien (pas de liste complète de l'équipe).
    expect($this->actingAs($this->editor)->getJson(route('admin.retouche.people', ['type' => 'equipe', 'q' => 'a']))->json('resultats'))->toBe([]);
    $this->actingAs($this->plain)->getJson(route('admin.retouche.people', ['type' => 'equipe', 'q' => 'alice']))->assertForbidden();
});

it('replaces the photo of a team member with the retouched one, keeping the old one in the media library', function () {
    $old = MediaAsset::create(['title' => 'Ancienne', 'alt' => 'Ancienne photo']);
    $member = TeamMember::factory()->create(['name' => 'Alice Rakoto', 'job_title' => 'Doyenne', 'photo_id' => $old->id]);

    $this->actingAs($this->editor)->post(route('admin.retouche.people', ['action' => 'remplacer']), [
        'type' => 'equipe', 'id' => $member->id, 'photo' => UploadedFile::fake()->image('profil.jpg', 800, 800),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('personne.nom', 'Alice Rakoto');

    $member->refresh();
    expect($member->photo_id)->not->toBe($old->id)
        ->and($member->photo->alt)->toBe('Alice Rakoto, Doyenne')
        ->and(MediaAsset::find($old->id))->not->toBeNull();
});

it('refuses a bad type, an unknown person and a file that is not a picture', function () {
    $member = TeamMember::factory()->create();
    $post = fn (array $data) => $this->actingAs($this->editor)->post(route('admin.retouche.people', ['action' => 'remplacer']), $data, ['Accept' => 'application/json']);

    $post(['type' => 'etudiant', 'id' => $member->id, 'photo' => UploadedFile::fake()->image('p.jpg')])->assertUnprocessable();
    $post(['type' => 'equipe', 'id' => 99999, 'photo' => UploadedFile::fake()->image('p.jpg')])->assertNotFound();
    $post(['type' => 'equipe', 'id' => $member->id, 'photo' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')])->assertUnprocessable();
    $this->actingAs($this->plain)->post(route('admin.retouche.people', ['action' => 'remplacer']), ['type' => 'equipe', 'id' => $member->id, 'photo' => UploadedFile::fake()->image('p.jpg')])->assertForbidden();
});

it('ships a sharper pipeline: output-scale sharpening, bicubic magnification and a mip bias in the shader', function () {
    $engine = file_get_contents(public_path('js/admin/retouche/moteur.js'));
    $workshop = file_get_contents(public_path('js/admin/retouche/atelier.js'));

    expect($engine)->toContain('vec3 echantillon(vec2 q, vec2 st)')->toContain('uniform float uBiais')->toContain('Catmull-Rom')
        ->and($workshop)->toContain('function netteteSortie(')->toContain('NETTETE_SORTIE');
});

it('renders the workshop page without leaking any template code, and with every control of the original', function () {
    $html = $this->actingAs($this->editor)->get(route('admin.retouche.index'))->assertOk()->getContent();

    // Aucun morceau de Blade ou de PHP ne doit apparaître dans la page (la conversion depuis registrar en avait laissé dans « Couleur par teinte »).
    foreach (['@php', '@foreach', '@endforeach', '@if', '@endif', '@else', '$rp', '$nom', '$hex', '$cle', '<?', '?>', '{{', '{!!'] as $leak) {
        expect($html)->not->toContain($leak, "fuite de code : {$leak}");
    }

    // Les huit teintes, les curseurs de teinte, les fonds de studio, les huit grilles et les formats de cadrage sont bien là.
    expect(substr_count($html, 'name="rpTeinte"'))->toBe(8)
        ->and($html)->toContain('id="rp-tslT"')->toContain('id="rp-tslS"')->toContain('id="rp-tslL"')
        ->and(substr_count($html, 'data-fond-studio='))->toBe(8)
        ->and(substr_count($html, 'name="rpGrille"'))->toBe(8)
        ->and(substr_count($html, 'name="rpFormat"'))->toBeGreaterThanOrEqual(13)
        ->and($html)->toContain('Rouges')->toContain('Magentas');
});

it('offers the glasses-reflection and deblur corrections, in the page and in the shader', function () {
    $html = $this->actingAs($this->editor)->get(route('admin.retouche.index'))->assertOk()->getContent();
    $engine = file_get_contents(public_path('js/admin/retouche/moteur.js'));
    $workshop = file_get_contents(public_path('js/admin/retouche/atelier.js'));

    expect($html)->toContain('data-reglage="refletLunettes"')->toContain('data-reglage="defloutage"')->toContain('data-reglage="defloutageRayon"')
        ->and($engine)->toContain('uniform float uReflet')->toContain('uniform vec4 uVisage')->toContain('definirVisage(')
        ->toContain('uniform float uDeflou')->toContain('vec3 flou9(')
        ->and($workshop)->toContain('refletLunettes: 0')->toContain('defloutage: 0')->toContain('function majVisage(');
});

it('shows the photo exactly as it is until a setting is changed', function () {
    $engine = file_get_contents(public_path('js/admin/retouche/moteur.js'));

    expect($engine)->toContain('uniform int uNeutre')->toContain('function estNeutre(')->toContain('uNeutre == 1');
});

it('only neutralises clipped highlights that are nearly white, so a vivid blue shirt keeps its colour', function () {
    expect(file_get_contents(public_path('js/admin/retouche/moteur.js')))->toContain('smoothstep(0.62, 0.9, min(brut.r, min(brut.g, brut.b)))');
});
