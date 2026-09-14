<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * A relation endpoint names no table.
 */
final class EmptyTableNameException extends PlanSyntaxException
{
    /**
     * A relation endpoint names no table.
     */
    public function __construct()
    {
        parent::__construct('A relation endpoint must name a table.');
    }
}
