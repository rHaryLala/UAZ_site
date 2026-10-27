<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/** Yearbook numérique : pages WebP générées à partir du PDF (public/books/yearbook/{année}/page-001.webp…). */
class YearbookController extends Controller
{
    public function show(?string $year = null): View
    {
        $books = config('uaz.yearbooks');
        $year ??= (string) array_key_last($books);
        abort_unless(isset($books[$year]), 404);

        $book = $books[$year] + ['year' => $year];
        $pages = collect(range(1, $book['pages']))->map(fn (int $n) => 'books/yearbook/'.$year.'/page-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT).'.webp');
        abort_unless(is_file(public_path($pages->first())), 404);

        return view('yearbook', ['book' => $book, 'pages' => $pages, 'books' => $books]);
    }
}
