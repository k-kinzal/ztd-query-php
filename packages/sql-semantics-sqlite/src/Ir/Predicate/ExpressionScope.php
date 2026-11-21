<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks all expanded expression occurrences against their visible table slots.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionScope
{
    /**
     * Traverses the closed operand graph and applies the expression and SQL size budgets.
     *
     * @param list<TableSource|null> $tables The table occurrences
     * @param list<array{Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter|Comparison|Logical, int, int}> $pending The expressions with height and scope width
     */
    public function check(array $tables, array $pending): void
    {
        $nodes = 0;
        $parameterBytes = 0;
        while ($pending !== []) {
            [$expression, $height, $visibility] = array_pop($pending);
            Check::input($height <= 32 && ++$nodes <= 8192, 'Expression height or expanded node count exceeds the IR profile.');
            match (true) {
                $expression instanceof ColumnReference => Check::input(
                    $expression->source < $visibility && isset($tables[$expression->source]->columns[$expression->slot]),
                    'Column slot must belong to the statement source at a visible occurrence.',
                ),
                $expression instanceof Parameter => $parameterBytes += strlen($expression->label),
                $expression instanceof Arithmetic, $expression instanceof Comparison, $expression instanceof Logical
                    => array_push($pending, [$expression->right, $height + 1, $visibility], [$expression->left, $height + 1, $visibility]),
                $expression instanceof IntegerLiteral, $expression instanceof NullLiteral => null,
            };
            Check::input($parameterBytes <= 990000000, 'Numbered parameter labels exceed the SQLite statement size budget.');
        }
    }
}
