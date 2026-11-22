<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Validates signed predicates against the exact table occurrences at their boundary.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class OccurrenceScope
{
    /**
     * Checks both operands of a comparison in its own visibility domain.
     * @param list<TableSource> $inputs
     */
    public function predicate(BooleanLiteral|IntegerComparison|NullLiteral|null $condition, array $inputs): void
    {
        if ($condition instanceof IntegerComparison) {
            foreach ([$condition->left, $condition->right] as $operand) {
                Check::input(
                    !$operand instanceof ColumnReference || isset($inputs[$operand->occurrence]->columns[$operand->slot]),
                    'A predicate slot must belong to a visible input occurrence.',
                );
            }
        }
    }
}
