<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Scalar;

/**
 * Tells which ORDER BY, GROUP BY and DISTINCT ON terms PostgreSQL reads as output column positions.
 *
 * Rule: PG-OUTPUT-POSITION-001. PostgreSQL decides this from the parse tree
 * before resolving anything. A term that is a plain constant is a position
 * when it is an integer of type integer and an error otherwise; parentheses
 * around the constant are not kept by the server, and a minus sign written
 * before it is folded into it. Every other term is an expression.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY,
 * https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Positions
{
    /**
     * Answers the position a term denotes as an exact decimal integer, or null when the term is not an integer constant.
     */
    public function value(Scalar $term): ?string
    {
        $negative = false;
        $current = $term;
        while ($current instanceof Grouped || ($current instanceof UnaryOperation && $this->minus($current))) {
            $negative = $negative !== $current instanceof UnaryOperation;
            $current = $current->operand;
        }
        if (!$current instanceof Constant || !$current->value instanceof IntegerConstant || $current->builtin() !== Builtin::Int4) {
            return null;
        }

        return ($negative && $current->value->digits !== '0' ? '-' : '') . $current->value->digits;
    }

    /**
     * Tells whether a term is a constant that is not an integer, which PostgreSQL rejects in these clauses.
     */
    public function misused(Scalar $term): bool
    {
        $current = $term;
        while ($current instanceof Grouped || ($current instanceof UnaryOperation && $this->minus($current))) {
            $current = $current->operand;
        }
        if ($this->value($term) !== null) {
            return false;
        }

        return $current instanceof Constant || $current instanceof NullLiteral || $current instanceof BooleanLiteral;
    }

    /**
     * Tells whether an operation is the plain minus sign the server folds into a numeric constant.
     */
    public function minus(UnaryOperation $operation): bool
    {
        return $operation->operator->name->value === '-' && !$operation->operator->explicit && $operation->operator->qualifiers === [];
    }
}
