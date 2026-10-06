<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeLimit;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Checks the datums of one side of a range partition bound.
 *
 * Rule: PG-RANGE-DATUMS-001. A side holds at least one datum, in order; a
 * datum is a value, MINVALUE or MAXVALUE. Source:
 * https://www.postgresql.org/docs/17/sql-createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RangeDatums
{
    /**
     * Checks a list of range datums.
     *
     * @param array<array-key, object|Scalar|null> $datums
     * @return non-empty-list<Scalar|RangeLimit>
     */
    public function checked(array $datums): array
    {
        $checked = [];
        foreach (Check::listOf($datums, Node::class, 'A range bound side is an ordered list of at least one datum.', 1) as $datum) {
            Check::input($datum instanceof Scalar || $datum instanceof RangeLimit, 'A range datum is a value, MINVALUE or MAXVALUE.');
            $checked[] = $datum;
        }

        return $checked;
    }
}
