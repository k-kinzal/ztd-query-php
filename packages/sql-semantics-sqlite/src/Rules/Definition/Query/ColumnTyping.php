<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Query;

use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\NoAffinity;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Derives the declared type SQLite records for a column of a view or of a table made from a query.
 *
 * Rule: SQLITE-QUERY-COLUMN-TYPE-001. A result expression carries a
 * declared type text when it is a column reference, in parentheses or not:
 * the declared type of a table column (none when the column declares no
 * type), `INTEGER` for the row identifier, the type a view records for its
 * column, or, for a column of a derived table or a common table, the text
 * carried by the expression at that position in the rightmost arm of its
 * query; a scalar subquery carries the text of the first result of its
 * rightmost arm; COLLATE and every other expression carry none. A column
 * of the view or table being defined carries the text of the expression at
 * its position in the leftmost arm of the defining query.
 *
 * A view records the carried text when the affinity SQLite derives from that
 * text (the five substring rules, NUMERIC for an empty text) is the affinity
 * of the column (SQLITE-QUERY-COLUMN-AFFINITY-001) and that affinity is not
 * the flexible numeric one; otherwise it records `NUM` for a numeric
 * affinity, the standard name of any other affinity, and no type (NoAffinity)
 * for a column without affinity. A table made from a query is written from
 * the affinity alone: `INT`, `TEXT`, `REAL`, `NUM`, and no type for BLOB or
 * no affinity. Precision: exact. Terminates: each step enters a strictly
 * nested query or a strict sub-expression.
 * Source: https://sqlite.org/lang_createtable.html#create_table_as_select_statements,
 * https://sqlite.org/lang_createview.html, https://sqlite.org/datatype3.html#determination_of_column_affinity
 * (and `columnType()`, `sqlite3SubqueryColumnTypes()` and `createTableStmt()`
 * in select.c and build.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ColumnTyping
{
    /**
     * The standard type name a view records for each affinity the carried text does not give.
     */
    private const VIEW = ['Integer' => 'INT', 'Text' => 'TEXT', 'Blob' => 'BLOB', 'Real' => 'REAL', 'Numeric' => 'NUM'];

    /**
     * The declared type `CREATE TABLE ... AS` writes for each affinity.
     */
    private const TABLE = ['Integer' => 'INT', 'Text' => 'TEXT', 'Blob' => '', 'Real' => 'REAL', 'Numeric' => 'NUM'];

    /**
     * Answers the affinity SQLite derives from a type text by the ordered substring rules; an empty text gives NUMERIC.
     */
    public function textAffinity(string $text): Affinity
    {
        $text = strtr($text, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');

        return match (true) {
            str_contains($text, 'INT') => Affinity::Integer,
            str_contains($text, 'CHAR'), str_contains($text, 'CLOB'), str_contains($text, 'TEXT') => Affinity::Text,
            str_contains($text, 'BLOB') => Affinity::Blob,
            str_contains($text, 'REAL'), str_contains($text, 'FLOA'), str_contains($text, 'DOUB') => Affinity::Real,
            default => Affinity::Numeric,
        };
    }

    /**
     * Answers the declared type text an expression carries, or null when it carries none.
     */
    public function text(Scalar $expression, Facts $facts): ?string
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof ScalarSubquery) {
            $arms = (new ExpressionAffinity())->arms($expression->query);

            return $this->arm($arms[count($arms) - 1], 0, $facts);
        }
        if ($expression instanceof ColumnUse || $expression instanceof DoubleQuotedWord || $expression instanceof LiteralColumn) {
            $resolution = $facts->covers($expression) ? $facts->scalar($expression)->resolution : null;

            return $resolution instanceof ResolvedColumn ? $this->resolved($resolution, $facts) : null;
        }

        return null;
    }

    /**
     * Answers the declared type text a resolved column carries.
     */
    public function resolved(ResolvedColumn $resolution, Facts $facts): ?string
    {
        if ($resolution->slot->column !== null) {
            return $this->column($resolution->slot->column);
        }
        $affinities = new ExpressionAffinity();
        $query = $affinities->computing($resolution->relation, $facts);
        if ($query !== null) {
            $position = $affinities->position($query, $resolution->slot, $facts);
            $arms = $affinities->arms($query);

            return $position === null ? null : $this->arm($arms[count($arms) - 1], $position, $facts);
        }
        $column = $resolution->slot->declaration();

        return $column === null ? null : $this->column($column);
    }

    /**
     * Answers the declared type text of a declared column, or null when it declares none.
     */
    public function column(Column $column): ?string
    {
        $type = $column->type;
        if ($type instanceof ColumnDomain) {
            return $type->typed() ? $type->declared : null;
        }

        return $type instanceof NoAffinity ? null : $type->name();
    }

    /**
     * Answers the declared type text the result of an arm at a position carries; an expanded star is a column reference.
     */
    public function arm(Select|ValueRow $arm, int $position, Facts $facts): ?string
    {
        if ($arm instanceof ValueRow) {
            return isset($arm->values[$position]) ? $this->text($arm->values[$position], $facts) : null;
        }
        $item = $facts->covers($arm) ? ($facts->query($arm)->projection[$position] ?? null) : null;

        return $item instanceof Field ? $this->field($item, $facts) : null;
    }

    /**
     * Answers the declared type text a column of a defining query carries: that of the leftmost arm at its position.
     */
    public function leftmost(Query $query, int $position, Facts $facts): ?string
    {
        return $this->arm((new ExpressionAffinity())->arms($query)[0], $position, $facts);
    }

    /**
     * Answers the declared type text an output field carries: that of its expression, or of the column an expanded star exposes.
     */
    public function field(Field $field, Facts $facts): ?string
    {
        if ($field->expression !== null) {
            return $this->text($field->expression, $facts);
        }

        return $field->resolution instanceof ResolvedColumn ? $this->resolved($field->resolution, $facts) : null;
    }

    /**
     * Answers the type a view records for a column with the given affinity and carried text.
     */
    public function viewType(DerivedAffinity $affinity, ?string $text): TypeDescriptor
    {
        if ($affinity->affinity === null) {
            return new NoAffinity();
        }
        if ($text !== null && !$affinity->flexible && $this->textAffinity($text) === $affinity->affinity) {
            return new ColumnDomain($text, false, $text !== '');
        }

        return new ColumnDomain(self::VIEW[$affinity->affinity->name]);
    }

    /**
     * Answers the type `CREATE TABLE ... AS` writes for a column with the given affinity.
     */
    public function tableType(DerivedAffinity $affinity): ColumnDomain
    {
        return new ColumnDomain($affinity->affinity === null ? '' : self::TABLE[$affinity->affinity->name]);
    }
}
