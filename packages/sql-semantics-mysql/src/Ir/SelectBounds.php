<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Checks scope membership and expanded expression limits at the SELECT boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectBounds
{
    /**
     * Checks every expression occurrence, including repeated uses of a shared value.
     *
     * @param non-empty-list<ResultColumn> $results The result expressions
     * @param TableSource|Join|null $source The source whose slots may be used
     */
    public function check(
        array $results,
        TableSource|Join|null $source,
        BooleanLiteral|IntegerComparison|NullLiteral|null $where = null,
    ): void {
        Check::input(count($results) <= 1024, 'A SELECT can have at most 1024 result columns in this profile.');
        $inputs = $source instanceof Join ? $source->inputs() : ($source === null ? [] : [$source]);
        (new OccurrenceScope())->predicate($where, $inputs);
        $nodes = 0;
        foreach ($results as $result) {
            $pending = [[$result->expression, 0]];
            while ($pending !== []) {
                [$expression, $height] = array_pop($pending);
                Check::input($height <= 32 && ++$nodes <= 8192, 'Expression height or expanded node count exceeds the IR profile.');
                match (true) {
                    $expression instanceof ColumnReference => Check::input(
                        isset($inputs[$expression->occurrence]->columns[$expression->slot]),
                        'Column slot must belong to the SELECT source.',
                    ),
                    $expression instanceof Arithmetic => array_push($pending, [$expression->right, $height + 1], [$expression->left, $height + 1]),
                    $expression instanceof IntegerLiteral, $expression instanceof BooleanLiteral, $expression instanceof NullLiteral => null,
                };
            }
        }
    }
}
