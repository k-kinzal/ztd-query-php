<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Path\Transform\Distinct;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Limit as LimitClause;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;

/**
 * Plans one query block: its FROM, WHERE, grouping, HAVING, select list, DISTINCT, ORDER BY and LIMIT, in the order the server applies them.
 *
 * The rows of the block's plan hold the select list followed by the sort keys that are not in it.
 * A select item of a block WITH ROLLUP that is a grouping expression reads no base column; when
 * the rows pass through a temporary table, for ORDER BY or DISTINCT, it is a column of that table.
 * The columns of a block with SQL_BUFFER_RESULT, or with DISTINCT over more than one table that is
 * not constant ({@see ConstantTables}), are of no key, unless the server finds at once that the
 * block reads no row (verified on a live 8.4 server).
 *
 * @visibility MySqlMemory
 */
final class Blocks
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans a SELECT block.
     *
     * @throws SqlError When an expression of the block cannot be compiled
     */
    public function select(Select $select, ?Scope $outer): QueryPlan
    {
        $compiler = $this->planner->compiler;
        $scope = new Scope($outer);
        $input = $select->from === null ? new SingleRow() : $this->planner->relations->plan($select->from, $scope);
        if ($select->where !== null) {
            $input = $this->where($input, $select->where, $scope);
        }
        $grouping = new Grouping($this->planner);
        [$input, $evaluation] = $grouping->plan($select, $input, $scope);
        if ($select->having !== null) {
            $input = new Filter($input, $compiler->compile($select->having, $evaluation));
        }
        $fields = [];
        foreach ($compiler->facts->query($select)->projection as $field) {
            if (!$field instanceof Field) {
                throw ErrorCode::NotSupportedYet->error('a star over an open relation');
            }
            $fields[] = $field;
        }
        $expressions = array_map(fn (Field $field): Evaluable => $compiler->names->field($field, $evaluation), $fields);
        $rolled = array_map(static fn (Field $field): bool => $grouping->rolls($select, $field), $fields);
        if ($select->groupBy?->modifier !== null) {
            $expressions = array_map(static fn (Field $field, Evaluable $expression): Evaluable => $grouping->output($field, $expression), $fields, $expressions);
        }
        $domains = array_map(static fn (Evaluable $expression) => $expression->domain(), $expressions);
        $keys = [];
        foreach ([...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)] as $item) {
            $resolution = ($item->expression instanceof ColumnUse || $item->expression instanceof OutputOrdinal) && $compiler->facts->covers($item->expression) ? $compiler->facts->scalar($item->expression)->resolution : null;
            $position = $resolution instanceof AliasTarget ? array_search($resolution->field, $fields, true) : false;
            if (is_int($position)) {
                $keys[] = [$position, $domains[$position], $item->direction?->value === 'DESC'];
                continue;
            }
            $key = $compiler->compile($item->expression, $evaluation);
            if ($compiler->constancy($item->expression)->constant() && (new Walker())->find($item->expression, Query::class) === []) {
                continue;
            }
            $keys[] = [count($expressions), $key->domain(), $item->direction?->value === 'DESC'];
            $expressions[] = $key;
        }
        $root = new Project($input, $expressions);
        if (in_array(SelectOption::Distinct, $select->options, true)) {
            $root = new Distinct($root, $domains);
        }
        if ($keys !== []) {
            $root = new Sort($root, $keys);
        }
        $root = $this->limit($root, $select->limit, $outer);
        $root = $this->limit($root, $select->late?->limit, $outer);

        $buffered = (in_array(SelectOption::BufferResult, $select->options, true) || (in_array(SelectOption::Distinct, $select->options, true) && (new ConstantTables($compiler->facts))->joined($select) > 1)) && !$this->empty($select, $outer);
        $materialized = $keys !== [] || in_array(SelectOption::Distinct, $select->options, true);
        $origins = array_map(fn (Field $field, bool $rolled, Domain $domain): ?ColumnOrigin => $rolled ? ($materialized ? $this->planner->materialized([$domain])[0] : null) : $this->origin($field, $scope), $fields, $rolled, $domains);

        return new QueryPlan($root, $domains, array_map(fn (Field $field): string => $this->name($field), $fields), $buffered ? array_map(static fn (?ColumnOrigin $origin): ?ColumnOrigin => $origin?->unkeyed(), $origins) : $origins);
    }

    /**
     * Tells whether the server finds at once that a block reads no row: its LIMIT is 0, or a part of its WHERE or HAVING constant for the statement is not true.
     *
     * The constant parts are evaluated apart from the statement, so that their warnings are
     * recorded only when the statement evaluates them; a part that fails is taken as true.
     */
    public function empty(Select $select, ?Scope $outer): bool
    {
        foreach ([$select->limit, $select->late?->limit] as $limit) {
            $count = $limit instanceof RowLimit ? $limit->count : null;
            while ($count instanceof Grouped) {
                $count = $count->operand;
            }
            if ($count instanceof NumberLiteral && (int) $count->text === 0) {
                return true;
            }
        }
        $compiler = $this->planner->compiler;
        $context = $compiler->connection->context;
        $frame = new Frame(new Context($context->modes, new Diagnostics(), $context->variables, $context->started));
        foreach (array_merge(...array_map(fn (Scalar $condition): array => $this->conjuncts($condition), array_values(array_filter([$select->where, $select->having])))) as $conjunct) {
            if (!$compiler->constancy($conjunct)->constant() || (new Walker())->find($conjunct, Query::class) !== []) {
                continue;
            }
            try {
                $condition = $compiler->compile($conjunct, new Scope($outer));
                if (Convert::toBool($condition->evaluate($frame), $condition->domain(), $frame->context) !== true) {
                    return true;
                }
            } catch (SqlError) {
                continue;
            }
        }

        return false;
    }

    /**
     * Plans the WHERE clause of a block over its rows.
     *
     * A conjunct of the condition constant for the statement is evaluated once, before any row is
     * read, as the server evaluates it when it optimizes the block; when it is not true no row is
     * read. The other conjuncts are evaluated for each row, in written order.
     *
     * @throws SqlError When the condition cannot be compiled
     */
    public function where(AccessPath $input, Scalar $where, Scope $scope): Filter
    {
        $compiler = $this->planner->compiler;
        $constant = null;
        $varying = null;
        foreach ($this->conjuncts($where) as $conjunct) {
            $compiled = $compiler->compile($conjunct, $scope);
            if ($compiler->constancy($conjunct)->constant()) {
                $constant = $constant === null ? $compiled : new Logic(LogicalOperator::And, $constant, $compiled, $compiler->operators->truth($constant->domain()->nullable || $compiled->domain()->nullable));
            } else {
                $varying = $varying === null ? $compiled : new Logic(LogicalOperator::And, $varying, $compiled, $compiler->operators->truth($varying->domain()->nullable || $compiled->domain()->nullable));
            }
        }

        return new Filter($input, $varying ?? new Constant($compiler->operators->truth(false), 1), $constant);
    }

    /**
     * Splits a condition into the operands of its top-level ANDs, in written order.
     *
     * @return list<Scalar>
     */
    public function conjuncts(Scalar $condition): array
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition instanceof Logical && $condition->operator === LogicalOperator::And) {
            return [...$this->conjuncts($condition->left), ...$this->conjuncts($condition->right)];
        }

        return [$condition];
    }

    /**
     * Plans `TABLE t`: every column of a table.
     */
    public function table(ExplicitTable $table, ?Scope $outer): QueryPlan
    {
        $schema = $table->table->schema->value ?? $this->planner->settings->database;
        $stored = $this->planner->dictionary->table($schema, $table->table->name->value);
        $view = $this->planner->dictionary->schema($schema)->views[$table->table->name->value] ?? null;
        if ($stored === null && $view !== null) {
            return (new Views($this->planner))->table($view);
        }
        if ($stored === null) {
            throw ErrorCode::NoSuchTable->error($schema, $table->table->name->value);
        }
        $columns = $stored->definition->columns;
        $visible = array_values(array_filter(array_keys($columns), static fn (int $position): bool => !$columns[$position]->invisible));
        $expressions = array_map(static fn (int $position) => new ColumnRead($columns[$position]->domain, $position), $visible);

        $definition = $stored->definition;
        $origins = array_map(static fn (int $position): ColumnOrigin => new ColumnOrigin($definition->schema, $definition->name, $definition->name, $columns[$position]->name, $definition->flags($position)), $visible);

        return new QueryPlan(new Project(new TableScan($stored), $expressions), array_map(static fn (int $position) => $columns[$position]->domain, $visible), array_map(static fn (int $position): string => $columns[$position]->name, $visible), $origins);
    }

    /**
     * Bounds a path by a LIMIT clause, whose values are known before any row is read.
     *
     * @throws SqlError When a bound is not a non-negative integer
     */
    public function limit(AccessPath $path, ?LimitClause $limit, ?Scope $outer): AccessPath
    {
        if (!$limit instanceof RowLimit) {
            return $path;
        }
        $count = $this->bound($limit->count, $outer);
        $offset = $limit->offset === null ? 0 : $this->bound($limit->offset, $outer);

        return new Limit($path, $count, $offset);
    }

    /**
     * Evaluates a LIMIT or OFFSET value.
     *
     * @throws SqlError When the value is not an integer
     */
    public function bound(Scalar $value, ?Scope $outer): int
    {
        $evaluable = $this->planner->compiler->compile($value, new Scope($outer));
        $frame = new Frame($this->planner->compiler->connection->context);
        $number = Convert::toInteger($evaluable->evaluate($frame), $evaluable->domain(), $frame->context, true);
        if ($number === null) {
            throw ErrorCode::WrongArguments->error('LIMIT');
        }

        return $number < 0 ? PHP_INT_MAX : $number;
    }

    /**
     * Answers the name of an output column.
     */
    public function name(Field $field): string
    {
        if ($field->name !== null) {
            return $field->name->value;
        }

        return $field->expression === null ? '' : (new \MySqlMemory\Evaluation\Compile\Printer())->expression($field->expression);
    }

    /**
     * Answers the base column an output column reads directly, if any.
     */
    public function origin(Field $field, Scope $scope): ?ColumnOrigin
    {
        $resolution = $field->resolution;
        if ($field->expression instanceof ColumnUse) {
            $resolution = $this->planner->compiler->facts->scalar($field->expression)->resolution;
        }
        if (!$resolution instanceof ResolvedColumn) {
            return null;
        }
        $id = spl_object_id($resolution->relation);
        if (isset($scope->derived[$id])) {
            $position = $this->planner->compiler->names->position($scope, $resolution);

            $inner = $scope->merged[$id][$position] ?? null;

            return new ColumnOrigin($inner->schema ?? '', $scope->derived[$id], $inner->originalTable ?? '', $scope->names[$id][$position] ?? '', $inner->flags ?? 0);
        }
        if (!isset($scope->tables[$id])) {
            return null;
        }
        $definition = $scope->tables[$id];
        $position = $this->planner->compiler->names->position($scope, $resolution);
        $alias = $resolution->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference && $resolution->relation->alias !== null ? $resolution->relation->alias->value : $definition->name;

        return new ColumnOrigin($definition->schema, $alias, $definition->name, $definition->columns[$position]->name, $definition->flags($position));
    }
}
