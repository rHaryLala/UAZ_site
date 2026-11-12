<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Forms\MediaField;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Retouche photo : l'atelier (plein écran, tout se passe dans le navigateur) et le seul point d'entrée serveur dont il a besoin, « Mettre en photo de
 * profil » : chercher un membre de l'équipe ou un témoignage, puis remplacer sa photo par la photo retouchée (ajoutée à la médiathèque ;
 * l'ancienne y reste). Accès : comptes autorisés à ajouter des médias.
 */
class RetouchePhotoController extends Controller
{
    /** Poids maximal de la photo reçue (l'atelier la réduit à moins de 2 Mo avant l'envoi). */
    private const MAX_KB = 4096;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect('/admin/login');
        }
        abort_unless($request->user()->can('create', MediaAsset::class), 403);

        return view('admin.retouche.index');
    }

    /** GET ?action=chercher&type=equipe|temoignage&q= ; POST ?action=remplacer (type, id, photo). */
    public function people(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('create', MediaAsset::class), 403);

        return $request->isMethod('post') ? $this->replace($request) : $this->search($request);
    }

    private function search(Request $request): JsonResponse
    {
        $type = $request->query('type') === 'temoignage' ? 'temoignage' : 'equipe';
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['ok' => true, 'resultats' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $found = $type === 'equipe'
            ? TeamMember::query()->with('photo.media')->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('job_title', 'like', $like))->orderBy('name')->limit(12)->get()
            : Testimonial::query()->with('photo.media')->where(fn ($q) => $q->where('author_name', 'like', $like)->orWhere('author_role', 'like', $like))->orderBy('author_name')->limit(12)->get();

        return response()->json(['ok' => true, 'resultats' => $found->map(fn ($person) => $this->describe($type, $person))->values()]);
    }

    private function replace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:equipe,temoignage'],
            'id' => ['required', 'integer', 'min:1'],
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_KB],
        ], [
            'photo.required' => 'Aucune photo reçue.',
            'photo.mimetypes' => 'La photo doit être un JPEG, un PNG ou un WebP.',
            'photo.max' => 'La photo est trop lourde.',
        ]);

        $person = $data['type'] === 'equipe' ? TeamMember::find($data['id']) : Testimonial::find($data['id']);

        if (! $person) {
            return response()->json(['ok' => false, 'message' => 'Cette personne est introuvable.'], 404);
        }

        abort_unless($request->user()->can('update', $person), 403);

        $name = $this->name($data['type'], $person);
        $role = $this->detail($data['type'], $person);
        $asset = MediaAsset::create(['title' => 'Photo de '.$name, 'alt' => trim($name.($role !== '' ? ', '.$role : ''))]);
        $asset->addMedia($request->file('photo')->getRealPath())->usingFileName('photo-'.str($name)->slug()->limit(40, '').'.jpg')->toMediaCollection(MediaAsset::COLLECTION);
        $person->forceFill(['photo_id' => $asset->id])->save();

        return response()->json(['ok' => true, 'personne' => $this->describe($data['type'], $person->refresh()->load('photo.media'))]);
    }

    /** @return array<string, mixed> */
    private function describe(string $type, TeamMember|Testimonial $person): array
    {
        return [
            'type' => $type,
            'id' => $person->getKey(),
            'nom' => $this->name($type, $person),
            'detail' => $this->detail($type, $person),
            'photo' => $person->photo ? MediaField::describe($person->photo)['thumb'] : null,
        ];
    }

    private function name(string $type, TeamMember|Testimonial $person): string
    {
        return (string) ($type === 'equipe' ? $person->name : $person->author_name);
    }

    private function detail(string $type, TeamMember|Testimonial $person): string
    {
        return (string) ($type === 'equipe' ? $person->job_title : $person->author_role);
    }
}
