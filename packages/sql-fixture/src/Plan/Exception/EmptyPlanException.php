<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * The fixture plan names no tables.
 */
final class EmptyPlanException extends PlanSyntaxException
{
    /**
     * The fixture plan names no tables.
     */
    public function __construct()
    {
        parent::__construct('A fixture plan must name at least one table.');
    }
}
