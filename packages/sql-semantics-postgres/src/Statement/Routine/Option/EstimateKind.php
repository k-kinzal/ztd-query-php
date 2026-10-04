<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

/**
 * What a planner estimate of a routine states: its execution cost or the number of rows it returns.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Spelling the row estimate
 *     \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\EstimateKind::Rows->value // => 'ROWS'
 */
enum EstimateKind: string
{
    case Cost = 'COST';
    case Rows = 'ROWS';
}
