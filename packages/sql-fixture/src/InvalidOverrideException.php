<?php

declare(strict_types=1);

namespace SqlFixture;

use InvalidArgumentException;

/**
 * Overrides that violate the table schema.
 */
abstract class InvalidOverrideException extends InvalidArgumentException
{
}
