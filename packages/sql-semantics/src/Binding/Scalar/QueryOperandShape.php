<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Diagnoses row-shape mismatches without evaluating values or cardinality.
 *
 * @visibility SqlSemantics
 */
final class QueryOperandShape
{
    /**
     * @throws InvalidSql
     */
    public static function check(Expression $left, Expression $right, Node $source): void
    {
        $a = self::width($left);
        $b = self::width($right);
        if ($a !== null && $b !== null && $a !== $b) {
            throw new InvalidSql(InputViolation::ComparisonWidth, $source);
        }
    }

    /**
     * Returns a known scalar or row width; null denotes an unresolved query expansion.
     */
    public static function width(Expression $value): ?int
    {
        return \SqlSemantics\Model\Validation\QueryComparison::width($value);
    }
}
