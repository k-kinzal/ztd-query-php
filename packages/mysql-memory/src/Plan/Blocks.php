<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Distinct;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\Path\Source\TableScan;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Limit as LimitClause;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

/**
 * Plans one query block: its FROM, WHERE, grouping, HAVING, select list, DISTINCT, ORDER BY and LIMIT, in the order the server applies them.
 *
 * The rows of the block's plan hold the select list followed by the sort keys that are not in it.
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
     * @throws \MySqlMemory\Error\SqlError When an expression of the block cannot be compiled
     */
    public function select(Select $select, ?Scope $outer): QueryPlan
    {
        $compiler = $this->planner->compiler;
        $scope = new Scope($outer);
        $input = $select->from === null ? new SingleRow() : $this->planner->relations->plan($select->from, $scope);
        if ($select->where !== null) {
            $input = new Filter($input, $compiler->compile($select->where, $scope));
        }
        [$input, $evaluation] = (new Grouping($this->planner))->plan($select, $input, $scope);
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
        $domains = array_map(static fn (Evaluable $expression) => $expression->domain(), $expressions);
        $keys = [];
        foreach ([...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)] as $item) {
            $key = $compiler->compile($item->expression, $evaluation);
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

        $buffered = in_array(SelectOption::BufferResult, $select->options, true);
        $origins = array_map(fn (Field $field): ?ColumnOrigin => $this->origin($field, $scope), $fields);

        return new QueryPlan($root, $domains, array_map(fn (Field $field): string => $this->name($field), $fields), $buffered ? array_map(static fn (?ColumnOrigin $origin): ?ColumnOrigin => $origin?->unkeyed(), $origins) : $origins);
    }

    /**
     * Plans `TABLE t`: every column of a table.
     */
    public function table(ExplicitTable $table, ?Scope $outer): QueryPlan
    {
        $schema = $table->table->schema?->value ?? $this->planner->settings->database;
        $stored = $this->planner->dictionary->table($schema, $table->table->name->value);
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
     * @throws \MySqlMemory\Error\SqlError When a bound is not a non-negative integer
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
     */
    public function bound(\SqlSemantics\Statement\Scalar $value, ?Scope $outer): int
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

            return new ColumnOrigin($inner?->schema ?? '', $scope->derived[$id], $inner?->originalTable ?? '', $scope->names[$id][$position] ?? '', $inner?->flags ?? 0);
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
