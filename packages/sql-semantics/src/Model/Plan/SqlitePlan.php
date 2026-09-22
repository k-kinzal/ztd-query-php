<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

use Override;

/**
 * The selected SqlitePlan instruction.
 * @visibility public
 */
enum SqlitePlan: string implements PlanOptions
{
    case Bytecode = 'EXPLAIN';
    case QueryPlan = 'EXPLAIN QUERY PLAN';

    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::Sqlite;
    }
}
