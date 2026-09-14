<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use RuntimeException;

/**
 * Failures while constructing an object from fixture data.
 */
abstract class HydrationException extends RuntimeException
{
}
