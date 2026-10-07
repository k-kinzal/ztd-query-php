<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Operator\Comparator;
use MySqlMemory\Evaluation\Operator\Compare;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\JoinKind;
use MySqlMemory\Plan\Path\Materialize;
use MySqlMemory\Plan\Path\NestedLoopJoin;
use MySqlMemory\Plan\Path\SingleRow;
use MySqlMemory\Plan\Path\TableScan;
use MySqlMemory\Typing\Materialized;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;

/**
 * Plans the FROM clause of a query block: each relation occurrence is placed in the row of the block and read by an access path.
 *
 * A comma join and a join without a condition pair every row; USING and NATURAL compare the
 * columns of one name on both sides. A derived table is computed in a frame of its own, inside
 * the block's for a LATERAL one.
 *
 * @visibility MySqlMemory
 */
final class Relations
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans a relation and places its occurrences in the scope of the block.
     *
     * @throws \MySqlMemory\Error\SqlError When a relation cannot be read
     */
    public function plan(Relation $relation, Scope $scope): AccessPath
    {
        return match (true) {
            $relation instanceof TableReference => $this->table($relation, $scope),
            $relation instanceof DerivedTable => $this->derived($relation, $scope),
            $relation instanceof JoinedTable => $this->join($relation, $scope),
            $relation instanceof TableList => $this->list($relation, $scope),
            $relation instanceof NestedRelation => $this->plan($relation->relation, $scope),
            $relation instanceof Dual => new SingleRow(),
            default => throw ErrorCode::NotSupportedYet->error('relation ' . (new \ReflectionClass($relation))->getShortName()),
        };
    }

    /**
     * Plans a table reference: a stored table, or a common table expression.
     *
     * @throws \MySqlMemory\Error\SqlError When the table does not exist
     */
    public function table(TableReference $reference, Scope $scope): AccessPath
    {
        $resolution = $this->planner->compiler->facts->relation($reference)->table;
        if ($resolution instanceof CommonTable) {
            $plan = $this->planner->commonTable($resolution->definition);
            $scope->place($reference, $plan->root instanceof \MySqlMemory\Plan\Path\RecursiveUnion || $plan->root instanceof \MySqlMemory\Plan\Path\WorkingTable ? $plan->domains : array_map(Materialized::column(...), $plan->domains), $plan->names);
            $scope->derived[spl_object_id($reference)] = $reference->alias?->value ?? $reference->name->name->value;

            return new Materialize($plan);
        }
        if (!$resolution instanceof DeclaredTable) {
            throw ErrorCode::NoSuchTable->error($reference->name->schema?->value ?? $this->planner->settings->database, $reference->name->name->value);
        }
        $name = $resolution->table->name;
        $stored = $this->planner->dictionary->table($name->schema?->value ?? $this->planner->settings->database, $name->name->value);
        if ($stored === null) {
            throw ErrorCode::NoSuchTable->error($name->schema?->value ?? $this->planner->settings->database, $name->name->value);
        }
        if ($reference->partitions !== []) {
            throw ErrorCode::PartitionClauseOnNonpartitioned->error();
        }
        $definition = $stored->definition;
        $scope->place($reference, array_map(static fn ($column) => $column->domain, $definition->columns), array_map(static fn ($column): string => $column->name, $definition->columns), $definition);

        return new TableScan($stored);
    }

    /**
     * Plans a derived table.
     */
    public function derived(DerivedTable $derived, Scope $scope): AccessPath
    {
        $plan = $this->planner->query($derived->query, $derived->lateral ? $scope : $scope->outer);
        $names = $derived->columns === [] ? $plan->names : array_map(static fn ($name): string => $name->value, $derived->columns);
        $scope->place($derived, array_map(Materialized::column(...), $plan->domains), $names);
        $scope->derived[spl_object_id($derived)] = $derived->alias?->value ?? '';

        return new Materialize($plan, $derived->lateral);
    }

    /**
     * Plans a comma join: every row of each member paired with every row of the others.
     */
    public function list(TableList $list, Scope $scope): AccessPath
    {
        $path = null;
        foreach ($list->members as $member) {
            $next = $this->plan($member, $scope);
            $path = $path === null ? $next : new NestedLoopJoin($path, $next, JoinKind::Inner, null, $this->lateral($member));
        }

        return $path ?? new SingleRow();
    }

    /**
     * Tells whether a relation reads the columns of the relations before it.
     */
    public function lateral(Relation $relation): bool
    {
        return $relation instanceof DerivedTable && $relation->lateral;
    }

    /**
     * Plans a join.
     *
     * @throws \MySqlMemory\Error\SqlError When a condition cannot be compiled
     */
    public function join(JoinedTable $join, Scope $scope): AccessPath
    {
        $before = array_keys($scope->offsets);
        $left = $this->plan($join->left, $scope);
        $middle = array_keys($scope->offsets);
        $right = $this->plan($join->right, $scope);
        $after = array_keys($scope->offsets);
        $kind = $join->operator->keepsRight() ? JoinKind::Right : ($join->operator->keepsLeft() ? JoinKind::Left : JoinKind::Inner);
        $condition = null;
        if ($join->on !== null) {
            $condition = $this->planner->compiler->compile($join->on, $scope);
        } elseif ($join->using !== [] || $join->operator->natural()) {
            $leftIds = array_values(array_diff($middle, $before));
            $rightIds = array_values(array_diff($after, $middle));
            $names = $join->operator->natural() ? $this->common($scope, $leftIds, $rightIds) : array_map(static fn ($name): string => $name->value, $join->using);
            $condition = $this->equalities($scope, $leftIds, $rightIds, $names);
        }

        return new NestedLoopJoin($left, $right, $kind, $condition, $this->lateral($join->right));
    }

    /**
     * Answers the column names both sides of a natural join have, in the order of the left side.
     *
     * @param list<int> $left
     * @param list<int> $right
     * @return list<string>
     */
    public function common(Scope $scope, array $left, array $right): array
    {
        $rightNames = [];
        foreach ($right as $id) {
            foreach ($this->visible($scope, $id) as $name) {
                $rightNames[strtolower($name)] = true;
            }
        }
        $names = [];
        foreach ($left as $id) {
            foreach ($this->visible($scope, $id) as $name) {
                if (isset($rightNames[strtolower($name)]) && !in_array(strtolower($name), array_map('strtolower', $names), true)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * Answers the names of the columns of a relation occurrence that `*` shows.
     *
     * @return list<string>
     */
    public function visible(Scope $scope, int $id): array
    {
        $names = [];
        foreach ($scope->names[$id] as $position => $name) {
            if (!isset($scope->tables[$id]) || !$scope->tables[$id]->columns[$position]->invisible) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Builds the conjunction of equalities between the columns of each name on both sides.
     *
     * @param list<int> $left
     * @param list<int> $right
     * @param list<string> $names
     */
    public function equalities(Scope $scope, array $left, array $right, array $names): ?Evaluable
    {
        $condition = null;
        foreach ($names as $name) {
            $leftColumn = $this->find($scope, $left, $name);
            $rightColumn = $this->find($scope, $right, $name);
            if ($leftColumn === null || $rightColumn === null) {
                throw ErrorCode::BadField->error($name, 'from clause');
            }
            $comparator = Comparator::of($leftColumn->domain(), $rightColumn->domain(), '=', $this->planner->settings->connectionCollation);
            $equality = new Compare(ComparisonOperator::Equal, $leftColumn, $rightColumn, $comparator, $this->planner->compiler->operators->truth(true));
            $condition = $condition === null ? $equality : new Logic(LogicalOperator::And, $condition, $equality, $this->planner->compiler->operators->truth(true));
        }

        return $condition;
    }

    /**
     * Finds a column by name among relation occurrences.
     *
     * @param list<int> $ids
     */
    public function find(Scope $scope, array $ids, string $name): ?ColumnRead
    {
        foreach ($ids as $id) {
            foreach ($scope->names[$id] as $position => $candidate) {
                if (strcasecmp($candidate, $name) === 0) {
                    return new ColumnRead($scope->columns[$id][$position], $scope->offsets[$id] + $position);
                }
            }
        }

        return null;
    }
}
