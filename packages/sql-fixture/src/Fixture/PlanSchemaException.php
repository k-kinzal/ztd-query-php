<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use RuntimeException;

/**
 * Failures matching a fixture plan to its schemas and generated rows.
 */
abstract class PlanSchemaException extends RuntimeException
{
}
