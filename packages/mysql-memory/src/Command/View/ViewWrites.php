<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Plan\Views;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

/**
 * Writes through a view: the statement written against the view, rewritten against the base table it reads.
 *
 * A view is updatable when the server merges its query into the statement that reads it, and
 * the query reads one base table: no DISTINCT, aggregate, GROUP BY, HAVING, LIMIT, set
 * operation or ALGORITHM=TEMPTABLE. A column of the view is written to the column of the table
 * it reads; a column computed by an expression can be read but not written
 * (ER_NONUPDATEABLE_COLUMN), and makes the view not insertable-into. The condition of the view
 * is added to an UPDATE or a DELETE. The check option is not enforced.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/view-updatability.html.
 *
 * @visibility MySqlMemory
 */
final class ViewWrites
{
    /**
     * @param string $table The base table, qualified by its database
     * @param string $qualifier The name the columns of the base table are read through: the alias of the view's query, or the table name
     * @param string $alias The alias clause the base table is written with, empty for none
     * @param list<array{string, string|null, string}> $columns For each column of the view: its name, the base column it writes or null, and the text it is read as
     * @param string|null $where The condition of the view, or null for none
     * @param bool $keyed Whether the view holds every column of a primary or unique key of the table
     */
    public function __construct(public readonly string $table, public readonly string $qualifier, public readonly string $alias, public readonly array $columns, public readonly ?string $where, public readonly bool $keyed = false)
    {
    }

    /**
     * Answers how a view writes to its base table, or null when it is not updatable.
     *
     * @throws SqlError When the view no longer resolves (ER_VIEW_INVALID), or reads another view
     */
    public static function of(View $view, Session $session): ?self
    {
        Views::refresh($view, $session->instance->dictionary, $session->settings());
        $query = self::block($view);
        if ($query === null || !$query->from instanceof TableReference) {
            return null;
        }
        $from = $query->from;
        $schema = $from->name->schema->value ?? $view->database;
        $base = $session->instance->dictionary->table($schema, $from->name->name->value);
        if ($base === null) {
            throw StatementError::NotSupportedYet->error('writing through a view of a view');
        }
        $qualifier = Routine::quoted($from->alias->value ?? $from->name->name->value);
        $tree = $session->semantics()->parser()->parse($view->select);
        $columns = self::columns($view, $query, $from, $qualifier, $tree);
        if ($columns === null) {
            return null;
        }
        $where = $tree->find('where_clause')[0] ?? null;
        $condition = $where?->children[1] ?? null;

        return new self(Routine::quoted($schema) . '.' . Routine::quoted($from->name->name->value), $qualifier, $from->alias === null ? '' : ' AS ' . $qualifier, $columns, $condition instanceof Node ? $condition->text($view->select) : null, self::keyed($base, $columns));
    }

    /**
     * Answers the query block of a view that the server can merge into a statement writing
     * through it, or null: one without a common table expression, LIMIT, set operation or
     * ALGORITHM=TEMPTABLE, which reads one table and is mergeable.
     */
    public static function block(View $view): ?Select
    {
        $query = $view->query;
        while ($query instanceof QueryExpression && $query->with === null && $query->limit === null) {
            $query = $query->body;
        }
        if ($view->algorithm === 'TEMPTABLE' || !$query instanceof Select || !$query->from instanceof TableReference || !(new Materialization())->mergeable($view->query)) {
            return null;
        }

        return $query;
    }

    /**
     * Answers each column of a view with the base column it writes, or null for a computed one,
     * and the text it is read as; null when the query returns a column that is not a field.
     *
     * @param Select $query The query block of the view
     * @param TableReference $from The table the block reads
     * @param string $qualifier The name the columns of the table are read through
     * @param Node $tree The syntax tree of the query of the view as written
     * @return list<array{string, string|null, string}>|null
     */
    public static function columns(View $view, Select $query, TableReference $from, string $qualifier, Node $tree): ?array
    {
        $items = array_values(array_filter($tree->find('select_item'), static fn (Node $item): bool => $item->ordinal === 1));
        $columns = [];
        $index = 0;
        foreach ($view->operation->facts->query($query)->projection as $position => $field) {
            if (!$field instanceof Field) {
                return null;
            }
            $name = $view->declaration->columns[$position]->name->value ?? '';
            $resolution = $field->expression === null || $field->expression instanceof ColumnUse ? ($field->expression === null ? $field->resolution : $view->operation->facts->scalar($field->expression)->resolution) : null;
            $item = $field->expression === null ? null : ($items[$index++] ?? null);
            if ($resolution instanceof ResolvedColumn && $resolution->relation === $from && $resolution->slot->name !== null) {
                $column = Routine::quoted($resolution->slot->name->value);
                $columns[] = [$name, $column, $qualifier . '.' . $column];
                continue;
            }
            $expression = $item?->children[0] ?? null;
            $columns[] = [$name, null, '(' . ($expression instanceof Node ? $expression->text($view->select) : 'NULL') . ')'];
        }

        return $columns;
    }

    /**
     * Answers whether the columns of a view write every column of a primary or unique key of
     * its base table, or every column of a base table that has no such key.
     *
     * @param list<array{string, string|null, string}> $columns The columns of the view
     */
    public static function keyed(StoredTable $base, array $columns): bool
    {
        $written = array_map('strtolower', array_filter(array_column($columns, 1), static fn (?string $column): bool => $column !== null));
        $keyed = false;
        $unique = false;
        foreach ($base->definition->keys as $key) {
            $names = array_map(static fn (int $position): string => strtolower(Routine::quoted($base->definition->columns[$position]->name ?? '')), $key->columns);
            $unique = $unique || $key->unique();
            $keyed = $keyed || ($key->unique() && array_diff($names, $written) === []);
        }
        if (!$unique) {
            $all = array_map(static fn (ColumnDefinition $column): string => strtolower(Routine::quoted($column->name)), $base->definition->columns);

            return array_diff($all, $written) === [];
        }

        return $keyed;
    }

    /**
     * Answers the column of the view a name denotes, or null.
     *
     * @return array{string, string|null, string}|null
     */
    public function column(string $name): ?array
    {
        foreach ($this->columns as $column) {
            if (strcasecmp($column[0], $name) === 0) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Answers the base column a column of the view writes.
     *
     * @throws SqlError When the column is computed (ER_NONUPDATEABLE_COLUMN) or not a column of the view (ER_BAD_FIELD_ERROR)
     */
    public function written(string $name): string
    {
        $column = $this->column($name);
        if ($column === null) {
            throw QueryError::BadField->error($name, 'field list');
        }
        if ($column[1] === null) {
            throw QueryError::NonUpdatableColumn->error($column[0]);
        }

        return $column[1];
    }

    /**
     * Answers the text of a statement with the column references of the view replaced by what they read, within a span of the text.
     *
     * A reference is replaced when it is unqualified or qualified by one of the names the view is written with.
     *
     * @param list<string> $names The names that qualify a column of the view
     * @param array<int, array{int, int, string}> $edits Replacements already decided: start, end and text
     * @return array<int, array{int, int, string}>
     */
    public function reads(Node $node, string $text, array $names, array $edits): array
    {
        foreach ($node->find('simple_ident') as $use) {
            $parts = array_values(array_filter($use->tokens(), static fn (Token $token): bool => $token->text !== '.'));
            $span = $use->span();
            $name = self::unquoted(end($parts) === false ? '' : end($parts)->text);
            $qualifier = count($parts) > 1 ? self::unquoted($parts[count($parts) - 2]->text) : null;
            $column = $this->column($name);
            if ($span === null || $column === null || ($qualifier !== null && !in_array(strtolower($qualifier), array_map('strtolower', $names), true))) {
                continue;
            }
            $edits[$span[0]] = [$span[0], $span[1], $column[2]];
        }

        return $edits;
    }

    /**
     * Applies replacements to a text, from the last to the first.
     *
     * @param array<int, array{int, int, string}> $edits
     */
    public static function apply(string $text, array $edits): string
    {
        krsort($edits);
        foreach ($edits as [$start, $end, $replacement]) {
            $text = substr($text, 0, $start) . $replacement . substr($text, $end);
        }

        return $text;
    }

    /**
     * Answers an identifier as it reads, without its quotes.
     */
    public static function unquoted(string $text): string
    {
        return str_starts_with($text, '`') ? str_replace('``', '`', substr($text, 1, -1)) : $text;
    }
}
