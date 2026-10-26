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
        ]);
    }

    public function show(NewsPost $post): View
    {
        $post->load(['category', 'cover.media', 'ogImage.media', 'author']);

        return view('news.show', [
            'post' => $post,
            'related' => NewsPost::visible()->with(['category', 'cover.media'])
                ->where('id', '!=', $post->id)->orderByDesc('published_at')->limit(3)->get(),
        ]);
    }
}
