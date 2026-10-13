<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
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
     * @param QualifiedName $table The base table, qualified by its database
     * @param Name $qualifier The name the columns of the base table are read through: the alias of the view's query, or the table name
     * @param Name|null $alias The alias of the base table, when written
     * @param list<array{string, Name|null, Scalar}> $columns For each column of the view: its name, the base column it writes or null, and the expression it reads
     * @param Scalar|null $where The condition of the view, or null for none
     * @param bool $keyed Whether the view holds every column of a primary or unique key of the table
     */
    public function __construct(public readonly QualifiedName $table, public readonly Name $qualifier, public readonly ?Name $alias, public readonly array $columns, public readonly ?Scalar $where, public readonly bool $keyed = false)
    {
    }

    /**
     * Answers how a view writes to its base table, or null when it is not updatable.
     *
     * @throws SqlError When the view no longer resolves (ER_VIEW_INVALID), or reads another view
     */
    public static function of(View $view, Session $session): ?self
    {
        \MySqlMemory\Session\Resolution\Views::refresh($view, $session->instance->dictionary, $session->settings());
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
        $qualifier = $from->alias ?? $from->name->name;
        $columns = self::columns($view, $query, $from, $qualifier);
        if ($columns === null) {
            return null;
        }

        return new self(new QualifiedName($from->name->name, new Name($schema)), $qualifier, $from->alias, $columns, $query->where, self::keyed($base, $columns));
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
     * and the expression it reads; null when the query returns a column that is not a field.
     *
     * @param Select $query The query block of the view
     * @param TableReference $from The table the block reads
     * @param Name $qualifier The name the columns of the table are read through
     * @return list<array{string, Name|null, Scalar}>|null
     */
    public static function columns(View $view, Select $query, TableReference $from, Name $qualifier): ?array
    {
        $columns = [];
        $projection = $view->operation->facts->query($query)->projection;
        $positions = $view->positions === [] ? array_keys($view->declaration->columns) : $view->positions;
        foreach ($positions as $position => $source) {
            $field = $projection[$source] ?? null;
            if (!$field instanceof Field) {
                return null;
            }
            $name = $view->declaration->columns[$position]->name->value ?? '';
            $resolution = $field->expression === null || $field->expression instanceof ColumnUse ? ($field->expression === null ? $field->resolution : $view->operation->facts->scalar($field->expression)->resolution) : null;
            if ($resolution instanceof ResolvedColumn && $resolution->relation === $from && $resolution->slot->name !== null) {
                $column = $resolution->slot->name;
                $columns[] = [$name, $column, new ColumnUse($column, new QualifiedName($qualifier))];
                continue;
            }
            if ($field->expression === null) {
                return null;
            }
            $columns[] = [$name, null, new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($field->expression)];
        }

        return $columns;
    }

    /**
     * Answers whether the columns of a view write every column of a primary or unique key of
     * its base table, or every column of a base table that has no such key.
     *
     * @param list<array{string, Name|null, Scalar}> $columns The columns of the view
     */
    public static function keyed(StoredTable $base, array $columns): bool
    {
        $written = array_map(static fn (Name $name): string => strtolower($name->value), array_filter(array_column($columns, 1), static fn (?Name $column): bool => $column !== null));
        $keyed = false;
        $unique = false;
        foreach ($base->definition->keys as $key) {
            $names = array_map(static fn (int $position): string => strtolower($base->definition->columns[$position]->name ?? ''), $key->columns);
            $unique = $unique || $key->unique();
            $keyed = $keyed || ($key->unique() && array_diff($names, $written) === []);
        }
        if (!$unique) {
            $all = array_map(static fn (ColumnDefinition $column): string => strtolower($column->name), $base->definition->columns);

            return array_diff($all, $written) === [];
        }

        return $keyed;
    }

    /**
     * Answers the column of the view a name denotes, or null.
     *
     * @return array{string, Name|null, Scalar}|null
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
    public function written(string $name): Name
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
}
