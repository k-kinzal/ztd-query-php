<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

/**
 * Dialect-specific instructions for reporting a statement's execution plan.
 * @visibility public
 */
interface PlanOptions
{
    /**
     * Returns the SQL dialect that defines these options.
     */
    public function dialect(): \SqlSemantics\Dialect;
}
