<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * Contract for SQL rewrite implementations.
 */
interface SqlRewriter
{
    /**
     * Rewrite a SQL string into a structured plan.
     * For multiple statements, only returns plan for the first statement.
     */
    public function rewrite(string $sql): RewritePlan;

    /**
     * Rewrite multiple SQL statements into separate plans.
     * Supports SQL strings containing multiple statements separated by semicolons.
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan;
}
