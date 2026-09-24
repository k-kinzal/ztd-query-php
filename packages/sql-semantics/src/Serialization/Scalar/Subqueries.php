<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Query;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Statements;

/**

 * Writes subquery predicates separately from scalar subqueries. @visibility SqlSemantics

 */
final class Subqueries
{
    /**
     * Writes scalar, row, existence, or comparison queries in their required operand positions.
     */
    public static function write(Query\RowSubquery|Query\ScalarSubquery|Query\ExistsSubquery|Query\InSubquery|Query\QuantifiedComparison|Query\ArraySubquery $value): Tree
    {
        $query = Build::parentheses(Statements::write($value->query));
        return match (true) {
            $value instanceof Query\ScalarSubquery, $value instanceof Query\RowSubquery => $query,
            $value instanceof Query\ExistsSubquery => new Tree('exists', [Build::keyword('EXISTS'), $query]),
            $value instanceof Query\ArraySubquery => new Tree('array', [Build::keyword('ARRAY'), $query]),
            $value instanceof Query\InSubquery => Build::parentheses(new Tree('membership', [Expressions::write($value->value), Build::keyword($value->negated ? 'NOT IN' : 'IN'), $query])),
            $value instanceof Query\QuantifiedComparison => Build::parentheses(new Tree('comparison', [Expressions::write($value->value), OperatorExpressions::quantified($value->operator, $value->negated, $value->quantifier), $query])),
        };
    }
}
