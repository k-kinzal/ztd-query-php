<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;

/**
 * Derives the affinity of a result expression, which decides the column type of a table or view made from a query.
 *
 * Rule: SQLITE-EXPRESSION-AFFINITY-001. "An expression that is a simple
 * reference to a column value has the same affinity as the column", also
 * inside parentheses and under COLLATE; "an expression of the form
 * CAST(expr AS type) has an affinity that is the same as a column with a
 * declared type of type"; every other expression has no affinity. Precision:
 * exact for these forms. A scalar subquery, whose affinity is that of its
 * result expression, is read as having none. Terminates: each step removes
 * one wrapper of a finite expression.
 * Source: https://sqlite.org/datatype3.html#affinity_of_expressions.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionAffinity
{
    /**
     * Answers the declared column a result expression simply refers to, if it does.
     */
    public function column(?Scalar $expression, Facts $facts): ?Column
    {
        while ($expression instanceof Grouped || $expression instanceof Collate) {
            $expression = $expression->operand;
        }
        if ($expression === null || !$facts->covers($expression)) {
            return null;
        }
        $resolution = $facts->scalar($expression)->resolution;

        return $resolution instanceof ResolvedColumn ? $resolution->slot->declaration() : null;
    }

    /**
     * Answers the affinity of a result expression, or null when it has none.
     */
    public function of(?Scalar $expression, Facts $facts): ?Affinity
    {
        $column = $this->column($expression, $facts);
        if ($column !== null) {
            return $column->type instanceof ColumnDomain ? $column->type->affinity : null;
        }
        while ($expression instanceof Grouped || $expression instanceof Collate) {
            $expression = $expression->operand;
        }

        return $expression instanceof Cast ? ($expression->target?->affinity() ?? Affinity::Numeric) : null;
    }
}
