<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Platform\Sqlite\Rendering\Canonical;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;

/**
 * Names a result column the way SQLite does.
 *
 * Rule: SQLITE-RESULT-NAME-001. An alias always names its column. Without
 * one, SQLite keeps the span of the expression, the text from its first to
 * its last token with the comments and whitespace between them, and names the
 * column in one of three ways (release 3.47.2, `select.c`):
 * (1) the rows a statement returns, of a SELECT or of RETURNING
 * (`sqlite3GenerateColumnNames()`, short_column_names on): an expression that
 * is a column reference, possibly in parentheses, which resolves to a column
 * takes the name of that column as it is declared (`rowid` and its synonyms
 * take the name of the integer primary key column, or `rowid`); any other
 * expression is named by its span;
 * (2) a subquery in FROM and a common table (`sqlite3ExpandSubquery()` and
 * `withExpand()`), named before resolution: an expression that is one word,
 * possibly qualified, in parentheses or under COLLATE, takes the word as
 * written; any other expression is named by its span;
 * (3) a view and a table created from a query (`sqlite3ResultSetOfSelect()`),
 * named after resolution: like (1), but COLLATE and the likely(), unlikely()
 * and likelihood() functions are looked through as well.
 * The span is the layout the column keeps (CORE-SPELLING-001); a column built
 * without a layout is rendered canonically and named after that rendering,
 * which is the text the database reads. Of a compound query the leftmost arm
 * names the columns; the expressions of a VALUES row have no span.
 * Source: https://sqlite.org/c3ref/column_name.html,
 * https://sqlite.org/lang_select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ResultNames
{
    /**
     * The functions SQLite looks through when it names a view column, by lower-case name, with their argument count.
     */
    public const LIKELIHOOD = ['likely' => 1, 'unlikely' => 1, 'likelihood' => 2];

    /**
     * Answers the span of a result column: its expression as written, from the first to the last token.
     */
    public function span(ResultColumn $column): Name
    {
        return new Name(($column->layout ?? (new Canonical())->layout($column->expression))->text());
    }

    /**
     * Answers the name a result column has in the rows a statement returns, or null when it depends on a missing declaration.
     *
     * @param Resolution|null $resolution The resolution of the expression, looked through parentheses
     */
    public function output(ResultColumn $column, ?Resolution $resolution): ?Name
    {
        if ($column->alias !== null) {
            return $column->alias;
        }
        $expression = $column->expression;
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if (!$expression instanceof ColumnUse && !$expression instanceof DoubleQuotedWord && !$expression instanceof TruthWord) {
            return $this->span($column);
        }

        return $this->resolved($column, $expression, $resolution);
    }

    /**
     * Answers the name of a result column that is a name use from its resolution.
     *
     * A column takes its declared name. A plain column name that resolves to
     * nothing, which SQLite refuses, keeps the name as written; a
     * double-quoted word or a truth word that resolves to nothing is a
     * literal and is named by its span. Where the resolution is conditional
     * on a missing declaration, so is the name of a word that may still be a
     * literal.
     */
    public function resolved(ResultColumn $column, ColumnUse|DoubleQuotedWord|TruthWord $expression, ?Resolution $resolution): ?Name
    {
        if ($resolution instanceof ResolvedColumn) {
            return $resolution->slot->name;
        }
        if ($expression instanceof ColumnUse) {
            return $resolution instanceof AliasTarget ? $resolution->field->name : $expression->name;
        }

        return $resolution instanceof ConditionalColumn ? null : $this->span($column);
    }

    /**
     * Answers the word a result expression is written as, when it is one word: a column use, a double-quoted word or a truth word, possibly in parentheses or under COLLATE.
     */
    public function written(?Scalar $expression): ?Name
    {
        while ($expression instanceof Grouped || $expression instanceof Collate) {
            $expression = $expression->operand;
        }
        if ($expression instanceof ColumnUse) {
            return $expression->name;
        }
        if ($expression instanceof DoubleQuotedWord) {
            return $expression->word;
        }

        return $expression instanceof TruthWord ? new Name($expression->value ? 'true' : 'false') : null;
    }

    /**
     * Answers the name of a result column of a subquery or common table, which SQLite gives before resolution.
     */
    public function relation(ResultColumn $column): Name
    {
        return $column->alias ?? $this->written($column->expression) ?? $this->span($column);
    }

    /**
     * Answers the name of a result column of a view or created table, or null when it depends on a missing declaration.
     *
     * @param Facts $facts The facts of the query, which include the expressions of the column
     */
    public function declared(ResultColumn $column, Facts $facts): ?Name
    {
        if ($column->alias !== null) {
            return $column->alias;
        }
        $expression = $column->expression;
        for ($inner = $this->inner($expression); $inner !== null; $inner = $this->inner($expression)) {
            $expression = $inner;
        }
        if (!$expression instanceof ColumnUse && !$expression instanceof DoubleQuotedWord && !$expression instanceof TruthWord) {
            return $this->span($column);
        }
        return $this->resolved($column, $expression, $facts->scalar($expression)->resolution);
    }

    /**
     * Answers the operand SQLite looks through when it names a view column: of parentheses, of COLLATE, and the first argument of likely(), unlikely() and likelihood(); null for any other expression.
     */
    public function inner(Scalar $expression): ?Scalar
    {
        if ($expression instanceof Grouped || $expression instanceof Collate) {
            return $expression->operand;
        }
        if ($expression instanceof FunctionCall && !$expression->star && (self::LIKELIHOOD[strtolower($expression->name->value)] ?? -1) === count($expression->arguments)) {
            return $expression->arguments[0] ?? null;
        }

        return null;
    }

    /**
     * Answers the arm of a query that names its columns: the leftmost selection or VALUES clause.
     */
    public function leftmost(Query $query): Select|ValuesClause|null
    {
        while ($query instanceof WithQuery || $query instanceof Compound) {
            $query = $query instanceof WithQuery ? $query->body : $query->first;
        }

        return $query instanceof Select || $query instanceof ValuesClause ? $query : null;
    }

    /**
     * Answers the result column of a selection a field projects, or null for a field a star contributes.
     */
    public function column(Select $select, Field $field): ?ResultColumn
    {
        foreach ($select->columns as $column) {
            if ($column instanceof ResultColumn && $field->expression !== null && $column->expression === $field->expression) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Applies the rule that a column SQLite would name TRUE or FALSE, in any letter case, is named `columnN` after its position.
     *
     * @param int $position The position of the column, from zero
     */
    public function truth(?Name $name, int $position): ?Name
    {
        if ($name !== null && in_array(Comparison::AsciiInsensitive->fold($name->value), ['true', 'false'], true)) {
            return new Name('column' . ($position + 1));
        }

        return $name;
    }
}
