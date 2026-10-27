<?php

use App\Models\Redirect;

it('normalizes legacy WordPress paths', function (string $input, string $expected) {
    expect(Redirect::normalize($input))->toBe($expected);
})->with([
    'trailing slash' => ['/a-propos/', '/a-propos'],
    'missing leading slash' => ['a-propos', '/a-propos'],
    'full URL' => ['https://zurcher.edu.mg/blog/', '/blog'],
    'root with domain' => ['https://zurcher.edu.mg', '/'],
    'root' => ['/', '/'],
    'fragment' => ['/page/#ancre', '/page'],
    'nested path' => ['/academique/emplois-du-temps/', '/academique/emplois-du-temps'],
    'surrounding spaces' => ['  /licence/  ', '/licence'],
]);
