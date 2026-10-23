<?php

declare(strict_types=1);

namespace Tests\Generator;

use RuntimeException;

/**
 * The construction budget produced no inhabitant; this is never a passing trial.
 */
final class Exhausted extends RuntimeException
{
}
