<?php

namespace App\Http\Controllers;

use App\Models\NewsCategory;
use App\Models\NewsPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $category = NewsCategory::where('slug', $request->query('categorie'))->first();

        $posts = NewsPost::visible()
            ->with(['category', 'cover.media'])
            ->when($category, fn ($q) => $q->where('news_category_id', $category->id))
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('news.index', [
            'posts' => $posts,
            'category' => $category,
            'categories' => NewsCategory::whereHas('posts', fn ($q) => $q->visible())->orderBy('name')->get(),
            // Le ruban des dernières nouvelles : les titres les plus récents, toutes rubriques confondues.
            'headlines' => NewsPost::visible()->orderByDesc('published_at')->limit(6)->get(['id', 'title', 'slug', 'translations']),
            'latestDate' => NewsPost::visible()->max('published_at'),
        ]);
    }

    public function show(NewsPost $post): View
    {
        $post->load(['category', 'cover.media', 'ogImage.media', 'author']);

        $visible = fn () => NewsPost::visible()->with(['category', 'cover.media'])->where('id', '!=', $post->id);

        return view('news.show', [
            'post' => $post,
            'related' => $visible()->orderByDesc('published_at')->limit(3)->get(),
            // Navigation d'un article à l'autre : le précédent est plus ancien, le suivant plus récent.
            'older' => $post->published_at ? $visible()->where('published_at', '<', $post->published_at)->orderByDesc('published_at')->first() : null,
            'newer' => $post->published_at ? $visible()->where('published_at', '>', $post->published_at)->orderBy('published_at')->first() : null,
            // Temps de lecture estimé (200 mots par minute) : calculé du texte, jamais saisi.
            'minutes' => max(1, (int) ceil(str_word_count(strip_tags((string) $post->body), 0, 'àâäçéèêëîïôöùûüÿœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸŒ') / 200)),
        ]);
    }
}
