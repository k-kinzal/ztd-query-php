<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Query\RowSubquery;
use SqlSemantics\Model\Scalar\Query\ScalarSubquery;
use SqlSemantics\Model\Scalar\Value\RowExpression;
use SqlSemantics\Type\Identity\BuiltinIdentity;

/**
 * Matches a scalar or row operand to the known output width of a subquery.
 * @visibility SqlSemantics
 */
final class QueryComparison
{
    /**
     * Unknown relation widths remain unresolved, without inventing columns.
     */
    public static function compatible(Expression $value, BoundQuery $query): bool
    {
        $left = self::width($value);
        $right = RowShape::width($query);
        return $left === null || $right === null || $left === $right;
    }

    /**
     * Returns a structural arity when the operand determines one.
     */
    public static function width(Expression $value): ?int
    {
        return match (true) {
            $value instanceof RowExpression => count($value->items),
            $value instanceof ScalarSubquery => isset($value->query->resultColumns()[0]) ? self::width($value->query->resultColumns()[0]->expression) : null,
            $value instanceof RowSubquery => RowShape::width($value->query),
            $value->type->identity === BuiltinIdentity::Record, $value->type->identity === BuiltinIdentity::Unknown => null,
            default => 1,
        };
    }
}
