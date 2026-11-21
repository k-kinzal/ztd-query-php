<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks scope membership and expanded expression limits at the SELECT boundary.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SelectBounds
{
    /**
     * Checks every expression occurrence in the scope of its clause.
     *
     * @param non-empty-list<ResultColumn> $results The result expressions
     * @param TableSource|null $source The first input
     * @param list<Join> $joins The remaining inputs
     * @param list<Order> $orderBy The output ordering
     */
    public function check(
        array $results,
        ?TableSource $source,
        array $joins = [],
        Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter|Comparison|Logical|null $where = null,
        array $orderBy = [],
        ?Limit $limit = null,
    ): void {
        Check::input(count($results) <= 2000, 'A SELECT can have at most 2000 result columns in this profile.');
        Check::input(count($joins) < 64 && ($joins === [] || $source !== null), 'Joins require a first table and at most 64 inputs.');
        Check::input(count($orderBy) <= 2000, 'A SELECT can have at most 2000 ordering terms in this profile.');
        foreach ($orderBy as $order) {
            Check::input($order->position <= count($results), 'An output order position must belong to the projection.');
        }

        $tables = [$source];
        $pending = [];
        foreach ($joins as $join) {
            $tables[] = $join->source;
            if ($join->on !== null) {
                $pending[] = [$join->on, 0, count($tables)];
            }
        }
        foreach ($results as $result) {
            $pending[] = [$result->expression, 0, count($tables)];
        }
        foreach ([$where, $limit?->count, $limit?->offset] as $operand) {
            if ($operand !== null) {
                $pending[] = [$operand, 0, count($tables)];
            }
        }

        $nodes = 0;
        $parameterBytes = 0;
        while ($pending !== []) {
            [$expression, $height, $visibility] = array_pop($pending);
            Check::input($height <= 32 && ++$nodes <= 8192, 'Expression height or expanded node count exceeds the IR profile.');
            match (true) {
                $expression instanceof ColumnReference => Check::input(
                    $expression->source < $visibility && isset($tables[$expression->source]->columns[$expression->slot]),
                    'Column slot must belong to the SELECT source at a visible occurrence.',
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
