<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveDefinition;
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
     * @param TableSource|LocalSource|null $source The first input
     * @param list<Join> $joins The remaining inputs
     * @param list<Order> $orderBy The output ordering
     */
    public function check(
        array $results,
        TableSource|LocalSource|null $source,
        array $joins = [],
        Arithmetic|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|UnaryExpression|null $where = null,
        array $orderBy = [],
        ?Limit $limit = null,
    ): void {
        Check::input(count($results) <= 2000, 'A SELECT can have at most 2000 result columns in this profile.');
        Check::input(count($joins) < 64 && ($joins === [] || $source !== null), 'Joins require a first table and at most 64 inputs.');
        Check::input(!$source instanceof LocalSource || $joins === [], 'A local row source requires a SELECT without joins.');
        Check::input(count($orderBy) <= 2000, 'A SELECT can have at most 2000 ordering terms in this profile.');
        foreach ($orderBy as $order) {
            Check::input($order->position <= count($results), 'An output order position must belong to the projection.');
        }

        $widths = [$source === null ? 0 : count($source->columns)];
        $pending = [];
        if ($source instanceof LocalSource && $source->query instanceof RecursiveDefinition) {
            $pending = $source->query->scopedExpressions();
        } elseif ($source instanceof LocalSource) {
            foreach ($source->query->rows as $row) {
                foreach ($row->values as $value) {
                    $pending[] = [$value, 0, 0];
                }
            }
        }
        foreach ($joins as $join) {
            $widths[] = count($join->source->columns);
            if ($join->on !== null) {
                $pending[] = [$join->on, 0, count($widths)];
            }
        }
        foreach ($results as $result) {
            $pending[] = [$result->expression, 0, count($widths)];
        }
        foreach ([$where, $limit?->count, $limit?->offset] as $operand) {
            if ($operand !== null) {
                $pending[] = [$operand, 0, count($widths)];
            }
        }

        (new ExpressionScope())->check($widths, $pending);
    }
}
