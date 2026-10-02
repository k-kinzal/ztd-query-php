<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Derives the columns of a table or view that is defined by a query.
 *
 * Rule: SQLITE-QUERY-COLUMNS-001. The object has one column per output field
 * of the query, in order.
 *
 * Names: a column takes the name of its field. A name an earlier column
 * already has, compared without regard to ASCII case, is made unique by
 * replacing a trailing `:digits` with `:1`, `:2`, `:3`, `:4` in turn; after four
 * attempts SQLite picks random digits, so the name is not determined. A view
 * with a column list takes its names from the list instead, made unique the
 * same way.
 *
 * Types: for `CREATE TABLE ... AS` "the declared type of each column is
 * determined by the expression affinity of the corresponding expression":
 * TEXT, NUM, INT, REAL, or no type when the expression has none. A view
 * column that simply refers to a column has the declared type of that
 * column; a CAST gives the standard type name of its affinity (NUM for
 * NUMERIC); any other expression gives a column without declared type.
 *
 * NULL facts: a column of a created table has no NOT NULL constraint and can
 * hold NULL whatever the query returns; a view column has the NULL fact of
 * its field.
 *
 * Minimum precision: the listed columns are exact. The column list is
 * incomplete (and stops at the first undetermined position) when the query
 * shape is open, when a field has no determined name, or when a unique name
 * is not determined. Remaining assumption: a field of a compound query has
 * no single expression in the model; it is read as having no affinity.
 * Terminates: one pass over the fields with at most four renames each.
 * Source: https://sqlite.org/lang_createtable.html#create_table_as_select_statements,
 * https://sqlite.org/lang_createview.html, https://sqlite.org/datatype3.html#affinity_of_expressions
 * (and `sqlite3ColumnsFromExprList()` in select.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class QueryColumns
{
    /**
     * The declared type `CREATE TABLE ... AS` records for each affinity.
     */
    private const TABLE_TYPES = ['Integer' => 'INT', 'Text' => 'TEXT', 'Blob' => '', 'Real' => 'REAL', 'Numeric' => 'NUM'];

    /**
     * The declared type a view column has for each affinity of a CAST.
     */
    private const VIEW_TYPES = ['Integer' => 'INT', 'Text' => 'TEXT', 'Blob' => 'BLOB', 'Real' => 'REAL', 'Numeric' => 'NUM'];

    /**
     * Answers the leading output fields of a query whose position is determined: those before the first unexpanded star.
     *
     * @return list<Field>
     */
    public function settled(QueryFact $query): array
    {
        $fields = [];
        foreach ($query->projection as $item) {
            if (!$item instanceof Field) {
                break;
            }
            $fields[] = $item;
        }

        return $fields;
    }

    /**
     * Makes column names unique as SQLite does; the list ends before the first name that is not determined.
     *
     * @param list<Name|null> $names The name of each column in order; null when a name is not determined
     * @return list<Name>
     */
    public function unique(array $names, Comparison $comparison): array
    {
        $unique = [];
        foreach ($names as $name) {
            $candidate = $name;
            for ($attempt = 1; $candidate !== null && $this->taken($candidate->value, $unique, $comparison); $attempt++) {
                $candidate = $attempt > 4 ? null : new Name($this->stem($candidate->value) . ':' . $attempt);
            }
            if ($candidate === null) {
                break;
            }
            $unique[] = $candidate;
        }

        return $unique;
    }

    /**
     * Tells whether an earlier column has a name.
     *
     * @param list<Name> $names
     */
    public function taken(string $name, array $names, Comparison $comparison): bool
    {
        foreach ($names as $earlier) {
            if ($comparison->equal($earlier->value, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers a name without a trailing colon and digits.
     */
    public function stem(string $name): string
    {
        $end = strlen($name) - 1;
        while ($end > 0 && ctype_digit($name[$end])) {
            $end--;
        }

        return $end >= 0 && $name[$end] === ':' ? substr($name, 0, $end) : $name;
    }

    /**
     * Answers the columns of a table created from a query.
     *
     * @return list<Column>
     */
    public function tableColumns(QueryFact $query, Facts $facts, Comparison $comparison): array
    {
        $fields = $this->settled($query);
        $names = [];
        foreach ($fields as $field) {
            $names[] = $field->name;
        }
        $columns = [];
        foreach ($this->unique($names, $comparison) as $position => $name) {
            $affinity = (new ExpressionAffinity())->of($fields[$position]->expression, $facts) ?? Affinity::Blob;
            $columns[] = new Column($name, new ColumnDomain(self::TABLE_TYPES[$affinity->name]), Nullability::Nullable);
        }

        return $columns;
    }

    /**
     * Answers the columns of a view.
     *
     * @param list<Name>|null $listed The names of the column list of the view, when it has one
     * @return list<Column>
     */
    public function viewColumns(QueryFact $query, Facts $facts, ?array $listed, Comparison $comparison): array
    {
        $fields = $this->settled($query);
        $names = $listed ?? [];
        foreach ($listed === null ? $fields : [] as $field) {
            $names[] = $field->name;
        }
        $columns = [];
        foreach ($this->unique($names, $comparison) as $position => $name) {
            $field = $fields[$position] ?? null;
            $columns[] = new Column($name, $this->viewType($field, $facts), $field === null ? Nullability::Dependent : $field->nullability);
        }

        return $columns;
    }

    /**
     * Answers the declared type of a view column; a column whose field is not determined has none.
     */
    public function viewType(?Field $field, Facts $facts): TypeDescriptor
    {
        $column = (new ExpressionAffinity())->column($field?->expression, $facts);
        if ($column !== null) {
            return $column->type;
        }
        $affinity = (new ExpressionAffinity())->of($field?->expression, $facts);

        return new ColumnDomain($affinity === null ? '' : self::VIEW_TYPES[$affinity->name]);
    }
}
