<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Dictionary\Routine;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;

/**
 * Writes the query of a view as the server stores it and SHOW CREATE VIEW shows it.
 *
 * Keywords are in lower case; each column is qualified by the table it is read from, and a
 * table by its database unless that is the current one; each select item is named with AS but
 * in a subquery of an expression; an
 * operation is enclosed in parentheses; COUNT(*) is count(0), a negative number -(n), and a
 * right join the left join of its operands swapped. A
 * query with a construct the writer does not know is shown as it was written (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-view.html.
 *
 * @visibility MySqlMemory
 */
final class ViewText
{
    /**
     * @param Facts $facts The facts the query was resolved with
     * @param string $current The current database, which the names of its tables are written without
     * @param string $database The database the unqualified names of the query are in
     */
    public function __construct(public readonly Facts $facts, public readonly string $current, public readonly string $database)
    {
    }

    /**
     * Writes a query, or answers null when it holds a construct the writer does not know.
     *
     * @param bool $named Whether the select items are named with AS, as they are but in a subquery of an expression
     */
    public function query(Query $query, bool $named = true): ?string
    {
        if ($query instanceof QueryStatement || $query instanceof ParenthesizedQuery) {
            return $this->query($query->query, $named);
        }
        if ($query instanceof Select) {
            return $this->select($query, $named);
        }
        if ($query instanceof SetOperation) {
            $left = $query->left instanceof Query ? $this->query($query->left, $named) : null;
            $right = $this->query($query->right, $named);

            return $left === null || $right === null ? null : $left . ' ' . strtolower($query->operator->value) . ($query->quantifier === SetQuantifier::All ? ' all ' : ' ') . $right;
        }
        if (!$query instanceof QueryExpression) {
            return null;
        }
        $text = '';
        if ($query->with instanceof With) {
            $tables = [];
            foreach ($query->with->tables as $table) {
                $body = $this->query($table->query);
                if ($body === null) {
                    return null;
                }
                $tables[] = Routine::quoted($table->name->value) . ($table->columns === [] ? '' : ' (' . implode(',', array_map(static fn ($column): string => Routine::quoted($column->value), $table->columns)) . ')') . ' as (' . $body . ')';
            }
            $text = 'with ' . ($query->with->recursive ? 'recursive ' : '') . implode(',', $tables) . ' ';
        }
        $body = $this->query($query->body, $named);
        $tail = $this->tail($query->orderBy, $query->limit);

        return $body === null || $tail === null ? null : $text . $body . $tail;
    }

    /**
     * Writes a query block.
     *
     * @param bool $named Whether the select items are named with AS
     */
    public function select(Select $select, bool $named = true): ?string
    {
        $items = [];
        foreach ($this->facts->query($select)->projection as $field) {
            if (!$field instanceof Field) {
                return null;
            }
            $value = $field->expression === null ? $this->column($field->resolution) : $this->scalar($field->expression);
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
            $where = $this->scalar($select->where);
            if ($where === null) {
                return null;
            }
            $text .= ' where ' . $where;
        }
        if ($select->groupBy !== null) {
            if ($select->groupBy->modifier !== null) {
                return null;
            }
            $groups = $this->scalars(array_map(static fn (OrderItem $item): Scalar => $item->expression, $select->groupBy->items));
            if ($groups === null) {
                return null;
            }
            $text .= ' group by ' . implode(',', $groups);
        }
        if ($select->having !== null) {
            $having = $this->scalar($select->having);
            if ($having === null) {
                return null;
            }
            $text .= ' having ' . $having;
        }
        $tail = $this->tail($select->orderBy, $select->limit);

        return $tail === null || $select->windows !== [] || $select->qualify !== null ? null : $text . $tail;
    }

    /**
     * Writes an ORDER BY and a LIMIT clause, each when there is one.
     *
     * @param list<OrderItem> $order
     */
    public function tail(array $order, ?object $limit): ?string
    {
        $text = '';
        if ($order !== []) {
            $keys = [];
            foreach ($order as $item) {
                $key = $this->scalar($item->expression);
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
        $count = $this->scalar($limit->count);
        $offset = $limit->offset === null ? null : $this->scalar($limit->offset);
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

            return $query === null || $relation->alias === null || $relation->columns !== [] ? null : '(' . $query . ') ' . Routine::quoted($relation->alias->value);
        }
        if ($relation instanceof NestedRelation) {
            return $this->relation($relation->relation);
        }
        if ($relation instanceof TableList) {
            $text = null;
            foreach ($relation->members as $member) {
                $written = $this->relation($member);
                if ($written === null) {
                    return null;
                }
                $text = $text === null ? $written : '(' . $text . ' join ' . $written . ')';
            }

            return $text;
        }
        if ($relation instanceof JoinedTable && !$relation->operator->natural() && $relation->using === []) {
            $swapped = $relation->operator->keepsRight();
            $left = $this->relation($swapped ? $relation->right : $relation->left);
            $right = $this->relation($swapped ? $relation->left : $relation->right);
            $on = $relation->on === null ? '' : $this->scalar($relation->on);
            if ($left === null || $right === null || $on === null) {
                return null;
            }
            $keyword = $relation->operator->keepsLeft() || $swapped ? 'left join' : 'join';

            return '(' . $left . ' ' . $keyword . ' ' . $right . ($on === '' ? '' : ' on(' . $on . ')') . ')';
        }

        return null;
    }

    /**
     * Writes the name of a table: with its database unless that is the current one, and without one for a common table expression.
     */
    public function table(?string $schema, string $name, ?TableReference $reference = null): string
    {
        $resolution = $reference !== null && $this->facts->covers($reference) ? $this->facts->relation($reference)->table : null;
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
        if ($relation instanceof DerivedTable && $relation->alias !== null) {
            return Routine::quoted($relation->alias->value) . '.' . $column;
        }

        return null;
    }

    /**
     * Writes a list of expressions, or answers null when one cannot be written.
     *
     * @param list<Scalar> $scalars
     * @return list<string>|null
     */
    public function scalars(array $scalars): ?array
    {
        $written = [];
        foreach ($scalars as $scalar) {
            $text = $this->scalar($scalar);
            if ($text === null) {
                return null;
            }
            $written[] = $text;
        }

        return $written;
    }

    /**
     * Writes an expression.
     */
    public function scalar(Scalar $scalar): ?string
    {
        return match (true) {
            $scalar instanceof ColumnUse => $this->facts->covers($scalar) ? $this->column($this->facts->scalar($scalar)->resolution) : null,
            $scalar instanceof Grouped => $this->scalar($scalar->operand),
            $scalar instanceof NumberLiteral => $scalar->text,
            $scalar instanceof SignedLiteral => $scalar->negative ? '-(' . $scalar->number->text . ')' : $scalar->number->text,
            $scalar instanceof StringLiteral => ($scalar->introducer === null ? '' : '_' . strtolower($scalar->introducer->value)) . "'" . strtr($scalar->value(), ['\\' => '\\\\', "'" => "\\'"]) . "'",
            $scalar instanceof NullLiteral => 'NULL',
            $scalar instanceof BooleanLiteral => $scalar->value ? 'true' : 'false',
            $scalar instanceof Comparison => $this->binary($scalar->left, $scalar->operator->value, $scalar->right),
            $scalar instanceof Arithmetic => $this->binary($scalar->left, $scalar->operator->value, $scalar->right),
            $scalar instanceof Logical => $this->binary($scalar->left, strtolower($scalar->operator->value), $scalar->right),
            $scalar instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated => $this->wrap($scalar->operand, '(', ' collate ' . $scalar->collation->value . ')'),
            $scalar instanceof NullTest => $this->wrap($scalar->operand, '(', $scalar->negated ? ' is not null)' : ' is null)'),
            $scalar instanceof Not => $this->not($scalar->operand),
            $scalar instanceof Unary => $scalar->operator === UnaryOperator::Plus ? $this->scalar($scalar->operand) : ($scalar->operator === UnaryOperator::Not ? $this->not($scalar->operand) : $this->wrap($scalar->operand, $scalar->operator->value . '(', ')')),
            $scalar instanceof Between => $this->between($scalar),
            $scalar instanceof InList => $this->in($scalar),
            $scalar instanceof Like => $scalar->escape !== null ? null : ($scalar->negated ? $this->wrap(new Like($scalar->operand, $scalar->pattern), '(not(', '))') : $this->binary($scalar->operand, 'like', $scalar->pattern)),
            $scalar instanceof FunctionCall => $this->call(strtolower($scalar->name->value), array_map(static fn ($argument): Scalar => $argument->expression, $scalar->arguments), $scalar->schema === null ? '' : Routine::quoted($scalar->schema->value) . '.'),
            $scalar instanceof Aggregate => $this->aggregate($scalar),
            $scalar instanceof CaseExpression => $this->branches($scalar),
            $scalar instanceof ScalarSubquery => $this->subquery('(', $scalar->query, ')'),
            $scalar instanceof Exists => $this->subquery('exists(', $scalar->query, ')'),
            $scalar instanceof InQuery => $this->wrap($scalar->operand, '', ($scalar->negated ? ' not in (' : ' in (') . $this->query($scalar->query, false) . ')', $this->query($scalar->query, false) === null),
            default => null,
        };
    }

    /**
     * Writes a binary operation in parentheses.
     */
    public function binary(Scalar $left, string $operator, Scalar $right): ?string
    {
        $first = $this->scalar($left);
        $second = $this->scalar($right);

        return $first === null || $second === null ? null : '(' . $first . ' ' . $operator . ' ' . $second . ')';
    }

    /**
     * Writes an expression between a prefix and a suffix.
     */
    public function wrap(Scalar $operand, string $before, string $after, bool $refused = false): ?string
    {
        $text = $this->scalar($operand);

        return $text === null || $refused ? null : $before . $text . $after;
    }

    /**
     * Writes a negation: a comparison negated, or a comparison of the operand with 0.
     */
    public function not(Scalar $operand): ?string
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        $negated = $operand instanceof Comparison ? match ($operand->operator) {
            ComparisonOperator::Equal => '<>',
            ComparisonOperator::NotEqual => '=',
            ComparisonOperator::Less => '>=',
            ComparisonOperator::LessOrEqual => '>',
            ComparisonOperator::Greater => '<=',
            ComparisonOperator::GreaterOrEqual => '<',
            ComparisonOperator::NullSafeEqual => null,
        } : null;
        if ($operand instanceof Comparison && $negated !== null) {
            return $this->binary($operand->left, $negated, $operand->right);
        }
        if ($operand instanceof NullTest) {
            return $this->wrap($operand->operand, '(', $operand->negated ? ' is null)' : ' is not null)');
        }
        if ($operand instanceof ColumnUse || $operand instanceof Arithmetic || $operand instanceof NumberLiteral || $operand instanceof FunctionCall) {
            return $this->wrap($operand, '(0 = ', ')');
        }
        $text = $this->scalar($operand);

        return $text === null ? null : '(not(' . $text . '))';
    }

    /**
     * Writes BETWEEN.
     */
    public function between(Between $between): ?string
    {
        $operand = $this->scalar($between->operand);
        $low = $this->scalar($between->low);
        $high = $this->scalar($between->high);

        return $operand === null || $low === null || $high === null ? null : '(' . $operand . ($between->negated ? ' not between ' : ' between ') . $low . ' and ' . $high . ')';
    }

    /**
     * Writes IN over a list; over one element, it is a comparison with it.
     */
    public function in(InList $in): ?string
    {
        if (count($in->elements) === 1) {
            return $this->binary($in->operand, $in->negated ? '<>' : '=', $in->elements[0]);
        }
        $operand = $this->scalar($in->operand);
        $elements = $this->scalars($in->elements);

        return $operand === null || $elements === null ? null : '(' . $operand . ($in->negated ? ' not in (' : ' in (') . implode(',', $elements) . '))';
    }

    /**
     * Writes a function call with its arguments.
     *
     * @param list<Scalar> $arguments
     */
    public function call(string $name, array $arguments, string $schema = ''): ?string
    {
        $written = $this->scalars($arguments);

        return $written === null ? null : $schema . $name . '(' . implode(',', $written) . ')';
    }

    /**
     * Writes an aggregate: COUNT(*) as count(0).
     */
    public function aggregate(Aggregate $aggregate): ?string
    {
        if ($aggregate->over !== null || $aggregate->function === AggregateFunction::JsonArray || $aggregate->function === AggregateFunction::Collect) {
            return null;
        }
        $name = strtolower($aggregate->function->value);
        $arguments = $aggregate->arguments;
        if ($arguments === []) {
            return $name . '(0)';
        }
        $written = $this->scalars($arguments);

        return $written === null ? null : $name . '(' . ($aggregate->distinct ? 'distinct ' : '') . implode(',', $written) . ')';
    }

    /**
     * Writes a CASE expression.
     */
    public function branches(CaseExpression $case): ?string
    {
        $text = '(case';
        if ($case->operand !== null) {
            $operand = $this->scalar($case->operand);
            if ($operand === null) {
                return null;
            }
            $text .= ' ' . $operand;
        }
        foreach ($case->branches as $branch) {
            $condition = $this->scalar($branch->condition);
            $result = $this->scalar($branch->result);
            if ($condition === null || $result === null) {
                return null;
            }
            $text .= ' when ' . $condition . ' then ' . $result;
        }
        if ($case->else !== null) {
            $else = $this->scalar($case->else);
            if ($else === null) {
                return null;
            }
            $text .= ' else ' . $else;
        }

        return $text . ' end)';
    }

    /**
     * Writes a subquery between a prefix and a suffix.
     */
    public function subquery(string $before, Query $query, string $after): ?string
    {
        $text = $this->query($query, false);

        return $text === null ? null : $before . $text . $after;
    }
}
