<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Exception;

use InvalidArgumentException;

/**
 * Overrides that violate the table schema.
 */
abstract class InvalidOverrideException extends InvalidArgumentException
{
}
