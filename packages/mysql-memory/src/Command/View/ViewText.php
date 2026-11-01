<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Dictionary\Routine;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\Field;

/**
 * Writes the query of a view as the server stores it and SHOW CREATE VIEW shows it.
 *
 * Keywords are in lower case; each column is qualified by the table it is read from, and a
 * table by its database unless that is the current one; each select item is named with AS but
 * in a subquery of an expression; an
 * operation is enclosed in parentheses; COUNT(*) is count(0), a negative number -(n), and a
 * right join the left join of its operands swapped. A
 * query with a construct the writer does not know falls back to the semantic rendering (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-view.html.
 *
 * @visibility MySqlMemory
 */
final class ViewText
{
    /**
     * The writer of the expressions of the query.
     */
    public readonly ExpressionText $expressions;

    /**
     * @param StatementSemantics $facts The facts the query was resolved with
     * @param string $current The current database, which the names of its tables are written without
     * @param string $database The database the unqualified names of the query are in
     * @param bool $legacy Whether the stored expression uses the MySQL 5.x representation
     * @param QueryExpression|null $afterWith The nested body whose diagnostic start is marked
     * @param string $boundary A marker chosen outside the generated stored text
     */
    public function __construct(public readonly StatementSemantics $facts, public readonly string $current, public readonly string $database, public readonly bool $legacy = false, public readonly ?QueryExpression $afterWith = null, public readonly string $boundary = '')
    {
        $this->expressions = new ExpressionText($this);
    }

    /**
     * Writes a query, or answers null when it holds a construct the writer does not know.
     *
     * @param bool $named Whether the select items are named with AS, as they are but in a subquery of an expression
     * @param string $hints The hint comment the first block of the query keeps, written after its SELECT, or the empty string
     */
    public function query(Query $query, bool $named = true, string $hints = ''): ?string
    {
        if ($query instanceof QueryStatement || $query instanceof ParenthesizedQuery) {
            return $this->query($query->query, $named, $hints);
        }
        if ($query instanceof Select) {
            $text = $this->select($query, $named);

            return $text === null ? null : 'select ' . $hints . substr($text, 7);
        }
        if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable) {
            return $this->explicit($query, $named);
        }
        if ($query instanceof SetOperation) {
            $left = $query->left instanceof Query ? $this->query($query->left, $named, $hints) : null;
            $right = $this->query($query->right, $named);

            return $left === null || $right === null ? null : $left . ' ' . strtolower($query->operator->value) . ($query->quantifier === SetQuantifier::All ? ' all ' : ' ') . $right;
        }
        if (!$query instanceof QueryExpression) {
            return null;
        }
        $text = (new WithText($this))->clause($query);
        $body = $this->query($query->body, $named, $hints);
        $tail = $this->tail($query->orderBy, $query->limit);

        return $text === null || $body === null || $tail === null ? null : $text . ($query === $this->afterWith ? $this->boundary : '') . $body . $tail;
    }

    /**
     * Writes TABLE as the resolved SELECT projection stored by MySQL views.
     */
    public function explicit(\SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable $query, bool $named): ?string
    {
        $items = [];
        foreach ($query->queryInformation()->projection as $field) {
            $value = $field instanceof Field ? $this->column($field->resolution) : null;
            if ($value === null) {
                return null;
            }
            $items[] = $named ? $value . ' AS ' . Routine::quoted($field->name->value ?? '') : $value;
        }
        return 'select ' . implode(',', $items) . ' from ' . $this->table($query->table->schema->value ?? null, $query->table->name->value, $query);
    }

    /**
     * Writes a query block.
     *
     * @param bool $named Whether the select items are named with AS
     */
    public function select(Select $select, bool $named = true): ?string
    {
        $items = [];
        foreach ($select->queryInformation()->projection as $field) {
            if (!$field instanceof Field) {
                return null;
            }
            $value = $field->expression === null ? $this->column($field->resolution) : $this->expressions->scalar($field->expression);
            if ($value === null) {
                return null;
            }
            $items[] = $named ? $value . ' AS ' . Routine::quoted($field->name->value ?? '') : $value;
        }
        $text = 'select ' . (in_array(SelectOption::Distinct, $select->options, true) ? 'distinct ' : '') . implode(',', $items);
        if ($select->from !== null) {
            $from = $this->relation($select->from);
            if ($from === null) {
                return null;
            }
            $text .= ' from ' . $from;
        }
        if ($select->where !== null) {
            $where = $this->expressions->scalar($select->where);
            if ($where === null) {
                return null;
            }
            $text .= ' where ' . $where;
        }
        $group = (new GroupingText($this->expressions))->clause($select->groupBy);
        if ($group === null) {
            return null;
        }
        $text .= $group;
        if ($select->having !== null) {
            $having = $this->expressions->scalar($select->having);
            if ($having === null) {
                return null;
            }
            $text .= ' having ' . $having;
        }
        $windows = (new WindowText($this->expressions))->clause($select);
        $tail = $this->tail($select->orderBy, $select->limit);

        return $tail === null || $windows === null || $select->qualify !== null ? null : $text . $windows . $tail;
    }

    /**
     * Writes an ORDER BY and a LIMIT clause, each when there is one.
     *
     * @param list<OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem> $order
     */
    public function tail(array $order, ?object $limit): ?string
    {
        $text = '';
        if ($order !== []) {
            $keys = [];
            foreach ($order as $item) {
                $key = $this->expressions->scalar($item->expression);
                if ($key === null) {
                    return null;
                }
                $keys[] = $key . ($item->direction?->value === 'DESC' ? ' desc' : '');
            }
            $text .= ' order by ' . implode(',', $keys);
        }
        if ($limit === null) {
            return $text;
        }
        if (!$limit instanceof RowLimit) {
            return null;
        }
        $count = $this->expressions->scalar($limit->count);
        $offset = $limit->offset === null ? null : $this->expressions->scalar($limit->offset);
        if ($count === null || ($limit->offset !== null && $offset === null)) {
            return null;
        }

        return $text . ' limit ' . ($offset === null ? '' : $offset . ',') . $count;
    }

    /**
     * Writes a relation of a FROM clause.
     */
    public function relation(Relation $relation): ?string
    {
        if ($relation instanceof TableReference) {
            $written = $this->table($relation->name->schema->value ?? null, $relation->name->name->value, $relation);

            return $relation->alias === null ? $written : $written . ' ' . Routine::quoted($relation->alias->value);
        }
        if ($relation instanceof DerivedTable) {
            $query = $this->query($relation->query);

            return $query === null || $relation->columns !== [] ? null : '(' . $query . ') ' . Routine::quoted($relation->alias->value);
        }
        if ($relation instanceof NestedRelation) {
            return $this->relation($relation->relation);
        }
        if ($relation instanceof TableList) {
            return $this->members($relation);
        }
        if ($relation instanceof JoinedTable) {
            return $this->join($relation);
        }

        return null;
    }

    /**
     * Writes the members of a comma-separated table list as joins nested from the left.
     */
    public function members(TableList $list): ?string
    {
        $text = null;
        foreach ($list->members as $member) {
            $written = $this->relation($member);
            if ($written === null) {
                return null;
            }
            $text = $text === null ? $written : '(' . $text . ' join ' . $written . ')';
        }

        return $text;
    }

    /**
     * Writes a join in parentheses: a right join as the left join of its operands swapped; a
     * natural joins and USING retain equality over their resolved input columns.
     */
    public function join(JoinedTable $join): ?string
    {
        $swapped = $join->operator->keepsRight();
        $left = $this->relation($swapped ? $join->right : $join->left);
        $right = $this->relation($swapped ? $join->left : $join->right);
        $on = $join->on === null ? (new JoinText($this))->condition($join) : $this->expressions->scalar($join->on);
        if ($left === null || $right === null || $on === null) {
            return null;
        }
        $keyword = $join->operator->keepsLeft() || $swapped ? 'left join' : 'join';

        return '(' . $left . ' ' . $keyword . ' ' . $right . ($on === '' ? '' : ' on(' . $on . ')') . ')';
    }

    /**
     * Writes the name of a table: with its database unless that is the current one, and without one for a common table expression.
     */
    public function table(?string $schema, string $name, ?\SqlSemantics\Statement\NamedRelation $reference = null): string
    {
        $resolution = $reference !== null && $reference->hasBinding() ? $reference->relationInformation()->table : null;
        if ($resolution instanceof \SqlSemantics\Statement\Reference\Table\CommonTable) {
            return Routine::quoted($name);
        }
        $database = $schema ?? $this->database;

        return ($database === $this->current ? '' : Routine::quoted($database) . '.') . Routine::quoted($name);
    }

    /**
     * Writes a column a name resolves to, qualified by the relation it is read from.
     */
    public function column(?object $resolution): ?string
    {
        if (!$resolution instanceof ResolvedColumn || $resolution->slot->name === null) {
            return null;
        }
        $relation = $resolution->relation;
        $column = Routine::quoted($resolution->slot->name->value);
        if ($relation instanceof TableReference) {
            return ($relation->alias === null ? $this->table($relation->name->schema->value ?? null, $relation->name->name->value, $relation) : Routine::quoted($relation->alias->value)) . '.' . $column;
        }
        if ($relation instanceof \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable) {
            return $this->table($relation->table->schema->value ?? null, $relation->table->name->value, $relation) . '.' . $column;
        }
        if ($relation instanceof DerivedTable) {
            return Routine::quoted($relation->alias->value) . '.' . $column;
        }

        return null;
    }
}
