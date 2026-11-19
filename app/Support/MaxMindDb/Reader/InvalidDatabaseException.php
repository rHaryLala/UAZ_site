<?php

declare(strict_types=1);

namespace App\Support\MaxMindDb\Reader;

/**
 * This class should be thrown when unexpected data is found in the database.
 *
 * @final
 */
// phpcs:disable
class InvalidDatabaseException extends \Exception {}
