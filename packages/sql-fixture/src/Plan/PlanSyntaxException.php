<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use InvalidArgumentException;

/**
 * Invalid fixture plan syntax and relation endpoints.
 */
abstract class PlanSyntaxException extends InvalidArgumentException
{
}
