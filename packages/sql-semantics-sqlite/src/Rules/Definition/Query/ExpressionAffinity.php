<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Query;

use SqlSemantics\Diagnostic\InvariantViolation;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundStep;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable as CommonTableDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\NoAffinity;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Derives the affinity of a result expression, which decides the column type of a table or view made from a query.
 *
 * Rule: SQLITE-EXPRESSION-AFFINITY-001. "An expression that is a simple
 * reference to a column value has the same affinity as the column", also
 * inside parentheses and under COLLATE; the row identifier has INTEGER
 * affinity; "an expression of the form CAST(expr AS type) has an affinity
 * that is the same as a column with a declared type of type"; a row value
 * has the affinity of its first member; a scalar subquery has the affinity
 * of the first result of its rightmost arm; every other expression has no
 * affinity. A column of a derived table or of a common table has the
 * affinity SQLite computes for that column of its query
 * (SQLITE-QUERY-COLUMN-AFFINITY-001, below); a column of a declared table or
 * view has the affinity of its declared type, or none (NoAffinity).
 *
 * Rule: SQLITE-QUERY-COLUMN-AFFINITY-001. The affinity of a column of a
 * query is that of the expression at its position in the leftmost arm; when
 * that expression has no affinity, the arms are read from left to right
 * until one has an affinity. A query with one arm is done there. Otherwise,
 * unless the affinity is BLOB, it is adjusted by the kinds of value the
 * other arms produce (SQLITE-DATA-TYPE-001): a TEXT affinity becomes BLOB
 * when another arm produces numbers, and a numeric affinity becomes BLOB
 * when another arm produces text; a numeric affinity becomes the flexible
 * numeric affinity when the expression of the leftmost arm is a CAST. Each
 * row of a VALUES list is one arm. Precision: exact; the affinity is not
 * determined when it depends on a declaration the context lacks or on a name
 * that did not resolve. Terminates: each step enters a strictly nested query
 * or a strict sub-expression, and the arms are finite.
 * Source: https://sqlite.org/datatype3.html#affinity_of_expressions
 * (and `sqlite3ExprAffinity()` in expr.c and `sqlite3SubqueryColumnTypes()`
 * in select.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionAffinity
{
    /**
     * Answers the affinity of an expression.
     */
    public function of(Scalar $expression, Facts $facts): DerivedAffinity
    {
        while ($expression instanceof Grouped || $expression instanceof Collate) {
            $expression = $expression->operand;
        }
        if ($expression instanceof Cast) {
            return new DerivedAffinity($expression->target?->affinity() ?? Affinity::Numeric);
        }
        if ($expression instanceof RowExpression) {
            return $this->of($expression->items[0], $facts);
        }
        if ($expression instanceof ScalarSubquery) {
            return $this->first($expression->query, $facts);
        }
        if ($expression instanceof ColumnUse || $expression instanceof DoubleQuotedWord || $expression instanceof LiteralColumn) {
            return $this->reference($expression, $facts);
        }

        return new DerivedAffinity();
    }

    /**
     * Answers the affinity of a column reference: that of what it resolved to; a double-quoted word that is no column is a string without affinity.
     */
    public function reference(Scalar $expression, Facts $facts): DerivedAffinity
    {
        $resolution = $facts->covers($expression) ? $facts->scalar($expression)->resolution : null;
        if ($resolution instanceof ResolvedColumn) {
            return $this->resolved($resolution, $facts);
        }
        if ($resolution instanceof AliasTarget) {
            return $this->field($resolution->field, $facts);
        }

        return new DerivedAffinity(null, false, $resolution === null && $expression instanceof DoubleQuotedWord);
    }

    /**
     * Answers the affinity of an output field: that of its expression, or of the column an expanded star exposes.
     */
    public function field(Field $field, Facts $facts): DerivedAffinity
    {
        if ($field->expression !== null) {
            return $this->of($field->expression, $facts);
        }

        return $field->resolution instanceof ResolvedColumn ? $this->resolved($field->resolution, $facts) : new DerivedAffinity(null, false, false);
    }

    /**
     * Answers the affinity of a resolved column: of its declaration, or of the column of the query that computes it.
     */
    public function resolved(ResolvedColumn $resolution, Facts $facts): DerivedAffinity
    {
        if ($resolution->slot->column !== null) {
            return $this->declared($resolution->slot->column);
        }
        $query = $this->computing($resolution->relation, $facts);
        if ($query !== null) {
            $position = $this->position($query, $resolution->slot, $facts);

            return $position === null ? new DerivedAffinity(null, false, false) : $this->column($query, $position, $facts);
        }
        $column = $resolution->slot->declaration();

        return $column === null ? new DerivedAffinity(null, false, false) : $this->declared($column);
    }

    /**
     * Answers the affinity of a declared column: that of its declared type, none for a view column without affinity, and that of the type name for any other descriptor.
     */
    public function declared(Column $column): DerivedAffinity
    {
        $type = $column->type;
        if ($type instanceof ColumnDomain) {
            return new DerivedAffinity($type->affinity);
        }

        return $type instanceof NoAffinity ? new DerivedAffinity() : new DerivedAffinity((new ColumnTyping())->textAffinity($type->name()));
    }

    /**
     * Answers the query that computes the columns of a relation occurrence: a derived query or the definition of a common table.
     */
    public function computing(Relation $relation, Facts $facts): ?Query
    {
        if ($relation instanceof DerivedQuery) {
            return $relation->query;
        }
        if (!$facts->covers($relation)) {
            return null;
        }
        $table = $facts->relation($relation)->table;

        return $table instanceof CommonTable && $table->definition instanceof CommonTableDefinition ? $table->definition->query : null;
    }

    /**
     * Answers the position of the output field of a query that a slot re-exposes, or null when the slot comes from nowhere in it.
     */
    public function position(Query $query, OutputSlot $slot, Facts $facts): ?int
    {
        if (!$facts->covers($query)) {
            return null;
        }
        for ($current = $slot; $current !== null; $current = $current->origin) {
            foreach ($facts->query($query)->projection as $item) {
                if ($item instanceof Field && $item->slot === $current) {
                    return $item->position;
                }
            }
        }

        return null;
    }

    /**
     * Answers the arms of a query from left to right; each row of a VALUES list is an arm.
     *
     * @return non-empty-list<Select|ValueRow>
     * @throws InvariantViolation When the query is not a selection, a row list or a compound of those
     */
    public function arms(Query $query): array
    {
        while ($query instanceof WithQuery) {
            $query = $query->body;
        }
        $parts = match (true) {
            $query instanceof Compound => [$query->first, ...array_map(static fn (CompoundStep $step): Select|ValuesClause => $step->query, $query->steps)],
            $query instanceof Select, $query instanceof ValuesClause => [$query],
            default => throw new InvariantViolation('A SQLite query is a selection, a row list or a compound of those.'),
        };
        $arms = [];
        foreach ($parts as $part) {
            if ($part instanceof ValuesClause) {
                array_push($arms, ...$part->rows);
            } else {
                $arms[] = $part;
            }
        }

        return $arms;
    }

    /**
     * Answers the affinity of the result of an arm at a position; an expanded star is a column reference.
     */
    public function arm(Select|ValueRow $arm, int $position, Facts $facts): DerivedAffinity
    {
        if ($arm instanceof ValueRow) {
            return isset($arm->values[$position]) ? $this->of($arm->values[$position], $facts) : new DerivedAffinity(null, false, false);
        }
        $item = $facts->covers($arm) ? ($facts->query($arm)->projection[$position] ?? null) : null;

        return $item instanceof Field ? $this->field($item, $facts) : new DerivedAffinity(null, false, false);
    }

    /**
     * Answers the expression an arm writes at a position, or null for an expanded star or a position the arm does not have.
     */
    public function written(Select|ValueRow $arm, int $position, Facts $facts): ?Scalar
    {
        if ($arm instanceof ValueRow) {
            return $arm->values[$position] ?? null;
        }
        $item = $facts->covers($arm) ? ($facts->query($arm)->projection[$position] ?? null) : null;

        return $item instanceof Field ? $item->expression : null;
    }

    /**
     * Answers the affinity of a scalar subquery: that of the first result of the rightmost arm.
     */
    public function first(Query $query, Facts $facts): DerivedAffinity
    {
        $arms = $this->arms($query);

        return $this->arm($arms[count($arms) - 1], 0, $facts);
    }

    /**
     * Answers the affinity SQLite computes for a column of a query (SQLITE-QUERY-COLUMN-AFFINITY-001).
     */
    public function column(Query $query, int $position, Facts $facts): DerivedAffinity
    {
        $arms = $this->arms($query);
        $kinds = 0;
        $index = 0;
        $affinity = $this->arm($arms[0], $position, $facts);
        while ($affinity->determined && $affinity->affinity === null && isset($arms[$index + 1])) {
            $kind = (new ValueKinds())->arm($arms[$index], $position, $facts);
            if ($kind === null) {
                return new DerivedAffinity(null, false, false);
            }
            $kinds |= $kind;
            $affinity = $this->arm($arms[++$index], $position, $facts);
        }
        if (!$affinity->typed() || ($index === 0 && !isset($arms[1]))) {
            return $affinity;
        }
        for ($later = $index + 1; isset($arms[$later]); $later++) {
            $kind = (new ValueKinds())->arm($arms[$later], $position, $facts);
            if ($kind === null) {
                return new DerivedAffinity(null, false, false);
            }
            $kinds |= $kind;
        }

        return $this->adjusted($affinity, $kinds, $this->written($arms[0], $position, $facts));
    }

    /**
     * Adjusts the affinity of a compound column by the kinds of value the other arms produce and by a CAST in the leftmost arm.
     */
    public function adjusted(DerivedAffinity $affinity, int $kinds, ?Scalar $leftmost): DerivedAffinity
    {
        if ($affinity->affinity === Affinity::Text && ($kinds & ValueKinds::NUMBER) !== 0) {
            return new DerivedAffinity(Affinity::Blob);
        }
        if ($affinity->numeric() && ($kinds & ValueKinds::TEXT) !== 0) {
            return new DerivedAffinity(Affinity::Blob);
        }
        while ($leftmost instanceof Grouped) {
            $leftmost = $leftmost->operand;
        }

        return $affinity->numeric() && $leftmost instanceof Cast ? new DerivedAffinity(Affinity::Numeric, true) : $affinity;
    }
}
