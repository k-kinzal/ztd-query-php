<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One SQLite set or bag operation and its right operand.
 *
 * @visibility public
 * @example Combining a preceding arm with one VALUES row
 *     $row = new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow([new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1')]);
 *     $step = new \SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundStep(\SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator::Except, new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery([$row]));
 *     $step->query->toString() // => 'VALUES (1)'
 */
final class CompoundStep
{
    use Snapshot;

    /**
     * Pairs the operation with its checked right arm.
     */
    public function __construct(public readonly CompoundOperator $operator, public readonly Select|ValuesQuery $query)
    {
    }
}
