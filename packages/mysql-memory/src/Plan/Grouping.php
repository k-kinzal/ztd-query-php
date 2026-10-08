<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Aggregate\GroupingFlags;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Aggregate as AggregatePath;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use UnitEnum;

/**
 * Plans the grouping of a query block: its GROUP BY expressions and the aggregates of its select list, HAVING and ORDER BY.
 *
 * A block groups when it has GROUP BY or an aggregate; after grouping, each aggregate is read
 * from the grouped row and every other column from the first row of the group. WITH ROLLUP, an
 * expression that is a grouping expression is read from the grouping values of the row, which
 * are NULL where the row rolls them up, and GROUPING() tells which ones it rolls up.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
 *
 * @visibility MySqlMemory
 */
final class Grouping
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans the grouping of a block over its input; answers the grouped path and the scope its later clauses compile in.
     *
     * GROUP BY CUBE runs only on a secondary engine, so a statement that
     * passes every check before planning fails on it (verified on a live
     * 8.4 server).
     *
     * @return array{AccessPath, Scope}
     * @throws \MySqlMemory\Error\SqlError When the block groups by CUBE
     */
    public function plan(Select $select, AccessPath $input, Scope $scope): array
    {
        if ($select->groupBy?->modifier === GroupingModifier::Cube) {
            throw ErrorCode::SecondaryEngineFailed->error('No secondary engine defined for at least one of the query tables');
        }
        $aggregates = $this->collect($select);
        if ($select->groupBy === null && $aggregates === []) {
            return [$input, $scope];
        }
        $compiler = $this->planner->compiler;
        $groups = [];
        $targets = [];
        foreach ($select->groupBy === null ? [] : $select->groupBy->items as $item) {
            $groups[] = $compiler->compile($item->expression, $scope);
            $targets[] = $this->target($item->expression);
        }
        $accumulations = array_map(fn (Scalar $node): Accumulation => $this->accumulation($node, $scope), $aggregates);
        $grouped = $scope->grouped();
        $width = $input->width();
        foreach ($aggregates as $index => $node) {
            $grouped->bind($node, new ColumnRead($accumulations[$index]->domain, $width + $index));
        }

        $rollup = $select->groupBy?->modifier !== null;
        $columns = $rollup ? $this->rollup($select, $groups, $targets, $grouped, $width + count($aggregates)) : [];

        return [new AggregatePath($input, $groups, $accumulations, $rollup, $columns), $grouped];
    }

    /**
     * Binds, for the rows WITH ROLLUP, the expressions of the select list, HAVING and ORDER BY that are grouping expressions to the grouping values of the row, and GROUPING() to the grouping expressions it names; answers the input position each grouping expression that is a column of the block reads.
     *
     * A grouping value is NULL where the row rolls it up, and so is a column of the block that is
     * a grouping expression.
     *
     * @param list<Evaluable> $groups The compiled grouping expressions
     * @param list<Scalar> $targets The grouping expressions as written, or the select items they name
     * @param int $offset The position of the first grouping value in the grouped row
     * @return list<int|null>
     *
     * @throws \MySqlMemory\Error\SqlError When an argument of GROUPING() is not a grouping expression
     */
    public function rollup(Select $select, array $groups, array $targets, Scope $grouped, int $offset): array
    {
        $columns = [];
        foreach ($groups as $group) {
            while ($group instanceof Retyped) {
                $group = $group->evaluable;
            }
            $columns[] = $group instanceof ColumnRead && $group->depth === 0 ? $group->position : null;
        }
        $roots = [];
        foreach ($select->items as $item) {
            if ($item instanceof SelectExpression) {
                $roots[] = $item->expression;
            }
        }
        if ($select->having !== null) {
            $roots[] = $select->having;
        }
        foreach ([...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)] as $item) {
            $roots[] = $item->expression;
        }
        $walker = new Walker();
        foreach ($roots as $root) {
            foreach ($walker->find($root, Scalar::class, false) as $node) {
                if ($node instanceof KeywordCall && $node->function === KeywordFunction::Grouping) {
                    $grouped->bind($node, new GroupingFlags($this->planner->compiler->domain($node), $this->arguments($node, $targets), count($groups), $offset + count($groups)));
                    continue;
                }
                foreach ($targets as $index => $target) {
                    if ($columns[$index] === null && $this->same($node, $target)) {
                        $grouped->bind($node, new ColumnRead($groups[$index]->domain()->withNullable(true), $offset + $index));
                        break;
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * Tells whether an output field of a block WITH ROLLUP is a rollup item: a grouping expression, or a column of a star that one names.
     */
    public function rolls(Select $select, Field $field): bool
    {
        if ($select->groupBy === null || $select->groupBy->modifier === null) {
            return false;
        }
        foreach ($select->groupBy->items as $item) {
            $target = $this->target($item->expression);
            if ($field->expression !== null ? $this->same($field->expression, $target) : $field->resolution instanceof ResolvedColumn && $target instanceof ColumnUse && $this->sameColumn($field->resolution, $target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether a column name resolves to the column a resolution names.
     */
    public function sameColumn(ResolvedColumn $resolution, ColumnUse $use): bool
    {
        $facts = $this->planner->compiler->facts;
        $other = $facts->covers($use) ? $facts->scalar($use)->resolution : null;

        return $other instanceof ResolvedColumn && $other->relation === $resolution->relation && $this->column($other) === $this->column($resolution);
    }

    /**
     * Gives an output field of a block WITH ROLLUP the type and NULL fact SQL Semantics derived for its rows.
     *
     * A BIT rollup item a temporary table holds is an integer there, so its bits are read as one.
     */
    public function output(Field $field, Evaluable $expression): Evaluable
    {
        $type = $field->type;
        $nullable = $field->nullability !== Nullability::NotNull;
        $domain = $type instanceof Known && $type->descriptor instanceof Resolved ? Domain::of($type->descriptor, $nullable) : $expression->domain()->withNullable($nullable);

        return $expression->domain()->kind === Kind::Bit && $domain->kind === Kind::Integer ? new Conversion($expression, $domain, null, 'UNSIGNED') : new Retyped($expression, $domain);
    }

    /**
     * Answers the position among the grouping expressions of the one each argument of GROUPING() names.
     *
     * @param list<Scalar> $targets
     * @return list<int>
     *
     * @throws \MySqlMemory\Error\SqlError When an argument is not a grouping expression
     */
    public function arguments(KeywordCall $call, array $targets): array
    {
        $positions = [];
        foreach ($call->arguments as $number => $argument) {
            $found = null;
            foreach ($targets as $index => $target) {
                if ($this->same($argument, $target)) {
                    $found = $index;
                    break;
                }
            }
            $positions[] = $found ?? throw ErrorCode::GroupingArgumentNotGrouped->error($number + 1);
        }

        return $positions;
    }

    /**
     * Answers the expression a grouping item groups by: the select item an alias or a position names, or the item itself.
     */
    public function target(Scalar $expression): Scalar
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        $facts = $this->planner->compiler->facts;
        if (($expression instanceof ColumnUse || $expression instanceof OutputOrdinal) && $facts->covers($expression)) {
            $resolution = $facts->scalar($expression)->resolution;
            if ($resolution instanceof AliasTarget && $resolution->field->expression !== null) {
                return $this->target($resolution->field->expression);
            }
        }

        return $expression;
    }

    /**
     * Answers the column a resolution names, the same for every use of one column of a relation, whether or not the use sees the column as one that can be NULL.
     */
    public function column(ResolvedColumn $resolution): object
    {
        $slot = $resolution->slot;
        while ($slot->origin instanceof OutputSlot && $slot->column === null) {
            $slot = $slot->origin;
        }

        return $slot->declaration() ?? $slot;
    }

    /**
     * Tells whether two parts of a statement are the same expression: written alike, without regard to parentheses or to the case of names, with each column name read as the column it resolves to.
     */
    public function same(object|int|float|string|bool|null $left, object|int|float|string|bool|null $right): bool
    {
        while ($left instanceof Grouped) {
            $left = $left->operand;
        }
        while ($right instanceof Grouped) {
            $right = $right->operand;
        }
        if (!is_object($left) || !is_object($right)) {
            return $left === $right;
        }
        if ($left::class !== $right::class) {
            return false;
        }
        $facts = $this->planner->compiler->facts;
        if ($left instanceof ColumnUse && $right instanceof ColumnUse && $facts->covers($left) && $facts->covers($right)) {
            $first = $facts->scalar($left)->resolution;
            if ($first instanceof ResolvedColumn) {
                return $this->sameColumn($first, $right);
            }
        }
        if ($left instanceof UnitEnum) {
            return $left === $right;
        }
        if ($left instanceof Name && $right instanceof Name) {
            return strcasecmp($left->value, $right->value) === 0;
        }
        $values = [[], []];
        foreach ([$left, $right] as $side => $object) {
            $properties = get_object_vars($object);
            array_walk_recursive($properties, static function ($value, $key) use (&$values, $side): void {
                $values[$side][] = [$key, $value];
            });
        }
        if (count($values[0]) !== count($values[1])) {
            return false;
        }
        foreach ($values[0] as $index => [$key, $value]) {
            [$otherKey, $other] = $values[1][$index];
            if ($key !== $otherKey || !(is_object($value) || is_scalar($value) || $value === null) || !(is_object($other) || is_scalar($other) || $other === null) || !$this->same($value, $other)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Finds the aggregates of a block in its select list, HAVING and ORDER BY, outside its subqueries.
     *
     * @return list<Aggregate|GroupConcat>
     */
    public function collect(Select $select): array
    {
        $roots = [];
        foreach ($select->items as $item) {
            if ($item instanceof SelectExpression) {
                $roots[] = $item->expression;
            }
        }
        if ($select->having !== null) {
            $roots[] = $select->having;
        }
        foreach ($select->orderBy as $item) {
            $roots[] = $item->expression;
        }
        $walker = new Walker();
        $found = [];
        foreach ($roots as $root) {
            foreach ($walker->find($root, Aggregate::class, false) as $node) {
                if ($node->over === null) {
                    $found[] = $node;
                }
            }
            foreach ($walker->find($root, GroupConcat::class, false) as $node) {
                if ($node->over === null) {
                    $found[] = $node;
                }
            }
        }

        return $found;
    }

    /**
     * Compiles an aggregate into the fold of its arguments.
     */
    public function accumulation(Aggregate|GroupConcat $node, Scope $scope): Accumulation
    {
        $compiler = $this->planner->compiler;
        $arguments = array_map(static fn (Scalar $argument): Evaluable => $compiler->compile($argument, $scope), $node->arguments);
        if ($node instanceof GroupConcat) {
            $order = array_map(static fn ($item): array => [$compiler->compile($item->expression, $scope), $item->direction?->value === 'DESC'], $node->order);
            $limit = (int) ($compiler->connection->variables->read('group_concat_max_len') ?? 1024);

            return new Accumulation(null, $arguments, $node->distinct, $compiler->domain($node), $order, $node->separator === null ? ',' : $node->separator->value, $limit);
        }

        return new Accumulation($node->function, $arguments, $node->distinct, $compiler->domain($node), [], ',', 0);
    }

}
