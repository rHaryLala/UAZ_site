<?php

declare(strict_types=1);

namespace App\Support\MaxMindDb\Reader;

/**
 * The platform cannot decode a database value or offset.
 *
 * @final
 */
// phpcs:ignore PSR2.Classes.ClassDeclaration, Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
class UnsupportedPlatformException extends \RuntimeException {}
