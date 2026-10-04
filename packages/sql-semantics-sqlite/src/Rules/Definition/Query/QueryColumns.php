<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Query;

use SqlSemantics\Platform\Sqlite\Statement\Type\NoAffinity;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;

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
 * Types: the affinity of each field is derived by
 * SQLITE-QUERY-COLUMN-AFFINITY-001 and the recorded type by
 * SQLITE-QUERY-COLUMN-TYPE-001: a created table is written from the affinity
 * alone, a view keeps the declared type of a column it simply refers to. A
 * view whose column list has another length than its query gets no types at all.
 *
 * NULL facts: a column of a created table has no NOT NULL constraint and can
 * hold NULL whatever the query returns; a view column has the NULL fact of
 * its field.
 *
 * Minimum precision: the listed columns are exact. The column list is
 * incomplete, and stops at the first undetermined position, when the query
 * shape is open, when a field has no determined name (SQLite names it after
 * its source text), when a unique name is not determined, or when the
 * affinity of a field depends on a declaration the context lacks.
 * Terminates: one pass over the fields with at most four renames each.
 * Source: https://sqlite.org/lang_createtable.html#create_table_as_select_statements,
 * https://sqlite.org/lang_createview.html (and `sqlite3ColumnsFromExprList()`
 * in select.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class QueryColumns
{
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
     * @param Query $query The query; its output must be the given fact
     * @return list<Column>
     */
    public function tableColumns(Query $query, QueryFact $output, Facts $facts, Comparison $comparison): array
    {
        $names = [];
        foreach ($this->settled($output) as $field) {
            $names[] = $field->name;
        }
        $columns = [];
        foreach ($this->unique($names, $comparison) as $position => $name) {
            $affinity = (new ExpressionAffinity())->column($query, $position, $facts);
            if (!$affinity->determined) {
                break;
            }
            $columns[] = new Column($name, (new ColumnTyping())->tableType($affinity), Nullability::Nullable);
        }

        return $columns;
    }

    /**
     * Answers the columns of a view.
     *
     * @param Query $query The query; its output must be the given fact
     * @param list<Name>|null $listed The names of the column list of the view, when it has one
     * @param bool $typed Whether SQLite assigns the column types; it does not when a column list has another length than the query result
     * @return list<Column>
     */
    public function viewColumns(Query $query, QueryFact $output, Facts $facts, ?array $listed, Comparison $comparison, bool $typed = true): array
    {
        $fields = $this->settled($output);
        $names = $listed ?? [];
        foreach ($listed === null ? $fields : [] as $field) {
            $names[] = $field->name;
        }
        $columns = [];
        foreach ($this->unique($names, $comparison) as $position => $name) {
            $field = $fields[$position] ?? null;
            if (!$typed) {
                $columns[] = new Column($name, new NoAffinity(), $field === null ? Nullability::Dependent : $field->nullability);
                continue;
            }
            $affinity = $field === null ? null : (new ExpressionAffinity())->column($query, $position, $facts);
            if ($field === null || $affinity === null || !$affinity->determined) {
                break;
            }
            $columns[] = new Column($name, (new ColumnTyping())->viewType($affinity, (new ColumnTyping())->leftmost($query, $position, $facts)), $field->nullability);
        }

        return $columns;
    }
}
