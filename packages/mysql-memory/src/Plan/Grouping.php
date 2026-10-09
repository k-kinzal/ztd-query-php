<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Aggregate\GroupingFlags;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Function\Json\Predicate;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Aggregate as AggregatePath;
use MySqlMemory\Plan\Window\Windowing;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
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
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

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
     * passes every check before planning fails on it. A grouping expression
     * constant for the statement is not evaluated, without WITH ROLLUP: every
     * row falls in the same group by it (verified on a live 8.4 server).
     *
     * @return array{AccessPath, Scope}
     * @throws \MySqlMemory\Error\SqlError When the block groups by CUBE
     */
    public function plan(Select $select, AccessPath $input, Scope $scope): array
    {
        if ($select->groupBy?->modifier === GroupingModifier::Cube) {
            throw (new \MySqlMemory\Session\Problem\Sampling())->unengined($this->planner->statement, $this->planner->compiler->facts, $this->planner->settings, $this->planner->dictionary);
        }
        $aggregates = $this->collect($select);
        if ($select->groupBy === null && $aggregates === []) {
            return [$input, $scope];
        }
        $compiler = $this->planner->compiler;
        $groups = [];
        $targets = [];
        foreach ($select->groupBy === null ? [] : $select->groupBy->items as $item) {
            $this->groupable($item->expression);
            $group = $compiler->compile($item->expression, $scope);
            $constant = $select->groupBy?->modifier === null && $compiler->constancy($item->expression)->constant() && (new Walker())->find($item->expression, Query::class) === [];
            $groups[] = $constant ? new Constant($group->domain(), null) : $group;
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

        return [new AggregatePath($input, $groups, $accumulations, $rollup, $columns, $this->sorts($select, $aggregates, $scope)), $grouped];
    }

    /**
     * Tells whether the server answers the groups of a block in the order of their grouping values rather than in the order they first appear.
     *
     * MySQL 8.0 and later group in a temporary table, which answers the groups in the order they
     * first appear, unless the block groups WITH ROLLUP, an aggregate needs the rows of each group
     * together (an aggregate of DISTINCT values but MIN and MAX, GROUP_CONCAT, JSON_ARRAYAGG and
     * JSON_OBJECTAGG), or the grouping expressions are the leading columns of an index of a table
     * of the block, whose rows are then read in index order. MySQL 5.6 and 5.7 sort the groups
     * (verified on live 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-optimization.html.
     *
     * @param list<Scalar> $aggregates The aggregates of the block
     */
    public function sorts(Select $select, array $aggregates, Scope $scope): bool
    {
        if ($this->planner->settings->legacy() || $select->groupBy?->modifier !== null) {
            return true;
        }
        foreach ($aggregates as $aggregate) {
            if ($aggregate instanceof GroupConcat || $aggregate instanceof JsonObjectAggregate
                || ($aggregate instanceof Aggregate && ($aggregate->function === AggregateFunction::JsonArray || ($aggregate->distinct && $aggregate->function !== AggregateFunction::Minimum && $aggregate->function !== AggregateFunction::Maximum)))) {
                return true;
            }
        }

        return $this->indexed($select, $scope);
    }

    /**
     * Tells whether the grouping expressions of a block are the leading whole columns of an index of one of its tables, in index order.
     */
    public function indexed(Select $select, Scope $scope): bool
    {
        $declarations = $this->declarations($select);
        if ($declarations === null || $declarations === []) {
            return false;
        }
        foreach ($scope->tables as $definition) {
            if ($this->leads($definition, $declarations)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the declared column each grouping expression of a block reads, or null when one is
     * not a column name.
     *
     * @return list<Column|null>|null
     */
    public function declarations(Select $select): ?array
    {
        $declarations = [];
        foreach ($select->groupBy === null ? [] : $select->groupBy->items as $item) {
            $expression = $item->expression;
            while ($expression instanceof Grouped) {
                $expression = $expression->operand;
            }
            $resolution = $expression instanceof ColumnUse && $this->planner->compiler->facts->covers($expression) ? $this->planner->compiler->facts->scalar($expression)->resolution : null;
            if (!$resolution instanceof ResolvedColumn) {
                return null;
            }
            $declarations[] = $resolution->slot->declaration();
        }

        return $declarations;
    }

    /**
     * Tells whether declared columns are all columns of a table and the leading whole columns of
     * one of its primary, unique or plain indexes, in index order.
     *
     * @param list<Column|null> $declarations
     */
    public function leads(TableDefinition $definition, array $declarations): bool
    {
        $positions = [];
        foreach ($declarations as $declaration) {
            foreach ($definition->columns as $position => $column) {
                if ($declaration !== null && $column->declaration === $declaration) {
                    $positions[] = $position;
                }
            }
        }
        if (count($positions) !== count($declarations)) {
            return false;
        }
        foreach ($definition->keys as $key) {
            $leading = array_slice($key->columns, 0, count($positions));
            $whole = array_filter(array_slice($key->prefixes, 0, count($positions)), static fn (?int $prefix): bool => $prefix !== null) === [];
            if (($key->kind === KeyKind::Primary || $key->kind === KeyKind::Unique || $key->kind === KeyKind::Index) && $leading === $positions && $whole) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks that a GROUP BY item does not group by an aggregate or a window function through the alias or position of a select item.
     *
     * An item that is such an alias or position is refused with ER_WRONG_GROUP_FIELD naming the
     * select item; an item that reads an aggregate through an alias inside an expression is refused
     * with the name `???` (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When the item groups by an aggregate or a window function
     */
    public function groupable(Scalar $expression): void
    {
        $facts = $this->planner->compiler->facts;
        $walker = new Walker();
        $direct = $expression;
        while ($direct instanceof Grouped) {
            $direct = $direct->operand;
        }
        foreach ($walker->find($expression, Scalar::class, false) as $node) {
            $resolution = ($node instanceof ColumnUse || $node instanceof OutputOrdinal) && $facts->covers($node) ? $facts->scalar($node)->resolution : null;
            if (!$resolution instanceof AliasTarget || $resolution->field->expression === null) {
                continue;
            }
            $computes = static fn (object $found): bool => Windowing::windowed($found) || (($found instanceof Aggregate || $found instanceof GroupConcat || $found instanceof JsonObjectAggregate) && $found->over === null);
            $found = array_filter($walker->find($resolution->field->expression, Scalar::class, false), $computes);
            if ($found !== [] && $node === $direct) {
                throw QueryError::WrongGroupField->error($this->planner->blocks->name($resolution->field));
            }
            if (array_filter($found, static fn (object $call): bool => !Windowing::windowed($call)) !== []) {
                throw QueryError::WrongGroupField->error('???');
            }
        }
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
        $equivalence = new Equivalence($this->planner);
        foreach ($roots as $root) {
            foreach ($walker->find($root, Scalar::class, false) as $node) {
                if ($node instanceof KeywordCall && $node->function === KeywordFunction::Grouping) {
                    $grouped->bind($node, new GroupingFlags($this->planner->compiler->domain($node), $this->arguments($node, $targets), count($groups), $offset + count($groups)));
                    continue;
                }
                foreach ($targets as $index => $target) {
                    if ($columns[$index] === null && $equivalence->same($node, $target)) {
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
        $equivalence = new Equivalence($this->planner);
        foreach ($select->groupBy->items as $item) {
            $target = $this->target($item->expression);
            if ($field->expression !== null ? $equivalence->same($field->expression, $target) : $field->resolution instanceof ResolvedColumn && $target instanceof ColumnUse && $equivalence->sameColumn($field->resolution, $target)) {
                return true;
            }
        }

        return false;
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
        $equivalence = new Equivalence($this->planner);
        foreach ($call->arguments as $number => $argument) {
            $found = null;
            foreach ($targets as $index => $target) {
                if ($equivalence->same($argument, $target)) {
                    $found = $index;
                    break;
                }
            }
            $positions[] = $found ?? throw QueryError::GroupingArgumentNotGrouped->error($number + 1);
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
     * Finds the aggregates of a block in its select list, HAVING and ORDER BY, outside its subqueries.
     *
     * @return list<Aggregate|GroupConcat|JsonObjectAggregate>
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
            foreach ($walker->find($root, JsonObjectAggregate::class, false) as $node) {
                if ($node->over === null) {
                    $found[] = $node;
                }
            }
        }

        return $found;
    }

    /**
     * Compiles a value JSON_ARRAYAGG or JSON_OBJECTAGG folds, marking a predicate, whose value becomes a JSON boolean.
     *
     * @throws \MySqlMemory\Error\SqlError When the value cannot be compiled
     */
    public function json(Scalar $value, Scope $scope): Evaluable
    {
        $compiled = $this->planner->compiler->compile($value, $scope);

        return $this->planner->compiler->jsons->boolean($value) ? new Predicate($compiled) : $compiled;
    }

    /**
     * Compiles an aggregate into the fold of its arguments; JSON_OBJECTAGG folds its name and value.
     *
     * A JSON aggregate names itself in lower case as the column a warning about its value read as
     * another type comes from (verified on a live 8.4 server).
     */
    public function accumulation(Aggregate|GroupConcat|JsonObjectAggregate $node, Scope $scope): Accumulation
    {
        $compiler = $this->planner->compiler;
        if ($node instanceof JsonObjectAggregate) {
            return new Accumulation(AggregateFunction::JsonArray, [$compiler->compile($node->key, $scope), $this->json($node->value, $scope)], false, $compiler->domain($node)->withSource('json_objectagg'), [], ',', 0, true);
        }
        if ($node instanceof Aggregate && $node->function === AggregateFunction::JsonArray) {
            return new Accumulation($node->function, array_map(fn (Scalar $argument): Evaluable => $this->json($argument, $scope), $node->arguments), false, $compiler->domain($node)->withSource('json_arrayagg'), [], ',', 0);
        }
        $arguments = array_map(static fn (Scalar $argument): Evaluable => $compiler->compile($argument, $scope), $node->arguments);
        if ($node instanceof GroupConcat) {
            $order = array_map(static fn ($item): array => [$compiler->compile($item->expression, $scope), $item->direction?->value === 'DESC'], $node->order);
            $limit = $compiler->connection->variables->count('group_concat_max_len', 1024);

            return new Accumulation(null, $arguments, $node->distinct, $compiler->domain($node), $order, $node->separator === null ? ',' : $node->separator->value, $limit);
        }

        return new Accumulation($node->function, $arguments, $node->distinct, $compiler->domain($node), [], ',', 0);
    }
}
