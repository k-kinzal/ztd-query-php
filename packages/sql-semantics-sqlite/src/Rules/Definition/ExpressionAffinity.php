<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable as CommonTableDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Derives the affinity of a result expression, which decides the column type of a table or view made from a query.
 *
 * Rule: SQLITE-EXPRESSION-AFFINITY-001. "An expression that is a simple
 * reference to a column value has the same affinity as the column", also
 * inside parentheses and under COLLATE; "an expression of the form
 * CAST(expr AS type) has an affinity that is the same as a column with a
 * declared type of type"; a scalar subquery has the affinity of the first
 * result expression of its rightmost arm (`sqlite3ExprAffinity()` reads the
 * last SELECT of a compound); every other expression has no affinity. A
 * column of a derived table or of a common table has the affinity of the
 * expression that computes it, found through the output slot the reference
 * resolved to. The source of an expression is the declared column or the
 * CAST reached this way; a view column made from a declared column keeps
 * its declared type. Precision: exact for these forms. Terminates: each step
 * removes one wrapper of a finite expression or enters a strictly nested
 * query.
 * Source: https://sqlite.org/datatype3.html#affinity_of_expressions
 * (and `sqlite3ExprAffinity()` and `columnType()` in expr.c and select.c of
 * the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionAffinity
{
    /**
     * Answers the first result position of the arm a scalar subquery takes its affinity from: an expression or, for an expanded star, a declared column.
     */
    public function first(Query $query, Facts $facts): Scalar|Column|null
    {
        while ($query instanceof WithQuery) {
            $query = $query->body;
        }
        if ($query instanceof Compound) {
            $query = $query->steps[count($query->steps) - 1]->query;
        }
        if ($query instanceof ValuesClause) {
            return $query->rows[count($query->rows) - 1]->values[0];
        }
        $item = $facts->query($query)->projection[0] ?? null;
        if (!$item instanceof Field) {
            return null;
        }

        return $item->expression ?? $item->column();
    }

    /**
     * Answers the output field of a query whose slot a slot re-exposes, if one does.
     */
    public function exposed(Query $query, OutputSlot $slot, Facts $facts): ?Field
    {
        for ($current = $slot; $current !== null; $current = $current->origin) {
            foreach ($facts->query($query)->projection as $item) {
                if ($item instanceof Field && $item->slot === $current) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Answers what a resolved column reference reads: the declared column, or the field of the derived or common table that computes it.
     */
    public function resolved(ResolvedColumn $resolution, Facts $facts): Column|Field|null
    {
        $column = $resolution->slot->declaration();
        if ($column !== null) {
            return $column;
        }
        $relation = $resolution->relation;
        $query = $relation instanceof DerivedQuery ? $relation->query : null;
        if ($query === null && $facts->covers($relation)) {
            $table = $facts->relation($relation)->table;
            $query = $table instanceof CommonTable && $table->definition instanceof CommonTableDefinition ? $table->definition->query : null;
        }

        return $query === null ? null : $this->exposed($query, $resolution->slot, $facts);
    }

    /**
     * Answers the source an expression takes its affinity from: a declared column, a CAST, or null when it has none.
     */
    public function source(?Scalar $expression, Facts $facts): Column|Cast|null
    {
        while ($expression !== null) {
            if ($expression instanceof Grouped || $expression instanceof Collate) {
                $expression = $expression->operand;
                continue;
            }
            if ($expression instanceof Cast) {
                return $expression;
            }
            $resolution = $facts->covers($expression) ? $facts->scalar($expression)->resolution : null;
            if ($expression instanceof ScalarSubquery) {
                $next = $this->step($this->first($expression->query, $facts));
            } elseif ($resolution instanceof ResolvedColumn) {
                $next = $this->step($this->resolved($resolution, $facts));
            } else {
                return null;
            }
            if ($next instanceof Column) {
                return $next;
            }
            $expression = $next;
        }

        return null;
    }

    /**
     * Answers the next expression to follow, or the declared column reached, for a position found through a query.
     */
    public function step(Scalar|Column|Field|null $found): Scalar|Column|null
    {
        if ($found instanceof Field) {
            return $found->expression ?? $found->column();
        }

        return $found;
    }

    /**
     * Answers the source of an output field: that of its expression, or the declared column an expanded star exposes.
     */
    public function field(Field $field, Facts $facts): Column|Cast|null
    {
        return $field->expression === null ? $field->column() : $this->source($field->expression, $facts);
    }

    /**
     * Answers the declared column a result expression simply refers to, if it does.
     */
    public function column(?Scalar $expression, Facts $facts): ?Column
    {
        $source = $this->source($expression, $facts);

        return $source instanceof Column ? $source : null;
    }

    /**
     * Answers the affinity of a result expression, or null when it has none.
     */
    public function of(?Scalar $expression, Facts $facts): ?Affinity
    {
        return $this->affinity($this->source($expression, $facts));
    }

    /**
     * Answers the affinity a source gives: that of the declared type of a column, or of the type name of a CAST.
     */
    public function affinity(Column|Cast|null $source): ?Affinity
    {
        if ($source instanceof Column) {
            return $source->type instanceof ColumnDomain ? $source->type->affinity : null;
        }

        return $source instanceof Cast ? ($source->target?->affinity() ?? Affinity::Numeric) : null;
    }
}
