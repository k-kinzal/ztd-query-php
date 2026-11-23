<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;

/**
 * Checks the grouped query clauses and resolves the ordered key layout.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class GroupedKeys
{
    /**
     * Checks the supported grouped input and maps each named key to its slot.
     *
     * @return list<ColumnReference>
     */
    public function lower(Select $statement, SourceBinding $binding): array
    {
        Check::input(
            $statement->windows === [] && $statement->distinct === null
                && $statement->into === null && $statement->groupQuantifier === null
                && ($statement->options->locking ?? []) === [] && !($statement->options->readOnly ?? false),
            'The grouped projection requires an ordinary GROUP BY and checked result clauses.',
        );
        $keys = [];
        foreach ($statement->groupBy as $key) {
            Check::input($key instanceof Scalar, 'A grouped projection requires simple grouping keys.');
            $keys[] = $binding->reference($key);
        }

        return $keys;
    }
}
