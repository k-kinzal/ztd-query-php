<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use LogicException;

/**
 * Fixture relations that cannot define a finite generation order.
 */
abstract class PlanStructureException extends LogicException
{
}
