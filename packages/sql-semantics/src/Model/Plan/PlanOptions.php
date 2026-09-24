<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

/**
 * Dialect-specific instructions for reporting a statement's execution plan.
 * @visibility public
 * @example Reading the dialect of bound plan options
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $options = $binder->bind('EXPLAIN SELECT 1')->options;
 *     $options instanceof \SqlSemantics\Model\Plan\PlanOptions // => true
 *     $options->dialect() // => \SqlSemantics\Dialect::PostgreSql
 */
interface PlanOptions
{
    /**
     * Returns the SQL dialect that defines these options.
     */
    public function dialect(): \SqlSemantics\Dialect;
}
