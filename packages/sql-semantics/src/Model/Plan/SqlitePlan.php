<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

use Override;

/**
 * The selected SqlitePlan instruction.
 * @visibility public
 * @example Reading the requested SQLite report
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $binder->bind('EXPLAIN QUERY PLAN SELECT 1')->options // => \SqlSemantics\Model\Plan\SqlitePlan::QueryPlan
 *     $binder->bind('EXPLAIN SELECT 1')->options // => \SqlSemantics\Model\Plan\SqlitePlan::Bytecode
 *     \SqlSemantics\Model\Plan\SqlitePlan::Bytecode->dialect() // => \SqlSemantics\Dialect::Sqlite
 */
enum SqlitePlan: string implements PlanOptions
{
    case Bytecode = 'EXPLAIN';
    case QueryPlan = 'EXPLAIN QUERY PLAN';

    /**
     * Returns the SQL dialect that defines these options.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::Sqlite;
    }
}
